#!/usr/bin/env python3
"""Require every discovered Integration case exactly once, even on assertion failures."""

import json
import os
import sys
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path

root = Path(sys.argv[1])
shards = int(sys.argv[2])
expected = None
selected = []
executed = []
failures = []
skipped = []
assertions = 0
runtimes = []
for shard in range(1, shards + 1):
    prefix = root / f"integration-{shard}"
    inventory = json.loads(Path(f"{prefix}-inventory.json").read_text())
    if expected is None:
        expected = inventory
    if inventory != expected:
        raise RuntimeError("Shard discovery inventories disagree")
    selected.extend(json.loads(Path(f"{prefix}-selection.json").read_text()))
    runtimes.append(json.loads(Path(f"{prefix}-runtime.json").read_text()))
    for case in ET.parse(f"{prefix}.xml").iter("testcase"):
        name = case.attrib["class"] + "::" + case.attrib["name"].replace(" with data set ", "")
        executed.append(name)
        assertions += int(case.attrib.get("assertions", 0))
        if case.find("skipped") is not None:
            skipped.append(name)
        for kind in ("failure", "error"):
            if case.find(kind) is not None:
                failures.append({"test": name, "kind": kind})
                break

complete = Counter(expected) == Counter(selected) == Counter(executed) and not skipped
summary = {
    "complete": complete, "discovered": len(expected), "executed": len(executed),
    "passed": len(executed) - len(failures) - len(skipped),
    "failed": len(failures), "skipped": skipped, "assertions": assertions,
    "failures": failures, "runtimes": runtimes,
}
(root / "integration-summary.json").write_text(json.dumps(summary, indent=2) + "\n")
print(json.dumps(summary, indent=2))
if os.environ.get("GITHUB_STEP_SUMMARY"):
    with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as handle:
        handle.write(f"Integration: {summary['passed']} passed, {len(failures)} failed; "
                     f"{len(executed)}/{len(expected)} cases, {assertions} assertions. "
                     f"Complete coverage: {complete}.\n")
if not complete:
    raise RuntimeError("Incomplete Integration coverage: missing, duplicate, skipped or undiscovered cases")
sys.exit(1 if failures or any(item["exit_code"] != 0 for item in runtimes) else 0)
