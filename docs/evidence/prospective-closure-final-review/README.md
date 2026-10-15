# Final Stage 3 validation evidence

Tested code: `9bc9e8576dc9c28d0f52d766193304a22f614d31`.

- `summary.json`: complete Core/Integration counts, PHP shard times, exact discovered/selected/executed accounting and comparison with reviewed `f5a8b8d` evidence.
- `*-results-*.csv`: every executed case, outcome, assertions and runtime. Failure text is bounded to 3,000 characters; complete JUnit diagnostics remain in the linked workflow artifacts.
- `*-failures-*.csv`: all remaining failures, classified without changing historical populations or authority.
- `*-comparison-*.csv`: exact checkpoint identity comparison. Four removed names map to the four replacement names documented in the final report; one genuinely new migration contract is separate. No previously passing identity regressed.
- `focused-results.json`: completed PostgreSQL/SQLite selections, with actual job/log summaries. The two SQLite skips are existing PostgreSQL-only checks, not complete-suite exclusions.
- `workflows.json`: completed final runs versus superseded/cancelled runs, plus actual Core/Integration job windows. Core elapsed includes 1m23s concurrency queueing; its 10m12s total is explicitly reported rather than hidden by the 8m49s execution window.
- `production-conformance.json`: reviewed production blob identities; no production edits in this pass.

The report is [here](../../prospective-closure-final-review.md). Earlier coverage consolidations and the 89-action transcript remain in [the checkpoint evidence](../prospective-closure-test-modernisation). Final Integration repeated 89 actions and one real Reconcile, with identical Closure values and one additional successful source-polarity assertion. Its fresh transcript is also in the final `php-integration-4-results` Actions artifact.

Core and Integration are complete but not green: one historical Core failure and eleven documented Integration trajectories remain. No full-green or exhaustive lifecycle claim is made.
