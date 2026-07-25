#!/usr/bin/env python
"""probe_images.py — does a part's DETAIL (moreinfo) gallery have more images than
the listing's inline image we currently capture? 99.9% of our parts have exactly 1
image; the user reports RockAuto shows a slider. Check the real image count."""
from __future__ import annotations
import os, re, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import config, parsers  # noqa: E402
from ra_client import RAClient  # noqa: E402
from proxy_manager import EvomiProxyManager  # noqa: E402

os.environ["EVOMI_COUNTRY"] = "US"


def base(u):
    return re.sub(r"_ra_[a-z]\.jpg$", "", u)


def main():
    c = RAClient(EvomiProxyManager())
    v = c.get("/en/catalog/acura,2003,cl,3.2l+v6")
    cc = [n for n in parsers.parse_nav(v) if n.get("nodetype") == "carcode"]
    url = "/en/catalog/acura,2003,cl,3.2l+v6," + cc[0]["carcode"] + ",wiper+&+washer,wiper+blade,8852"
    h = c.get(url)

    # what parse_listings currently extracts for TRICO 16190
    for lst in parsers.parse_listings(h, {}):
        if lst.get("part_number") == "16190":
            print("parser image_urls for 16190:", lst.get("image_urls"))
            print("parser doc_urls for 16190  :", lst.get("doc_urls"))
            break

    # every moreinfo link in the page
    links = sorted(set(re.findall(r"/en/moreinfo\.php\?[^\"'\s>]+", h)))
    print("moreinfo links in page:", len(links))
    if not links:
        # maybe partinfo / different path
        alt = sorted(set(re.findall(r"moreinfo[^\"'\s>]*", h)))[:3]
        print("alt moreinfo tokens:", alt)
        return 0

    mih = c.get(links[0])
    imgs = re.findall(r"/(?:info|media)/[0-9a-zA-Z/_.-]+_ra_[a-z]\.jpg", mih)
    b = sorted(set(base(u) for u in imgs))
    print(f"\nmoreinfo {links[0][:60]} -> {len(b)} DISTINCT images:")
    for x in b[:10]:
        print("   ", x)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
