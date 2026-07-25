#!/usr/bin/env python
"""fetch_images.py — self-host the part photos a DATA-ONLY crawl left as URLs.

A data-only crawl records each RockAuto photo URL in part_images.path but never
downloads it, so the storefront would hotlink rockauto.com (no watermark crop, no
CDN, breaks if they delete). This fetches every raw URL, strips the watermark
(scraper/imgclean via images.download), stores it under assets/parts/<sharded>,
and rewrites the DB path to the local /RockAuto/assets/parts/ URL — so
bin/bunny_upload.py can mirror it and img_url() serves it from BunnyCDN.

Images are NOT IP-priced, so this is FREE (no Evomi). Idempotent + resumable:
download() skips files already on disk, and a relinked row is no longer a raw URL
so it won't be re-selected.

    python bin/fetch_images.py [--limit N] [--workers 12]
"""
from __future__ import annotations

import argparse
import os
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor

import pymysql.err  # noqa: E402  (lock-timeout retry vs the concurrent moreinfo pass)

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
import db          # noqa: E402
import images      # noqa: E402  (download + imgclean watermark crop)
import requests    # noqa: E402

ASSETS = os.path.join(ROOT, "assets", "parts")
_local = threading.local()


def _session() -> requests.Session:
    s = getattr(_local, "s", None)
    if s is None:
        s = requests.Session()
        s.headers["User-Agent"] = "Mozilla/5.0 (SupremeAutos image fetch)"
        _local.s = s
    return s


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0, help="cap URLs processed (0 = all)")
    ap.add_argument("--workers", type=int, default=12)
    args = ap.parse_args()

    conn = db.connect()
    cur = conn.cursor()
    q = "SELECT DISTINCT path FROM part_images WHERE path LIKE 'http%%rockauto%%'"
    if args.limit:
        q += f" LIMIT {int(args.limit)}"
    cur.execute(q)
    urls = [r["path"] for r in cur.fetchall()]
    print(f"{len(urls)} raw image URLs to fetch+crop+relink", flush=True)
    if not urls:
        return 0

    def work(url):  # runs in worker threads — download only, no DB
        return url, images.download(_session(), url, ASSETS)

    def relink(url, web):
        # Two carousel-variant URLs can crop to ONE local file; with the unique
        # (part_id, path) index the 2nd relink collides. UPDATE IGNORE relinks the
        # non-colliding rows, then DELETE drops the leftover raw rows whose local
        # path already exists for that part (a true dup).
        # Commit each relink on its own so `parts` row locks are held for ~ms — the
        # concurrent moreinfo pass also UPDATEs `parts`, and a fat transaction here
        # would block it past innodb_lock_wait_timeout. Retry the rare 1205 collision.
        for attempt in range(6):
            try:
                cur.execute("UPDATE IGNORE part_images SET path=%s WHERE path=%s", (web, url))
                cur.execute("DELETE FROM part_images WHERE path=%s", (url,))
                cur.execute("UPDATE parts SET primary_image_path=%s WHERE primary_image_path=%s",
                            (web, url))
                conn.commit()
                return
            except pymysql.err.OperationalError as e:
                conn.rollback()
                if e.args[0] != 1205 or attempt == 5:
                    raise
                time.sleep(1 + attempt)

    done = ok = 0
    with ThreadPoolExecutor(max_workers=args.workers) as ex:
        for url, web in ex.map(work, urls):   # DB writes stay in the main thread
            done += 1
            if web:
                ok += 1
                relink(url, web)
            if done % 100 == 0:
                print(f"  {done}/{len(urls)}  ok={ok}", flush=True)
    conn.commit()
    print(f"DONE: fetched+cropped+relinked {ok}/{len(urls)} images -> assets/parts/", flush=True)
    print("next: python bin/bunny_upload.py   (mirror the new files to BunnyCDN)", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
