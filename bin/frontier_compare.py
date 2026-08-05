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

if hrs < 0.5:
    print(f"\n  WINDOW TOO SHORT ({hrs:.2f} h). Per-run noise swamps the trend below")
    print("  ~30 min — a 75-second window once implied a 0.2h ETA. Need >= 0.5 h.")
    raise SystemExit(0)

# RAW `pending` IS CONFOUNDED and must never drive the ETA on its own: it is the sum
# over whatever lanes reported in that one run, and that count swings (1,318 -> 764
# across two real readings). Pending then "falls" purely because fewer lanes reported.
# Both metrics below are RATIOS, so sample size cancels out.
def per_lane(d):
    return d["pending"] / d["live"] if d["live"] else 0.0


def frac(d):
    return 100 * d["drained"] / max(d["drained"] + d["live"], 1)


print(f"\n  pending per live lane : {per_lane(prev):.0f} -> {per_lane(cur):.0f}"
      f"  ({per_lane(cur) - per_lane(prev):+.0f})")
print(f"  units drained         : {frac(prev):.1f}% -> {frac(cur):.1f}%"
      f"  ({frac(cur) - frac(prev):+.1f} pts)")

d_frac = frac(cur) - frac(prev)
if d_frac <= 0:
    print("\n  NO DRAIN. The drained-unit fraction is flat or falling: the crawl is")
    print("  still opening work as fast as it retires it. No finite ETA — dividing")
    print("  by this would invent a number.")
else:
    pts_per_h = d_frac / hrs
    remaining_h = (100 - frac(cur)) / pts_per_h
    print(f"\n  drain rate : {pts_per_h:.1f} percentage points/hour")
    print(f"  ETA (floor): {remaining_h:.1f} h to 100% of units retired")
    print("  FLOOR, not a point estimate — the last units are the fattest and the")
    print("  curve flattens near the end, so treat this as the optimistic bound.")
