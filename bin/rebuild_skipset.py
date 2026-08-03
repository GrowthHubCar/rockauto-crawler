"""Rebuild the crawler's skip-set from what the DB can PROVE was crawled.

    python bin/rebuild_skipset.py            # writes coverage/visited_seed.txt.gz
    python bin/rebuild_skipset.py --selftest

WHY: only ~11% of crawled rows were new — the fleet kept re-fetching leaves already in
the DB because nothing ever recorded which leaves had been visited. The seed built from
source_url alone caught 1.4M leaves, but a leaf URL appears in source_url only for the
ONE vehicle a part was first seen on.

THE KEY INSIGHT: every part_fitment row is itself proof. If part P is recorded as fitting
vehicle V, then the leaf page for (V, P's part-type) was crawled — that is the only place
that fact could have come from. So the crawled-leaf set is the cross product of real
fitments, not of the catalog:

    leaf(V, P) = prefix(V) + "," + triple(P)
    prefix(V)  = make,year,model,engine,carcode      (5 fields)
    triple(P)  = group,subgroup,parttypeid           (3 fields)

prefix(V) comes from any leaf URL mentioning V (carcode lives only in the URL, not in the
vehicles table). Matching those URLs back to vehicle_id by (make, year, model, engine)
was measured at 100.0% — 29,317 hit, 9 miss, the misses being URL-encoded '%2B' in Lexus
plug-in model names.

SAFETY: only the 8-field carcode form is ever emitted. A nav URL in the skip-set would
make the crawler skip ground it has NEVER crawled, permanently — crawl_jsonl caches a leaf
only when it actually yielded rows, for exactly this reason.
"""
from __future__ import annotations

import argparse
import gzip
import os
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))

OUT = os.path.join(ROOT, "coverage", "visited_seed.txt.gz")
PREFIX_FIELDS = 5
LEAF_FIELDS = 8


def norm(s: str | None) -> str:
    return (s or "").strip().lower().replace(" ", "+")


def split_leaf(href: str) -> tuple[str, str] | None:
    """('make,year,model,engine,carcode', 'group,subgroup,ptid') or None."""
    if not href.startswith("/en/catalog/"):
        return None
    f = href.split("/en/catalog/", 1)[-1].split(",")
    if len(f) != LEAF_FIELDS:
        return None
    return ",".join(f[:PREFIX_FIELDS]), ",".join(f[PREFIX_FIELDS:])


