#!/usr/bin/env python3
"""Split oversized unit frontiers so no shard holds more than --cap requests.

WHY: a unit is crawled by exactly ONE sequential lane, fleet-wide. Measured from
plan/targets.tsv: 651 of 1,698 units need >2,301 requests, and those units hold
83.9% of all remaining work. No number of runners fixes that — only more shards.

Splitting is safe: each shard is an independent DFS frontier, lanes share no
state, the leaf skip-set dedups across shards, and the idepth guard in
crawl_jsonl.py is per-process.

Shard names are <make>.s<N>_<lo>-<hi> so the workflow's `${U%_*}` / `${U##*_}`
still yield make and year-band; the crawl step strips the `.sN` with `${MK%.s*}`
before building the skip-set grep pattern.
"""
import argparse, collections, csv, math, os, sys

FR = "plan/fr"


def unit_reqs(targets: str) -> dict[str, int]:
    u: collections.Counter = collections.Counter()
    with open(targets, newline="", encoding="utf-8") as f:
        for row in csv.DictReader(f, delimiter="\t"):
            u[row["ukey"]] += int(row["reqs"])
    return u


def remap_to_frontier(u: dict[str, int]) -> dict[str, int]:
    """targets.tsv keys are SINGLE years (chevrolet_2000-2000) but plan/fr was re-cut
    into 5-year bands (f_chevrolet_2000-2004.ndjson) after targets.tsv was generated,
    so a direct name join matches nothing and the splitter reported 0 shards. Fold each
    target's requests into whichever band file actually covers its year."""
    bands: dict[str, list[tuple[int, int, str]]] = collections.defaultdict(list)
    for path in os.listdir(FR):
        if not (path.startswith("f_") and path.endswith(".ndjson")):
            continue
        unit = path[2:-len(".ndjson")]
        make, _, band = unit.rpartition("_")
        lo, _, hi = band.partition("-")
        if not (lo.isdigit() and hi.isdigit()):
            continue
        bands[make].append((int(lo), int(hi), unit))

    out: collections.Counter = collections.Counter()
    unmatched = 0
    for ukey, reqs in u.items():
        make, _, band = ukey.rpartition("_")
        year = band.partition("-")[0]
        if not year.isdigit():
            unmatched += reqs
            continue
        y = int(year)
        hit = next((n for lo, hi, n in bands.get(make, []) if lo <= y <= hi), None)
        if hit is None:
            unmatched += reqs
            continue
        out[hit] += reqs
    if unmatched:
        print(f"[warn] {unmatched:,} requests had no matching frontier band file")
    return out


def split(cap: int, targets: str, apply: bool) -> int:
    u = remap_to_frontier(unit_reqs(targets))
    made = kept = 0
    for unit, reqs in sorted(u.items()):
        src = os.path.join(FR, f"f_{unit}.ndjson")
        if not os.path.exists(src):
            continue
        k = max(1, math.ceil(reqs / cap))
        lines = open(src, encoding="utf-8").read().splitlines()
        k = min(k, len(lines))          # never make an empty shard
        if k <= 1:
            kept += 1
            continue
        make, band = unit.rsplit("_", 1)
        assert "." not in make, f"make {make!r} contains a dot; shard suffix is ambiguous"
        for i in range(k):
            part = lines[i::k]          # round-robin: models interleave, sizes balance
            dst = os.path.join(FR, f"f_{make}.s{i}_{band}.ndjson")
            if apply:
                with open(dst, "w", encoding="utf-8", newline="\n") as f:
                    f.write("\n".join(part) + "\n")
            made += 1
        if apply:
            os.remove(src)
    total = made + kept
    print(f"cap={cap}: {kept} units unsplit + {made} shards = {total} shards "
          f"({'APPLIED' if apply else 'dry run'})")
    return total


def selftest() -> int:
    u = unit_reqs("plan/targets.tsv")
    assert sum(u.values()) == 6183851, sum(u.values())
    assert len(u) == 1698, len(u)
    # every unit name must round-trip through the workflow's shell parsing
    for unit in u:
        make, band = unit.rsplit("_", 1)
        assert "." not in make, unit
        lo, hi = band.split("-")
        assert lo.isdigit() and hi.isdigit(), unit
        shard = f"{make}.s3_{band}"
        assert shard.rsplit("_", 1)[1] == band, shard          # ${U##*_}
        assert shard.rsplit("_", 1)[0].split(".s")[0] == make  # ${MK%.s*}
    # round-robin split is complete and disjoint
    lines = [f"l{i}" for i in range(137)]
    parts = [lines[i::5] for i in range(5)]
    assert sorted(x for p in parts for x in p) == sorted(lines)
    assert max(len(p) for p in parts) - min(len(p) for p in parts) <= 1
    print("OK: targets sum, name round-trip, round-robin completeness")
    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--cap", type=int, default=3000)
    ap.add_argument("--targets", default="plan/targets.tsv")
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--selftest", action="store_true")
    a = ap.parse_args()
    sys.exit(selftest() if a.selftest else (split(a.cap, a.targets, a.apply) and 0))
