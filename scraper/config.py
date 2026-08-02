"""
Crawl scope + politeness + proxy configuration for the RockAuto pipeline.
Edit SCOPE to widen/narrow what gets crawled. Everything here is read by
crawl.py and the client/proxy modules — no magic numbers elsewhere.
"""
from __future__ import annotations
import os

BASE = "https://www.rockauto.com"
CATALOG_ROOT = f"{BASE}/en/catalog/"

# Where requests are actually SENT. Defaults to BASE; set SP_FETCH_BASE to a CDN/proxy front
# (e.g. https://ra-crawl-zone.b-cdn.net) to egress through it.
#
# DELIBERATELY SEPARATE FROM BASE. `BASE` is the CANONICAL IDENTITY of a page: it builds
# `source_url` (scraper/crawl_jsonl.py:145), which is stored on every part row and is the join
# key back to RockAuto. If the fetch front leaked into BASE, every row crawled through the CDN
# would be written with a b-cdn.net source_url — silently corrupting the parts table and
# splitting each part into two identities across crawls. Keeping them separate means the CDN is
# purely a transport detail and the data is byte-identical to a direct crawl.
FETCH_BASE = (os.getenv("SP_FETCH_BASE", "") or "").rstrip("/") or BASE
CATALOG_API = f"{BASE}/catalog/catalogapi.php"
CAPTCHA_PATH = "/captcha/"  # redirect target when anti-bot fires

# ---- CRAWL SCOPE ---------------------------------------------------------
# The council's answer to "fast, within ~10h": crawl a bounded-but-broad slice
# first, then widen by editing this. Empty list = ALL (full catalog = weeks).
SCOPE = {
    # Lowercase make slugs as they appear in /en/catalog/<slug>. [] = every make.
    "makes": ["honda", "toyota", "subaru"],
    # Inclusive year range. RockAuto groups everything <2006 behind one filter.
    "year_min": int(os.getenv("SP_YEAR_MIN", "2010")),
    "year_max": int(os.getenv("SP_YEAR_MAX", "2024")),
    # Top-level category names to keep (lowercase substring match). [] = all.
    "categories": [],
    # Markets to include (RockAuto market checkboxes). US only = fewer nodes.
    # SP_MARKETS="" (empty) = worldwide (every market). CSV to restrict, e.g. "US,MX".
    "markets": [m.strip().upper() for m in os.getenv("SP_MARKETS", "US").split(",") if m.strip()],
}

# ---- POLITENESS / RATE LIMITING -----------------------------------------
RATE = {
    "min_delay_s": float(os.getenv("SP_MIN_DELAY", "1.5")),   # per-IP base delay
    "max_delay_s": float(os.getenv("SP_MAX_DELAY", "4.0")),   # jittered upper bound
    "concurrency": int(os.getenv("SP_CONCURRENCY", "6")),     # parallel workers (each on its own proxy)
    # requests' timeout is (connect, read) — read is "gap between bytes", NOT total —
    # so a low value only kills STALLED sockets, never a slow-but-streaming 2MB leaf.
    # Behind the API Gateway a stalled socket is pure dead lane-time (measured: ~1/3 of
    # attempts hang to this value while the gateway's own p50 is 0.25s).
    "request_timeout_s": float(os.getenv("SP_REQUEST_TIMEOUT", "15")),
    # Connect and read are budgeted SEPARATELY. RockAuto blackholes a burned source IP
    # at L3 — it DROPS the SYN rather than refusing it — so a request to a spent gateway
    # IP does not fail, it hangs for the whole timeout. With one 15 s scalar covering
    # both phases, a dead IP cost 15 s and lanes sat at 98% idle CPU logging one progress
    # line per ~5 min (measured 2026-07-27). A short connect budget fails a blackholed IP
    # fast so the next attempt draws a different IP from the gateway pool, while the read
    # budget stays generous because a live RockAuto leaf page is genuinely slow to render.
    "connect_timeout_s": float(os.getenv("SP_CONNECT_TIMEOUT", "4")),
    "max_attempts": 4,          # per-node retry budget before marking 'failed'
    # Cool-down after a CAPTCHA. This is a PER-IP idea: on a proxy fleet the exit IP
    # is sticky, so hitting the wall means THAT IP is hot and must rest — 90 s is right.
    # Behind API Gateway it is actively harmful: every request already draws a fresh
    # egress IP (measured — 60 requests over one connection gave 60 distinct IPs), so
    # the cooldown rests an IP we will never touch again. Measured 2026-07-27: a lane
    # managed 7 requests in 120 s (17 s/request) because 2 captchas cost 180 s of sleep,
    # while the same fetch through the same gateway takes ~1 s. Set SP_CAPTCHA_BACKOFF=1
    # in gateway mode; keep 90 for proxy mode.
    "captcha_backoff_s": float(os.getenv("SP_CAPTCHA_BACKOFF", "90")),
}

