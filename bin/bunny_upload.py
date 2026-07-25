"""bunny_upload.py — mirror every cleaned part photo to BunnyCDN storage, then
optionally warm the CDN edge.

  python bin/bunny_upload.py            # upload all not-yet-uploaded images
  python bin/bunny_upload.py --warm     # also GET each pull URL (populate edge)
  python bin/bunny_upload.py --warm-only
  python bin/bunny_upload.py --limit 50 # smoke test on a handful

Resumable + idempotent: uploaded rel-paths are recorded in .bunny_upload_state.json,
so an interrupted run picks up where it left off. Bunny storage PUT overwrites, so
re-uploading is always safe.
"""
from __future__ import annotations

import glob
import json
import os
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor

import requests

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "bin"))
import bunny  # noqa: E402

PARTS = os.path.join(ROOT, "assets", "parts")
STATE = os.path.join(ROOT, ".bunny_upload_state.json")
WORKERS = int(os.environ.get("BUNNY_WORKERS", "32"))
_lock = threading.Lock()


def rel_of(path: str) -> str:
    return os.path.relpath(path, PARTS).replace(os.sep, "/")


def load_state() -> set:
    try:
        with open(STATE, encoding="utf-8") as fh:
            return set(json.load(fh).get("uploaded", []))
    except (OSError, ValueError):
        return set()


def save_state(done: set) -> None:
    tmp = STATE + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump({"uploaded": sorted(done)}, fh)
    os.replace(tmp, STATE)


def main() -> int:
    warm = "--warm" in sys.argv or "--warm-only" in sys.argv
    warm_only = "--warm-only" in sys.argv
    limit = None
    if "--limit" in sys.argv:
        limit = int(sys.argv[sys.argv.index("--limit") + 1])

    cfg = bunny.load_cfg()
    files = sorted(glob.glob(os.path.join(PARTS, "**", "*.jpg"), recursive=True))
    if limit:
        files = files[:limit]
    done = load_state()
    todo = files if warm_only else [f for f in files if rel_of(f) not in done]
    print(f"{len(files)} images | already uploaded {len(done)} | "
          f"{'warming' if warm_only else 'to upload'} {len(todo)}", flush=True)

    sess = requests.Session()
    n_ok = n_err = n_warm = 0
    t0 = time.time()

    def up(path):
        nonlocal n_ok, n_err, n_warm
        rel = rel_of(path)
        try:
            if not warm_only:
                with open(path, "rb") as fh:
                    st = bunny.put(sess, cfg, rel, fh.read())
                if st not in (200, 201):
                    with _lock:
                        n_err += 1
                    return
                with _lock:
                    done.add(rel)
                    n_ok += 1
            if warm:
                ws = bunny.warm(sess, cfg, rel)
                if ws == 200:
                    with _lock:
                        n_warm += 1
        except Exception:  # noqa: BLE001
            with _lock:
                n_err += 1

    with ThreadPoolExecutor(max_workers=WORKERS) as ex:
        for i, _ in enumerate(ex.map(up, todo), 1):
            if i % 2000 == 0:
                with _lock:
                    save_state(done)
                dt = time.time() - t0
                print(f"  {i}/{len(todo)}  ok={n_ok} warm={n_warm} err={n_err}  "
                      f"{i/dt:.0f}/s", flush=True)
    save_state(done)
    print(f"DONE  uploaded={n_ok} warmed={n_warm} errors={n_err} "
          f"total_on_bunny={len(done)}  in {time.time()-t0:.0f}s", flush=True)
    return 0 if n_err == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
