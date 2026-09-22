#!/usr/bin/env python3
"""Read-only diagnostic for a PostgreSQL plain-text Commons dump.

Historical timestamps supply ordering only, never interval duration. Tied
completion/Commit timestamps are reported as ambiguous, not silently ordered.
No database connection or write is performed, and proposition text is omitted.
"""

import argparse
import collections
import json
import re
from pathlib import Path


def basin_interval(basin, producer, committed_at):
    """Count completed participation before Commit, plus its producer once."""
    if producer["governor"] is None:
        raise ValueError("An ordinary transition requires a governing Committal")
    completed = [
        c for c in basin
        if c["governor"] == producer["governor"]
        and c["id"] != producer["id"] and c["completed_at"] is not None
    ]
    return {
        "count": 1 + sum(c["completed_at"] < committed_at for c in completed),
        "ambiguous_ties": sum(c["completed_at"] == committed_at for c in completed),
    }


def inspect(path):
    sql = Path(path).read_text()

    def table(name):
        match = re.search(
            rf"COPY public\.{name} \((.*?)\) FROM stdin;\n(.*?)\n\\\.",
            sql,
            re.S,
        )
        if match is None:
            raise ValueError(f"Missing COPY table {name}")
        columns = match[1].split(", ")
        return [
            dict(zip(columns, (None if v == r"\N" else v for v in line.split("\t"))))
            for line in match[2].splitlines()
        ]

    cycles = {r["id"]: r for r in table("cycles")}
    phases = {r["id"]: r for r in table("phases")}
    propositions = {r["id"]: r for r in table("propositions")}
    snapshots = {r["proposition_id"]: r for r in table("commitment_snapshots")}
    horizons = {r["id"]: r for r in table("context_horizons")}
    substrates = {r["id"]: r for r in table("substrates")}
    entries = collections.defaultdict(list)
    basins = collections.defaultdict(list)
    for phase in phases.values():
        entries[phase["cycle_id"]].append(phase)

    def cycle_of(proposition_id):
        return cycles[phases[propositions[proposition_id]["phase_id"]]["cycle_id"]]

    origins = {
        cycle_of(pid)["id"]
        for pid, snapshot in snapshots.items()
        if snapshot["calculator_version"] == "origin-bootstrap-v1"
    }
    for cycle in cycles.values():
        if cycle["id"] in origins:
            cycle["governor"] = None
            continue
        entry = min(entries[cycle["id"]], key=lambda p: (int(p["kind"]), p["id"]))
        governor = entry["gate_proposition_id"]
        if entry["kind"] != "1" or governor not in snapshots:
            raise ValueError(f"Unresolved entry for Cycle {cycle['id']}")
        cycle["governor"] = governor
        basins[governor].append(cycle)

    intervals = {}
    for pid, snapshot in snapshots.items():
        cycle = cycle_of(pid)
        if cycle["governor"] is None:
            continue
        intervals[pid] = basin_interval(
            basins[cycle["governor"]], cycle, snapshot["evaluated_at"]
        )

    def similarity(a, b):
        return round(1 - 2 * abs(a - b) / max(a, b), 7) if max(a, b) else 1.0

    results = []
    for pid, snapshot in snapshots.items():
        lineage = []
        cursor = pid
        while cursor is not None:
            if cursor in lineage:
                raise ValueError("Cyclic governing history")
            lineage.append(cursor)
            cursor = cycle_of(cursor)["governor"]
        lineage.reverse()
        if len(lineage) == 1:
            depth = cadence = -1.0
        elif len(lineage) == 2:
            depth = cadence = 0.0
        else:
            runs = []
            for ancestor in lineage:
                domain = horizons[cycle_of(ancestor)["context_horizon_id"]]["domain"]
                if runs and runs[-1][0] == domain:
                    runs[-1][1] += 1
                else:
                    runs.append([domain, 1])
            if len(runs) < 2:
                raise ValueError("No preceding opposite-Domain run")
            depth = similarity(runs[-1][1], runs[-2][1])
            latest, previous = intervals[pid], intervals[lineage[-2]]
            cadence = None if latest["ambiguous_ties"] or previous["ambiguous_ties"] else similarity(latest["count"], previous["count"])
        results.append({
            "substrate": substrates[snapshot["substrate_id"]]["title"],
            "commit": pid,
            "governor": cycle_of(pid)["governor"],
            "ancestral_commits": len(lineage),
            "interval": intervals.get(pid),
            "old_depth": float(snapshot["depth"]) if snapshot["depth"] is not None else None,
            "old_cadence": float(snapshot["cadence"]) if snapshot["cadence"] is not None else None,
            "proposed_depth": depth,
            "proposed_cadence": cadence,
        })
    return {
        "ordinary_cycles": len(cycles) - len(origins),
        "origin_cycles": len(origins),
        "commits": results,
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dump", type=Path)
    args = parser.parse_args()
    print(json.dumps(inspect(args.dump), indent=2))
