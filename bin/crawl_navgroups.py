#!/usr/bin/env python
"""crawl_navgroups.py — fetch RockAuto's REAL per-vehicle GROUP list (nav only, no
leaf parts) to repair the normalized-crawl miscategorization.

Why: the crawl is part-centric — each part is scraped ONCE (one global category from
its source leaf) then `getbuyersguide` fans it onto every vehicle it fits. The fan-out
carries no group, so a manual-truck transmission mount keeps 'Transmission-Manual' even
on an automatic Tahoe, where RockAuto would never list it. We fetch each vehicle's
ACTUAL group set and later suppress storefront (vehicle, group) pairs RockAuto lacks.

  python bin/crawl_navgroups.py --selftest                    # offline red-green
  python bin/crawl_navgroups.py --in hrefs.txt --out nav.jsonl # box (gateway reused)

Input: one carcode catalog href per line = the first 5 comma-fields of a part
source_url, e.g. https://www.rockauto.com/en/catalog/chevrolet,2004,tahoe,5.3l+v8,1234567
Output JSONL: {"href": "...", "groups": ["Brake & Wheel Hub", ...],
               "slugs": ["brake-wheel-hub", ...]}   (groups=null => fetch blocked)
"""
import argparse
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
sys.path.insert(0, os.path.join(ROOT, "bin"))
SITE = "https://www.rockauto.com"


def slugify(s: str) -> str:
    # identical to bin/loader.py / recat_from_url.py so slugs line up with categories.slug
    return re.sub(r"[^a-z0-9]+", "-", (s or "").lower()).strip("-")


def groups_from_html(html: str) -> dict:
    """{slug: name} for every TOP-LEVEL groupname node in a carcode nav fragment.
    Only nodetype=='groupname' counts — parttype/leaf nodes carry a groupname too but
    are NOT the vehicle's group list."""
    import parsers  # noqa: PLC0415  (scraper import; only available with the repo on sys.path)
    out = {}
    for n in parsers.parse_nav(html):
        if n.get("nodetype") != "groupname":
            continue
        name = (n.get("jsn") or {}).get("groupname") or n.get("label")
        if name and name.strip():
            out[slugify(name)] = name.strip()
    return out


def _selftest() -> bool:
    """Offline proof the parser pulls the vehicle's groups and DROPS non-group nodes —
    the auto Tahoe fixture has Transmission-Automatic but NOT Transmission-Manual."""
    html = (
        '<input id="jsn[10]" value=\'{"groupindex":"10","nodetype":"groupname",'
        '"groupname":"Brake & Wheel Hub","make":"Chevrolet","year":"2004","model":"Tahoe"}\'>'
        '<input id="jsn[11]" value=\'{"groupindex":"11","nodetype":"groupname",'
        '"groupname":"Transmission-Automatic"}\'>'
        '<input id="jsn[12]" value=\'{"groupindex":"12","nodetype":"groupname",'
        '"groupname":"Suspension"}\'>'
        '<input id="jsn[13]" value=\'{"groupindex":"13","nodetype":"parttype",'
        '"groupname":"Brake & Wheel Hub","label":"Disc Brake Pad"}\'>'  # must be ignored
    )
    g = groups_from_html(html)
    ok = True

    def chk(label, cond):
        nonlocal ok
        ok = ok and cond
        print(f"  [{'PASS' if cond else 'FAIL'}] {label}")

    chk("brake-wheel-hub present", "brake-wheel-hub" in g)
    chk("transmission-automatic present", "transmission-automatic" in g)
    chk("suspension present", "suspension" in g)
    chk("transmission-manual ABSENT (the bug's tell)", "transmission-manual" not in g)
    chk("only groupname nodes counted (parttype dropped)", len(g) == 3)
    chk("slugs match categories.slug convention", g.get("brake-wheel-hub") == "Brake & Wheel Hub")
    print("PASS" if ok else "FAIL")
    return ok


def _fetch_loop(args) -> int:
    from requests_ip_rotator import ApiGateway, EXTRA_REGIONS
    import crawl_apigw  # reuse _mount_gateway (patches RAClient to egress via the gateway)
    from ra_client import RAClient
    from proxy_manager import ProxyManager

    os.environ.setdefault("SP_USE_PROXIES", "0")
    gw = ApiGateway(SITE, regions=EXTRA_REGIONS[: args.regions])
    print(f"[navgroups] starting/reusing {args.regions} gateway region(s)...", flush=True)
    gw.start(force=False)  # reuse the box's already-running shared gateway if present
    crawl_apigw._mount_gateway(gw)
    client = RAClient(ProxyManager())
    client.new_session()

    done = set()
    if os.path.exists(args.out):
        with open(args.out, encoding="utf-8") as fh:
            for line in fh:
                try:
                    done.add(json.loads(line)["href"])
                except Exception:
                    pass

    todo = []
    with open(args.in_, encoding="utf-8") as fh:
        for i, line in enumerate(fh):
            h = line.strip()
            if not h or h in done:
                continue
            if args.shard_total > 1 and i % args.shard_total != args.shard_index:
                continue
            todo.append(h)
    print(f"[navgroups] {len(todo)} to fetch, {len(done)} already done", flush=True)

    n = ok = blocked = 0
    with open(args.out, "a", encoding="utf-8") as out:
        for h in todo:
            url = h if h.startswith("http") else SITE + h
            groups = None
            for _ in range(4):  # gateway rotates IP per request, so a retry is a fresh IP
                try:
                    html = client.get(url)
                except Exception:
                    client.rotate()
                    continue
                if html and "/captcha" not in html[:3000].lower():
                    g = groups_from_html(html)
                    if g:  # non-empty only — never suppress on an empty/garbage page
                        groups = g
                        break
                client.rotate()
            rec = {"href": h}
            if groups is None:
                rec["groups"] = None
                rec["error"] = "blocked_or_empty"
                blocked += 1
            else:
                rec["groups"] = list(groups.values())
                rec["slugs"] = list(groups.keys())
                ok += 1
            out.write(json.dumps(rec, ensure_ascii=False) + "\n")
            out.flush()
            n += 1
            if n % 200 == 0:
                print(f"[navgroups] {n}/{len(todo)} ok={ok} blocked={blocked}", flush=True)
    print(f"[navgroups] DONE n={n} ok={ok} blocked={blocked}", flush=True)
    return 0


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--selftest", action="store_true", help="offline parse check, no network")
    ap.add_argument("--in", dest="in_", help="input file: one carcode catalog href per line")
    ap.add_argument("--out", default="navgroups.jsonl", help="output JSONL (append, resumable)")
    ap.add_argument("--regions", type=int, default=5, help="gateway regions to start/reuse")
    ap.add_argument("--shard-index", type=int, default=0)
    ap.add_argument("--shard-total", type=int, default=1)
    a = ap.parse_args()
    if a.selftest:
        return 0 if _selftest() else 1
    if not a.in_:
        ap.error("--in is required (or use --selftest)")
    return _fetch_loop(a)


if __name__ == "__main__":
    raise SystemExit(main())
