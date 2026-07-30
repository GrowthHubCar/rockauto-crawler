#!/usr/bin/env python
"""gw_health.py — provision API Gateway in EVERY region, then continuously
weight + evict endpoints by measured egress health.

WHY THIS EXISTS
  requests_ip_rotator.ApiGateway.send() picks `choice(self.endpoints)` — UNIFORM.
  Regions do NOT have equal capacity: measured distinct egress IPs range 110
  (ap-southeast-1) to 454 (eu-west-2), and the sustainable request rate of a region
  is proportional to its IP pool (each IP tolerates ~780 catalog requests, then TCP
  blackhole for hours). Uniform choice therefore over-drives the small regions into
  a blackhole while the big ones idle. Repeating an endpoint N times in the list
  gives it N/total of the traffic — that is the whole weighting mechanism.

  We do not know the pool sizes of the opt-in regions, and pools change. So instead
  of a static table: AIMD. Weight climbs while the region is clean, halves when it
  starts 5XX-ing, hits 0 (evicted) when it is poisoned, and is re-probed for
  recovery. That converges on each region's true capacity without measuring it.

PROBE COST IS ZERO
  We probe with /robots.txt. Measured 2026-07-26: 1,500 robots.txt at 5 req/s did
  NOT accrue against the per-IP budget (the IP was still clean 90 min later), while
  ~780 *catalog* requests always blackhole the IP. And a poisoned endpoint fails
  robots.txt at the same 45-80% rate as catalog pages (e6.py, 6 request shapes x 5
  endpoints) — because the failure is API Gateway being unable to open TCP to the
  origin from a blackholed egress IP, which is path-independent. So robots.txt is a
  free, exact, zero-lag health signal. CloudWatch would cost money and lag 1-2 min.

USAGE
  python bin/gw_health.py provision            # create/patch gateways in all regions
  python bin/gw_health.py run                  # health loop; rewrites gw_endpoints.txt
  python bin/gw_health.py selftest             # offline; proves AIMD + weighting
"""
from __future__ import annotations

import concurrent.futures as cf
import json
import os
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = "https://www.rockauto.com"
STATE = os.path.join(ROOT, "gw_state.json")          # {endpoint: {"region":..,"w":..}}
ENDPOINTS = os.path.join(ROOT, "gw_endpoints.txt")   # weighted comma list (lanes read this)

# 17 opted-in today + 17 opt-in-required. init_gateway() returns success=False and
# logs a warning for any region this account cannot reach, so passing all 34 is safe.
ENABLED = [
    "us-east-1", "us-east-2", "us-west-1", "us-west-2", "ca-central-1", "sa-east-1",
    "eu-west-1", "eu-west-2", "eu-west-3", "eu-central-1", "eu-north-1", "ap-south-1",
    "ap-northeast-1", "ap-northeast-2", "ap-northeast-3", "ap-southeast-1", "ap-southeast-2",
]
OPT_IN = [
    "ap-east-1", "ap-east-2", "ap-south-2", "ap-southeast-3", "ap-southeast-4",
    "ap-southeast-5", "ap-southeast-6", "ap-southeast-7", "af-south-1", "ca-west-1",
    "eu-central-2", "eu-south-1", "eu-south-2", "il-central-1", "me-central-1",
    "me-south-1", "mx-central-1",
]
REGIONS = ENABLED + OPT_IN

# Seed weights from the measured distinct-egress-IP census (250-500 draws, true TCP
# peer via ifconfig.me). Unmeasured regions start low and AIMD upward — starting an
# unknown region high would blackhole its pool before we learn it is small.
SEED = {
    "eu-west-2": 45, "ca-central-1": 36, "eu-west-3": 26, "eu-north-1": 23,
    "us-east-1": 24, "ap-south-1": 22, "ap-northeast-3": 20, "us-east-2": 19,
    "ap-southeast-2": 19, "eu-west-1": 18, "eu-central-1": 17, "us-west-2": 16,
    "us-west-1": 14, "ap-northeast-1": 13, "sa-east-1": 11, "ap-northeast-2": 11,
    "ap-southeast-1": 11,
}
SEED_UNKNOWN = 6            # opt-in regions: start at ~1/2 the smallest known pool
WMAX = 60                   # cap so one region can never take the whole fleet
INTERVAL = float(os.getenv("GW_HEALTH_INTERVAL", "30"))
PROBES = int(os.getenv("GW_HEALTH_PROBES", "12"))     # robots.txt hits per endpoint per cycle
S3_URI = os.getenv("GW_HEALTH_S3", "")                # e.g. s3://rockauto-relay-.../gw/


