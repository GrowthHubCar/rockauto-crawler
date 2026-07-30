#!/usr/bin/env python3
"""plan_targets.py — turn the DB into a crawl plan: what to SKIP, what to SEED,
and in what ORDER, so a fixed request budget buys the most NEW rows.

The crawler's budget is requests, not time: ~780 leaf fetches per source IP before
an L3 blackhole. Measured on this DB (2026-07-27):

  * 2,878,637 (vehicle, category) leaves are ALREADY banked, holding all 14,545,843
    fitments. Re-fetching any of them costs a 2 MB request and returns zero new rows.
    1,895,248 of those sit on 5,255 vehicles that are >=200 categories deep — those
    vehicles are done, and walking one costs ~283 requests for nothing.
  * Rows per leaf are wildly uneven BY CATEGORY: the top 20 of 3,970 categories are
    4.1% of leaves but 21.5% of all rows (Brake Pad 43.6 rows/leaf, Wiper Blade 37.5,
    median category 2.2). Draining rich categories first is a ~5x rows/request lever.
  * Rows per leaf are FLAT by vehicle (3.96-5.65 across year bands), so vehicle choice
    is a weak yield lever — worth ~1.5x, not 5x. Coverage and category are the levers.

Writes (default --out plan):
    plan/cat_yield.tsv        category-slug TAB avg rows   -> SP_CAT_YIELD (leaf order)
    plan/targets.tsv          the prioritised work order (human + machine readable)
    plan/skip/<ukey>.txt      covered-node keys for that unit -> SP_SKIP_FILE
    plan/fr/f_<ukey>.ndjson   pre-seeded frontier for that unit -> --frontier-file

ukey matches bin/wq.py exactly, so fleet_v2.sh picks these up with no changes beyond
one SP_SKIP_FILE env var.

    python bin/plan_targets.py --units units_all.tsv --out plan
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "scraper"))
import db  # noqa: E402  (scraper/db.py — same DSN as the rest of the pipeline)

# Measured rows-per-leaf and leaves-per-vehicle, by year band (well-covered vehicles
# only, >=100 categories, n=8,688). These are the estimator's only constants and they
# came off this DB — re-measure with --stats if the catalog shifts.
BANDS = [  # (year_min, est_leaves_per_vehicle, est_rows_per_leaf)
    (2023, 231, 3.96),
    (2006, 300, 5.34),
    (1996, 297, 5.65),
    (1990, 286, 5.18),
    (1980, 252, 4.71),
    (0,    182, 3.99),
]
NAV_REQS = 24          # 1 vehicle page + ~23 group pages, paid per vehicle regardless
DONE_FRAC = 0.90       # >= this share of the estimated leaves = done, prune the vehicle


def band(year: int) -> tuple[int, float]:
    for lo, cats, rpl in BANDS:
        if year >= lo:
            return cats, rpl
    return BANDS[-1][1], BANDS[-1][2]


def nm(s: str) -> str:
    """Match a DB make name to a units-file make. Unit files spell multi-word makes
    in URL form ('land+rover'); RockAuto and the DB say 'LAND ROVER'. Same bug that
    once emptied 19 makes' frontiers — see crawl_jsonl.run's _nm."""
    return (s or "").strip().lower().replace("+", " ")


def ukey_for(make: str, lo, hi) -> str:
    """Identical to bin/wq.py load_units() — the S3 claim marker and these filenames
    must never disagree."""
    return re.sub(r"[^A-Za-z0-9._-]", "_", f"{make}_{lo}-{hi}")


def model_href(make: str, year, model: str, observed: str | None) -> str:
    """/en/catalog/<make>,<year>,<model>. Verified against every leaf URL in the DB:
    10,094 of 10,095 distinct prefixes rebuild exactly as LOWER(name) with ' '->'+'.
    The one miss (packard one-twenty) is why `observed` wins when we have it."""
    if observed:
        return "/en/catalog/" + observed
    lower = lambda s: str(s or "").strip().lower().replace(" ", "+")  # noqa: E731
    return f"/en/catalog/{lower(make)},{year},{lower(model)}"


# --------------------------------------------------------------------------- #
# SQL
# --------------------------------------------------------------------------- #
# Per-vehicle coverage. A "leaf" is one (vehicle, category) pair = exactly one
# RockAuto parttype page = exactly one request. Two-level aggregate so the 14.5M-row
# fitment scan happens once.
SQL_COVERAGE = """
DROP TEMPORARY TABLE IF EXISTS t_vcov;
CREATE TEMPORARY TABLE t_vcov (
  vid INT UNSIGNED PRIMARY KEY, cats INT NOT NULL, rows_held INT NOT NULL
) ENGINE=MEMORY;
INSERT INTO t_vcov
SELECT vid, COUNT(*), SUM(n) FROM (
  SELECT pf.vehicle_id vid, p.category_id cid, COUNT(*) n
  FROM part_fitment pf JOIN parts p ON p.id = pf.part_id
  WHERE p.category_id IS NOT NULL
  GROUP BY pf.vehicle_id, p.category_id
) x GROUP BY vid;
DROP TEMPORARY TABLE IF EXISTS t_url3;
CREATE TEMPORARY TABLE t_url3 (u VARCHAR(190) PRIMARY KEY) ENGINE=MEMORY;
INSERT IGNORE INTO t_url3
SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(source_url,'/catalog/',-1),',',3)
FROM parts WHERE source_url LIKE '%,%,%';
"""

# THE WORK ORDER. One row per vehicle still worth requests, with the estimate that
# ranks it. Vehicles already >=DONE_FRAC covered are dropped here and land in the
# skip-set instead — that is the "skip already-healthy ones entirely" rule.
SQL_TARGETS = """
SELECT v.id, v.slug, mk.name AS make, mo.name AS model, v.year, e.name AS engine,
       COALESCE(c.cats,0) AS cats, COALESCE(c.rows_held,0) AS rows_held,
       COALESCE(sib.best,0) AS sibling_best,
       (v.market LIKE '%%US%%') AS us_mkt,
       u3.u AS observed_prefix
