#!/usr/bin/env bash
# Find this box's real lane ceiling by probing it, and hold there. Runs forever, on-box.
#   setsid bash bin/autoscale.sh &
#
# AIMD on the CATALOG captcha rate: climb +8 lanes while clean, cut -4 when RockAuto pushes
# back. Additive-increase/multiplicative-ish-decrease converges on the true limit in a
# handful of steps and keeps re-probing as that limit moves through the day, which a
# hand-set lane count cannot. The asymmetry is deliberate: overshooting costs real money
# (a poisoned egress IP is unusable for hours), so back off faster than we climb.
#
# WHY THE SENSOR IS LANE LOGS, NOT A PROBE: bin/gw_health.py probes /robots.txt, which
# RockAuto serves freely even from an egress IP that is already blackholed for catalog
# pages. That sensor is decoupled from the thing that saturates — it once drove weight
# 359 -> 1127 (~845 req/s) while catalog captchas sat at 45% and banked rows were ~0.
# crawl_jsonl prints "reqs=N captchas=M" every 20 nodes; that is the honest signal.
#
# DELTAS ONLY. A relaunched lane resets its counters to 0, so a raw cumulative ratio would
# read a freshly-cycled fleet as 0% forever and open the throttle right at the cliff.
set -u
D=$(ls -d /home/ubuntu/repo /home/ubuntu/rockauto-crawler 2>/dev/null | head -1)
cd "$D" || exit 1

INTERVAL="${INTERVAL:-180}"        # ~38k requests of sample at 210 req/s — plenty
UP="${UP:-8}"                      # additive increase while clean
DOWN="${DOWN:-4}"                  # decrease when past the knee
CLEAN="${CLEAN:-0.03}"             # <3% captcha  -> climb
KNEE="${KNEE:-0.97}"               # captcha rail. 0.08 was sized when a captcha cost a 90s sleep,
                                   # so a high captcha rate really meant wasted lane-time. At
                                   # SP_CAPTCHA_BACKOFF=1 captcha runs 74-84% WHILE output is
                                   # 3.6-4.3x higher — the rail read success as failure and walked
                                   # the fleet 4,163 -> 2,387 lanes. Judge by OUTPUT, not captcha.
MIN_LANES="${MIN_LANES:-16}"
MIN_SAMPLE="${MIN_SAMPLE:-300}"    # too few requests -> no opinion, do not move

# Ceiling from actual memory, not vCPU: settled lane RSS is ~179MB (measured). Swap counts
# for half — it prevents an OOM kill (which is SIGKILL, discarding the in-memory frontier)
# but thrashing is not throughput.
MEM=$(awk '/MemTotal/{print int($2/1024)}' /proc/meminfo)
SWP=$(awk '/SwapTotal/{print int($2/1024)}' /proc/meminfo)
MAX_LANES="${MAX_LANES:-195}"      # was (MEM + SWP/2 - 1200)/190, i.e. 190MB/lane. MEASURED across
                                   # 21 boxes: 62-68MB/lane, so the formula capped every box at ~1/3
                                   # of its real ceiling. 195 with target 185 leaves ~1.8GB headroom;
                                   # above ~230 lanes the boxes swap (us-west-2 hit so=7529 at 252).
[ "$MAX_LANES" -lt "$MIN_LANES" ] && MAX_LANES="$MIN_LANES"

log () { echo "$(date -u '+%F %T') $*" >> "$D/logs/autoscale.log"; }

# Sum the newest reqs=/captchas= pair from every lane log.
counters () {
  local r=0 c=0 pair
  for f in logs/*.log; do
    [ -f "$f" ] || continue
    case "$(basename "$f")" in supervisor.log|unit_supervisor.log|autoscale.log|offsite.log|relay.log|gw_health.log) continue ;; esac
    pair=$(tail -c 4000 "$f" 2>/dev/null | grep -o 'reqs=[0-9]* captchas=[0-9]*' | tail -1)
    [ -z "$pair" ] && continue
    r=$(( r + $(echo "$pair" | sed 's/reqs=\([0-9]*\).*/\1/') ))
    c=$(( c + $(echo "$pair" | sed 's/.*captchas=\([0-9]*\)/\1/') ))
  done
  echo "$r $c"
}

