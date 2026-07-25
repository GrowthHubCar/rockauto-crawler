#!/usr/bin/env python
"""dump_moreinfo.py — fetch ONE moreinfo page and save raw HTML for inspection.
Try direct (user's residential IP) first; fall back to Evomi only if blocked.

    python scraper/dump_moreinfo.py "<moreinfo path or full url>"
"""
from __future__ import annotations
import os, re, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..",
                   "artifacts", "moreinfo_dump.html")


def looks_blocked(h: str) -> bool:
    low = h.lower()
    return len(h) < 3000 or "captcha" in low or "are you a human" in low


def main() -> int:
    path = sys.argv[1] if len(sys.argv) > 1 else \
        "/en/moreinfo.php?pk=1014978&cc=1001046&pt=8852&jsn=614&optionchoice=0-0-1-1"
    url = path if path.startswith("http") else "https://www.rockauto.com" + path

    html = ""
    # 1) direct
    try:
        import requests
        r = requests.get(url, headers={
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                          "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36",
            "Accept": "text/html,application/xhtml+xml",
        }, timeout=20)
        html = r.text
        print(f"direct: {r.status_code}, {len(html)} chars, blocked={looks_blocked(html)}")
    except Exception as e:
        print("direct failed:", e)

    # 2) Evomi fallback
    if looks_blocked(html):
        os.environ["EVOMI_COUNTRY"] = "US"
        from ra_client import RAClient
        from proxy_manager import EvomiProxyManager
        c = RAClient(EvomiProxyManager())
        html = c.get(path if not path.startswith("http") else path)
        print(f"evomi: {len(html)} chars, blocked={looks_blocked(html)}")

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, "w", encoding="utf-8") as f:
        f.write(html)
    print("saved ->", os.path.abspath(OUT))

    # quick census of image-ish URLs (ALL patterns, not just _ra_)
    info = sorted(set(re.findall(r"/(?:info|media)/[0-9A-Za-z/_.\-]+\.(?:jpg|jpeg|png|gif)", html)))
    print(f"\n/info|/media URLs found: {len(info)}")
    for u in info[:40]:
        print("   ", u)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
