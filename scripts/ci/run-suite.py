#!/usr/bin/env python3
"""Partition PHPUnit's actual discovery; Composer remains the suite definition."""

import argparse
from collections import Counter
import json
import os
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SUITES = ("core", "integration", "query", "load")


def discover(suite):
    command = ["composer", "--no-ansi", f"test:{suite}", "--", "--list-tests"]
    result = subprocess.run(command, cwd=ROOT, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    tests = re.findall(r"^\s*-\s+(Tests\\[^:]+::.+)$", result.stdout, re.MULTILINE)
    if not tests or len(tests) != len(set(tests)):
        raise RuntimeError(f"Invalid or empty PHPUnit discovery for {suite}: {result.stdout}")
    return sorted(tests)


def partition(tests, weights, count):
    classes = {test.split("::", 1)[0] for test in tests}
    shards = [[] for _ in range(count)]
    totals = [0.0] * count
    for name in sorted(classes, key=lambda name: (-weights.get(name, 1.0), name)):
        index = min(range(count), key=lambda index: (totals[index], index))
        shards[index].append(name)
        totals[index] += weights.get(name, 1.0)
    return shards


def audit(output):
    all_tests = set(discover("all"))
    selections = {suite: discover(suite) for suite in SUITES}
    owners = {}
    for suite, tests in selections.items():
        for test in tests:
            owners.setdefault(test, []).append(suite)
    duplicate = {test: suites for test, suites in owners.items() if len(suites) != 1}
    missing = sorted(all_tests - owners.keys())
    extra = sorted(owners.keys() - all_tests)
    (output / "suite-inventory.json").write_text(json.dumps(selections, indent=2) + "\n")
    if duplicate or missing or extra:
        raise RuntimeError(json.dumps({"duplicate": duplicate, "missing": missing, "extra": extra}, indent=2))
    print("Disjoint, complete PHP coverage:", {suite: len(tests) for suite, tests in selections.items()}, flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("suite", choices=SUITES)
    parser.add_argument("--shards", type=int, default=1)
    parser.add_argument("--shard", type=int, default=1)
    parser.add_argument("--audit", action="store_true")
    parser.add_argument("--plan", action="store_true", help="Discover the shard and publish its runtime dependencies")
    args = parser.parse_args()
    if not 1 <= args.shard <= args.shards:
        parser.error("shard must be between 1 and shards")
    output = ROOT / "test-results"
    output.mkdir(exist_ok=True)
    if args.audit:
        audit(output)
    tests = discover(args.suite)
    (output / f"{args.suite}-{args.shard}-inventory.json").write_text(json.dumps(tests, indent=2) + "\n")
    weights = json.loads((ROOT / "scripts/ci/class-durations.json").read_text())
    if args.suite == "integration":
        weights.update(json.loads((ROOT / "scripts/ci/integration-class-durations.json").read_text()))
    shards = partition(tests, weights, args.shards)
    selected_classes = set(shards[args.shard - 1])
    selected = [test for test in tests if test.split("::", 1)[0] in selected_classes]
    if not selected:
        raise RuntimeError("Empty shard; reduce the shard count")
    prefix = f"{args.suite}-{args.shard}"
    (output / f"{prefix}-selection.json").write_text(json.dumps(selected, indent=2) + "\n")
    print(f"{prefix}: {len(selected)} cases in {len(selected_classes)} classes", flush=True)
    if args.plan:
        # The real websocket smoke test requires the locked pusher-js client.
        # Only its selected shard needs npm; frontend build/tests still run once.
        needs_node = "Tests\\Feature\\ReverbTransportTest" in selected_classes
        if os.environ.get("GITHUB_OUTPUT"):
            with open(os.environ["GITHUB_OUTPUT"], "a") as handle:
                handle.write(f"needs_node={str(needs_node).lower()}\n")
        return 0
    pattern = "/^(?:" + "|".join(re.escape(name) for name in sorted(selected_classes)) + ")::/"
    command = ["composer", "--no-ansi", f"test:{args.suite}", "--", "--filter", pattern,
               "--log-junit", str(output / f"{prefix}.xml"),
               "--log-events-verbose-text", str(output / f"{prefix}-events.log")]
    started = time.monotonic()
    result = subprocess.run(command, cwd=ROOT)
    elapsed = time.monotonic() - started
    (output / f"{prefix}-runtime.json").write_text(json.dumps({
        "suite": args.suite, "shard": args.shard, "shards": args.shards,
        "selected_tests": len(selected), "php_wall_seconds": elapsed, "exit_code": result.returncode,
    }, indent=2) + "\n")
    if (output / f"{prefix}.xml").exists():
        cases = list(ET.parse(output / f"{prefix}.xml").iter("testcase"))
        expected = Counter(selected)
        executed = Counter(case.attrib["class"] + "::" + case.attrib["name"].replace(" with data set ", "") for case in cases)
        if executed != expected or any(case.find("skipped") is not None for case in cases):
            raise RuntimeError("Executed coverage differs from the selected inventory")
    else:
        raise RuntimeError("Missing JUnit report; suite did not reach a final summary")
    return result.returncode


if __name__ == "__main__":
    sys.exit(main())
