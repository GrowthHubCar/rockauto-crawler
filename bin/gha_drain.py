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
from itertools import zip_longest

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
    per_repo: list[list[str]] = []
    for repo in REPOS:
        rc, out = _sh(["gh", "run", "list", "--repo", repo, "--workflow", WORKFLOW,
                       "--limit", "30", "--json", "databaseId,status", "-q",
                       '.[] | select(.status=="completed") | .databaseId'])
        if rc != 0:
            log(f"[warn] gh run list {repo} failed: {out.strip()[:160]}")
            continue
        got = [ln.strip() for ln in out.splitlines() if ln.strip().isdigit()]
        per_repo.append([f"{repo}#{i}" for i in reversed(got)])

    # INTERLEAVE the shards. Appending repo-by-repo meant the first repo's whole queue
    # drained before the second was touched, and the second shard starved: measured
    # ahmerfr 28 pending vs haseeb-shoukat2029 49. Each shard owns a DISJOINT half of the
    # catalog, so starving one is not a delay — it is half the catalog missing from the DB.
    for group in zip_longest(*per_repo):
        ids += [x for x in group if x]
    return ids


MIN_FREE_GB = 6.0


def free_gb() -> float:
    return shutil.disk_usage(ROOT).free / 1e9


def drain(tagged: str, keep: bool = False) -> bool:
    # "<owner>/<repo>#<run_id>" — run ids are globally unique, but carrying the repo is
    # what lets `gh run download` find the run at all.
    repo, _, run_id = tagged.partition("#")
    d = os.path.join(ROOT, ".gha_dl", run_id)
    shutil.rmtree(d, ignore_errors=True)
    os.makedirs(d, exist_ok=True)

    # Only the crawl output. The visited deltas ride in their own rockauto-visited-*
    # artifacts, which this would otherwise download and then ignore.
    # DISK GUARD. Artifacts land on the same volume as MariaDB's datadir. The laptop sat
    # at 98% (7.9 GB free) with a single run's download holding 12 GB, and filling that
    # volume takes the DATABASE down, not just the crawl. Skip the download rather than
    # risk it; the run stays un-drained and retries once space frees.
    if free_gb() < MIN_FREE_GB:
        log(f"run {tagged}: SKIPPED — only {free_gb():.1f} GB free (need {MIN_FREE_GB})")
        shutil.rmtree(d, ignore_errors=True)
        return False

    rc, out = _sh(["gh", "run", "download", run_id, "--repo", repo,
                   "--pattern", "rockauto-shard-*", "-D", d])
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

    # STAGE THEN LOAD, PER CHUNK. Two separate reasons, both measured:
    #
    # 1. ARG_MAX — a 462-artifact run produced ~55,000 characters of paths against
    #    Windows' 32,768 limit and the drain died mid-staging, silently, leaving 1.95M
    #    rows in staging while the DB sat flat.
    # 2. BIG BATCHES WEDGE THE LOADER — staging all 30 chunks and then loading 516,407
    #    rows in one go produced repeated
    #    "TransactionLost: server rolled back the transaction (savepoint sp_chunk gone)".
    #    Small staging batches are the known-good shape for this loader.
    #
    # Loading after each chunk also means a failure costs one chunk, not the whole run.
    # CHUNK sizes the loader's WARM CACHE, not just the command line.
    #
    # loader.load_listing skips re-materialising a part it has already seen this process
    # (`_part_done`, loader.py:529) and its own comment calls that redundancy "~75% of
    # loader work". But _part_done is PER PROCESS, so restarting loader.py every 40 files
    # paid that 75% penalty 21 times per run. Measured: 312 rows/s, 24.9 min per run,
    # against a fleet producing ~50 min of ingest work every 25 min — the backlog grew
    # twice as fast as it drained and reached 40 runs.
    #
    # 40 was chosen when big batches produced "TransactionLost ... savepoint sp_chunk
    # gone". That turned out to be a GHOST second loader (.awstmp/ingest_supervisor.sh)
    # competing for locks, not batch size — killed, and no TransactionLost since. 140
    # keeps the path list well inside Windows' 32,768-char ARG_MAX (140 x ~120 = ~17k).
    # ONE BATCH PER RUN, via a GLOB.
    #
    # Measured split on run 30799012866: stage 2.7m, load 20.1m — the loader is 88% of the
    # time. loader.load_listing skips re-materialising a part it has already seen this
    # PROCESS (_part_done, loader.py:529), redundancy its own comment calls "~75% of loader
    # work". So every extra loader invocation re-pays that. Going 40 -> 140 files per batch
    # already took ingest 238 -> 473 rows/s; one batch per run warms the cache once.
    #
    # The paths cannot be passed directly — 840 x ~120 chars blows Windows' 32,768 ARG_MAX,
    # which silently killed this drain once already. ingest_artifacts._iter_listings globs
    # each argument, and artifacts extract to <dir>/<artifact>/<file>.ndjson, so a single
    # "<dir>/*/*.ndjson" covers the whole run in one argument.
    CHUNK = 140
    stage_s = load_s = 0.0   # where the drain's wall-clock actually goes
    loaded = 0
    run_glob = os.path.join(d, "*", "*.ndjson")
    batches = [[run_glob]] if len(glob.glob(run_glob)) == len(files) else               [files[i:i + CHUNK] for i in range(0, len(files), CHUNK)]
    for i, part in enumerate(batches):
        t_stage = time.time()
        rc, out = _sh([sys.executable, "bin/ingest_artifacts.py"] + part)
        stage_s += time.time() - t_stage
        if rc != 0:
            log(f"run {tagged}: STAGING FAILED at batch {i+1}/{len(batches)} rc={rc} :: {out.strip()[-220:]}")
            shutil.rmtree(d, ignore_errors=True)
            return False
        t_load = time.time()
        rc, out = _sh([sys.executable, "bin/loader.py"])
        load_s += time.time() - t_load
        if rc != 0:
            log(f"run {tagged}: LOADER FAILED at batch {i+1}/{len(batches)} rc={rc} :: {out.strip()[-220:]}")
            shutil.rmtree(d, ignore_errors=True)
            return False
        loaded += len(files) if len(batches) == 1 else len(part)
    log(f"run {tagged}: loaded {loaded}/{len(files)} artifacts in "
        f"{len(batches)} batches — stage {stage_s/60:.1f}m load {load_s/60:.1f}m "
        f"({rows/max(stage_s+load_s,1):.0f} rows/s)")

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


def sweep_orphans() -> None:
    """Delete .gha_dl directories whose run is already drained.

    The delete happens after _mark(), so a kill in that window strands the download
    forever. Measured: one orphan held 14 GB on a volume with 5.3 GB free — the same
    volume as MariaDB's datadir."""
    dl = os.path.join(ROOT, ".gha_dl")
    if not os.path.isdir(dl):
        return
    done = {t.partition("#")[2] for t in _state()}
    for name in os.listdir(dl):
        if name in done:
            shutil.rmtree(os.path.join(dl, name), ignore_errors=True)
            log(f"swept orphan download {name} (already drained)")


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
            sweep_orphans()
            done = _state()
            todo = [r for r in finished_runs() if r not in done]
            if todo:
                log(f"pending runs: {len(todo)} -> {todo[:5]}")
            for run_id in todo:
                drain(run_id, keep=a.keep)
            log(f"DB: {db_counts()}  free={free_gb():.1f}GB")
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
