#!/bin/sh
# qualify.sh — is this box usable for the RockAuto crawl? Answer in <5 min. curl + awk only.
#
#   ssh box 'sh -s' < bin/qualify.sh                 # default: 900 reqs, 6 lanes, 240s cap
#   N=900 C=6 ssh box 'sh -s' < bin/qualify.sh       # count-vs-rate pass A (fast)
#   N=120 C=1 PACE=2 ssh box 'sh -s' < bin/qualify.sh# pass B (slow) — compare where the wall fell
#
# WHY IT LOOKS LIKE THIS (each phase exists because a real misdiagnosis cost us a provider):
#  P0 CONTROL   a box with no egress (missing NAT / default-deny firewall) fails EVERY target.
#               Without this line you read your own VPC bug as "RockAuto blocks this provider".
#  P2 LEAF      nav pages (/en/catalog/acura) keep returning 200 while the IP is walled.
#               Only leaf pages 302 to /captcha/. Qualifying on a nav page qualifies nothing.
#  P3 CURVE     logs every request so the wall shows up in BOTH dimensions (request number and
#               elapsed seconds). Same request number at both paces = COUNT wall. Same elapsed
#               seconds = RATE wall, and a paced box runs forever.
#
# EXIT: 0 qualified | 1 walled/blackholed | 3 box broken (NOT a RockAuto verdict)
set -u

LEAF=${LEAF:-'https://www.rockauto.com/en/catalog/ac,1947,two-litre,2.0l+122cid+l6,1486554,cooling+system,coolant+/+antifreeze,11393'}
N=${N:-900}          # total requests. >700 on purpose: that is the previously measured AWS wall
C=${C:-6}            # lanes. 6/box measured optimal; more lanes go SLOWER once an egress is walled
PACE=${PACE:-0}      # seconds between requests inside a lane (the variable under test)
DEADLINE=${DEADLINE:-240}
UA=${UA:-'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36'}
# Real catalog content, straight out of ra_client.py's _CATALOG_MARKER_RE. A walled page is a
# stub without these; a 200 alone proves nothing.
MARK=${MARK:-'ranavnode\|navlabellink\|treeroot\|html_fill_sections\|listing-\|nchildren'}
D=$(mktemp -d) || exit 3
trap 'rm -rf "$D"' EXIT

hit() { curl -sS --compressed -o /dev/null --max-time 15 -A "$UA" \
             -w '%{http_code} %{size_download} %{time_total}' "$1" 2>/dev/null; }
hitbody() { curl -sS --compressed -o "$D/body" --max-time 15 -A "$UA" \
                 -w '%{http_code} %{size_download} %{time_total}' "$1" 2>/dev/null; }

echo "== P0 control (proves the BOX has egress, so a VPC bug can never read as a RockAuto block)"
ctl=$(hit https://example.com/)
echo "   example.com -> ${ctl:-no-answer}"
case "$ctl" in
  200\ *) ;;
  *) echo "   VERDICT: BOX-BROKEN — no egress. This says NOTHING about RockAuto. Fix NAT/firewall."
     exit 3 ;;
esac

echo "== P1 identity"
ip=$(curl -sS --max-time 10 https://api.ipify.org 2>/dev/null)
echo "   egress IP : ${ip:-unknown}"
echo "   network   : $(curl -sS --max-time 10 "https://ipinfo.io/${ip}/org" 2>/dev/null)"

echo "== P2 single leaf (nav pages lie; leaves do not)"
one=$(hitbody "$LEAF"); echo "   leaf -> ${one:-no-answer}"
code=${one%% *}; rest=${one#* }; size=${rest%% *}
case "$code" in
  200) grep -q "$MARK" "$D/body" || {
         echo "   VERDICT: WALLED — 200/${size}B but no catalog markers (stub or interstitial)."; exit 1; } ;;
  000) echo "   VERDICT: L4-BLACKHOLE — SYN dropped, DNS fine, control OK. This IP/prefix is burned."; exit 1 ;;
  30*) echo "   VERDICT: CAPTCHA-WALL — 302 to /captcha/. This IP is walled."; exit 1 ;;
  *)   echo "   VERDICT: UNEXPECTED HTTP $code."; exit 1 ;;
esac

echo "== P3 wall curve: N=$N C=$C PACE=${PACE}s deadline=${DEADLINE}s"
t0=$(date +%s); end=$((t0 + DEADLINE)); per=$(( N / C )); [ "$per" -lt 1 ] && per=1
i=0
while [ "$i" -lt "$C" ]; do
  ( n=0
    while [ "$n" -lt "$per" ] && [ "$(date +%s)" -lt "$end" ]; do
      r=$(hit "$LEAF")
      echo "$(date +%s) $r" >> "$D/lane.$i"
      case "${r%% *}" in 200) ;; *) break ;; esac   # stop this lane at the wall — that IS the datum
      n=$((n + 1)); [ "$PACE" != "0" ] && sleep "$PACE"
    done ) &
  i=$((i + 1))
done
wait
t1=$(date +%s); el=$((t1 - t0)); [ "$el" -lt 1 ] && el=1

sort -n "$D"/lane.* > "$D/all" 2>/dev/null || : > "$D/all"
# Floor calibrated off the page P2 actually got, so no magic constant rots when RockAuto
# changes page weight. ponytail: a quarter of a known-good page is unambiguously not a stub.
floor=$(( size / 4 ))
awk -v t0="$t0" -v el="$el" -v floor="$floor" -v out="$D/bad" '
  { n++; if ($2 == 200 && $3 > floor) ok++;
    else if (!bad) { bad = n; badt = $1 - t0; badcode = $2 } }
  END {
    printf "   requests   = %d\n   ok         = %d\n   elapsed    = %ds\n   sustained  = %.2f req/s\n",
           n, ok, el, ok / el
    if (bad) printf "   WALL       = request #%d at t=%ds (code %s)\n", bad, badt, badcode
    else     printf "   WALL       = none in %d requests / %ds\n", n, el
    print bad + 0 > out
  }' "$D/all"

if [ "$(cat "$D/bad" 2>/dev/null || echo 1)" = "0" ]; then
  echo "   VERDICT: QUALIFIED — no wall. Deploy here."
  echo "   NEXT: rerun with  N=120 C=1 PACE=2  — same request number = COUNT wall (IPs are the lever),"
  echo "         same elapsed seconds = RATE wall (pace one box under it and it runs forever)."
  exit 0
fi
echo "   VERDICT: WALLED — see request/elapsed pair above, then rerun at PACE=2 to classify it."
exit 1