def build(max_fitment_id: int | None = None) -> int:
    import db  # noqa: PLC0415
    import config  # noqa: PLC0415
    import pymysql.cursors  # noqa: PLC0415

    B = config.BASE.rstrip("/")
    conn = db.connect()
    cur = conn.cursor()
    cur.execute("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED")
    t0 = time.time()

    # 1. every leaf URL the DB knows -> seeds the set AND yields prefix per vehicle key
    known: set[str] = set()
    prefix_by_key: dict[tuple, str] = {}
    for tbl in ("stg_listings", "parts"):
        cur.execute(f"SELECT DISTINCT source_url FROM {tbl} "
                    f"WHERE source_url LIKE '{B}/en/catalog/%'")
        for r in cur.fetchall():
            href = r["source_url"][len(B):]
            parts = split_leaf(href)
            if not parts:
                continue
            known.add(href)
            pre = parts[0]
            f = pre.split(",")
            prefix_by_key.setdefault((f[0], f[1], f[2], f[3]), pre)
    print(f"[1] known leaves {len(known):,} · vehicle prefixes {len(prefix_by_key):,} "
          f"({time.time()-t0:.0f}s)", flush=True)

    # 2. vehicle_id -> prefix
    cur.execute("""SELECT v.id, LOWER(mk.name) mk, v.year, LOWER(mo.name) mo, LOWER(e.name) en
                   FROM vehicles v
                   JOIN makes mk ON mk.id=v.make_id
                   JOIN models mo ON mo.id=v.model_id
                   LEFT JOIN engines e ON e.id=v.engine_id""")
    prefix_by_vid: dict[int, str] = {}
    for r in cur.fetchall():
        key = (norm(r["mk"]), str(r["year"]), norm(r["mo"]), norm(r["en"]))
        pre = prefix_by_key.get(key)
        if pre:
            prefix_by_vid[r["id"]] = pre
    print(f"[2] vehicles with a prefix {len(prefix_by_vid):,}", flush=True)

    # 3. part_id -> triple
    cur.execute(f"SELECT id, source_url FROM parts WHERE source_url LIKE '{B}/en/catalog/%'")
    triple_by_pid: dict[int, str] = {}
    for r in cur.fetchall():
        parts = split_leaf(r["source_url"][len(B):])
        if parts:
            triple_by_pid[r["id"]] = parts[1]
    print(f"[3] parts with a triple {len(triple_by_pid):,} ({time.time()-t0:.0f}s)", flush=True)

    # 4. stream part_fitment — server-side cursor, 36M rows will not fit in memory
    #
    # THE KEY INSIGHT ABOVE HOLDS ONLY FOR LEAF-DERIVED FITMENTS. bin/crawl_bg_jsonl.py
    # gets fitments from `func=getbuyersguide` — it proves part P fits vehicle V without
    # ever loading leaf(V, P's parttype), and that leaf may list OTHER parts we do not
    # have. Feeding those rows in here would make the crawler permanently skip ground it
    # has never crawled: silent, unrecoverable loss. So: record max(part_fitment.id)
    # BEFORE the first `bin/ingest_bg.py` run and pass it as --max-fitment-id forever
    # after. Default None = pre-BG behaviour, unchanged.
    ss = conn.cursor(pymysql.cursors.SSCursor)
    if max_fitment_id:
        ss.execute("SELECT vehicle_id, part_id FROM part_fitment WHERE id <= %s",
                   [int(max_fitment_id)])
        print(f"[4] leaf-derived fitments only (id <= {int(max_fitment_id):,})", flush=True)
    else:
        ss.execute("SELECT vehicle_id, part_id FROM part_fitment")
    added = seen = 0
    for vid, pid in ss:
        seen += 1
        pre = prefix_by_vid.get(vid)
        tri = triple_by_pid.get(pid)
        if pre and tri:
            k = "/en/catalog/" + pre + "," + tri
            if k not in known:
                known.add(k)
                added += 1
        if seen % 5_000_000 == 0:
            print(f"    {seen:,} fitments -> +{added:,} new leaves "
                  f"({time.time()-t0:.0f}s)", flush=True)
    ss.close()
    print(f"[4] scanned {seen:,} fitments, reconstructed +{added:,} leaves", flush=True)

    bad = [k for k in list(known)[:2000] if len(k.split("/en/catalog/")[-1].split(",")) != LEAF_FIELDS]
    if bad:
        sys.exit(f"FATAL: malformed key would suppress uncrawled ground: {bad[:3]}")

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with gzip.open(OUT, "wt", encoding="utf-8") as fh:
        fh.write("\n".join(sorted(known)))
    print(f"[5] wrote {len(known):,} leaves -> {OUT} "
          f"({os.path.getsize(OUT):,} bytes, {time.time()-t0:.0f}s)")
    return 0


def _selftest() -> None:
    assert split_leaf("/en/catalog/ac,1947,two-litre,2.0l+l6,148,cooling,coolant,113") == \
        ("ac,1947,two-litre,2.0l+l6,148", "cooling,coolant,113")
    assert split_leaf("/en/catalog/acura") is None, "nav page must never enter the skip-set"
    assert split_leaf("/en/catalog/ac,1947,two-litre") is None
    assert split_leaf("https://x/en/catalog/a,b,c,d,e,f,g,h") is None, "must be a bare href"
    assert norm("3.4L V6") == "3.4l+v6"
    assert norm(None) == ""
    print("selfcheck ok")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        _selftest()
        raise SystemExit(0)
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--max-fitment-id", type=int, default=None,
                    help="only trust part_fitment rows with id <= N as proof a leaf was "
                         "crawled. Set this to max(id) taken BEFORE the first "
                         "bin/ingest_bg.py run — buyers-guide fitments prove a FITMENT, "
                         "not a leaf visit, and would make the crawler skip uncrawled ground.")
    a = ap.parse_args()
    raise SystemExit(build(a.max_fitment_id))
