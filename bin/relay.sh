#!/bin/bash
# Ship finished crawl chunks to S3.  bash bin/relay.sh &
#
# WHY THIS EXISTS AS A FILE: the relay used to be an inline loop inside
# launch_fleet.sh. deploy_v2.sh relaunched the crawl lanes but not that loop, so the
# relay silently stopped while the crawl kept running — 805 files / 496 MB stranded on
# ONE box (0 .sent markers), and S3 live/ sat flat at 186 MB for hours while everything
# upstream looked healthy. A dead uploader is indistinguishable from a dead crawler if
# you only watch S3, which is exactly how it went unnoticed. Own it as a script that
# can be checked with pgrep and restarted on its own.
#
# SAFETY: a lane writes its current chunk with mode "w". Renaming that file out from
# under it would send every subsequent row to a .sent file nothing ever uploads. So a
# file is shipped only when no process holds it open — checked against /proc, which is
# exact, rather than an mtime guess (a captcha-stalled lane can leave its open chunk
# untouched for minutes and would trip any timeout heuristic).
set -u
BUCKET="${BUCKET:-rockauto-relay-1785178561}"
REGION="${REGION:-us-east-1}"
D=$(ls -d /home/ubuntu/rockauto-crawler /home/ubuntu/repo 2>/dev/null | head -1)
cd "$D" || exit 1
AWS="$D/venv/bin/aws"
B=$(hostname)

while true; do
  # snapshot every .ndjson currently held open by any process, once per pass
  OPEN=$(ls -l /proc/*/fd/ 2>/dev/null | grep -oE '/[^ ]*\.ndjson' | sort -u)

  # Match EVERY chunk, not out/u_*. Lanes are launched by make, so they write
  # out/<make>_<epoch>.ndjson (and out/ab_<make>_<ts>.ndjson) — nothing has ever been
  # named u_*, so this loop shipped exactly zero files while looking perfectly healthy:
  # 62 chunks / 2.8 GB stranded on one box with 0 .sent markers. The open-fd guard below
  # is what makes the wide glob safe.
  for f in out/*.ndjson; do
    [ -e "$f" ] || continue
    [ -s "$f" ] || continue
    case "$OPEN" in *"$(basename "$f")"*) continue ;; esac   # lane still writing
    if gzip -c "$f" > /tmp/u.gz 2>/dev/null &&
       "$AWS" s3 cp /tmp/u.gz "s3://$BUCKET/live/$B/$(basename "$f").gz" \
              --region "$REGION" --only-show-errors 2>/dev/null; then
      mv "$f" "$f.sent"
    fi
  done
  sleep 20
done
