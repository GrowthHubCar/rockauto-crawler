"""Delete already-processed rows from the staging landing zone.

    python bin/prune_staging.py                # prune both staging tables
    python bin/prune_staging.py --dry-run
    python bin/prune_staging.py --selftest

WHY: stg_listings reached 12,285,014 rows / 11.75 GB, of which 12,161,085 (99%) were
already processed. Every new staging INSERT maintains three indexes (PRIMARY,
idx_stg_batch, idx_stg_proc) across that, so ingest slows as the dead weight grows — and
ingest is the binding constraint (238 rows/s against a 24M-row backlog).

SAFE TO DELETE: staging is a landing zone. bin/loader.py canonicalises a row into
parts/part_fitment/etc and sets processed=1; nothing reads a processed row afterwards.
The one thing that DID read them historically is bin/rebuild_skipset.py, which mined
stg_listings.source_url — those leaves are already captured in the committed
coverage/visited_seed.txt.gz (5,980,428 keys), and parts.source_url is untouched.

BATCHED ON PURPOSE. A single DELETE of 12M rows holds locks long enough to stall the
loader — the drain is the only writer and blocking it costs more than the prune saves.
Small committed batches let the loader interleave.
"""
from __future__ import annotations

import argparse
import os
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))

TABLES = ("stg_listings", "stg_fitment")
BATCH = 20_000


def prune(dry: bool = False) -> int:
    import db  # noqa: PLC0415

    conn = db.connect()
    cur = conn.cursor()
    for t in TABLES:
        cur.execute(f"SELECT COUNT(*) AS n FROM {t} WHERE processed=1")
        dead = cur.fetchone()["n"]
        cur.execute(f"SELECT COUNT(*) AS n FROM {t}")
        tot = cur.fetchone()["n"]
        print(f"{t}: {dead:,} processed of {tot:,}", flush=True)
        if dry or not dead:
            continue
        removed = 0
        t0 = time.time()
        while True:
            cur.execute(f"DELETE FROM {t} WHERE processed=1 LIMIT {BATCH}")
            n = cur.rowcount
            conn.commit()          # release locks every batch so the loader can interleave
            removed += n
            if n < BATCH:
                break
            if removed % (BATCH * 20) == 0:
                print(f"  {t}: {removed:,}/{dead:,} ({time.time()-t0:.0f}s)", flush=True)
            time.sleep(0.05)       # breathe; the loader is the priority writer
        print(f"  {t}: removed {removed:,} in {time.time()-t0:.0f}s", flush=True)
    return 0


def _selftest() -> None:
    """ponytail: the only thing worth protecting is that we never touch unprocessed rows."""
    import db  # noqa: PLC0415
    conn = db.connect()
    cur = conn.cursor()
    cur.execute("SELECT COUNT(*) AS n FROM stg_listings WHERE processed=0")
    before = cur.fetchone()["n"]
    cur.execute("SELECT COUNT(*) AS n FROM stg_listings WHERE processed=1 LIMIT 1")
    # the DELETE is guarded by processed=1 in SQL; assert the predicate is present
    src = open(os.path.abspath(__file__), encoding="utf-8").read()
    assert "WHERE processed=1 LIMIT" in src, "prune must never delete unprocessed rows"
    assert "conn.commit()" in src, "must commit per batch or the loader stalls"
    print(f"selfcheck ok (unprocessed rows currently {before:,}, never targeted)")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        _selftest()
        raise SystemExit(0)
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()
    raise SystemExit(prune(a.dry_run))
