#!/usr/bin/env python
"""ingest_moreinfo_jsonl.py — load the file-based backfill output into MySQL.

Reads the NDJSON produced by bin/crawl_moreinfo_jsonl.py and applies it exactly
like bin/crawl_moreinfo.py's _store did:

    description -> parts.description        (only when we actually have text)
    specs       -> part_attributes          (existence-guarded, never duplicated)
    alt numbers -> part_interchange         (type='alternate')
    then parts.moreinfo_done = 1

Idempotent: re-running over the same files changes nothing. Safe to interrupt.

    python bin/ingest_moreinfo_jsonl.py out/mi_*.ndjson
"""
from __future__ import annotations

import glob
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
sys.path.insert(0, os.path.join(ROOT, "bin"))

import db                          # noqa: E402
from loader import norm_number     # noqa: E402


def store(cur, pid: int, mi: dict) -> int:
    desc = (mi.get("description") or "").strip()
    if mi.get("features"):
        desc = (desc + "\n\nFeatures & Benefits:\n"
                + "\n".join("• " + f for f in mi["features"])).strip()
    n_specs = 0
    # Only overwrite description when we HAVE text — never blank a good one.
    if desc:
        cur.execute("UPDATE parts SET description=%s WHERE id=%s", (desc[:65000], pid))
    for s in mi.get("specs", []):
        name, val = s.get("name"), s.get("value")
        if not name or not val:
            continue
        cur.execute(
            "INSERT INTO part_attributes (part_id, name, value) "
            "SELECT %s,%s,%s FROM DUAL WHERE NOT EXISTS "
            "(SELECT 1 FROM part_attributes WHERE part_id=%s AND name=%s)",
            (pid, name[:120], val[:255], pid, name[:120]),
        )
        n_specs += 1
    for num in mi.get("alt_numbers", []):
        nn = norm_number(num)
        if not nn:
            continue
        cur.execute(
            "INSERT INTO part_interchange (part_id, brand_name, part_number, number_norm, type) "
            "VALUES (%s,NULL,%s,%s,'alternate') ON DUPLICATE KEY UPDATE type=VALUES(type)",
            (pid, num[:100], nn[:100]),
        )
    cur.execute("UPDATE parts SET moreinfo_done=1 WHERE id=%s", (pid,))
    return n_specs


def main() -> int:
    pats = sys.argv[1:] or ["out/mi_*.ndjson"]
    files: list[str] = []
    for p in pats:
        # recursive=True so a '**' pattern actually descends. Callers pass a glob
        # rather than an expanded file list because a downloaded run can hold
        # hundreds of shards, and the expanded argv blows the 32,767-char Windows
        # command-line limit (WinError 206) - the same trap ingest_artifacts.py hit.
        files.extend(sorted(glob.glob(p, recursive=True)))
    if not files:
        print("no input files", flush=True)
        return 1
    conn = db.connect()
    parts_done = specs_done = missing = 0
    with conn.cursor() as cur:
        for path in files:
            with open(path, encoding="utf-8") as fh:
                for ln in fh:
                    ln = ln.strip()
                    if not ln:
                        continue
                    try:
                        mi = json.loads(ln)
                    except Exception:  # noqa: BLE001 - skip a torn line, keep going
                        continue
                    sku = mi.get("sku")
                    if not sku:
                        continue
                    cur.execute("SELECT id FROM parts WHERE sku=%s LIMIT 1", (sku,))
                    row = cur.fetchone()
                    if not row:
                        missing += 1        # part not ingested yet — a later run picks it up
                        continue
                    pid = row["id"] if isinstance(row, dict) else row[0]
                    specs_done += store(cur, pid, mi)
                    parts_done += 1
                    if parts_done % 500 == 0:
                        conn.commit()
                        print(f"  {parts_done} parts, {specs_done} specs", flush=True)
            conn.commit()
            print(f"[mi-ingest] {path} done", flush=True)
    conn.commit()
    conn.close()
    print(f"DONE: {parts_done} parts enriched, {specs_done} spec rows, "
          f"{missing} skus not in DB yet", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
