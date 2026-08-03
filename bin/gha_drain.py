"""Drain finished GitHub Actions crawl runs into the local DB, forever.

    python bin/gha_drain.py                 # loop
    python bin/gha_drain.py --once          # one pass
    python bin/gha_drain.py --selftest      # offline checks

WHY THIS EXISTS: the Actions fleet banks NDJSON as run artifacts, which expire (90
days) and which nothing else pulls down. Without a drain the crawl looks like it is
working while the DB stays flat.

TWO RULES THIS ENCODES, both learned the hard way:

1. ONE LOADER AT A TIME. Four parallel ingest workers measured 1.54x briefly, then
   died on 1205 lock-wait timeouts with staging GROWING. Everything here is strictly
   sequential, and a lock file stops a second copy of this script from starting.

2. NEVER DELETE AN ARTIFACT DIRECTORY BEFORE THE LOADER SAYS "ok". A download that
   half-finished, or a loader that failed mid-batch, must be re-runnable — so a run
   is only recorded as drained after loader.py exits 0, and the state file is written
   after that, never before.

Progress is appended to logs/gha_drain.log and the drained run ids live in
.gha_drained.json so a restart never re-ingests (the loader dedupes on the
deterministic sku anyway, but re-downloading gigabytes is pure waste).
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import shutil
import subprocess
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
STATE = os.path.join(ROOT, ".gha_drained.json")
LOCK = os.path.join(ROOT, ".gha_drain.lock")
LOG = os.path.join(ROOT, "logs", "gha_drain.log")
WORKFLOW = "crawl.yml"
# Both shards. The fleet is split across two accounts by ACCOUNT_SLOT (disjoint unit
# halves), so a drain that watches only one repo silently ingests half the crawl and the
# DB looks like it stalled at 50%.
REPOS = [
    "ahmerfr/rockauto-crawler",
    "haseeb-shoukat2029/rockauto-crawler",
]


def log(msg: str) -> None:
    os.makedirs(os.path.dirname(LOG), exist_ok=True)
    line = f"{time.strftime('%Y-%m-%d %H:%M:%S')} {msg}"
    print(line, flush=True)
    with open(LOG, "a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def _state() -> set[str]:
    try:
        with open(STATE, encoding="utf-8") as fh:
            return set(json.load(fh).get("drained", []))
    except (OSError, ValueError):
        return set()


def _mark(run_id: str) -> None:
    done = _state()
    done.add(str(run_id))
    tmp = STATE + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump({"drained": sorted(done)}, fh, indent=1)
    os.replace(tmp, STATE)          # atomic: a crash mid-write cannot corrupt state


def _sh(cmd: list[str], timeout: int = 3600) -> tuple[int, str]:
    p = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True,
                       timeout=timeout, encoding="utf-8", errors="replace")
    return p.returncode, (p.stdout or "") + (p.stderr or "")


def finished_runs() -> list[str]:
    """Completed crawl runs, oldest first. Includes conclusion=failure on purpose:
    a run where some shards failed still uploaded every shard that succeeded, and
    those artifacts are real data."""
    ids: list[str] = []
    for repo in REPOS:
        rc, out = _sh(["gh", "run", "list", "--repo", repo, "--workflow", WORKFLOW,
                       "--limit", "30", "--json", "databaseId,status", "-q",
                       '.[] | select(.status=="completed") | .databaseId'])
        if rc != 0:
            log(f"[warn] gh run list {repo} failed: {out.strip()[:160]}")
            continue
        got = [ln.strip() for ln in out.splitlines() if ln.strip().isdigit()]
        ids += [f"{repo}#{i}" for i in reversed(got)]
    return ids


def drain(tagged: str, keep: bool = False) -> bool:
    # "<owner>/<repo>#<run_id>" — run ids are globally unique, but carrying the repo is
    # what lets `gh run download` find the run at all.
    repo, _, run_id = tagged.partition("#")
    d = os.path.join(ROOT, ".gha_dl", run_id)
    shutil.rmtree(d, ignore_errors=True)
    os.makedirs(d, exist_ok=True)

    rc, out = _sh(["gh", "run", "download", run_id, "--repo", repo, "-D", d])
    files = glob.glob(os.path.join(d, "**", "*.ndjson"), recursive=True)
    if not files:
        # No NDJSON is a legitimate outcome (every shard drew a burned IP), and it is
        # terminal for this run — mark it so the loop does not retry forever.
        log(f"run {tagged}: no ndjson (rc={rc}) — nothing to ingest")
        shutil.rmtree(d, ignore_errors=True)
        _mark(tagged)
        return True

    rows = 0
    for f in files:
        with open(f, encoding="utf-8", errors="replace") as fh:
            rows += sum(1 for ln in fh if ln.strip())
    log(f"run {tagged}: {len(files)} artifacts, {rows:,} rows — staging")

    rc, out = _sh([sys.executable, "bin/ingest_artifacts.py"] + files)
    if rc != 0:
        log(f"run {tagged}: STAGING FAILED rc={rc} :: {out.strip()[-300:]}")
        return False                      # keep the directory; retry next pass

    rc, out = _sh([sys.executable, "bin/loader.py"])
    if rc != 0:
        log(f"run {tagged}: LOADER FAILED rc={rc} :: {out.strip()[-300:]}")
        return False
    log(f"run {tagged}: loaded :: {out.strip().splitlines()[-1][:160] if out.strip() else 'ok'}")

    _mark(tagged)                          # only after the loader succeeded
    if not keep:
        shutil.rmtree(d, ignore_errors=True)
    return True


def db_counts() -> str:
    sys.path.insert(0, os.path.join(ROOT, "scraper"))
    try:
        import db  # noqa: PLC0415
        c = db.connect()
        cur = c.cursor()
        out = []
        for t in ("part_fitment", "parts", "vehicles"):
            cur.execute(f"SELECT COUNT(*) AS n FROM {t}")
            out.append(f"{t}={cur.fetchone()['n']:,}")
        return "  ".join(out)
    except Exception as exc:  # noqa: BLE001 — a DB hiccup must not kill the drain loop
        return f"(db unavailable: {type(exc).__name__})"


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--once", action="store_true")
    ap.add_argument("--interval", type=int, default=300)
    ap.add_argument("--keep", action="store_true", help="do not delete downloads")
    a = ap.parse_args()

    if os.path.exists(LOCK):
        age = time.time() - os.path.getmtime(LOCK)
        if age < 7200:
            print(f"another drain holds {LOCK} (age {age/60:.0f}m) — refusing to double-load")
            return 1
        print(f"stale lock ({age/3600:.1f}h) — taking over")
    open(LOCK, "w").write(str(os.getpid()))
    try:
        while True:
            done = _state()
            todo = [r for r in finished_runs() if r not in done]
            if todo:
                log(f"pending runs: {len(todo)} -> {todo[:5]}")
            for run_id in todo:
                drain(run_id, keep=a.keep)
            log(f"DB: {db_counts()}")
            if a.once:
                return 0
            time.sleep(a.interval)
    finally:
        try:
            os.remove(LOCK)
        except OSError:
            pass


def _selftest() -> None:
    """ponytail: offline checks on the state file, the only stateful part."""
    global STATE
    import tempfile
    STATE = os.path.join(tempfile.mkdtemp(), "s.json")
    assert _state() == set(), "missing state file must read as empty, not crash"
    _mark("111")
    _mark("222")
    _mark("111")
    assert _state() == {"111", "222"}, _state()
    with open(STATE, "w") as fh:
        fh.write("{ not json")
    assert _state() == set(), "corrupt state must degrade to empty, never raise"
    print("selfcheck ok")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        _selftest()
        raise SystemExit(0)
    raise SystemExit(main())
