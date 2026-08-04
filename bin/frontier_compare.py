"""Compare the two most recent .eta readings and derive an ETA from MEASURED drain.

ETA = pending_now / (drop in pending per hour). If pending is flat or rising the
frontier is still discovering work faster than it consumes it, and no finite ETA is
reported — saying "no drain yet" is the honest output, not dividing by a small number.
"""
import glob
import json
import os
from datetime import datetime


def load(p):
    d = json.load(open(p, encoding="utf-8"))
    d["_path"] = p
    tot = lambda k: sum(v.get(k, 0) for v in d["shards"].values())  # noqa: E731
    d["pending"] = tot("pending")
    d["live"] = tot("live")
    d["drained"] = tot("drained")
    d["retired"] = tot("retired")
    d["when"] = datetime.strptime(d["ts"], "%Y%m%dT%H%M%SZ")
    return d


files = sorted(glob.glob(".eta/reading-*.json"))
if not files:
    raise SystemExit("no readings yet")
cur = load(files[-1])
print(f"\n  LATEST  {cur['ts']}")
for name, v in sorted(cur["shards"].items()):
    print(f"    {name:<20} pending={v['pending']:>7,}  live={v['live']:>4}"
          f"  drained={v['drained']:>4}  retired={v['retired']:>3}")
frac = 100 * cur["drained"] / max(cur["drained"] + cur["live"], 1)
print(f"    {'TOTAL':<20} pending={cur['pending']:>7,}  live={cur['live']:>4}"
      f"  drained={cur['drained']:>4} ({frac:.0f}%)  retired={cur['retired']:>3}")

if len(files) < 2:
    print("\n  Only one reading — run again in ~45 min for a drain rate.")
    raise SystemExit(0)

prev = load(files[-2])
hrs = (cur["when"] - prev["when"]).total_seconds() / 3600
if hrs <= 0:
    raise SystemExit("readings out of order")
dp = prev["pending"] - cur["pending"]
dr = cur["retired"]
print(f"\n  WINDOW  {prev['ts']} -> {cur['ts']}  ({hrs:.2f} h)")
print(f"    pending {prev['pending']:,} -> {cur['pending']:,}   change {-dp:+,}")
print(f"    drained {prev['drained']:,} -> {cur['drained']:,}   change "
      f"{cur['drained'] - prev['drained']:+,}")

if dp <= 0:
    print("\n  NO DRAIN. Pending frontier is flat or GROWING: the crawl is still")
    print("  discovering work faster than it finishes it. No finite ETA can be")
    print("  derived yet — dividing by this would invent a number.")
else:
    rate = dp / hrs
    print(f"\n  drain rate: {rate:,.0f} pending-nodes/hour")
    print(f"  ETA at this rate: {cur['pending'] / rate:.1f} h")
    print("  (assumes the drain rate holds; it accelerates as units near exhaustion)")
