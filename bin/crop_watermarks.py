"""One-time batch: crop RockAuto's bottom "RockAuto.com" watermark off every
self-hosted part photo under assets/parts/.

Idempotent — imgclean stamps a JFIF marker on cleaned files, so already-cleaned
images are skipped and re-runs are safe. New images arrive pre-cleaned through
scraper/images.py (crop-on-download), so this only needs to sweep the existing
library once.
"""
from __future__ import annotations

import glob
import os
import sys
import time
from concurrent.futures import ProcessPoolExecutor

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
import imgclean  # noqa: E402


def _work(path: str) -> int:
    try:
        return 1 if imgclean.clean_file(path) else 0
    except Exception:  # noqa: BLE001
        return -1


def main() -> int:
    root = os.path.join(ROOT, "assets", "parts")
    files = glob.glob(os.path.join(root, "**", "*.jpg"), recursive=True)
    n = len(files)
    print(f"{n} images under {root}", flush=True)
    changed = skipped = failed = 0
    t0 = time.time()
    with ProcessPoolExecutor(max_workers=os.cpu_count()) as ex:
        for i, r in enumerate(ex.map(_work, files, chunksize=64), 1):
            if r == 1:
                changed += 1
            elif r == 0:
                skipped += 1
            else:
                failed += 1
            if i % 5000 == 0:
                dt = time.time() - t0
                print(f"  {i}/{n}  changed={changed} skipped={skipped} "
                      f"failed={failed}  {i/dt:.0f}/s", flush=True)
    print(f"DONE  changed={changed} skipped={skipped} failed={failed}  "
          f"in {time.time()-t0:.0f}s", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
