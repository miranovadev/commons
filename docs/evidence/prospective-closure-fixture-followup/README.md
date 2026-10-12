# Fixture follow-up validation evidence

Results are attributed to the tested code head in `summary.json`, not to the later reporting commit.

- `core-*.csv` and `integration-*.csv`: every executed identity, result, assertion count, duration, failure category, summary and immediate test frame. Discovered, shard-selected and executed multisets are compared exactly, including dataset identities.
- `comparison-*.csv`: every Core identity compared with the reviewed 832/308 checkpoint and the Stage 2 baseline. `comparison-summary.json` explicitly records the two authorised preview-contract renames and the disposition of the original 554 masked cases. A renamed test is not counted as a newly added pass.
- `recovered-cases.csv`: the 55 newly passing identities in one reviewable list. `remaining-setup-families.csv` counts the 246 still-masked cases by test class.
- `setup-path-outcomes.csv`: updates the existing 118-path grouping. The original exact identities and source mapping remain in [the previous inventory](../prospective-closure-fixture-repair/original-554-setup-groups.csv) and its six numbered JSON companions. No initial discovery was repeated.
- `workflows.json`: GitHub Actions heads, run/job identities, outcomes, timings and focused PostgreSQL/SQLite summaries, including incremental validation.

Missing-history errors are classified as fixture/setup boundaries **with their substantive assertions still masked**. This is not a declaration that those assertions would pass, nor proof that production has no remaining defect. See [the report](../../prospective-closure-fixture-followup.md) for the precise construction boundaries.

The speculative extra negative-polarity candidate checked in the second intermediate run was inadmissible and withdrawn. It is absent from the final discovered/executed inventory. The historical scenario remains unchanged. The final Core suite retains 1,140 cases; Integration retains all 31 cases and four shards.
