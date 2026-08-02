"""Measure GZIPPED wire bytes per leaf — the unit a metered proxy actually bills.

    python bin/wire_bytes.py                 # uses the committed sample below
    python bin/wire_bytes.py --urls f.txt    # one URL per line
    python bin/wire_bytes.py --selftest      # offline

WHY: deciding whether N GB of a metered residential proxy covers the remaining
6,183,851 requests. Estimates in play span 20x — one report assumed 6.6 KB/request
(41 GB for the job) while measured page BODIES are ~38 KB.

THE TRAP THIS AVOIDS: `curl --compressed` (and requests' default) transparently
DECODES the response and reports the decoded length. That is where ~38 KB comes from,
and billing against it overstates the cost ~4x. Proxies bill what crosses the wire, so
we send Accept-Encoding: gzip and read the RAW encoded length without decoding.

Runs from anywhere unblocked — a GitHub runner. The wire size of a page is identical
whether fetched from a free Azure IP or a paid residential exit, so this pins the bill
for $0 before any purchase.
"""
from __future__ import annotations

import argparse
import sys
import urllib.request

REMAINING_REQUESTS = 6_183_851        # plan/targets.tsv, summed

UA = ("Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) "
      "Chrome/125.0 Safari/537.36")

# Real leaves sampled from the parts table (known to have returned parts), so we are
# not measuring a stub or an error page. bytes_probe.py derives its own leaf URLs by
# walking the tree and exits "no non-empty leaves measured".
SAMPLE = [
    "https://www.rockauto.com/en/catalog/ram,2021,1500,6.2l+v8+supercharged,3447035,interior,seat+frame,11485",
    "https://www.rockauto.com/en/catalog/porsche,1988,911,3.2l+h6,1262810,brake+&+wheel+hub,rotor,1896",
    "https://www.rockauto.com/en/catalog/ac,1947,two-litre,2.0l+122cid+l6,1486554,cooling+system,coolant+/+antifreeze,11393",
]


def wire_size(url: str, timeout: int = 25) -> tuple[int, int]:
    """(http_status, raw encoded bytes). Never decodes: no Accept-Encoding handling by
    urllib means the gzip stream is returned verbatim, which is what gets billed."""
    req = urllib.request.Request(url, headers={
        "User-Agent": UA,
        "Accept-Encoding": "gzip",
        "Accept": "text/html,application/xhtml+xml",
    })
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.status, len(resp.read())


def report(sizes: list[int]) -> None:
    if not sizes:
        print("!! nothing measured")
        raise SystemExit(1)
    avg = sum(sizes) // len(sizes)
    gb = avg * REMAINING_REQUESTS / 1e9
    print()
    print(f"leaves measured : {len(sizes)}")
    print(f"avg wire size   : {avg:,} B = {avg/1024:.1f} KB gzipped")
    print(f"remaining reqs  : {REMAINING_REQUESTS:,}")
    print(f"TOTAL NEEDED    : {gb:.1f} GB")
    for budget in (50, 100, 200):
        pct = min(100.0, budget / gb * 100)
        print(f"  {budget:3d} GB covers : {pct:5.1f}%"
              + ("  <-- enough" if pct >= 100 else ""))


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--urls", help="file with one leaf URL per line")
    a = ap.parse_args()

    urls = SAMPLE
    if a.urls:
        with open(a.urls, encoding="utf-8") as fh:
            urls = [ln.strip() for ln in fh if ln.strip().startswith("http")]

    sizes: list[int] = []
    for u in urls:
        try:
            code, n = wire_size(u)
        except Exception as exc:  # noqa: BLE001 — one dead URL must not kill the sample
            print(f"  ERR  {type(exc).__name__}  {u[38:110]}")
            continue
        print(f"  {code}  {n:>8,}B  {u[38:110]}")
        if code == 200 and n > 0:
            sizes.append(n)
    report(sizes)
    return 0


def _selftest() -> None:
    """ponytail: the arithmetic is the part that can be wrong; no network."""
    import io
    import contextlib
    buf = io.StringIO()
    with contextlib.redirect_stdout(buf):
        report([10_000, 20_000])          # avg 15,000 B
    out = buf.getvalue()
    assert "15,000 B" in out, out
    expect_gb = 15_000 * REMAINING_REQUESTS / 1e9        # ~92.8 GB
    assert f"{expect_gb:.1f} GB" in out, out
    assert "100 GB covers : 100.0%" in out.replace("  100 GB", "100 GB"), out
    try:
        report([])
    except SystemExit:
        pass
    else:
        raise AssertionError("empty sample must exit non-zero, not report 0 GB")
    print("selfcheck ok")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        _selftest()
        raise SystemExit(0)
    raise SystemExit(main())