FROM vehicles v
JOIN makes  mk ON mk.id = v.make_id
JOIN models mo ON mo.id = v.model_id
LEFT JOIN engines e ON e.id = v.engine_id
LEFT JOIN t_vcov c ON c.vid = v.id
LEFT JOIN (SELECT v2.model_id, MAX(c2.cats) best
           FROM t_vcov c2 JOIN vehicles v2 ON v2.id = c2.vid
           GROUP BY v2.model_id) sib ON sib.model_id = v.model_id
LEFT JOIN t_url3 u3
       ON u3.u = CONCAT(REPLACE(LOWER(mk.name),' ','+'),',',v.year,',',REPLACE(LOWER(mo.name),' ','+'))
"""

# The measured rows-per-leaf of every category, and the loader's own category slug —
# which is exactly what scraper/crawl.py slugify()s at enqueue time, so the two sides
# join with no name munging.
SQL_CAT_YIELD = """
SELECT c.slug, ROUND(AVG(t.n),2) avg_rows, COUNT(*) leaves
FROM (SELECT pf.vehicle_id vid, p.category_id cid, COUNT(*) n
      FROM part_fitment pf JOIN parts p ON p.id = pf.part_id
      WHERE p.category_id IS NOT NULL
      GROUP BY pf.vehicle_id, p.category_id) t
JOIN categories c ON c.id = t.cid
GROUP BY c.id HAVING leaves >= 20
"""

# Covered leaves, as the crawler's own key: l:<vehicles.slug>|<categories.slug>.
SQL_COVERED_LEAVES = """
SELECT v.slug AS vslug, c.slug AS cslug, LOWER(mk.name) AS mk, v.year AS yr
FROM (SELECT DISTINCT pf.vehicle_id vid, p.category_id cid
      FROM part_fitment pf JOIN parts p ON p.id = pf.part_id
      WHERE p.category_id IS NOT NULL) t
