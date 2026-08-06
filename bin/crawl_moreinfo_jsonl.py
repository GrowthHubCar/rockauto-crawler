#!/usr/bin/env python
"""crawl_moreinfo_jsonl.py — the specs/description backfill, but FILE-BASED.

bin/crawl_moreinfo.py reads pending parts straight from MySQL and writes the
enriched rows back. That only works on a machine with the database. The EC2 crawl
boxes have no DB, so this variant takes the same work as a plain key file and
emits NDJSON for a later ingest on the laptop:

    in   sku<TAB>pk,cc,pt      (one per line; see --keys)
    out  {"sku":..., "description":..., "features":[...], "specs":[{name,value}],
          "alt_numbers":[...]}                                        (one per line)

Runs through the same API-Gateway IP rotation as the listing crawl, and honours
the same shard mechanism so N boxes split the work with no overlap:

    python bin/crawl_moreinfo_jsonl.py --keys keys.tsv --out out/mi_0.ndjson \
        --shard-index 0 --shard-total 8 --regions 8

Resumable: already-done skus are read back from --out on start and skipped, so a
restart never re-fetches a page it already banked.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
sys.path.insert(0, os.path.join(ROOT, "bin"))

SITE = "https://www.rockauto.com"


def _mount_gateway(gw):
    """Route every RAClient session through the gateway (same trick crawl_apigw uses)."""
    import ra_client
    orig = ra_client.RAClient._build_session

    def build(self):
        s = orig(self)
        s.mount(SITE, gw)
        return s

    ra_client.RAClient._build_session = build


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--keys", required=True, help="TSV: sku <TAB> pk,cc,pt")
    ap.add_argument("--out", required=True)
    ap.add_argument("--shard-index", type=int, default=0)
    ap.add_argument("--shard-total", type=int, default=1)
    ap.add_argument("--regions", type=int, default=8)
    ap.add_argument("--direct", action="store_true",
                    help="fetch from this host's own IP; no AWS API-Gateway rotation "
                         "(the mode the GitHub Actions fleet uses)")
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--max-seconds", type=int, default=86400)
    a = ap.parse_args()

    # DIRECT mode: skip the AWS API-Gateway rotation entirely and fetch from this
    # machine's own IP. Written for the GitHub Actions fleet, where every runner is
    # already a distinct egress IP — the gateway would add a hop and buy nothing, and
    # the AWS accounts it depended on are suspended, so importing requests_ip_rotator
    # here is a hard failure rather than a fallback.
    if a.direct or os.environ.get("SP_MI_DIRECT") == "1":
        print("[mi] DIRECT mode: using this host's IP, no API gateway", flush=True)
    else:
        from requests_ip_rotator import ApiGateway, EXTRA_REGIONS
        gw = ApiGateway(SITE, regions=EXTRA_REGIONS[:a.regions])
        preset = os.environ.get("SP_GW_ENDPOINTS", "").strip()
        if preset:
            gw.endpoints = [e.strip() for e in preset.split(",") if e.strip()]
            print(f"[mi] {len(gw.endpoints)} preset endpoints", flush=True)
        else:
            gw.start(force=False)
        _mount_gateway(gw)

    from ra_client import RAClient, Blocked
    from proxy_manager import ProxyManager
    from parsers import parse_moreinfo

    # this shard's slice of the key file
    rows = []
    with open(a.keys, encoding="utf-8") as fh:
        for i, ln in enumerate(fh):
            if a.shard_total > 1 and i % a.shard_total != a.shard_index:
                continue
            parts = ln.rstrip("\n").split("\t")
            if len(parts) >= 2 and parts[1].count(",") == 2:
                rows.append((parts[0], parts[1]))
    if a.limit:
        rows = rows[:a.limit]

    # resume: skip skus already banked in --out
    done: set[str] = set()
    if os.path.exists(a.out):
        with open(a.out, encoding="utf-8") as fh:
            for ln in fh:
                try:
                    done.add(json.loads(ln)["sku"])
                except Exception:  # noqa: BLE001 - a torn last line is not fatal
                    pass
    todo = [r for r in rows if r[0] not in done]
    print(f"[mi] shard {a.shard_index}/{a.shard_total}: {len(todo)} to fetch "
          f"({len(done)} already done)", flush=True)

    client = RAClient(ProxyManager())
    t0 = time.time()
    ok = blocked = 0
    os.makedirs(os.path.dirname(a.out) or ".", exist_ok=True)
    with open(a.out, "a", encoding="utf-8") as out:
        for n, (sku, key) in enumerate(todo, 1):
            if time.time() - t0 > a.max_seconds:
                print("[mi] time budget reached", flush=True)
                break
            pk, cc, pt = key.split(",")
            try:
                html = client.get(f"/en/moreinfo.php?pk={pk}&cc={cc}&pt={pt}")
            except Blocked as e:
                blocked += 1
                print(f"[mi] blocked {sku}: {e}", flush=True)
                continue
            except Exception as e:  # noqa: BLE001 - one bad page never aborts the pass
                print(f"[mi] fail {sku}: {type(e).__name__}: {e}", flush=True)
                continue
            mi = parse_moreinfo(html)
            mi["sku"] = sku
            out.write(json.dumps(mi, ensure_ascii=False) + "\n")
            ok += 1
            if n % 200 == 0:
                out.flush()
                rate = ok / max(1e-9, time.time() - t0)
                print(f"[mi] {n}/{len(todo)} ok={ok} blocked={blocked} "
                      f"{rate:.1f}/s", flush=True)
    print(f"[mi] DONE ok={ok} blocked={blocked} out={a.out}", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