cur=$(cat "$D/target.txt" 2>/dev/null)
case "$cur" in ''|*[!0-9]*) cur=$(ps -eo comm= | grep -c '^python') ;; esac
[ "$cur" -lt "$MIN_LANES" ] && cur="$MIN_LANES"
echo "$cur" > "$D/target.txt"

read -r pr pc < <(counters)
log "autoscale start: target=$cur min=$MIN_LANES max=$MAX_LANES (mem=${MEM}MB swap=${SWP}MB) interval=${INTERVAL}s up=+$UP down=-$DOWN"

settle=1        # skip the first decision after any change so we measure the NEW rate
while true; do
  sleep "$INTERVAL"
  read -r r c < <(counters)
  dr=$(( r - pr )); dc=$(( c - pc ))
  pr=$r; pc=$c

  # A negative delta means lanes cycled and reset their counters. Re-baseline, no opinion.
  if [ "$dr" -lt 0 ] || [ "$dc" -lt 0 ]; then log "counter reset (dr=$dr dc=$dc) — re-baselined"; prev_tp=""; continue; fi
  if [ "$dr" -lt "$MIN_SAMPLE" ]; then log "sample too small (dr=$dr) — holding at $cur"; continue; fi
  tp=$(awk -v r="$dr" -v i="$INTERVAL" 'BEGIN{printf "%.1f", r/i}')   # req/s this window
  if [ "$settle" = "1" ]; then settle=0; log "settling: dr=$dr dc=$dc rate=$(awk -v a=$dc -v b=$dr 'BEGIN{printf "%.3f",a/b}') — holding at $cur"; continue; fi

  rate=$(awk -v a="$dc" -v b="$dr" 'BEGIN{printf "%.4f", a/b}')
  run=$(ps -eo comm= | grep -c '^python')
  new=$cur

  # THROUGHPUT IS THE OBJECTIVE, captcha is only the safety rail. Measured 2026-07-29:
  # the fleet went 362 -> 611 lanes and req/s FELL 107.8 -> ~56, while captcha stayed at
  # 0.6-1.2% and CPU sat 40-60% idle. RockAuto tarpits with latency long before it
  # captchas, so a captcha-only controller reads "clean" and climbs straight past the
  # real optimum. Hill-climb on req/s and let captcha veto.
  if awk -v x="$rate" -v k="$KNEE" 'BEGIN{exit !(x>k)}'; then
    new=$(( cur - DOWN ))                       # safety rail wins outright
    dir=-1
  elif [ "$run" -lt $(( cur - 2 )) ]; then
    log "rate=$rate but running=$run < target=$cur — not climbing (lane launches lagging)"
  else
    # Compare this window's req/s against the previous one. Improved -> keep going the
    # same way; degraded -> reverse. 3% deadband so noise does not cause flapping.
    if [ -n "${prev_tp:-}" ] && awk -v a="$tp" -v b="$prev_tp" 'BEGIN{exit !(b>0)}'; then
      if awk -v a="$tp" -v b="$prev_tp" 'BEGIN{exit !(a < b*0.97)}'; then
        dir=$(( -1 * ${dir:-1} ))               # got worse -> turn around
        log "throughput fell ${prev_tp} -> ${tp} req/s — reversing to dir=$dir"
      fi
    fi
    dir=${dir:-1}
    if [ "$dir" -gt 0 ]; then new=$(( cur + UP )); else new=$(( cur - DOWN )); fi
  fi
  prev_tp="$tp"
  [ "$new" -gt "$MAX_LANES" ] && new="$MAX_LANES"
  [ "$new" -lt "$MIN_LANES" ] && new="$MIN_LANES"

  if [ "$new" != "$cur" ]; then
    echo "$new" > "$D/target.txt"
    log "captcha=$rate (dc=$dc/dr=$dr) running=$run : target $cur -> $new"
    cur=$new; settle=1
  else
    log "captcha=$rate tp=${tp}r/s running=$run : hold at $cur"
  fi
done