JOIN vehicles v   ON v.id = t.vid
JOIN makes mk     ON mk.id = v.make_id
JOIN categories c ON c.id = t.cid
"""


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--units", default="units_all.tsv")
    ap.add_argument("--out", default="plan")
    ap.add_argument("--tier1-only", action="store_true",
                    help="US-market vehicles only (drops the completeness tail)")
    a = ap.parse_args()

    units = {}
    with open(a.units, encoding="utf-8") as fh:
        for line in fh:
            f = line.rstrip("\n").rstrip("\r").split("\t")
            if len(f) >= 3 and f[0].strip():
                for y in range(int(f[1]), int(f[2]) + 1):
                    units[(nm(f[0]), y)] = ukey_for(f[0].strip(), f[1], f[2])

    os.makedirs(os.path.join(a.out, "skip"), exist_ok=True)
    os.makedirs(os.path.join(a.out, "fr"), exist_ok=True)
    conn = db.connect()
    cur = conn.cursor()

    for stmt in SQL_COVERAGE.strip().split(";"):
        if stmt.strip():
            cur.execute(stmt)

    # ---- 1. category yield table (leaf ordering) ----
    cur.execute(SQL_CAT_YIELD)
    cats = cur.fetchall()
    with open(os.path.join(a.out, "cat_yield.tsv"), "w", encoding="utf-8") as fh:
        for r in sorted(cats, key=lambda r: -r["avg_rows"]):
            fh.write(f"{r['slug']}\t{r['avg_rows']}\n")

    # ---- 2. the prioritised target list ----
    cur.execute(SQL_TARGETS)
    skip: dict[str, list] = {}
    seeds: dict[str, dict] = {}
    targets = []
    for r in cur.fetchall():
        ukey = units.get((nm(r["make"]), r["year"]))
        if ukey is None:
            continue                       # outside the unit plan the fleet will run
        est_cats, rpl = band(r["year"])
        est_cats = max(est_cats, r["sibling_best"] or 0)   # a sibling proves the floor
        if r["cats"] >= est_cats * DONE_FRAC:
            skip.setdefault(ukey, []).append("v:" + r["slug"])   # done — prune subtree
            continue
        if a.tier1_only and not r["us_mkt"]:
            continue
        todo = est_cats - r["cats"]
        new_rows = todo * rpl
        reqs = todo + NAV_REQS
        targets.append({
            "ukey": ukey, "make": r["make"], "year": r["year"], "model": r["model"],
            "engine": r["engine"], "cats": r["cats"], "todo": todo,
            "new_rows": round(new_rows), "reqs": reqs,
            "rpr": round(new_rows / reqs, 2),
            "tier": 1 if (r["us_mkt"] and r["year"] >= 1990) else (2 if r["us_mkt"] else 3),
            "href": model_href(r["make"], r["year"], r["model"], r["observed_prefix"]),
        })

    # Rows-per-request first (that is the budget's exchange rate), business tier as the
    # gate, absolute new rows as the tie-break so whole vehicles finish.
    targets.sort(key=lambda t: (t["tier"], -t["rpr"], -t["new_rows"]))
    with open(os.path.join(a.out, "targets.tsv"), "w", encoding="utf-8") as fh:
        fh.write("tier\tmake\tyear\tmodel\tengine\thave_cats\ttodo_leaves\t"
                 "est_new_rows\treqs\trows_per_req\tcum_rows\tcum_reqs\tukey\n")
        cr = cq = 0
        for t in targets:
            cr += t["new_rows"]; cq += t["reqs"]
            fh.write(f"{t['tier']}\t{t['make']}\t{t['year']}\t{t['model']}\t{t['engine']}\t"
                     f"{t['cats']}\t{t['todo']}\t{t['new_rows']}\t{t['reqs']}\t{t['rpr']}\t"
                     f"{cr}\t{cq}\t{t['ukey']}\n")

    # ---- 3. covered leaves -> the rest of the skip-set (only for units we will crawl) ----
    # 2.9M rows: stream them (SSDictCursor) instead of buffering ~3 GB of dicts.
    import pymysql.cursors
    live = {t["ukey"] for t in targets}
    done_veh = {k for keys in skip.values() for k in keys}
    scur = conn.cursor(pymysql.cursors.SSDictCursor)
    scur.execute(SQL_COVERED_LEAVES)
    for r in scur:
        ukey = units.get((nm(r["mk"]), r["yr"]))
        if ukey in live and f"v:{r['vslug']}" not in done_veh:
            skip.setdefault(ukey, []).append(f"l:{r['vslug']}|{r['cslug']}")
    scur.close()

    for ukey, keys in skip.items():
        with open(os.path.join(a.out, "skip", f"{ukey}.txt"), "w", encoding="utf-8") as fh:
            fh.write("\n".join(keys) + "\n")

    # ---- 4. pre-seeded frontiers, one MODEL node per target model ----
    # Seeding at the model page instead of the make root skips make->year (the measured
    # dead walk: 34 requests, 0 listings) and, more importantly, fixes the ORDER: the
    # frontier is a DFS stack popped from the RIGHT, so the LAST line is crawled first.
    for t in reversed(targets):                       # reversed -> best target last
        s = seeds.setdefault(t["ukey"], {})
        s.setdefault(t["href"], {
            "node_type": "model", "href": t["href"],
            "payload": {"nodetype": "model",
                        "ctx": {"make": t["make"], "year": t["year"], "model": t["model"]}},
        })
    for ukey, nodes in seeds.items():
        with open(os.path.join(a.out, "fr", f"f_{ukey}.ndjson"), "w", encoding="utf-8") as fh:
            for node in nodes.values():
                fh.write(json.dumps(node, ensure_ascii=False) + "\n")

    conn.close()
    tot_reqs = sum(t["reqs"] for t in targets)
    tot_rows = sum(t["new_rows"] for t in targets)
    skipped = sum(len(v) for v in skip.values())
    print(f"[plan] {len(targets)} target vehicles in {len(seeds)} units | "
          f"{tot_reqs:,} requests -> ~{tot_rows:,} new rows ({tot_rows/max(tot_reqs,1):.2f}/req)")
    print(f"[plan] skip-set {skipped:,} keys "
          f"({sum(1 for v in skip.values() for k in v if k[0] == 'v'):,} whole vehicles) | "
          f"{len(cats):,} categories in cat_yield.tsv")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
