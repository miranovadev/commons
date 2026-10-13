# Authorised history continuation evidence

Tested head: `a6a809fa260b01366065f02e021a744f02688169`.

See [report](../../prospective-closure-history-continuation.md) for acceptance criteria and limits.

- `summary.json`: exact complete-suite identity accounting, counts, assertions and shard PHP times.
- `core-*.csv` and `integration-*.csv`: every executed identity, outcome, classification, assertion count and runtime.
- `comparison-*.csv`, `comparison-summary.json`: all 895 checkpoint passes preserved; seven original recoveries; original 554 dispositions; Stage 2 membership.
- `recovered-cases.csv`: five original Tragedy datasets and two original Search contracts.
- `new-scenarios.csv`: one separately named Integration scenario; it does not remove or recover the older multi-Commit/foreign-Horizon scenarios.
- `integration-comparison.csv`: all 32 checkpoint outcomes unchanged, plus one new pass.
- `setup-path-outcomes.csv`: all 118 original setup-path groups, accounting for all original 554 masked cases.
- `remaining-setup-families.csv`: the remaining 234 masked Core cases by class.
- `second-commit-attempt.json`: actual failed SQLite/PostgreSQL continuation traces and last eligibility result. This is not evidence that second Commits are impossible.
- `unproven-second-commit-attempt.php.txt`: exact attempted scenario at `05e1c8a`, retained as failed construction evidence outside test discovery. No original test was removed.
- `workflows.json`: exact final workflow heads/times and focused results; explicit intermediate attempt outcomes.

No superseded/cancelled run supplies complete-suite evidence. Focused selections overlap complete-suite identities. Production semantics are unchanged. Still-masked assertions prevent full regression sign-off.
