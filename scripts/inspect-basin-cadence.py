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
    ledger = {r["id"]: r for r in table("substrate_ledger_entries")}
    event_sequence = {r["ledgerable_id"]: int(r["sequence"]) for r in ledger.values() if "receipt" in r["ledgerable_type"].lower()}
    receipt_contexts = {r["id"]: r for r in table("participant_receipt_contexts")}
    events = table("receipt_context_events")
    links = table("proposition_receipt_links")
    commit_parents = collections.defaultdict(list)
    for edge in table("supersessions"):
        if edge["type"] == "commit":
            commit_parents[edge["target_proposition_id"]].append(edge["source_proposition_id"])
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

    def reinforced(pid, snapshot, depth, cadence):
        if depth is None or cadence is None:
            return None, "ambiguous interval"
        if snapshot["calculator_version"] == "origin-bootstrap-v1":
            return -1.0, None
        sources, pending = set(), [pid]
        while pending:
            node = pending.pop()
            if node in sources:
                continue
            sources.add(node)
            if propositions[node]["tier"] == "committal":
                pending.extend(commit_parents[node])
        cutoff = snapshot["evaluated_at"]
        linked = set()
        for link in links:
            if link["proposition_id"] in sources:
                if link["created_at"] == cutoff:
                    return None, "ambiguous Receipt-link cutoff"
                if link["created_at"] < cutoff:
                    linked.add(link["receipt_id"])
        sequence = int(ledger[snapshot["ledger_entry_id"]]["sequence"])
        participants = set()
        for context in receipt_contexts.values():
            if context["proposition_id"] not in sources and context["receipt_id"] not in linked:
                continue
            history = [e for e in events if e["participant_receipt_context_id"] == context["id"]]
            if any(e["id"] not in event_sequence for e in history):
                return None, "unledgered Receipt context event"
            prior = [e for e in history if event_sequence[e["id"]] < sequence]
            if prior:
                if max(prior, key=lambda e: event_sequence[e["id"]])["action"] == "loaded":
                    participants.add(context["participant_id"])
            elif not history and context["loaded_at"] <= cutoff:
                return None, "missing historical Receipt context events"
        configuration = json.loads(substrates[snapshot["substrate_id"]]["configuration"] or "{}")
        # Configuration is not versioned in the dump: explicitly conditional output.
        ratio = float(configuration.get("observables", {}).get("contribution_receipt_ratio", 3))
        mass = len(participants) / (ratio if ratio > 0 else 3)
        return round((depth + cadence + mass) / (2 + mass), 7), "assumes dump-time reinforcement ratio"

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
        final_closure, reconstruction_note = reinforced(pid, snapshot, depth, cadence)
        correspondence, chirality = float(snapshot["correspondence"]), float(snapshot["chirality"])
        prominence = None if final_closure is None else round(sum((1 + x) / 2 for x in (correspondence, chirality, final_closure)) / 3, 7)
        turbulence = None if final_closure is None else round(sum((1 - x) / 2 for x in (correspondence, chirality, final_closure)) / 3, 7)
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
            "old_structural_closure": round((float(snapshot["depth"]) + float(snapshot["cadence"])) / 2, 7) if snapshot["depth"] is not None and snapshot["cadence"] is not None else None,
            "proposed_structural_closure": None if cadence is None else round((depth + cadence) / 2, 7),
            "old_final_closure": float(snapshot["closure"]),
            "proposed_final_closure": final_closure,
            "old_tension": float(snapshot["tension"]),
            "proposed_tension": None if prominence is None else round(prominence - turbulence, 7),
            "reinforcement_reconstruction": reconstruction_note,
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
