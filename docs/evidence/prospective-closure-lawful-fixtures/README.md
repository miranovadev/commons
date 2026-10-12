# Lawful fixture continuation evidence

Tested head: `111583715db7b16a3ada52baf0f698e40823bf3e`.

See [report](../../prospective-closure-lawful-fixtures.md) for interpretation and limits.

- `summary.json`: exact complete-suite accounting, counts, assertions and per-shard PHP times.
- `core-*.csv`, `integration-*.csv`: every executed identity, result, classification, assertion count and time.
- `comparison-*.csv`, `comparison-summary.json`: comparison to the reviewed 887-pass checkpoint, original 554 masked population and Stage 2 failure membership.
- `recovered-cases.csv`: five original masked cases whose assertions now pass.
- `new-scenarios.csv`: three separate Core cases and one separate Integration case. These do not remove historical tests.
- `integration-comparison.csv`: all 31 original outcomes preserved; one new passing case.
- `setup-path-outcomes.csv`: original 118 setup-path groups, accounting for all 554 original missing-history cases.
- `remaining-setup-families.csv`: 241 masked Core cases grouped by class; exact identities remain in the main inventories.
- `lawful-commit-history.json`: actual successful action transcript and preserved source/snapshot state from final Integration, not a seed or fabricated historical fixture.
- `workflows.json`: exact workflow heads, timestamps, focused database results and intermediate complete-run metadata.

No cancelled run contributes to complete-suite evidence. Focused selections overlap suite populations and must not be added to their totals. Missing-history errors mask substantive assertions; zero confirmed new defects is not full regression sign-off.
