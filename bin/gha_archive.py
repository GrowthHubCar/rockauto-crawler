"""Pull every crawl run's NDJSON off GitHub and keep it locally, gzipped.

    python bin/gha_archive.py              # archive every run not already archived
    python bin/gha_archive.py --selftest

WHY: GitHub is currently the ONLY copy of everything the fleet has crawled but not yet
ingested — 79 runs / ~19.75M rows at the time of writing. This project has already had
cloud accounts suspended without warning (AWS twice, and three of this user's GitHub
accounts), and artifacts also expire on their own after 90 days. If an account is blocked
the un-ingested crawl is simply gone.

DISK IS THE CONSTRAINT, not bandwidth: the laptop has ~11 GB free and it shares that
volume with MariaDB's datadir. Raw artifact directories run 200-500 MB per run, so this
never keeps them — it downloads a run, concatenates its NDJSON into ONE gzip, and deletes
the download before moving on. Measured ~1 KB/row raw, ~8x gzip, so ~19.75M rows lands
around 2.5 GB instead of ~30 GB.

Independent of bin/gha_drain.py on purpose: the drain deletes each download after loading,
so a run that is drained leaves nothing on disk. Archiving is a separate durability
concern from ingesting, and either can run without the other.
"""
from __future__ import annotations

import argparse
import glob
import gzip
import json
import os
import shutil
import subprocess
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "backups", "gha")
STATE = os.path.join(ROOT, ".gha_archived.json")
LOG = os.path.join(ROOT, "logs", "gha_archive.log")
WORKFLOW = "crawl.yml"
MIN_FREE_GB = 4.0
REPOS = ["ahmerfr/rockauto-crawler", "haseeb-shoukat2029/rockauto-crawler"]


def log(msg: str) -> None:
    os.makedirs(os.path.dirname(LOG), exist_ok=True)
    line = f"{time.strftime('%Y-%m-%d %H:%M:%S')} {msg}"
    print(line, flush=True)
    with open(LOG, "a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def _state() -> set[str]:
    try:
        with open(STATE, encoding="utf-8") as fh:
            return set(json.load(fh).get("archived", []))
    except (OSError, ValueError):
        return set()


def _mark(tag: str) -> None:
    done = _state()
    done.add(tag)
    tmp = STATE + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump({"archived": sorted(done)}, fh, indent=1)
    os.replace(tmp, STATE)


def _sh(cmd: list[str], token: str | None = None, timeout: int = 3600):
    env = dict(os.environ)
    if token:
        env["GH_TOKEN"] = token
    p = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True,
                       timeout=timeout, env=env, encoding="utf-8", errors="replace")
    return p.returncode, (p.stdout or "") + (p.stderr or "")


def token_for(user: str) -> str | None:
    rc, out = _sh(["gh", "auth", "token", "--user", user])
    tok = out.strip().splitlines()[-1].strip() if rc == 0 and out.strip() else ""
    return tok if tok.startswith(("gho_", "ghp_", "ghu_", "ghs_")) else None


def archive_run(repo: str, run_id: str, token: str) -> bool:
    tag = f"{repo}#{run_id}"
    dest = os.path.join(OUT, f"{repo.split('/')[0]}-{run_id}.ndjson.gz")
    tmpd = os.path.join(ROOT, ".gha_arc", run_id)
    if shutil.disk_usage(ROOT).free / 1e9 < MIN_FREE_GB:
        log(f"{tag}: SKIPPED — {shutil.disk_usage(ROOT).free/1e9:.1f} GB free")
        return False
    shutil.rmtree(tmpd, ignore_errors=True)
    os.makedirs(tmpd, exist_ok=True)
    try:
        rc, out = _sh(["gh", "run", "download", run_id, "--repo", repo,
                       "--pattern", "rockauto-shard-*", "-D", tmpd], token)
        files = glob.glob(os.path.join(tmpd, "**", "*.ndjson"), recursive=True)
        if not files:
            log(f"{tag}: no ndjson (rc={rc})")
            _mark(tag)               # terminal: nothing to archive, never retry
            return True
        os.makedirs(OUT, exist_ok=True)
        rows = 0
        with gzip.open(dest + ".part", "wt", encoding="utf-8", newline="\n") as gz:
            for f in files:
                with open(f, encoding="utf-8", errors="replace") as fh:
                    for ln in fh:
                        if ln.strip():
                            gz.write(ln if ln.endswith("\n") else ln + "\n")
                            rows += 1
        os.replace(dest + ".part", dest)   # atomic: a crash never leaves a torn archive
        log(f"{tag}: {rows:,} rows -> {os.path.basename(dest)} "
            f"({os.path.getsize(dest)/1e6:.0f} MB)")
        _mark(tag)
        return True
    finally:
        shutil.rmtree(tmpd, ignore_errors=True)


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--limit", type=int, default=200)
    a = ap.parse_args()
    done = _state()
    todo = []
    for repo in REPOS:
        tok = token_for(repo.split("/")[0])
        if not tok:
            log(f"[warn] no token for {repo} — cannot archive it")
            continue
        rc, out = _sh(["gh", "run", "list", "--repo", repo, "--workflow", WORKFLOW,
                       "--limit", "100", "--json", "databaseId,status", "-q",
                       '.[]|select(.status=="completed")|.databaseId'], tok)
        for i in out.splitlines():
            i = i.strip()
            if i.isdigit() and f"{repo}#{i}" not in done:
                todo.append((repo, i, tok))
    log(f"archive: {len(todo)} runs to pull")
    n = 0
    for repo, rid, tok in todo[:a.limit]:
        if archive_run(repo, rid, tok):
            n += 1
    tot = sum(os.path.getsize(f) for f in glob.glob(os.path.join(OUT, "*.gz")))
    log(f"archive done: {n} runs this pass, {len(glob.glob(os.path.join(OUT,'*.gz')))} "
        f"archives on disk, {tot/1e9:.2f} GB")
    return 0


def _selftest() -> None:
    global STATE
    import tempfile
    STATE = os.path.join(tempfile.mkdtemp(), "s.json")
    assert _state() == set()
    _mark("a/b#1"); _mark("a/b#1"); _mark("c/d#2")
    assert _state() == {"a/b#1", "c/d#2"}, _state()
    src = open(os.path.abspath(__file__), encoding="utf-8").read()
    assert 'os.replace(dest + ".part", dest)' in src, "archive must be written atomically"
    assert "shutil.rmtree(tmpd" in src, "raw download must be deleted or disk fills"
    print("selfcheck ok")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        _selftest()
        raise SystemExit(0)
    raise SystemExit(main())