def aimd(w: float, fail: float) -> float:
    """One AIMD step. `fail` = fraction of probes that did not return 200.

    Thresholds come from the measured poisoning curve: a region at <2% 5XX has
    headroom, >10% is already draining its IP pool, >25% is poisoned and every
    further request is thrown away AND deepens the block. 0 = evicted; the endpoint
    stays probed (probes are free) and climbs back from 1 when it recovers.
    """
    if fail > 0.25:
        return 0.0
    if fail > 0.10:
        return max(1.0, w * 0.5)          # multiplicative decrease, never to 0 here
    if fail < 0.02:
        return min(float(WMAX), (w or 1.0) + 2.0)   # additive increase, 0 -> 1 -> 3 ...
    return max(1.0, w)                    # 2-10%: hold, we are at the knee


# Hard ceiling on total weight. The list length IS the fleet throttle
# (crawl_apigw.py: gap = SP_FLEET_LANES / (0.75 * len(eps))), so an unbounded AIMD
# climb is an unbounded request rate.
#
# WHY A CEILING IS REQUIRED: the AIMD signal is a /robots.txt probe, but robots.txt
# is a static asset RockAuto serves freely — it does not captcha and does not accrue
# against the per-IP catalog budget. So the controller's sensor is DECOUPLED from the
# quantity that actually saturates: the catalog captcha rate. Measured 2026-07-27,
# weight climbed 359 -> 1127 in 30 min (=> ~845 req/s) while catalog captchas sat at
# ~45% and banked output was ~0. The loop happily drove 4.4x past the cliff because
# every robots.txt probe came back 200.
# 24 live regions x ~8 req/s sustainable = ~190 req/s => ~253 weight units.
WEIGHT_CAP = float(os.getenv("SP_GW_MAX_WEIGHT", "260"))
CAP_FLOOR = float(os.getenv("SP_GW_CAP_FLOOR", "60"))     # ~45 req/s — never throttle below this
CAP_CEIL = float(os.getenv("SP_GW_CAP_CEIL", "900"))      # ~675 req/s — above the 845 req/s cliff


def catalog_captcha_rate(logdir: str = "logs", _last: dict = {}) -> float | None:
    """Measured CAPTCHA rate on real CATALOG requests. None until lanes report.

    This is the sensor gw_health has always been missing. Its own probe is
    /robots.txt, which RockAuto serves freely from an egress IP that is ALREADY
    blackholed for catalog pages — so the AIMD loop is open-loop with respect to
    the thing that actually saturates. That is not a theory: weight climbed
    359 -> 1127 (~845 req/s) in 30 min while catalog captchas sat at ~45% and
    banked rows were ~0, and every probe came back 200.

    The lanes already count it. crawl_jsonl prints `reqs=N captchas=M` every 20
    nodes; this reads the newest line per log and rates the DELTA since last cycle.
    Deltas only, because a relaunched lane resets its counters to 0 — a raw ratio
    would read a fresh lane as 0% forever and re-open the throttle at the cliff.
    """
    import glob
    import re
    pat = re.compile(r"reqs=(\d+) captchas=(\d+)")
    d_req = d_cap = 0
    for path in glob.glob(os.path.join(ROOT, logdir, "*.log")):
        try:
            with open(path, "rb") as fh:
                fh.seek(0, 2)
                fh.seek(max(0, fh.tell() - 4096))
                hits = pat.findall(fh.read().decode("utf-8", "replace"))
        except OSError:
            continue
        if not hits:
            continue
        req, cap = int(hits[-1][0]), int(hits[-1][1])
        p_req, p_cap = _last.get(path, (0, 0))
        if req >= p_req:                      # lane restarted => counters reset => skip
            d_req += req - p_req
            d_cap += cap - p_cap
        _last[path] = (req, cap)
    return (d_cap / d_req) if d_req >= 50 else None   # <50 reqs is noise, not a signal


def adapt_cap(cap: float, rate: float | None) -> float:
    """AIMD the fleet's rate cap on the CATALOG captcha rate.

    Trip points sit BELOW aimd()'s robots.txt ones (10% draining / 25% poisoned):
    a catalog captcha means an egress IP is already spent, so react earlier.
    """
    if rate is None:
        return cap                                   # no lane data — never move blind
    if rate > 0.08:
        return max(CAP_FLOOR, round(cap * 0.5))      # past the knee — halve NOW
    if rate < 0.03:
        return min(CAP_CEIL, round(cap * 1.15))      # clean — climb 15%/cycle
    return cap                                       # 3-8% IS the knee — hold here


