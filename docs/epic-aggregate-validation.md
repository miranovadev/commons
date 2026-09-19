# Epic aggregate validation

The final acceptance gate is PostgreSQL 18 / PHP 8.5 across Core (617 cases),
Full Integration (619), Query Regression (16), and Load / Concurrency (22).
Full includes Core; there are 657 unique mandatory cases. The two complete
natural Domain traces remain unchanged, explicit/manual artifacts.

## First aggregate attempt

Candidate `8faf7ddff531af46a7f418fd1de4d0e558e3d87f` and the PR merge checkout
`61b0c221f9795f033b9a542d0fba91bcf2d1f8be` have identical application trees.
All four jobs tested that tree.

| Suite | Result | PHP duration | Job duration |
| --- | --- | ---: | ---: |
| Core | Cancelled at its 60-minute ceiling; incomplete | No summary | 60m16s |
| Full Integration | 618 passed, 1 failed; 45,510 assertions | 4,749.63s | 80m50s |
| Query Regression | 16 passed; 706 assertions | 303.07s | 5m37s |
| Load / Concurrency | 22 passed; 185 assertions | 59.67s | 1m46s |

Core run: <https://github.com/miranovadev/commons/actions/runs/35432518780>.
Full/Query/Load run: <https://github.com/miranovadev/commons/actions/runs/35432559586>.
The cancelled Core run is **not a pass**. Nor are the older cancelled Stage 5
aggregate runs passes.

Full's sole failure was `HorizonProofMigrationTest`'s legacy backfill setup:
its explicit reverse migration list omitted the new `tragedy_final_attempts`
table, whose foreign key correctly prevents dropping `horizon_tragedies`.
The correction adds that migration to the same reverse/reapply list. No test
assertion, fixture evidence, production migration, or suite membership changes.

Full passed the unchanged natural Tragedy test in **3,660.32 seconds**. This
is much slower than the completed focused PostgreSQL run (216.69 seconds).
Core reached that test after approximately eight minutes and did not finish
it before cancellation. A separate bounded diagnostic preserves the preceding
test order and records request/SQL cost without changing application semantics.
The wider lineage/observable performance effort remains deferred.

Supporting SQLite checks do not replace the PostgreSQL gate: the same test
prefix passed 55 cases / 37,181 assertions in 112.85 seconds (Tragedy 26.54s).
Complete Core passed 614 cases / 45,407 assertions in 252.46 seconds, with
three existing PostgreSQL-only backend guards skipped. The migration correction
also passed its two local cases / 18 assertions.

## Retry and final record

Core and Full now have 120-minute ceilings, based on the completed 80m50s Full
measurement. Query and Load retain 20 minutes. Correctness coverage, statement
timeout, migrations, and production code remain unchanged; only the migration
test's dependency setup is corrected.

Exact retry commits, runs, complete outcomes and diagnostic findings are recorded
in the final checkpoint on <https://github.com/miranovadev/commons/pull/181>.
This document does not claim a green aggregate result in advance.

Review order: #176 → #177 → #178 → #179 → #180 → #181. PR #177 currently uses
`nova/restore-substrate-motor`, the Stage 1 tip mirror, as its base and needs
retargeting as the stack lands. Nothing has been merged or deployed here.
Fresh natural substrate validation follows review, merge and deployment.