# ---- CAPTCHA SOLVING -----------------------------------------------------
# RockAuto's wall is securimage (a weak PHP text-image CAPTCHA). captcha_solver
# cracks it locally with Tesseract OCR. When "solve" is on, ra_client tries to
# solve+submit the code (requesting fresh codes up to "attempts" times) BEFORE
# falling back to rotating the IP. Set SP_SOLVE_CAPTCHA=0 to disable (pure
# rotate-on-block behavior). Needs the tesseract binary (see requirements.txt);
# degrades gracefully to rotate() if it's absent.
CAPTCHA = {
    "solve": os.getenv("SP_SOLVE_CAPTCHA", "1") == "1",
    "attempts": int(os.getenv("SP_CAPTCHA_ATTEMPTS", "5")),
}

# ---- PROXY ROTATION ------------------------------------------------------
# Free proxy list sources (GitHub raw). proxy_manager fetches, health-checks,
# and quarantines. Treat every proxy as disposable. Set SP_USE_PROXIES=0 to
# crawl direct (single IP) for local testing.
PROXY = {
    "enabled": os.getenv("SP_USE_PROXIES", "1") == "1",
    "sources": [
        "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt",
        "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt",
        "https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt",
        "https://raw.githubusercontent.com/ShiftyTR/Proxy-List/master/http.txt",
        "https://raw.githubusercontent.com/mmpx12/proxy-list/master/http.txt",
        "https://raw.githubusercontent.com/roosterkid/openproxylist/main/HTTPS_RAW.txt",
        "https://raw.githubusercontent.com/hookzof/socks5_list/master/proxy.txt",
        "https://raw.githubusercontent.com/jetkai/proxy-list/main/online-proxies/txt/proxies-http.txt",
        "https://raw.githubusercontent.com/proxifly/free-proxy-list/main/proxies/protocols/http/data.txt",
        "https://raw.githubusercontent.com/zloi-user/hideip.me/main/http.txt",
        "https://raw.githubusercontent.com/MuRongPIG/Proxy-Master/main/http.txt",
        "https://raw.githubusercontent.com/Zaeem20/FREE_PROXIES_LIST/master/http.txt",
        "https://api.proxyscrape.com/v2/?request=displayproxies&protocol=http&timeout=10000&country=all",
        "https://proxyspace.pro/http.txt",
        "https://raw.githubusercontent.com/prxchk/proxy-list/main/http.txt",
        "https://raw.githubusercontent.com/sunny9577/proxy-scraper/master/generated/http_proxies.txt",
    ],
    # MUST be a LEAF, never a nav page. RockAuto keeps serving nav pages (/en/catalog/,
    # /en/catalog/acura) with HTTP 200 to an exit IP that is already walled — only leaf
    # pages 302 to /captcha/. Health-checking on a nav page therefore marks every burned
    # proxy "healthy", and the pool silently fills with exits that return nothing.
    # (bin/qualify.sh P2 exists for the same reason: "nav pages lie; leaves do not".)
    "health_check_url": "https://www.rockauto.com/en/catalog/"
                        "ac,1947,two-litre,2.0l+122cid+l6,1486554,"
                        "cooling+system,coolant+/+antifreeze,11393",
    "health_timeout_s": 8,
    "min_pool": 10,             # refill when healthy pool drops below this
    "pool_cache": "scraper/.proxy_pool.json",
    "refresh_interval_s": 1800,
}

# Rotating realistic browser identities. client picks one per session.
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36",
]

BATCH_ID = os.getenv("SP_BATCH_ID", "")  # set at runtime by crawl.py if empty
