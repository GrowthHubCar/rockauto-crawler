"""fleet_watchdog.py — keep all 8 crawl shards alive without a human in the loop.

WHY THIS EXISTS
On 2026-08-06 a GitHub Actions outage ("Internal Server Error occurred while
resolving actions/checkout@v4") killed 6 of 37 jobs at "Set up job". That made each
run's overall conclusion `failure`, and although crawl.yml's relaunch job is
`if: always()`, its FIRST step is actions/checkout - the very thing the outage could
not resolve. So the relaunch died at setup and the self-relaunch chain broke on 6
shards. The fleet sat idle for 2.5 hours and nothing noticed.

A self-relaunching workflow cannot be its own watchdog: whatever breaks the run can
break the relaunch. The supervisor has to live outside Actions.

Each pass: for every shard, if there is no run in_progress/queued and the last run
ended more than STALE_MIN minutes ago, dispatch a new one. GitHub 5xx is expected
during an outage and is logged, not raised - the next pass retries.
"""
from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
from datetime import datetime, timedelta, timezone

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOG_FILE = os.path.join(ROOT, "logs", "fleet_watchdog.log")
STATUS_FILE = os.path.join(ROOT, "logs", "fleet_status.json")
GH = os.getenv("SP_GH_BIN") or shutil.which("gh") or "/usr/bin/gh"

# A run that ended this long ago with nothing queued behind it means the chain broke.
# Cycles are ~10 min, so 25 tolerates one slow cycle without flapping.
STALE_MIN = int(os.getenv("SP_WATCHDOG_STALE_MIN", "25"))

SHARDS = [
    ("ahmerfr/rockauto-crawler", "ahmerfr"),
    ("haseeb-shoukat2029/rockauto-crawler", "haseeb-shoukat2029"),
    ("Affilusion/rockauto-crawler", "ahmerfr"),
    ("Nexarce/rockauto-crawler", "haseeb-shoukat2029"),
    ("arbyahad/rockauto-crawler", "arbyahad"),
    ("GrowthHubCar/rockauto-crawler", "arbyahad"),
    ("artechonza/rockauto-crawler", "artechonza"),
    ("Techonza/rockauto-crawler", "artechonza"),
]


def log(msg: str) -> None:
    line = f"{datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')}Z  {msg}"
    print(line, flush=True)
    os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
    with open(LOG_FILE, "a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def gh(args: list[str], user: str | None = None):
    if user:
        subprocess.run([GH, "auth", "switch", "--user", user],
                       capture_output=True, text=True, cwd=ROOT)
    return subprocess.run([GH, *args], capture_output=True, text=True, cwd=ROOT)


def main() -> int:
    now = datetime.now(timezone.utc)
    status, restarted, stalled, errors = [], 0, 0, 0

    for repo, user in SHARDS:
        name = repo.split("/")[0]
        out = gh(["run", "list", "--repo", repo, "--workflow", "crawl.yml",
                  "--limit", "1", "--json", "databaseId,status,conclusion,updatedAt"], user)
        if out.returncode != 0:
            log(f"{name}: cannot list runs ({out.stderr.strip()[:90]})")
            status.append({"shard": name, "state": "unknown"})
            errors += 1
            continue
        try:
            runs = json.loads(out.stdout or "[]")
        except ValueError:
            runs = []
        if not runs:
            status.append({"shard": name, "state": "no-runs"})
            continue

        r = runs[0]
        st = r.get("status")
        if st in ("in_progress", "queued", "requested", "waiting", "pending"):
            status.append({"shard": name, "state": st})
            continue

        # finished - how long ago?
        try:
            ended = datetime.strptime(r["updatedAt"], "%Y-%m-%dT%H:%M:%SZ").replace(tzinfo=timezone.utc)
        except (KeyError, ValueError):
            ended = now
        age_min = (now - ended).total_seconds() / 60
        if age_min < STALE_MIN:
            status.append({"shard": name, "state": "between-runs", "age_min": round(age_min, 1)})
            continue

        stalled += 1
        d = gh(["workflow", "run", "crawl.yml", "--repo", repo, "--ref", "master"], user)
        if d.returncode == 0:
            log(f"{name}: STALLED {age_min:.0f}m (last {r.get('conclusion')}) — restarted")
            restarted += 1
            status.append({"shard": name, "state": "restarted", "age_min": round(age_min, 1)})
        else:
            # 5xx during a GitHub incident is expected; the next pass retries.
            log(f"{name}: STALLED {age_min:.0f}m — dispatch failed ({d.stderr.strip()[:90]})")
            errors += 1
            status.append({"shard": name, "state": "restart-failed", "age_min": round(age_min, 1)})

    live = sum(1 for s in status if s["state"] in ("in_progress", "queued", "between-runs"))
    if stalled or errors:
        log(f"pass: {live}/8 live, {stalled} stalled, {restarted} restarted, {errors} errors")
    os.makedirs(os.path.dirname(STATUS_FILE), exist_ok=True)
    with open(STATUS_FILE, "w", encoding="utf-8") as fh:
        json.dump({"ts": now.strftime("%Y-%m-%dT%H:%M:%SZ"), "live": live,
                   "stalled": stalled, "restarted": restarted, "shards": status}, fh, indent=2)
    return 0


if __name__ == "__main__":
    sys.exit(main())
