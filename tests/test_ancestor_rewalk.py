"""Regression: RockAuto re-emits the open ANCESTOR CHAIN as nav nodes on every page.

A model page lists its own make/year/model alongside the real carcode children. Within one run
`seen_this_run` masks them; on RESUME that set is empty, so the ancestors re-enter the frontier
and the whole make is re-walked from the root. That single defect produced the growing frontier,
the ~59% "nav overhead", the 0.68%-new-SKU rate and the 0.050 fitments/row ingest yield, and it
inflated the remaining crawl from ~6.2M requests (plan/targets.tsv) to an apparent ~42M.

The guard in crawl_jsonl.process() drops any child whose idepth <= the parent's. A genuine child
is always parent+1, so this CANNOT drop a real child — it is lossless.

Run: python tests/test_ancestor_rewalk.py
"""
import os, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scraper"))
sys.path.insert(0, ROOT)

import parsers  # noqa: E402

FIXTURE = os.path.join(ROOT, ".awstmp", "sample_page.html")


def main() -> int:
    if not os.path.exists(FIXTURE):
        print(f"SKIP: fixture missing ({FIXTURE})")
        return 0
    html = open(FIXTURE, encoding="utf-8", errors="replace").read()
    children = parsers.parse_nav(html)
    # the model page this fixture came from sits at idepth 2
    pdep = 2
    kept, echoes = [], []
    for c in children:
        cdep = (c.get("jsn") or {}).get("idepth")
        (echoes if (cdep is not None and int(cdep) <= pdep) else kept).append(c)

    print(f"  parse_nav returned {len(children)} nav nodes")
    print(f"  ancestor echoes (idepth <= {pdep}): {len(echoes)}")
    print(f"  genuine children  (idepth >  {pdep}): {len(kept)}")

    assert echoes, "fixture no longer contains ancestor echoes — test is not exercising the bug"
    assert kept, "guard would drop every child — that WOULD lose coverage"
    for c in kept:
        assert int((c.get("jsn") or {}).get("idepth")) == pdep + 1, \
            "a kept child is not exactly parent+1; the guard's premise is wrong"
    print(f"PASS: {len(echoes)} ancestor echoes filtered, {len(kept)} real children kept "
          f"(all at idepth {pdep + 1})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
