"""moreinfo_sync.py — pull moreinfo artifacts from all 8 shards and load them.

auto_sync.py only knows about crawl.yml. Without this the backfill workflow would
produce artifacts that nothing ever consumes.

Each cycle:
  1. list completed "RockAuto Moreinfo Backfill" runs we have not loaded
  2. download their moreinfo-* artifacts
  3. bin/ingest_moreinfo_jsonl.py applies them (sets parts.moreinfo_done=1)
  4. record the run id so it is never loaded twice

Deliberately NOT regenerating plan/mi_keys.tsv.gz here: that is a repo commit and a
push to 8 remotes, which is not something a unattended timer should do on its own.
Regenerate between rounds with bin/moreinfo_keys.sh when the todo count has dropped
enough to be worth it.

Shares auto_sync's single-instance discipline: two loaders on the same tables
deadlock on lock waits.
"""
from __future__ import annotations

import glob
import json
import os
import shutil
import subprocess
import sys
import time
from datetime import datetime, timezone

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))

WORKFLOW = "moreinfo.yml"
STATE_FILE = os.path.join(ROOT, ".moreinfo_sync_state.json")
LOG_FILE = os.path.join(ROOT, "logs", "moreinfo_sync.log")
DL_DIR = os.path.join(ROOT, "artifacts", "_moreinfo")
LOCK_FILE = os.path.join(ROOT, ".moreinfo_sync.lock")
GH = os.getenv("SP_GH_BIN") or shutil.which("gh") or "/usr/bin/gh"
PY = sys.executable

REPOS = [
    ("ahmerfr/rockauto-crawler", "ahmerfr"),
    ("haseeb-shoukat2029/rockauto-crawler", "haseeb-shoukat2029"),
    ("Affilusion/rockauto-crawler", "ahmerfr"),
    ("Nexarce/rockauto-crawler", "haseeb-shoukat2029"),
    ("arbyahad/rockauto-crawler", "arbyahad"),
    ("GrowthHubCar/rockauto-crawler", "arbyahad"),
    ("artechonza/rockauto-crawler", "artechonza"),
    ("Techonza/rockauto-crawler", "artechonza"),
]

_NO_WINDOW = getattr(subprocess, "CREATE_NO_WINDOW", 0)


def _run(*args, **kwargs):
    kwargs.setdefault("creationflags", _NO_WINDOW)
    return subprocess.run(*args, **kwargs)  # noqa: S603


def log(msg: str) -> None:
    line = f"{datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')}Z  {msg}"
    print(line, flush=True)
    os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
    with open(LOG_FILE, "a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def acquire_lock() -> bool:
    """Single instance. Same reasoning as auto_sync: two loaders racing the same
    tables deadlock on lock waits with staging growing."""
    try:
        fd = os.open(LOCK_FILE, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        os.write(fd, str(os.getpid()).encode())
        os.close(fd)
        return True
    except FileExistsError:
        try:
            pid = int(open(LOCK_FILE, encoding="utf-8").read().strip() or 0)
            os.kill(pid, 0)
            with open(f"/proc/{pid}/cmdline", "rb") as fh:
                if "moreinfo_sync" in fh.read().decode(errors="replace"):
                    log(f"another moreinfo_sync is running (pid {pid}) — exiting")
                    return False
        except (OSError, ValueError):
            pass
        log("clearing stale lock")
        try:
            os.unlink(LOCK_FILE)
        except OSError:
            return False
        return acquire_lock()


def load_state() -> set[str]:
    try:
        with open(STATE_FILE, encoding="utf-8") as fh:
            return set(json.load(fh).get("processed", []))
    except (OSError, ValueError):
        return set()


def save_state(done: set[str]) -> None:
    with open(STATE_FILE, "w", encoding="utf-8") as fh:
        json.dump({"processed": sorted(done)}, fh, indent=2)


def gh_json(args: list[str]):
    out = _run([GH, *args], capture_output=True, text=True, cwd=ROOT)
    if out.returncode != 0:
        raise RuntimeError(f"gh {' '.join(args)}: {out.stderr.strip()[:160]}")
    return json.loads(out.stdout or "[]")


def process_run(repo: str, rid: str) -> bool:
    dest = os.path.join(DL_DIR, repo.split("/")[0], rid)
    os.makedirs(dest, exist_ok=True)
    _run([GH, "run", "download", rid, "--repo", repo, "-D", dest],
         capture_output=True, text=True, cwd=ROOT)
    files = glob.glob(os.path.join(dest, "**", "*.ndjson"), recursive=True)
    rows = 0
    for f in files:
        try:
            with open(f, encoding="utf-8") as fh:
                rows += sum(1 for ln in fh if ln.strip())
        except OSError:
            pass
    if not files or not rows:
        # A run whose runners were all walled produces nothing. That is not a
        # failure - record it so we do not re-download it every cycle.
        log(f"{repo} run {rid}: no rows (all runners walled)")
        return True
    log(f"{repo} run {rid}: {len(files)} file(s), {rows:,} rows")
    # Pass the GLOB, not the expanded list: a run can carry many files and Windows
    # caps a command line at 32,767 chars. ingest_moreinfo_jsonl globs its args.
    ing = _run([PY, os.path.join("bin", "ingest_moreinfo_jsonl.py"),
                os.path.join(dest, "**", "*.ndjson")],
               capture_output=True, text=True, cwd=ROOT)
    tail = (ing.stdout or ing.stderr or "").strip().splitlines()
    if tail:
        log("  " + tail[-1][:200])
    return ing.returncode == 0


def main() -> int:
    log("=== moreinfo_sync start ===")
    if not acquire_lock():
        return 0
    try:
        done = load_state()
        for repo, user in REPOS:
            _run([GH, "auth", "switch", "--user", user],
                 capture_output=True, text=True, cwd=ROOT)
            try:
                runs = gh_json(["run", "list", "--repo", repo, "--workflow", WORKFLOW,
                                "--limit", "40", "--json", "databaseId,status,createdAt"])
            except Exception as exc:  # noqa: BLE001
                log(f"{repo}: could not list runs: {exc}")
                continue
            new = [r for r in runs
                   if r.get("status") == "completed"
                   and f"{repo}#{r['databaseId']}" not in done]
            new.sort(key=lambda r: r.get("createdAt", ""))
            if new:
                log(f"{repo}: {len(new)} new run(s)")
            for r in new:
                rid = str(r["databaseId"])
                try:
                    if process_run(repo, rid):
                        done.add(f"{repo}#{rid}")
                        save_state(done)
                except Exception as exc:  # noqa: BLE001
                    log(f"{repo} run {rid}: error {exc} — retry next cycle")
                time.sleep(1)
        log("=== moreinfo_sync done ===")
        return 0
    finally:
        try:
            os.unlink(LOCK_FILE)
        except OSError:
            pass


if __name__ == "__main__":
    sys.exit(main())
