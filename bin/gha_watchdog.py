"""Keep both crawl shards running, forever, without an operator.

    python bin/gha_watchdog.py                # loop, 5-min checks
    python bin/gha_watchdog.py --once
    python bin/gha_watchdog.py --selftest

WHY THIS EXISTS: the crawl relies on each run's `relaunch` job dispatching the next one.
That chain breaks for reasons nobody is awake to notice — a run gets cancelled by the
concurrency group, the relaunch step hits a transient API error, or a run finishes with
every job failed and never fires. When it breaks the fleet just stops, silently, and the
DB flatlines until someone looks.

This re-dispatches a shard whenever it has NO run queued or in progress. That is
idempotent with the relaunch chain: if the chain is healthy there is always an active run
and this does nothing.

PER-COMMAND AUTH, NOT `gh auth switch`. Switching the active account is global state, and
bin/gha_drain.py is reading both repos concurrently — a switch mid-download would point it
at the wrong identity. Each command carries its own GH_TOKEN instead.
"""
from __future__ import annotations

import argparse
import os
import subprocess
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOG = os.path.join(ROOT, "logs", "gha_watchdog.log")
WORKFLOW = "crawl.yml"

SHARDS = [
    ("ahmerfr", "ahmerfr/rockauto-crawler"),
    ("haseeb-shoukat2029", "haseeb-shoukat2029/rockauto-crawler"),
]


def log(msg: str) -> None:
    os.makedirs(os.path.dirname(LOG), exist_ok=True)
    line = f"{time.strftime('%Y-%m-%d %H:%M:%S')} {msg}"
    print(line, flush=True)
    with open(LOG, "a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def _run(cmd: list[str], token: str | None = None, timeout: int = 180) -> tuple[int, str]:
    env = dict(os.environ)
    if token:
        env["GH_TOKEN"] = token
    # Runs from a 5-minute scheduled task on the user's desktop — without this every
    # gh call flashes a console window and makes the machine unusable.
    p = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True,
                       timeout=timeout, env=env, encoding="utf-8", errors="replace",
                       creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))
    return p.returncode, (p.stdout or "") + (p.stderr or "")


def token_for(user: str) -> str | None:
    rc, out = _run(["gh", "auth", "token", "--user", user])
    tok = out.strip().splitlines()[-1].strip() if rc == 0 and out.strip() else ""
    return tok if tok.startswith(("gho_", "ghp_", "ghu_", "ghs_")) else None


def active_runs(repo: str, token: str | None) -> int:
    rc, out = _run(["gh", "run", "list", "--repo", repo, "--workflow", WORKFLOW,
                    "--limit", "10", "--json", "status", "-q",
                    '[.[] | select(.status!="completed")] | length'], token)
    if rc != 0:
        log(f"[warn] {repo}: run list failed :: {out.strip()[:140]}")
        return -1          # unknown -> do NOT dispatch; never pile on during an outage
    for ln in reversed(out.strip().splitlines()):
        if ln.strip().isdigit():
            return int(ln.strip())
    return -1


def poke(repo: str, token: str | None) -> bool:
    rc, out = _run(["gh", "workflow", "run", WORKFLOW, "--repo", repo, "--ref", "master"], token)
    if rc != 0:
        log(f"[warn] {repo}: dispatch failed :: {out.strip()[:140]}")
        return False
    log(f"{repo}: DISPATCHED (chain had stalled)")
    return True


def drain_alive() -> bool:
    """True if a gha_drain.py process is running. Checked by name because the lock file
    survives a hard kill and would read as 'alive' forever."""
    rc, out = _run(["powershell", "-NoProfile", "-Command",
                    "(Get-CimInstance Win32_Process -Filter \"Name='python.exe'\" | "
                    "Where-Object { $_.CommandLine -like '*gha_drain*' }).Count"])
    for ln in reversed(out.strip().splitlines()):
        t = ln.strip()
        if t.isdigit():
            return int(t) > 0
    return True          # unknown -> assume alive; never spawn a second loader on a guess


PAUSE_FLAG = os.path.join(ROOT, ".INGEST_PAUSED")


def ensure_drain() -> None:
    """The drain is what actually moves crawled rows into the DB. It died once mid-run
    (staging held 1.95M rows while the DB sat flat), and nothing noticed until a human
    looked. A dead drain is invisible: the crawl keeps producing artifacts and everything
    looks healthy."""
    if os.path.exists(PAUSE_FLAG):
        # Ingest is deliberately paused — the DB volume filled and MariaDB was killed
        # mid-query once already. The CRAWL must keep running (it writes nothing to this
        # machine), so the shard checks below still execute; only the drain is held back.
        # Delete .INGEST_PAUSED to resume.
        return
    if drain_alive():
        return
    lock = os.path.join(ROOT, ".gha_drain.lock")
    try:
        os.remove(lock)          # stale: the holder is gone
    except OSError:
        pass
    subprocess.Popen([sys.executable, "bin/gha_drain.py", "--interval", "300"],
                     cwd=ROOT, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                     creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))
    log("drain was DEAD — restarted")


def tick() -> None:
    ensure_drain()
    for user, repo in SHARDS:
        tok = token_for(user)
        if not tok:
            log(f"[warn] {repo}: no usable token for {user} — shard is DOWN")
            continue
        n = active_runs(repo, tok)
        if n == 0:
            poke(repo, tok)
        elif n > 0:
            log(f"{repo}: ok ({n} active)")


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--once", action="store_true")
    ap.add_argument("--interval", type=int, default=300)
    a = ap.parse_args()
    log("watchdog start")
    while True:
        try:
            tick()
        except Exception as exc:            # noqa: BLE001 — must survive anything
            log(f"[warn] tick failed: {type(exc).__name__}: {exc}")
        if a.once:
            return 0
        time.sleep(a.interval)


def _selftest() -> None:
    """ponytail: the one rule worth protecting is 'unknown must not dispatch'."""
    calls: list[str] = []
    global _run
    orig = _run

    def fake(cmd, token=None, timeout=180):
        calls.append(cmd[1] if len(cmd) > 1 else "")
        if cmd[:3] == ["gh", "auth", "token"]:
            return 0, "gho_faketoken\n"
        if cmd[:3] == ["gh", "run", "list"]:
            return 1, "boom"          # API failure
        return 0, ""

    _run = fake
    try:
        assert active_runs("x/y", "t") == -1, "API failure must read as unknown, not 0"
        tick()
        assert "workflow" not in calls, "must NOT dispatch when run count is unknown"
    finally:
        _run = orig
    print("selfcheck ok")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        _selftest()
        raise SystemExit(0)
    raise SystemExit(main())