def weighted_list(state: dict, cap: float = None) -> list[str]:
    """Expand {endpoint: weight} into the repetition list ApiGateway.send() samples
    uniformly. Endpoint appearing N times => N/total of the traffic.

    Scaled down proportionally past WEIGHT_CAP so AIMD keeps deciding the *mix*
    (which region earns more traffic) while the total stays under the measured
    cliff. Every live endpoint keeps >=1 slot, so a scale-down never silently
    evicts a healthy region."""
    cap = WEIGHT_CAP if cap is None else cap
    total = sum(rec["w"] for rec in state.values())
    scale = cap / total if total > cap else 1.0
    out = []
    for ep, rec in sorted(state.items()):
        n = int(round(rec["w"] * scale))
        out += [ep] * max(1, n) if rec["w"] > 0 else []
    return out


def probe(ep: str, n: int) -> float:
    """Fire n robots.txt requests through this endpoint; return the failure fraction."""
    import requests
    url = f"https://{ep}/ProxyStage/robots.txt"
    hdr = {"Host": ep, "X-My-X-Forwarded-For": "1.2.3.4"}
    bad = 0

    def one():
        try:
            r = requests.get(url, headers=hdr, timeout=14)
            return r.status_code != 200
        except Exception:  # noqa: BLE001 — a timeout IS the blackhole signal
            return True

    with cf.ThreadPoolExecutor(max_workers=n) as ex:
        for is_bad in ex.map(lambda _: one(), range(n)):
            bad += is_bad
    return bad / float(n)


def load_state() -> dict:
    with open(STATE, encoding="utf-8") as fh:
        return json.load(fh)


def save_state(state: dict) -> None:
    tmp = STATE + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(state, fh, indent=1, sort_keys=True)
    os.replace(tmp, STATE)                      # atomic: lanes never read a half file


def write_endpoints(state: dict, cap: float = None) -> int:
    eps = weighted_list(state, cap)
    if not eps:
        print("[gw_health] REFUSING to write an empty endpoint list "
              "(every region evicted) — leaving the last good file in place", flush=True)
        return 0
    tmp = ENDPOINTS + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        fh.write(",".join(eps))
    os.replace(tmp, ENDPOINTS)
    if S3_URI:
        os.system(f"aws s3 cp {ENDPOINTS} {S3_URI.rstrip('/')}/gw_endpoints.txt --only-show-errors")
    return len(eps)


def provision() -> int:
    """Create (or reuse) one REST API per region, then patch binaryMediaTypes so
    responses come back gzip. WITHOUT that patch API Gateway decodes gzip and bills
    ~370KB/leaf instead of ~39KB — the difference is ~$243 vs ~$26 for a full pass."""
    import boto3
    from botocore.config import Config
    from requests_ip_rotator import ApiGateway

    # ONE REGION AT A TIME. ApiGateway.start() loops every region internally, so a
    # single unreachable one aborts the whole run — which is exactly what happened
    # with me-south-1 (ConnectTimeoutError) right after the account opted into the
    # remaining regions: newly enabled regions can take a while to become routable,
    # and that one timeout cost us all 34. Short timeouts + per-region try/except
    # mean a dead region costs ~8 s instead of the entire provisioning pass.
    fast = Config(connect_timeout=8, read_timeout=15, retries={"max_attempts": 1})
    eps: list[str] = []
    for reg in REGIONS:
        try:
            g = ApiGateway(SITE, regions=[reg])
            got = g.start(force=False)
            eps.extend(got)
            print(f"[provision] {reg:<16} ok ({len(got)})", flush=True)
        except Exception as e:  # noqa: BLE001 — an unusable region must not stop the rest
            print(f"[provision] {reg:<16} SKIP {type(e).__name__}: {str(e)[:60]}", flush=True)
    print(f"[provision] {len(eps)} endpoints across {len(REGIONS)} attempted regions", flush=True)

    # MERGE, never reset. run() only ever probes endpoints already present in state,
    # so a region whose gateway exists but is absent here stays invisible forever —
    # that is why 24 of 33 provisioned regions sat idle while only 9-10 carried the
    # whole fleet. Re-running provision must therefore ADD the missing endpoints
    # without discarding what AIMD has learned: a plain `state = {}` would reset every
    # converged weight AND re-seed the burned-out regions high again (eu-west-2 back to
    # 45, ca-central-1 back to 36), instantly re-poisoning the exact pools that took
    # hours to fall. New endpoints enter at SEED_UNKNOWN and let AIMD find their real
    # capacity, because the SEED table is measurably anti-correlated with live capacity.
    state = load_state()
    for ep in eps:
        region = ep.split(".")[2]
        api_id = ep.split(".")[0]
        c = boto3.client("apigateway", region_name=region)
        try:
            c.update_rest_api(restApiId=api_id, patchOperations=[
                {"op": "add", "path": "/binaryMediaTypes/*~1*"}])
            c.create_deployment(restApiId=api_id, stageName="ProxyStage")
            gz = "gzip-patched"
        except Exception as e:  # noqa: BLE001 — already patched is the common case
            gz = f"patch-skip({str(e)[:40]})"
        state.setdefault(ep, {"region": region, "w": float(SEED_UNKNOWN)})
        print(f"  {region:<16} {ep}  w={state[ep]['w']:<5} {gz}", flush=True)

    save_state(state)
    n = write_endpoints(state)
    missing = sorted(set(REGIONS) - {r["region"] for r in state.values()})
    print(f"[provision] wrote {n} weighted entries -> {ENDPOINTS}", flush=True)
    if missing:
        print(f"[provision] NOT available (enable in console: Account -> AWS Regions): "
              f"{','.join(missing)}", flush=True)
    return 0


