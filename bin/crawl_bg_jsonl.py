#!/usr/bin/env python
"""crawl_bg_jsonl.py — PART-CENTRIC fitment crawl: one request per PART instead of
one request per (vehicle, parttype) leaf.

Why: the part universe is saturated (1,481,495 parts; new parts arrive at ~700/h
against ~106,000 new fitments/h = 0.66%). Essentially every one of the 27.5M
remaining fitment rows attaches to a part we ALREADY have. `func=getbuyersguide`
returns EVERY vehicle one part fits, so:

    leaf route : 6,183,851 requests @ 4.445 new rows/req
    this route : 1,476,594 requests @ ~18.6 new rows/req      (-76.1% requests)

partData is synthesised straight from parts.moreinfo_key ("pk,cc,pt") — the same
key the leaf crawl already banked — so there is NO discovery fetch. `groupindex`
and `opts` are page-local presentation data (see the listing_data_essential blob in
artifacts/leaf_dump.html); only partkey/carcode/parttype identify the part.

    # 1. dump the work (laptop, one query)
    mysql -u root -P 3307 -h 127.0.0.1 supreme_parts -N -B -e \
      "select sku, moreinfo_key from parts where moreinfo_key<>'' and moreinfo_key is not null" \
      > bg_keys.tsv
    # 2. crawl (GH runner / any egress IP), sharded like every other fleet job
    python bin/crawl_bg_jsonl.py --keys bg_keys.tsv --out out/bg_0.ndjson \
        --shard-index 0 --shard-total 40 --budget 1200
    # 3. ingest (laptop) — stg_fitment -> loader.load_fitment, both already exist
    python bin/ingest_bg.py out/bg_*.ndjson

out NDJSON: {"sku","make_name","model_name","year","engine_name","carcode"}
Resumable: skus already in --out are skipped on restart.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))


def part_data(mi_key: str) -> dict | None:
    """parts.moreinfo_key ("pk,cc,pt") -> the partData blob getbuyersguide wants."""
    bits = (mi_key or "").split(",")
    if len(bits) != 3 or not all(bits):
        return None
    pk, cc, pt = bits
    # ponytail: groupindex/opts are page-local; empty opts is the minimal shape.
    # If a probe shows the server needs them, put the leaf's real blob here instead.
    return {"groupindex": "1", "carcode": cc, "parttype": pt, "partkey": pk, "opts": {}}


def _load_keys(path: str, shard_index: int, shard_total: int) -> list[tuple[str, str]]:
    rows = []
    with open(path, encoding="utf-8") as fh:
        for i, ln in enumerate(fh):
            if shard_total > 1 and i % shard_total != shard_index:
                continue
            bits = ln.rstrip("\n").split("\t")
            if len(bits) >= 2 and bits[0] and bits[1].count(",") == 2:
                rows.append((bits[0], bits[1]))
    return rows


def _done_skus(path: str) -> set[str]:
    done: set[str] = set()
    if os.path.exists(path):
        with open(path, encoding="utf-8") as fh:
            for ln in fh:
                try:
                    done.add(json.loads(ln)["sku"])
                except Exception:  # noqa: BLE001 - a torn last line is not fatal
                    pass
    return done


def run(a) -> int:
    import parsers
    from ra_client import RAClient, Blocked
    from proxy_manager import ProxyManager

    todo = _load_keys(a.keys, a.shard_index, a.shard_total)
    done = _done_skus(a.out)
    todo = [r for r in todo if r[0] not in done]
    if a.limit:
        todo = todo[:a.limit]
    print(f"[bg] {len(todo)} parts to fetch, {len(done)} already done", flush=True)

    client = RAClient(ProxyManager())
    started = time.monotonic()
    n = rows = blocked = empty = 0
    os.makedirs(os.path.dirname(a.out) or ".", exist_ok=True)
    with open(a.out, "a", encoding="utf-8") as out:
        for sku, mi in todo:
            if n >= a.budget or time.monotonic() - started > a.max_seconds:
                print("[bg] budget/time reached", flush=True)
                break
            if blocked >= a.max_blocked:
                print("[bg] IP burned — abort for a fresh runner", flush=True)
                break
            pd = part_data(mi)
            if pd is None:
                continue
            n += 1
            try:
                vehicles = parsers.parse_buyers_guide(client.fetch_buyers_guide(pd))
            except Blocked:
                blocked += 1
                continue
            except Exception as exc:  # noqa: BLE001 - one bad part never kills the run
                print(f"[warn] {sku}: {type(exc).__name__}: {exc}", flush=True)
                continue
            blocked = 0
            if not vehicles:
                empty += 1
            for v in vehicles:
                out.write(json.dumps(dict(v, sku=sku), ensure_ascii=False) + "\n")
                rows += 1
            if n % 50 == 0:
                out.flush()
                print(f"[bg] parts={n} rows={rows} empty={empty} "
                      f"rows/req={rows / max(1, n):.1f}", flush=True)
    print(f"[final] parts={n} rows={rows} empty={empty} "
          f"rows/req={rows / max(1, n):.2f} out={a.out}", flush=True)
    return 0


def _selftest() -> bool:
    """Offline: partData shape + both buyers-guide markup shapes + dedup."""
    sys.path.insert(0, os.path.join(ROOT, "scraper"))
    import parsers
    ok = True

    def chk(label, cond):
        nonlocal ok
        ok = ok and cond
        print(f"  [{'PASS' if cond else 'FAIL'}] {label}")

    pd = part_data("18165373,1410286,8852")
    chk("partData built from moreinfo_key",
        pd == {"groupindex": "1", "carcode": "1410286", "parttype": "8852",
               "partkey": "18165373", "opts": {}})
    chk("malformed moreinfo_key rejected",
        part_data("") is None and part_data("1,2") is None and part_data("1,,3") is None)

    JSN_BG = (
        '<input id="jsn[3309958]" value=\'{"nodetype":"carcode","make":"Honda",'
        '"year":"2015","model":"Accord","carcode":3309958,"engine":"2.4L L4"}\'>'
        '<input id="jsn[3309959]" value=\'{"nodetype":"carcode","make":"Honda",'
        '"year":"2016","model":"Accord","carcode":3309959,"engine":"3.5L V6"}\'>'
    )
    v = parsers.parse_buyers_guide(JSN_BG)
    chk("jsn shape -> 2 vehicles", len(v) == 2)
    chk("jsn shape keeps leaf-identical names",
        v[0]["make_name"] == "Honda" and v[0]["model_name"] == "Accord"
        and v[0]["year"] == 2015 and v[0]["engine_name"] == "2.4L L4")

    HREF_BG = (
        '<a href="/en/catalog/land+rover,2015,range+rover,3.0l+v6,3309960">x</a>'
        '<a href="https://www.rockauto.com/en/catalog/honda,2015,accord,2.4l+l4,3309958">y</a>'
    )
    v2 = parsers.parse_buyers_guide(HREF_BG)
    chk("href shape -> 2 vehicles", len(v2) == 2)
    byc = {x["carcode"]: x for x in v2}
    chk("href shape un-slugs make/model/engine",
        byc["3309960"]["make_name"] == "land rover"
        and byc["3309960"]["model_name"] == "range rover"
        and byc["3309960"]["engine_name"] == "3.0l v6"
        and byc["3309960"]["year"] == 2015)
    # both shapes together: the jsn node WINS (proper names), href copy is dropped
    v3 = parsers.parse_buyers_guide(JSN_BG + HREF_BG)
    chk("mixed shapes dedup on carcode", len(v3) == 3)
    chk("jsn wins over href for the same carcode",
        {x["carcode"]: x["make_name"] for x in v3}["3309958"] == "Honda")
    chk("garbage in -> no rows", parsers.parse_buyers_guide("<html>no</html>") == [])
    print("PASS" if ok else "FAIL")
    return ok


def main() -> int:
    ap = argparse.ArgumentParser(description="RockAuto part-centric fitment crawl.")
    ap.add_argument("--keys", help="TSV: sku <TAB> pk,cc,pt (from parts.moreinfo_key)")
    ap.add_argument("--out", default="bg.ndjson")
    ap.add_argument("--shard-index", type=int, default=int(os.getenv("SHARD_INDEX", "0")))
    ap.add_argument("--shard-total", type=int, default=int(os.getenv("SHARD_TOTAL", "1")))
    ap.add_argument("--budget", type=int, default=int(os.getenv("SP_JOB_BUDGET", "1200")),
                    help="max requests this job makes (stay under the per-IP wall)")
    ap.add_argument("--max-seconds", type=int, default=int(os.getenv("SP_JOB_MAX_SECONDS", "300")))
    ap.add_argument("--max-blocked", type=int, default=int(os.getenv("SP_MAX_BLOCKED", "3")))
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--selftest", action="store_true")
    a = ap.parse_args()
    if a.selftest:
        return 0 if _selftest() else 1
    if not a.keys:
        ap.error("--keys is required (or use --selftest)")
    return run(a)


if __name__ == "__main__":
    raise SystemExit(main() if len(sys.argv) > 1 else (0 if _selftest() else 1))
