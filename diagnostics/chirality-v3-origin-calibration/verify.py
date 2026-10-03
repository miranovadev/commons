#!/usr/bin/env python3
"""Verify the Origin-calibration C1 reachability calculation from prior diagnostic exports."""
import csv, json
from collections import defaultdict, deque
from pathlib import Path

HERE = Path(__file__).resolve().parent
MATURITY = HERE.parent / "chirality-v3-maturity"

morphologies = json.loads((MATURITY / "actual-commit-morphologies.json").read_text())
m = morphologies[0]
head = m["head"]
parents = defaultdict(list)
tiers = {n["id"]: n["tier"] for n in m["nodes"]}
for edge in m["edges"]:
    parents[edge["target"]].append(edge["source"])

# Match production LineageMeasurementService::positions(): reverse BFS, but do not walk beyond
# a non-head Committal.
distance = {head: 0}
q = deque([head])
while q:
    node = q.popleft()
    d = distance[node]
    if d > 0 and tiers[node] == "committal":
        continue
    for parent in parents[node]:
        if parent not in distance:
            distance[parent] = d + 1
            q.append(parent)

with (MATURITY / "prospective-commits.csv").open() as fh:
    rows = list(csv.DictReader(fh))
row = next(r for r in rows if r["origin_label"] == "support" and r["actual_commit"] == "True")
correspondence = float(row["correspondence"])
closure = float(row["closure"])

mass = 0.0
numerator = 0.0
ordinary_local_max = 0.75
origin_local = -0.5
origin = max(distance, key=distance.get)
for node, d in distance.items():
    w = 1 / (1 + d) ** 2
    mass += w
    numerator += w * (origin_local if node == origin else ordinary_local_max)

xmax = numerator / mass
tmax = (correspondence + xmax + closure) / 3

# Solve for a common ordinary-position Cadence k with Orientation fixed at +0.5.
# local chi = (0.5 + k)/2 = 0.25 + 0.5k
origin_weight = 1 / (1 + distance[origin]) ** 2
ordinary_mass = mass - origin_weight
required_x = -(correspondence + closure)
# required_x = [ordinary_mass*(.25+.5k) + origin_weight*(-.5)] / mass
k = ((required_x * mass + 0.5 * origin_weight) / ordinary_mass - 0.25) / 0.5

out = {
    "distances": dict(sorted(distance.items(), key=lambda x: (x[1], x[0]))),
    "mass": mass,
    "correspondence": correspondence,
    "closure": closure,
    "required_attenuated_chirality": required_x,
    "maximum_attenuated_chirality": xmax,
    "maximum_tension": tmax,
    "common_cadence_zero_tension_threshold": k,
    "reachable": tmax > 0,
}
print(json.dumps(out, indent=2))
assert xmax > required_x
assert tmax > 0
assert -1 <= k <= 1
