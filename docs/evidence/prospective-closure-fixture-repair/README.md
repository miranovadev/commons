# Fixture repair evidence

Tested code head: `117e657c456d3f7856903458075507b6bd5cd141`.
See [the report](../../prospective-closure-fixture-repair.md) for interpretation.

- `summary.json`: complete Core and Integration totals, classification and shard times.
- `core-*.csv`, `integration-*.csv`: every executed case, result, classification, immediate failure, test frame, assertions and time. These files contain 1,140 and 31 unique cases respectively.
- `comparison-*.csv`: each Core case compared with the pre-repair Stage 3 run; the last column also identifies the eight Stage 2 failures.
- `comparison-summary.json`: formerly masked passes, exposed failures and still-masked cases.
- `original-554-setup-groups.csv` and six numbered JSON files: 118 construction paths and all original 554 missing-state cases, derived from their actual stacks.
- `setup-path-outcomes.csv`: pass/exposed/still-masked outcomes for each original path.
- `polarity-commit-eligibility.json`: the newly exposed source-polarity fixture's real Closure disagreement, reproduced on both databases.
- `workflows.json`: complete run IDs, head, status and timing metadata.

A failure category is not a passing assertion. Missing-history errors still mask
the intended application contract; exact suite accounting does not imply that
regression validation is green. Focused commands overlap these suite inventories.
