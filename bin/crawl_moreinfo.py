#!/usr/bin/env python
"""crawl_moreinfo.py — enrich parts with their RockAuto moreinfo detail page.

The listing crawl captures each part's `moreinfo_key` {pk,cc,pt}. The listing
rows do NOT carry the marketing description, Features & Benefits, the structured
Specifications table, or alternate inventory numbers — those live only on
moreinfo.php. This fetches ONE detail page per part (dedup is implicit: one row
per part), parses it (scraper.parsers.parse_moreinfo), and stores:

    description -> parts.description         (richer blurb; features appended)
    specs       -> part_attributes           (renders in the storefront Specs table)
    alt numbers -> part_interchange (type='alternate')

Resumable via parts.moreinfo_done (set to 1 after a part is enriched). Byte-metered
through ra_client.BUDGET, so --max-gb is a HARD ceiling the Evomi bill can't cross.
Runs via Evomi (US) like the listing crawl; safe to Ctrl-C and re-run.

    python bin/crawl_moreinfo.py [--limit N] [--max-gb 5] [--commit-every 50]
"""
from __future__ import annotations

import argparse
import os
import sys
import time

import pymysql.err

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
sys.path.insert(0, os.path.join(ROOT, "bin"))

import db                                   # noqa: E402
from loader import norm_number              # noqa: E402  (reuse the same normaliser)
from parsers import parse_moreinfo          # noqa: E402
from ra_client import RAClient, BUDGET, BudgetExceeded, Blocked  # noqa: E402
from proxy_manager import EvomiProxyManager  # noqa: E402


def _store(conn, pid: int, mi: dict) -> None:
    """Persist one part's parsed moreinfo. Existence-guarded so a re-run and the
    listing-crawl's own attributes never duplicate."""
    desc = mi.get("description") or ""
    if mi.get("features"):
        desc = (desc + "\n\nFeatures & Benefits:\n"
                + "\n".join("• " + f for f in mi["features"])).strip()
    with conn.cursor() as c:
        # Description: only overwrite when moreinfo actually gave us content, so we
        # never blank a good listing description with an empty detail page.
        if desc:
            c.execute("UPDATE parts SET description=%s WHERE id=%s", (desc[:65000], pid))
        # Specs -> part_attributes (no unique key -> guard on part_id+name).
        for s in mi.get("specs", []):
            name, val = s.get("name"), s.get("value")
            if not name or not val:
                continue
            c.execute(
                "INSERT INTO part_attributes (part_id, name, value) "
                "SELECT %s,%s,%s FROM DUAL WHERE NOT EXISTS "
                "(SELECT 1 FROM part_attributes WHERE part_id=%s AND name=%s)",
                (pid, name[:120], val[:255], pid, name[:120]),
            )
        # Alternate inventory numbers -> interchange (uq part_id+number_norm).
        for n in mi.get("alt_numbers", []):
            nn = norm_number(n)
            if not nn:
                continue
            c.execute(
                "INSERT INTO part_interchange "
                "(part_id, brand_name, part_number, number_norm, type) "
                "VALUES (%s,NULL,%s,%s,'alternate') "
                "ON DUPLICATE KEY UPDATE type=VALUES(type)",
                (pid, n[:100], nn[:100]),
            )
        c.execute("UPDATE parts SET moreinfo_done=1 WHERE id=%s", (pid,))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0, help="cap parts processed (0 = all)")
    ap.add_argument("--max-gb", default=os.getenv("SP_MAX_GB", ""),
                    help="hard Evomi byte ceiling in GB (blank = unlimited)")
    ap.add_argument("--commit-every", type=int, default=50)
    ap.add_argument("--shards", type=int, default=1, help="total parallel shards")
    ap.add_argument("--shard", type=int, default=0, help="this shard index [0..shards)")
    args = ap.parse_args()

    if args.max_gb:
        BUDGET.configure(args.max_gb, os.getenv("SP_BUDGET_STATE", "coverage/moreinfo_bytes.txt"))
    os.environ.setdefault("EVOMI_COUNTRY", "US")

    conn = db.connect()
    with conn.cursor() as c:
        # Shards partition by id so N parallel workers touch DISJOINT rows (no
        # overlap, no cross-worker lock contention). MOD keeps it index-friendly.
        shardf = (f" AND MOD(id,{int(args.shards)})={int(args.shard)}"
                  if args.shards > 1 else "")
        q = ("SELECT id, moreinfo_key FROM parts "
             f"WHERE moreinfo_key IS NOT NULL AND moreinfo_done=0{shardf} ORDER BY id")
        if args.limit:
            q += f" LIMIT {int(args.limit)}"
        c.execute(q)
        todo = c.fetchall()
    print(f"{len(todo)} parts to enrich with moreinfo", flush=True)
    if not todo:
        return 0

    client = RAClient(EvomiProxyManager())
    done = specs = 0
    for i, row in enumerate(todo, 1):
        pid, key = row["id"], row["moreinfo_key"]
        try:
            pk, cc, pt = key.split(",")
        except ValueError:
            continue
        try:
            html = client.get(f"/en/moreinfo.php?pk={pk}&cc={cc}&pt={pt}")
        except BudgetExceeded as e:
            print(f"[stop] {e} — {done} enriched this run", flush=True)
            break
        except Blocked as e:
            # Leave moreinfo_done=0 so the next run retries this part.
            print(f"[warn] blocked on part {pid}: {e}", flush=True)
            continue
        except Exception as e:  # noqa: BLE001 - one bad page never aborts the pass
            print(f"[warn] part {pid} fetch/parse failed: {type(e).__name__}: {e}", flush=True)
            continue
        mi = parse_moreinfo(html)
        # Per-part commit + retry: N parallel shards write parts/part_attributes, so
        # InnoDB can deadlock (1213) or lock-wait (1205). Roll back + retry instead of
        # letting a shard die (as one did before this guard).
        for _try in range(6):
            try:
                _store(conn, pid, mi)
                conn.commit()
                break
            except pymysql.err.OperationalError as e:
                conn.rollback()
                if e.args[0] not in (1205, 1213) or _try == 5:
                    raise
                time.sleep(0.5 * (_try + 1))
        done += 1
        specs += len(mi.get("specs", []))
        if i % args.commit_every == 0:
            print(f"  {i}/{len(todo)} enriched={done} specs={specs} "
                  f"spent={BUDGET.spent/1e9:.2f}GB", flush=True)
    conn.commit()
    conn.close()
    print(f"DONE: enriched {done} parts, {specs} spec rows, spent {BUDGET.spent/1e9:.3f}GB", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
