#!/usr/bin/env python
"""probe_fanout.py — measure part fitment fan-out (the normalized-crawl decider).

If each unique part fits many vehicles, a part-centric crawl (each part once + its
getbuyersguide fitment) mirrors the whole catalog in a fraction of the 146GB the
per-vehicle view costs. Fan-out = vehicles per part = the denormalization factor.

Drills a vehicle to a few parttypes, reads each part's data blob from the listing
fragment, calls getbuyersguide, and counts how many vehicles each part fits.
Run on a US runner. Reports avg fan-out + buyers-guide byte size.
"""
from __future__ import annotations
import gzip, html, json, os, re, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import config, parsers  # noqa: E402
from ra_client import RAClient  # noqa: E402
from proxy_manager import ProxyManager  # noqa: E402

VEH = "/en/catalog/acura,2010,zdx,3.7l+v6,1445611"
ESS = re.compile(r'id="listing_data_essential\[(\d+)\]"\s+value="([^"]*)"')
SUP = re.compile(r'id="listing_data_supplemental\[(\d+)\]"\s+value="([^"]*)"')


def gz(s):
    return len(gzip.compress((s or "").encode("utf-8", "replace")))


def blob(m):
    try:
        return json.loads(html.unescape(m))
    except Exception:
        return None


def count_vehicles(bg_html):
    # buyers guide lists vehicles; count carcode-ish rows / vehicle links
    n = len(re.findall(r'carcode', bg_html))
    if not n:
        n = len(re.findall(r'buyersguide.*?row|bg_vehicle|catalog/[a-z]', bg_html))
    return n


def main():
    os.environ.setdefault("SP_USE_PROXIES", "0")
    c = RAClient(ProxyManager())
    vhtml = c.get(VEH)
    mm = re.search(r'id="max_group_index"[^>]*value="(\d+)"', vhtml)
    mgi = int(mm.group(1)) if mm else 0
    groups = [n for n in parsers.parse_nav(vhtml) if n.get("nodetype") == "groupname"]

    parttypes = []
    for g in groups[:6]:
        try:
            pts = [n for n in parsers.parse_nav(c.get(g["href"])) if n.get("nodetype") == "parttype"]
        except Exception:
            continue
        parttypes.extend(pts)
        if len(parttypes) >= 6:
            break

    print(f"max_group_index={mgi}, testing {min(6,len(parttypes))} parttypes\n")
    fanouts = []
    for pt in parttypes[:6]:
        frag = c.fetch_listings(pt.get("jsn") or {}, mgi)
        ess = dict(ESS.findall(frag))
        sup = dict(SUP.findall(frag))
        parts = parsers.parse_listings(frag, config.__dict__.get("listing_ctx", lambda x: {})({}))
        gips = list(ess.keys())[:3]           # up to 3 parts from this parttype
        for gip in gips:
            e, s = blob(ess.get(gip, "")), blob(sup.get(gip, "")) or {}
            if e is None:
                continue
            pd = {"groupindex": gip, "listing_data_essential": e, "listing_data_supplemental": s}
            try:
                bg = c.fetch_buyers_guide(pd)
            except Exception as exc:  # noqa: BLE001
                print(f"  parttype {pt.get('jsn',{}).get('parttype')} gip {gip}: buyers-guide err {exc}")
                continue
            nv = count_vehicles(bg)
            fanouts.append(nv)
            print(f"  parttype {pt.get('jsn',{}).get('parttype'):>7} part gip {gip:>4}: "
                  f"fits ~{nv:4} vehicles   bg_gz={gz(bg):5}B  ess_parts={len(ess)}")

    if fanouts:
        avg = sum(fanouts) / len(fanouts)
        print(f"\n  AVG FAN-OUT = {avg:.1f} vehicles/part  (n={len(fanouts)})")
        print(f"  => normalized crawl ~ {12_698_470/max(1,avg):.0f} unique-part fetches vs 12.7M")
        print(f"  => est bytes: {12_698_470/max(1,avg)*avg*0 + (12_698_470/max(1,avg))*6000/1e9:.1f} GB at 6KB/part")
    else:
        print("\n  no fan-out captured (partData/getbuyersguide shape needs adjusting)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
