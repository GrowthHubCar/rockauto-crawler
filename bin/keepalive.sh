#!/usr/bin/env bash
# Ensure every long-running crawl process is up. Idempotent; safe to run every minute.
# Installed from cron as both "* * * * *" and "@reboot", so the box self-heals after a
# process dies, an OOM kill, or a full reboot — with no operator and no session attached.
#
#   * * * * * /home/ubuntu/repo/bin/keepalive.sh
#   @reboot   /home/ubuntu/repo/bin/keepalive.sh
#
# Everything here is started detached and as `ubuntu`, because a root-owned redirect makes
# the log file unwritable by the lane that has to append to it (that silently killed the
# Bunny backup on three boxes).
set -u
D=/home/ubuntu/repo
cd "$D" || exit 0
export HOME=/home/ubuntu

LANE_FLOOR="${LANE_FLOOR:-40}"
[ -s "$D/target.txt" ] || echo "$LANE_FLOOR" > "$D/target.txt"
mkdir -p out fr logs
chown -R ubuntu:ubuntu "$D" 2>/dev/null

EPS=$(tr -d ' \r\n' < "$D/gw_endpoints.txt" 2>/dev/null)

up () { pgrep -f -- "$1" >/dev/null 2>&1; }

# --- unit supervisor: keeps target.txt lanes busy from the unit queue ---
if ! up 'bin/unit_supervisor.sh'; then
  setsid sudo -u ubuntu env HOME=/home/ubuntu \
    UNITS="$D/units.mine" TARGET="$LANE_FLOOR" SP_GW_ENDPOINTS="$EPS" \
    bash "$D/bin/unit_supervisor.sh" </dev/null >/dev/null 2>&1 &
  echo "$(date -u '+%F %T') restarted unit_supervisor" >> "$D/logs/keepalive.log"
fi

# --- autoscaler: probes the captcha ceiling, owns target.txt ---
# `touch NO_AUTOSCALE` to keep it off. Without this guard keepalive resurrects autoscale within
# 60 s of any manual kill, and autoscale then rewrites target.txt — measured 2026-08-03: a fleet
# deliberately set to 6 lanes/box was back at 16 (392 lanes) one minute later. Autoscale judges by
# CAPTCHA RATE, which is the wrong signal in direct mode where the wall is a silent TCP blackhole.
if [ ! -f "$D/NO_AUTOSCALE" ] && ! up 'bin/autoscale.sh'; then
  setsid sudo -u ubuntu env HOME=/home/ubuntu \
    bash "$D/bin/autoscale.sh" </dev/null >/dev/null 2>&1 &
  echo "$(date -u '+%F %T') restarted autoscale" >> "$D/logs/keepalive.log"
fi

# --- relay: ships finished chunks to S3 ---
if ! up 'bin/relay.sh'; then
  setsid sudo -u ubuntu env HOME=/home/ubuntu \
    BUCKET=rockauto-relay-1785178561 REGION=us-east-1 \
    bash "$D/bin/relay.sh" </dev/null >/dev/null 2>&1 &
  echo "$(date -u '+%F %T') restarted relay" >> "$D/logs/keepalive.log"
fi

# --- offsite: mirrors to Bunny, the ONLY copy outside AWS ---
if ! up 'bin/offsite_backup.sh'; then
  setsid sudo -u ubuntu env HOME=/home/ubuntu INTERVAL=900 \
    bash "$D/bin/offsite_backup.sh" </dev/null >/dev/null 2>&1 &
  echo "$(date -u '+%F %T') restarted offsite_backup" >> "$D/logs/keepalive.log"
fi
