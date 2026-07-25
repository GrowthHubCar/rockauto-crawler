#!/usr/bin/env python
"""backfill_style_keys.py — re-fetch each leaf's FULL page and reconcile the three
things the fragment crawl got wrong: Style sub-group, moreinfo_key, and fitment.

crawl_jsonl preferred the catalogapi `navnode_fetch` FRAGMENT (half the bytes), which
omits the `.listing-sortgroupheader` divs (Style) and the moreinfo link (key), and
whose part set drifts from the live full page over time. This re-fetches the FULL
leaf page for every leaf (fattest first) and, in one pass:

  * Style  — insert a 'Style' attribute where absent.
  * key    — set moreinfo_key where NULL (unlocks the specs pass for that part).
  * fitment— reconcile part<->vehicle for this leaf: ADD parts the live page lists
             but we don't fit, and PRUNE parts we fit that the live page dropped.
             The vehicle is resolved from the source_url slug (verified 100% on a
             3k sample); prune is GUARDED — skipped when the fetch looks partial, so
             a blocked/truncated page can never delete valid fitments (add is always
             safe). New parts not yet in our DB can't be fitment-added (no part_id).

It never touches price/description/variants/images. Runs via Evomi (US); byte-metered
through --max-gb.

    python bin/backfill_style_keys.py [--limit N] [--max-gb 1] [--commit-every 1] [--no-prune]
"""
from __future__ import annotations

import argparse
import os
import re
import sys
import time
from collections import Counter

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
import db          # noqa: E402
import parsers     # noqa: E402
import pymysql.err  # noqa: E402
from ra_client import RAClient, BUDGET, BudgetExceeded, Blocked  # noqa: E402
from proxy_manager import EvomiProxyManager  # noqa: E402

_SLUG_RE = re.compile(r"[^a-z0-9]+")
_LEAF_RE = re.compile(r"/catalog/([^,]+),([^,]+),([^,]+),([^,]+),")  # make,year,model,engine,carcode...


def _path(source_url: str) -> str:
    return source_url.replace("https://www.rockauto.com", "").replace("http://www.rockauto.com", "")


def _slug(s: str) -> str:
    return _SLUG_RE.sub("-", (s or "").strip().lower()).strip("-")