def run() -> int:
    state = load_state()
    cap = WEIGHT_CAP
    while not os.path.exists(os.path.join(ROOT, "STOP_CRAWL")):
        t0 = time.time()
        eps = list(state)
        with cf.ThreadPoolExecutor(max_workers=min(34, len(eps))) as ex:
            fails = dict(zip(eps, ex.map(lambda e: probe(e, PROBES), eps)))
        for ep, f in fails.items():
            before = state[ep]["w"]
            state[ep]["w"] = aimd(before, f)
            state[ep]["fail"] = round(f, 3)
            if before > 0 and state[ep]["w"] == 0:
                print(f"[evict] {state[ep]['region']} fail={f:.0%}", flush=True)
            elif before == 0 and state[ep]["w"] > 0:
                print(f"[recover] {state[ep]['region']} fail={f:.0%}", flush=True)
        save_state(state)
        cc = catalog_captcha_rate()
        cap = adapt_cap(cap, cc)
        n = write_endpoints(state, cap)
        live = sum(1 for r in state.values() if r["w"] > 0)
        print(f"[gw_health] {live}/{len(state)} regions live, {n} weighted entries, "
              f"cap={cap:.0f} (~{0.75 * n:.0f} req/s) "
              f"catalog_captcha={'n/a' if cc is None else f'{cc:.1%}'}, "
              f"cycle {time.time() - t0:.1f}s", flush=True)
        time.sleep(max(1.0, INTERVAL - (time.time() - t0)))
    return 0


def selftest() -> int:
    ok = True

    def chk(label, cond):
        nonlocal ok
        ok = ok and cond
        print(f"  [{'PASS' if cond else 'FAIL'}] {label}")

    chk("clean region climbs", aimd(10, 0.00) == 12)
    chk("knee holds", aimd(10, 0.05) == 10)
    chk("draining halves", aimd(10, 0.15) == 5)
    chk("poisoned evicts", aimd(10, 0.40) == 0)
    chk("evicted stays evicted while poisoned", aimd(0, 0.40) == 0)
    chk("evicted recovers from 0 (not stuck)", aimd(0, 0.00) == 3)
    chk("weight capped", aimd(WMAX, 0.00) == WMAX)
    chk("decrease never reaches 0 (only eviction does)", aimd(1, 0.15) == 1)

    # The catalog-captcha loop: the robots.txt sensor cannot see this cliff.
    chk("cap halves past the catalog knee", adapt_cap(400, 0.20) == 200)
    chk("cap climbs while catalog is clean", adapt_cap(400, 0.00) == 460)
    chk("cap HOLDS at the 3-8% knee", adapt_cap(400, 0.05) == 400)
    chk("no lane data -> never move blind", adapt_cap(400, None) == 400)
    chk("cap floor honoured", adapt_cap(CAP_FLOOR, 0.50) == CAP_FLOOR)
    chk("cap ceiling honoured", adapt_cap(CAP_CEIL, 0.00) == CAP_CEIL)
    chk("cap threads through to the list length",
        len(weighted_list({f"e{i}.x": {"region": "r", "w": 10} for i in range(50)}, cap=100)) == 100)

    st = {"a.x": {"region": "r1", "w": 3}, "b.x": {"region": "r2", "w": 1}}
    lst = weighted_list(st)
    chk("repetition encodes weight", lst.count("a.x") == 3 and lst.count("b.x") == 1)
    chk("traffic share == weight share", lst.count("a.x") / len(lst) == 0.75)

    # The whole point: a big pool must out-weigh a small one at steady state.
    chk("seed favours measured big pools",
        SEED["eu-west-2"] > SEED["ap-southeast-1"] * 3)
    chk("unmeasured regions start below every measured one",
        SEED_UNKNOWN < min(SEED.values()))
    print("PASS" if ok else "FAIL")
    return 0 if ok else 1


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "selftest"
    raise SystemExit({"provision": provision, "run": run, "selftest": selftest}[cmd]())
