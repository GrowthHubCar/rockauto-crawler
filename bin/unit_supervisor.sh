#!/usr/bin/env bash
# Keep TARGET lanes busy by pulling work from a unit queue, forever.
#   UNITS=units.mine TARGET=60 SP_GW_ENDPOINTS="$(cat gw_endpoints.txt)" \
#     setsid bash bin/unit_supervisor.sh &
#
# WHY THIS EXISTS: lanes are keyed one-per-make and there are only ~196 makes with real
# work, so boxes ran out of things to do and nothing refilled them. Measured 2026-07-29:
# box5 fell 24 -> 10 running lanes and box4 26 -> 18 within an hour while sitting on 14 GB
# of free RAM. Both the old per-make supervisor and the raw launcher relaunch a FIXED list,
# so when that list is exhausted the box just idles.
#
# A unit is a (make x year-band) key from plan/targets.tsv, e.g. freightliner_1995-2004.
# There are 1,668 of them, which both refills the fleet and breaks the ~196-make lane
# ceiling. Each unit gets its own frontier and its own --out, so units never collide.
#
# EXIT CODES: bin/crawl_apigw.py returns 42 when the frontier drains — that unit is
# genuinely finished and must never be relaunched, or the lane re-walks a completed tree
# and banks duplicates (the measured 1.08%-yield failure). Any other exit (--max-seconds,
# exit_reason=blocked) means work remains, so the unit goes back in the queue.
set -u
D=$(ls -d /home/ubuntu/repo /home/ubuntu/rockauto-crawler 2>/dev/null | head -1)
cd "$D" || exit 1

UNITS="${UNITS:?set UNITS to a file with one ukey per line}"
# TARGET is a FLOOR set at launch; bin/autoscale.sh owns target.txt and moves it up and
# down by probing the captcha rate. Re-read every loop so scaling needs no restart.
TARGET_DEFAULT="${TARGET:-40}"
TARGET="$TARGET_DEFAULT"
SECS="${SECS:-43200}"
CHECK="${CHECK:-45}"
EPS="${SP_GW_ENDPOINTS:-}"

mkdir -p out fr logs
log () { echo "$(date -u '+%F %T') $*" >> "$D/logs/unit_supervisor.log"; }

launch () {
  local U="$1"
  local M="${U%_*}" BAND="${U##*_}"
  local LO="${BAND%-*}" HI="${BAND#*-}"
  # Resume from this unit's own progress if it has any, else the pre-seeded target
  # frontier. The seed in plan/fr/ is never written back to, so it stays reusable.
  local FR="plan/fr/f_${U}.ndjson"
  [ -s "fr/f_${U}.ndjson" ] && FR="fr/f_${U}.ndjson"
  [ -s "$FR" ] || return 1                     # no frontier for this unit — skip it
  # ---- TUNING (measured 2026-08-01, do NOT revert) ---------------------------------
  # Every constant below was sized when a CAPTCHA COST A 90s SLEEP. Behind the API Gateway a
  # captcha costs ~1s (every request already egresses from a fresh AWS IP), so each of these
  # rails fired constantly and strangled the fleet. All four are abort/idle thresholds ONLY —
  # a Blocked node is re-queued by crawl_jsonl.py:447 without being marked seen, so raising
  # them skips nothing and loses nothing.
  #   SP_CAPTCHA_BACKOFF 90 -> 1    proxy-quarantine sleep; pure idle here. A/B: 3.61x per lane.
  #   SP_MAX_ATTEMPTS     4 -> 2    ra_client.py:199 says fail fast behind a gateway.
  #   SP_REQUEST_TIMEOUT 15 -> 4    ~1/3 of attempts hung the full 15s on blackholed IPs. A/B: 4.32x.
  #   SP_MAX_CAPTCHAS   500 -> 200k lanes hit 500 in MINUTES at 1s backoff -> abort/relaunch churn;
  #                                 crawl output collapsed 13,432 -> 735 MB/hour until raised.
  #   SP_MAX_BLOCKED     60 -> 100k CONSECUTIVE-block breaker; units 1-11 nodes from done aborted
  #                                 with nodes=0 and could never write fr/done_, freezing progress.
  (
    setsid sudo -u ubuntu env HOME=/home/ubuntu AWS_DEFAULT_REGION=us-east-1 \
      SP_DOWNLOAD_IMAGES=0 SP_SOLVE_CAPTCHA=0 SP_USE_PROXIES=0 \
      SP_MIN_DELAY=0.05 SP_MAX_DELAY=0.15 SP_MAX_ATTEMPTS=2 \
      SP_CAPTCHA_BACKOFF=1 SP_REQUEST_TIMEOUT=4 \
      SP_MAX_CAPTCHAS=200000 SP_MAX_BLOCKED=100000 \
      SP_YEAR_MIN="$LO" SP_YEAR_MAX="$HI" \
      ${EPS:+SP_GW_ENDPOINTS="$EPS"} \
      "$D/venv/bin/python" "$D/bin/crawl_apigw.py" --only-makes "$M" \
        --budget 500000 --max-seconds "$SECS" --no-teardown \
        --out "out/t_${U}_$(date +%s).ndjson" \
        --frontier-file "$FR" --frontier-out "fr/f_${U}.ndjson" \
      </dev/null > "logs/t_${U}.log" 2>&1
    rc=$?
    # 42 = frontier drained. ONLY this means the unit has no work left.
    [ "$rc" = "42" ] && { : > "fr/done_${U}"; echo "$(date -u '+%F %T') $U DONE (rc 42)" >> "$D/logs/unit_supervisor.log"; }
  ) &
  return 0
}

log "unit supervisor start: units=$UNITS target=$TARGET secs=$SECS eps=$(echo "$EPS" | tr ',' '\n' | grep -c .)"
while true; do
  # autoscale.sh writes target.txt; a missing/garbage file falls back to the launch value
  # so a broken autoscaler can never park the fleet at zero lanes.
  T=$(cat "$D/target.txt" 2>/dev/null)
  case "$T" in ''|*[!0-9]*) T="$TARGET_DEFAULT" ;; esac
  TARGET="$T"

  # `ps -eo comm=` counts real python processes. `ps -eo args | grep python` would also
  # match each lane's sudo wrapper and report double — that misread caused two outages.
  running=$(ps -eo comm= | grep -c '^python')

  # Scale DOWN: kill the YOUNGEST lanes first — they have the least frontier depth and the
  # least banked work. SIGTERM is safe: crawl_jsonl installs handlers (:258), breaks at a
  # node boundary (:398) and saves the frontier (:531). Never SIGKILL here.
  if [ "$running" -gt "$TARGET" ]; then
    kills=$(( running - TARGET ))
    ps -eo pid=,etimes=,comm= | awk '$3=="python"{print $2, $1}' | sort -n | head -n "$kills" \
      | awk '{print $2}' | xargs -r kill -TERM 2>/dev/null
    log "scale-down: running=$running target=$TARGET -> SIGTERM $kills youngest"
  fi

  need=$(( TARGET - running ))
  if [ "$need" -gt 0 ]; then
    while read -r U; do
      [ "$need" -le 0 ] && break
      [ -z "$U" ] && continue
      [ -e "fr/done_${U}" ] && continue                                  # drained
      pgrep -f -- "--frontier-out fr/f_${U}.ndjson" >/dev/null 2>&1 && continue   # already running
      if launch "$U"; then
        log "launched $U (running=$running target=$TARGET)"
        need=$(( need - 1 ))
        sleep 2        # stagger so many lanes do not rebuild sessions at once
      fi
    done < "$UNITS"
  fi
  sleep "$CHECK"
done