def _veh_slug_from_source(url: str) -> str | None:
    m = _LEAF_RE.search(url or "")
    if not m:
        return None
    make, year, model, engine = m.group(1), m.group(2), m.group(3), m.group(4)
    return _slug(f"{year} {make} {model} {engine}")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--max-gb", default=os.getenv("SP_MAX_GB", ""))
    ap.add_argument("--commit-every", type=int, default=1)
    ap.add_argument("--no-prune", action="store_true", help="add missing fitments but never delete")
    args = ap.parse_args()

    if args.max_gb:
        BUDGET.configure(args.max_gb, os.getenv("SP_BUDGET_STATE", "coverage/backfill_bytes.txt"))
    os.environ.setdefault("EVOMI_COUNTRY", "US")

    conn = db.connect()
    cur = conn.cursor()

    # Every leaf, fattest first: one full-page fetch fixes Style+key+fitment for all
    # its parts, so a 107-part leaf is the best value-per-byte and covers the styled
    # categories (wipers, brakes) where grouping + fitment accuracy matter most.
    q = ("SELECT source_url, COUNT(*) AS n FROM parts WHERE source_url IS NOT NULL "
         "GROUP BY source_url ORDER BY n DESC")
    if args.limit:
        q += f" LIMIT {int(args.limit)}"
    cur.execute(q)
    leaves = [r["source_url"] for r in cur.fetchall()]
    print(f"{len(leaves)} leaves to re-fetch (full page) for Style/key/fitment", flush=True)
    if not leaves:
        return 0

    # (brand_lower, pn_lower) -> {id, need_style, need_key, category_id}
    cur.execute(
        "SELECT p.id, LOWER(COALESCE(b.name,'')) br, LOWER(p.part_number) pn, p.category_id AS cat, "
        "  (p.moreinfo_key IS NULL) AS need_key, "
        "  (NOT EXISTS(SELECT 1 FROM part_attributes a WHERE a.part_id=p.id AND a.name='Style')) AS need_style "
        "FROM parts p LEFT JOIN brands b ON b.id=p.brand_id")
    lookup: dict = {}
    for r in cur.fetchall():
        lookup[(r["br"], r["pn"])] = {"id": r["id"], "cat": r["cat"],
                                      "need_key": bool(r["need_key"]),
                                      "need_style": bool(r["need_style"])}
    cur.execute("SELECT slug, id FROM vehicles")
    slug2id = {r["slug"]: r["id"] for r in cur.fetchall()}

    client = RAClient(EvomiProxyManager())
    done = styled = keyed = fit_add = fit_del = 0
    for url in leaves:
        try:
            html = client.get(_path(url))
        except BudgetExceeded as e:
            print(f"[stop] {e} — {done} leaves this run", flush=True)
            break
        except Blocked as e:
            print(f"[warn] blocked {url}: {e}", flush=True)
            continue
        except Exception as e:  # noqa: BLE001
            print(f"[warn] fetch failed {url}: {type(e).__name__}: {e}", flush=True)
            continue
        try:
            listings = parsers.parse_listings(html, {"category_path": ""})
        except Exception as e:  # noqa: BLE001
            print(f"[warn] parse failed {url}: {type(e).__name__}: {e}", flush=True)
            continue

        # --- Style + moreinfo_key backfill (guarded per part) ---
        truth = set()
        for lst in listings:
            key = ((lst.get("brand_name") or "").lower(), (lst.get("part_number") or "").lower())
            truth.add(key)
            row = lookup.get(key)
            if not row:
                continue
            style = next((a["value"] for a in (lst.get("attributes") or [])
                          if a.get("name") == "Style"), None)
            mi = lst.get("moreinfo") or {}
            mkey = f"{mi['pk']},{mi['cc']},{mi['pt']}" if mi.get("pk") else None
            try:
                if style and row["need_style"]:
                    cur.execute("INSERT INTO part_attributes (part_id,name,value) VALUES (%s,'Style',%s)",
                                (row["id"], style))
                    row["need_style"] = False
                    styled += 1
                if mkey and row["need_key"]:
                    cur.execute("UPDATE parts SET moreinfo_key=%s WHERE id=%s AND moreinfo_key IS NULL",
                                (mkey, row["id"]))
                    row["need_key"] = False
                    keyed += 1
            except pymysql.err.OperationalError as e:
                conn.rollback()
                if e.args[0] not in (1205, 1213):
                    raise
                time.sleep(1)

        # --- fitment reconcile for this leaf's vehicle ---
        vid = slug2id.get(_veh_slug_from_source(url) or "")
        matched = [(k, lookup[k]) for k in truth if k in lookup]
        if vid and matched:
            cats = Counter(info["cat"] for _, info in matched if info["cat"])
            if cats:
                category_id = cats.most_common(1)[0][0]
                truth_in_cat = {k for k, info in matched if info["cat"] == category_id}
                cur.execute(
                    "SELECT p.id, LOWER(COALESCE(b.name,'')) br, LOWER(p.part_number) pn "
                    "FROM part_fitment pf JOIN parts p ON p.id=pf.part_id "
                    "LEFT JOIN brands b ON b.id=p.brand_id "
                    "WHERE pf.vehicle_id=%s AND p.category_id=%s", (vid, category_id))
                cur_map = {(r["br"], r["pn"]): r["id"] for r in cur.fetchall()}
                # GUARD: only prune when the fetch clearly covered the leaf — a partial
                # or blocked page (few parsed parts vs many currently fitted) must never
                # delete fitments. Adds are always safe.
                prune_ok = (not args.no_prune) and len(truth_in_cat) >= max(1, int(len(cur_map) * 0.6))
                try:
                    for k in truth_in_cat - set(cur_map):
                        cur.execute("INSERT IGNORE INTO part_fitment (part_id,vehicle_id) VALUES (%s,%s)",
                                    (lookup[k]["id"], vid))
                        fit_add += 1
                    if prune_ok:
                        for k in set(cur_map) - truth_in_cat:
                            cur.execute("DELETE FROM part_fitment WHERE part_id=%s AND vehicle_id=%s",
                                        (cur_map[k], vid))
                            fit_del += 1
                except pymysql.err.OperationalError as e:
                    conn.rollback()
                    if e.args[0] not in (1205, 1213):
                        raise
                    time.sleep(1)

        done += 1
        if done % args.commit_every == 0:
            conn.commit()
        if done % 50 == 0:
            print(f"  {done}/{len(leaves)} leaves | +{styled} style +{keyed} key | "
                  f"fit +{fit_add}/-{fit_del} | spent {BUDGET.spent/1e9:.3f}GB", flush=True)

    conn.commit()
    print(f"DONE: {done} leaves | +{styled} Style +{keyed} keys | "
          f"fitment +{fit_add}/-{fit_del} | spent {BUDGET.spent/1e9:.3f}GB", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
