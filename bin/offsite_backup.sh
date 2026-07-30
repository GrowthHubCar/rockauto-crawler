#!/usr/bin/env bash
# Ship crawl output OFF AWS, every hour.  setsid bash bin/offsite_backup.sh &
#
# WHY NOT S3: the relay bucket lives in the same AWS account as the crawl boxes. If that
# account is blocked — which has already happened once on this project — the boxes AND
# the backup vanish together. A backup that shares a failure domain with the thing it
# protects is not a backup. Bunny is a separate provider, already paid for and already
# working (244k part images went through it), so it costs nothing extra and survives an
# AWS suspension.
#
# Incremental and idempotent: shipped files are recorded in .offsite_state, so an
# interrupted run resumes and re-running ships nothing twice. Bunny PUT overwrites, so
# a duplicate upload is harmless anyway.
#
# Only closed files are shipped: a file still held open by a crawl lane is skipped via a
# /proc scan (same guard as bin/relay.sh). Shipping a half-written NDJSON would store a
# truncated final line.
set -u
D=$(ls -d /home/ubuntu/repo /home/ubuntu/rockauto-crawler 2>/dev/null | head -1)
cd "$D" || exit 1

STATE="$D/.offsite_state"
INTERVAL="${INTERVAL:-3600}"
HOSTTAG=$(hostname)
touch "$STATE"

# .bunny.env is authored on Windows, so every value carries a trailing CR. Sourcing it
# raw put a \r inside the URL and inside the AccessKey header — curl then reported
# http=000 "URL using bad/illegal format", which looks like a network failure but is a
# line-ending bug. Strip CR before sourcing.
tr -d '\r' < "$D/.bunny.env" > /tmp/.bunny.env.clean 2>/dev/null
# shellcheck disable=SC1091
set -a; . /tmp/.bunny.env.clean 2>/dev/null; set +a
rm -f /tmp/.bunny.env.clean
ZONE="${BUNNY_STORAGE_ZONE:?missing BUNNY_STORAGE_ZONE}"
KEY="${BUNNY_STORAGE_KEY:?missing BUNNY_STORAGE_KEY}"
HOST=$(echo "${BUNNY_STORAGE_HOST:?missing BUNNY_STORAGE_HOST}" | sed 's#https\?://##; s#/$##')

log () { echo "$(date -u '+%F %T') $*" >> "$D/logs/offsite.log"; }

ship_once () {
  local sent=0 bytes=0
  # snapshot files currently held open by any process, once per pass
  local OPEN
  OPEN=$(ls -l /proc/*/fd/ 2>/dev/null | grep -oE '/[^ ]*\.ndjson' | sort -u)

  # Also match *.ndjson.sent: bin/relay.sh renames a chunk the moment it reaches S3, and
  # a glob of only *.ndjson would stop seeing it right then — freezing the offsite copy
  # at whatever partial snapshot happened to be taken while the lane still held it open.
  # The state key strips .sent so the pre- and post-rename file are the same object and
  # the closed copy simply overwrites the open snapshot.
  for f in out/*.ndjson out/*.ndjson.sent; do
    [ -e "$f" ] || continue
    [ -s "$f" ] || continue
    local base; base=$(basename "$f"); base=${base%.sent}
    grep -qxF "$base" "$STATE" && continue                      # closed AND already shipped

    # A lane holds ONE file open for its entire 12h session. Skipping open files (the
    # rule bin/relay.sh uses, where chunks close every few minutes) would mean shipping
    # nothing for 12 hours — useless as hourly protection. So open files ARE shipped, as
    # a snapshot that overwrites last hour's copy, and are NOT recorded as done until the
    # lane closes them. Worst case the snapshot ends mid-line; the loader skips malformed
    # lines, and the next hour's copy supersedes it anyway.
    local is_open=0
    case "$OPEN" in *"$base"*) is_open=1 ;; esac

    gzip -c "$f" > /tmp/offsite.gz 2>/dev/null || continue
    local sz; sz=$(stat -c%s /tmp/offsite.gz 2>/dev/null || echo 0)
    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' -X PUT \
             --data-binary @/tmp/offsite.gz \
             -H "AccessKey: $KEY" \
             -H "Content-Type: application/octet-stream" \
             "https://$HOST/$ZONE/crawl-backup/$HOSTTAG/$base.gz" 2>/dev/null)
    if [ "$code" = "201" ] || [ "$code" = "200" ]; then
      # Only mark done when the lane has released the file — otherwise we would stop
      # re-shipping a file that is still growing and freeze the backup at hour one.
      [ "$is_open" = "0" ] && echo "$base" >> "$STATE"
      sent=$((sent+1)); bytes=$((bytes+sz))
    else
      log "FAILED $base http=$code"
    fi
    rm -f /tmp/offsite.gz
  done
  log "shipped=$sent bytes=$bytes total_tracked=$(wc -l < "$STATE")"
}

mkdir -p "$D/logs"
log "offsite backup started (interval ${INTERVAL}s, zone=$ZONE, host=$HOSTTAG)"
while true; do
  ship_once
  sleep "$INTERVAL"
done
