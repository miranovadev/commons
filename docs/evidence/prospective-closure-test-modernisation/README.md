# Validation evidence

Tested code: `661d52ab7d8061595107d3ca0eaa6e6df85e8d12`.

- `summary.json`: complete suite counts, failure categories, exact discovered/selected/executed multiset equality, measured PHP shard times.
- `core-N-XX.csv` and `integration-N-XX.csv`: every executed case, outcome, classification, diagnostic summary, assertion count and time.
- `*-added.csv`, `*-removed.csv`, `*-recovered.csv`: exact changes from the `a6a809f` tested checkpoint; include the recorded Stage 2 outcome. A recovered component case is not end-to-end lifecycle evidence.
- `*-remaining-failures.csv`: every remaining failure with the actual message and baseline status. Full diagnostic payloads remain in the linked Actions artifacts.
- `checkpoint-masked-accounting.csv` and `coverage-summary.json`: disposition of all 234 checkpoint missing-history errors and the explicitly labelled component group.
- `comparison-summary.json`: aggregate identity comparison, including retained passes and deliberate consolidation.
- `focused-results.json`: PostgreSQL/SQLite job identities and exact test-runner summaries.
- `workflows.json`: final workflow statuses and timestamps; failures are assertion outcomes, not timeouts.
- `lawful-commit-history.json`: actual 89-action transcript with one verified Reconcile and the preserved source state/snapshot.

Coverage meaning, replacements, exclusions and review limits are explained in [the report](../../prospective-closure-test-modernisation.md). Counts do not turn authored coordinate inputs into lawful committed histories.
