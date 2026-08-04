"""Emit one shard's frontier stats as a JSON fragment. Called by frontier_probe.sh.

Reads .eta/_log.txt (a `gh run view --log` dump) and counts:
  live      lanes that seeded or resumed a unit with real work
  pending   sum of that run's unexpanded frontier nodes  <- the remaining-work signal
  drained   distinct units a lane found already finished
  retired   units the run permanently retired this cycle
"""
import os
import re

t = open(".eta/_log.txt", encoding="utf-8", errors="replace").read()
nodes = [int(x) for x in re.findall(r"(?:seeded|resumed) \((\d+) nodes\)", t)]
drained = len(set(re.findall(
    r"\[lane \d+\] ([a-z0-9_.+-]+) COMPLETE \(drained earlier\)", t)))
retired = len(set(re.findall(r"\[frontier\] ([a-z0-9_.+-]+) COMPLETE", t)))
print('    "%s": {"run": "%s", "live": %d, "pending": %d, "drained": %d, "retired": %d}'
      % (os.environ["SHARD"], os.environ.get("RUNID", ""), len(nodes),
         sum(nodes), drained, retired))
