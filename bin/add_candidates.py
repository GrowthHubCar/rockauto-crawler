"""Append predicted missing leaves for one unit to its frontier.

    python bin/add_candidates.py <unit> <frontier_file> [max]

plan/cand_leaves.txt.gz holds 3,407,670 leaf hrefs predicted from engine-scoped family
unions: for each crawled vehicle, the part-type triples its same make+model+engine
siblings have and it lacks. Measured hit rate 40% on a 60-URL sample (19 hit, 31 empty,
0 error), so 60% cost one cheap empty response — far less than fetching a vehicle's group
list and every part-type list just to DISCOVER the same leaves. The whole partial-vehicle
sweep is 3.4M fetches (~1.2 h at 776 req/s) against 17-53 h of nav walking.

Emitted as leaf nodes with no jsn, so the ancestor-rewalk guard cannot drop their children
(it needs idepth on both sides), and the skip-set still filters anything already crawled.
"""
import gzip, json, os, sys

def main() -> int:
    unit, out = sys.argv[1], sys.argv[2]
    cap = int(sys.argv[3]) if len(sys.argv) > 3 else 20000
    src = os.path.join("plan", "cand_leaves.txt.gz")
    if not os.path.exists(src):
        print("[cand] no plan/cand_leaves.txt.gz — skipping"); return 0
    make, band = unit.rsplit("_", 1)
    lo, hi = (int(x) for x in band.split("-"))
    pfx = "/en/catalog/" + make + ","
    n = 0
    with gzip.open(src, "rt", encoding="utf-8") as fh, open(out, "a", encoding="utf-8", newline="\n") as w:
        for ln in fh:
            if not ln.startswith(pfx):
                continue
            f = ln.strip().split(",")
            if len(f) != 8:
                continue
            try:
                if not (lo <= int(f[1]) <= hi):
                    continue
            except ValueError:
                continue
            w.write(json.dumps({"node_type": "parttype", "href": ln.strip(),
                                "payload": {"nodetype": "parttype"}}) + "\n")
            n += 1
            if n >= cap:
                break
    print(f"[cand] {unit}: +{n} predicted leaves")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
