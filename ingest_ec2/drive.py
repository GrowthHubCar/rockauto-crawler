"""drive.py — in-process, resumable bulk ingest of the EC2 crawl NDJSON.

One long-lived Python process (no per-file `wc`, no per-batch subprocess startup —
both pathologically slow on Windows). Reads each file once, accumulates listings
into ~MAXLINES batches, and per batch: stage (crawl.stage_listings, bulk
executemany) -> load (loader.run, canonical upserts) -> drain that batch's staged
rows -> delete its files -> record them done.

Resumable: ingest_ec2/state/done_files.txt lists finished files; re-running skips
them. out.tgz stays as source-of-truth, so deleting extracted files is safe.

    python ingest_ec2/drive.py
"""
import glob
import os
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(ROOT)
sys.path.insert(0, os.path.join(ROOT, "scraper"))
sys.path.insert(0, os.path.join(ROOT, "bin"))

import db          # noqa: E402
import crawl       # noqa: E402
import loader      # noqa: E402
from ingest_artifacts import _iter_listings  # noqa: E402

# Input dir is overridable so a large artifact set can be ingested straight from
# another drive instead of being copied onto the system disk first.
OUT = os.environ.get("INGEST_OUT", "ingest_ec2/out")
# STATE is overridable so N workers can run in parallel over disjoint INGEST_OUT dirs.
# Measured 2026-07-29: one worker pegs 82.5% of ONE core on a 16-core box while MySQL sits
# at 1-2 active threads — the ingest is CPU-bound in Python, not IO-bound, so the only real
# speedup is more processes. Each worker needs its OWN done-file or they race on append.
STATE = os.environ.get("INGEST_STATE", "ingest_ec2/state")
DONE_FILE = os.path.join(STATE, "done_files.txt")
MAXLINES = int(os.environ.get("MAXLINES", "100000"))
MAXBATCHES = int(os.environ.get("MAXBATCHES", "0"))   # 0 = unlimited (smoke-test cap)
# PID-qualified: batch_id keys staging, loading AND the drain DELETE, so two workers that
# started in the same second would otherwise share a batch_id and delete each other's rows.
RUN = f"{int(time.time())}_{os.getpid()}"

os.makedirs(STATE, exist_ok=True)
done = set()
if os.path.exists(DONE_FILE):
    with open(DONE_FILE, encoding="utf-8") as fh:
        done = {ln.strip() for ln in fh if ln.strip()}

# Recursive: a multi-box pull lands as <root>/<box>/out/*.ndjson. Globbing the
# tree directly means a 3.5 GB artifact set never has to be copied into one flat
# directory first (that copy cost more wall-clock than the ingest itself on Windows,
# where every subprocess spawn in the staging loop is ~50-100 ms).
# Full paths stay unique, so the done-file bookkeeping is unchanged.
allfiles = sorted(glob.glob(os.path.join(OUT, "*.ndjson")))
if not allfiles:
    allfiles = sorted(glob.glob(os.path.join(OUT, "**", "*.ndjson"), recursive=True))
todo = [f for f in allfiles if f not in done]
print(f"[drive] {len(todo)}/{len(allfiles)} files remaining (MAXLINES={MAXLINES})", flush=True)

t_start = time.time()
staged_total = 0
loaded_batches = 0


def flush(listings, batch_files, idx):
    global staged_total, loaded_batches
    if not listings:
        return
    bid = f"ec2_{RUN}_{idx:04d}"
    t0 = time.time()
    conn = db.connect()
    try:
        crawl.stage_listings(conn, listings, bid)   # bulk insert + commit
    finally:
        conn.close()
    # A lock-wait timeout can make InnoDB roll the loader's whole transaction back
    # (TransactionLost). The staged rows are already committed, so retrying re-reads them
    # and nothing is lost; without this one flaky batch kills an hours-long ingest.
    for attempt in (1, 2, 3):
        try:
            loader.run(bid, None)                    # canonicalize this batch
            break
        except loader.TransactionLost as exc:
            if attempt == 3:
                raise
            print(f"[{bid}] {exc} — retry {attempt + 1}/3", flush=True)
            time.sleep(5 * attempt)
    # drain the now-loaded staged rows so stg_* stays ~1 batch (InnoDB reuses pages)
    conn = db.connect()
    try:
        cur = conn.cursor()
        cur.execute("DELETE FROM stg_listings WHERE batch_id=%s", [bid])
        cur.execute("DELETE FROM stg_fitment  WHERE batch_id=%s", [bid])
        conn.commit()
    finally:
        conn.close()
    # record + delete files (out.tgz remains source of truth)
    with open(DONE_FILE, "a", encoding="utf-8") as df:
        for bf in batch_files:
            df.write(bf + "\n")
    for bf in batch_files:
        try:
            os.remove(bf)
        except OSError:
            pass
    staged_total += len(listings)
    loaded_batches += 1
    dt = time.time() - t0
    rate = len(listings) / dt if dt else 0
    print(f"[{bid}] {len(listings):>6} listings, {len(batch_files):>4} files, "
          f"{dt:>4.0f}s ({rate:>4.0f}/s) | total {staged_total} in {loaded_batches} batches, "
          f"elapsed {(time.time()-t_start)/60:.1f}m", flush=True)


buf, bfiles, idx = [], [], 0
for f in todo:
    rows = list(_iter_listings([f]))
    buf.extend(rows)
    bfiles.append(f)
    if len(buf) >= MAXLINES:
        flush(buf, bfiles, idx)
        buf, bfiles = [], []
        idx += 1
        if MAXBATCHES and idx >= MAXBATCHES:
            print(f"[drive] MAXBATCHES={MAXBATCHES} reached — stopping (smoke test).", flush=True)
            sys.exit(0)
flush(buf, bfiles, idx)   # final partial batch

print(f"[drive] DONE — {staged_total} listings loaded in {loaded_batches} batches, "
      f"{(time.time()-t_start)/60:.1f} min total", flush=True)
