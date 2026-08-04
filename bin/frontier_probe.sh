#!/usr/bin/env bash
# frontier_probe.sh — measure REMAINING crawl work, not throughput.
#
# Every ETA this project produced divided a measured leaves/hour by an INFERRED
# remaining-work figure, so the answers swung from 3.6h to 15h on the same fleet.
# The crawler already prints its own pending frontier depth each run
# ("<unit> seeded (N nodes)" / "resumed (N nodes)"); this sums that across all
# eight shards. Two readings an hour apart give a drain rate, and pending/drain
# is an ETA where BOTH halves are measured.
#
#   bin/frontier_probe.sh            # one reading -> .eta/reading-<ts>.json
#   bin/frontier_probe.sh --compare  # diff the two most recent readings
set -uo pipefail
cd "$(dirname "$0")/.."
export PATH="$PATH:/c/Program Files/GitHub CLI"
mkdir -p .eta

# A `while read` loop over a heredoc loses shards: gh reads from the SAME stdin and
# swallows the remaining lines, so only the first and last survived. Use an array.
SHARDS=(
  "ahmerfr/rockauto-crawler:ahmerfr"
  "Affilusion/rockauto-crawler:ahmerfr"
  "haseeb-shoukat2029/rockauto-crawler:haseeb-shoukat2029"
  "Nexarce/rockauto-crawler:haseeb-shoukat2029"
  "arbyahad/rockauto-crawler:arbyahad"
  "GrowthHubCar/rockauto-crawler:arbyahad"
  "artechonza/rockauto-crawler:artechonza"
  "Techonza/rockauto-crawler:artechonza"
)

if [ "${1:-}" = "--compare" ]; then
  python bin/frontier_compare.py
  exit $?
fi

TS=$(date -u +%Y%m%dT%H%M%SZ)
OUT=".eta/reading-$TS.json"
echo "{" > "$OUT"
echo "  \"ts\": \"$TS\"," >> "$OUT"
echo "  \"shards\": {" >> "$OUT"
FIRST=1
for spec in "${SHARDS[@]}"; do
  R="${spec%%:*}"; U="${spec##*:}"
  gh auth switch --user "$U" >/dev/null 2>&1 </dev/null
  RID=$(gh run list --repo "$R" --workflow crawl.yml --limit 6 \
        --json databaseId,status \
        --jq '[.[]|select(.status=="completed")][0].databaseId' 2>/dev/null </dev/null)
  [ -z "$RID" ] && { echo "  !! $R: no completed run" >&2; continue; }
  gh run view "$RID" --repo "$R" --log 2>/dev/null </dev/null > .eta/_log.txt
  # An empty/truncated log yields pending=0 and would read as "finished" — skip it
  # rather than record a false zero.
  if [ ! -s .eta/_log.txt ]; then echo "  !! $R: empty log for $RID" >&2; continue; fi
  [ "$FIRST" -eq 0 ] && echo "    ," >> "$OUT"
  FIRST=0
  SHARD="${R%%/*}" RUNID="$RID" python bin/frontier_parse.py >> "$OUT"
done
echo "  }" >> "$OUT"
echo "}" >> "$OUT"
rm -f .eta/_log.txt
echo "wrote $OUT"
python bin/frontier_compare.py
