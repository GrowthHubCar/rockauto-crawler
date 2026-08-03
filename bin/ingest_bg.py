#!/usr/bin/env python
"""ingest_bg.py — buyers-guide NDJSON -> stg_fitment, for the existing loader.

bin/ingest_artifacts.py stages LISTINGS. These rows are fitment-only (the part is
already in `parts`), so they go straight to stg_fitment — the same thing
bin/ingest_acespies.py does. bin/loader.py:load_fitment then resolves sku -> part_id
and make/model/year/engine -> vehicle_id and upserts part_fitment. No loader change.

    python bin/ingest_bg.py out/bg_*.ndjson
    python bin/ingest_bg.py --selftest        # offline, rolled back
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import sys
from datetime import datetime, timezone

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))

import db  # noqa: E402

SQL = ("INSERT INTO stg_fitment "
       "(sku, make_name, model_name, `year`, engine_name, trim, note, batch_id, processed) "
       "VALUES (%s,%s,%s,%s,%s,NULL,NULL,%s,0)")
CHUNK = 5000


def _rows(paths):
    for pattern in paths:
        for path in (glob.glob(pattern) or [pattern]):
            if not os.path.isfile(path):
                print(f"[warn] not a file: {path}")
                continue
            with open(path, encoding="utf-8") as fh:
                for ln in fh:
                    ln = ln.strip()
                    if not ln:
                        continue
                    try:
                        r = json.loads(ln)
                    except ValueError:
                        continue
                    if r.get("sku") and r.get("make_name") and r.get("model_name") and r.get("year"):
                        yield (r["sku"], r["make_name"], r["model_name"], int(r["year"]),
                               r.get("engine_name"))


def ingest(paths, batch_id: str | None = None) -> int:
    batch_id = batch_id or ("bg_" + datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S"))
    conn = db.connect()
    n = 0
    try:
        with conn.cursor() as cur:
            buf = []
            for row in _rows(paths):
                buf.append(row + (batch_id,))
                if len(buf) >= CHUNK:
                    cur.executemany(SQL, buf)
                    conn.commit()
                    n += len(buf)
                    buf.clear()
            if buf:
                cur.executemany(SQL, buf)
                conn.commit()
                n += len(buf)
    finally:
        conn.close()
    print(f"staged {n} rows into stg_fitment (batch {batch_id})")
    return n


def _selftest() -> bool:
    """Round-trip 2 rows through stg_fitment, then delete them."""
    import tempfile
    ok = True
    tmp = os.path.join(tempfile.gettempdir(), "bg_selftest.ndjson")
    with open(tmp, "w", encoding="utf-8") as fh:
        fh.write(json.dumps({"sku": "selftestbrand-selftest-1", "make_name": "Honda",
                             "model_name": "Accord", "year": 2015,
                             "engine_name": "2.4L L4"}) + "\n")
        fh.write(json.dumps({"sku": "selftestbrand-selftest-1", "make_name": "Honda",
                             "model_name": "Accord", "year": 2016}) + "\n")
        fh.write('{"sku":"x"}\n')            # incomplete -> must be dropped
        fh.write("not json\n")               # garbage -> must be dropped
    batch = "bg_selftest"
    n = ingest([tmp], batch)
    conn = db.connect()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) c FROM stg_fitment WHERE batch_id=%s", [batch])
            got = cur.fetchone()["c"]
            cur.execute("DELETE FROM stg_fitment WHERE batch_id=%s", [batch])
        conn.commit()
    finally:
        conn.close()
    os.remove(tmp)
    ok = (n == 2 and got == 2)
    print(f"  [{'PASS' if ok else 'FAIL'}] 2 valid rows staged, 2 bad rows dropped "
          f"(returned={n} in_db={got})")
    print("PASS" if ok else "FAIL")
    return ok


def main() -> int:
    ap = argparse.ArgumentParser(description="Stage buyers-guide NDJSON into stg_fitment.")
    ap.add_argument("paths", nargs="*")
    ap.add_argument("--batch-id", default=None)
    ap.add_argument("--selftest", action="store_true")
    a = ap.parse_args()
    if a.selftest:
        return 0 if _selftest() else 1
    if not a.paths:
        ap.error("give at least one NDJSON path/glob (or --selftest)")
    ingest(a.paths, a.batch_id)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
