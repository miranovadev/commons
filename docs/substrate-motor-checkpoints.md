# Substrate motor: review checkpoints

Stage 0 investigated production exports and current implementation at
`170668a027d4b2b8c29051c66eebaf7e3054defd`. Stage 1 only separates test execution
and Actions; [testing.md](testing.md) records suite membership and invocation.
Stages 1 and 2 were reviewed and approved. Stage 3 is authorized for exact local
exploration epochs and authoritative current spentness. Backward-lineage recovery
and broader lifecycle corrections remain later review boundaries.

The next approved design constraints are:

1. Stage 2 must make the successful-route revisit / Bias dead end one of its
   first explicit red HTTP participation regressions. Revisit a route that has
   already reached Commit and reproduce the externally visible lack of forward
   action before fixing option visibility or surrounding semantics. Setup must
   not manufacture intermediate participation state.
2. Lineage-constrained recovery must include the Tragedy override. The direct
   `recovery_proposition_id` jump to the Horizon opener must not remain a bypass
   around normal backward-lineage recovery. Investigate and report any compelling
   structural reason for an exception before retaining one.

Keep the staged review order: test/Actions separation; HTTP harness and red
regressions; local structural epochs and scoped spentness; backward-lineage
recovery; complete lifecycle/constitutional coverage; protected cleanup. Existing
test expectations are preserved in Stage 1, including expectations that later
stages may demonstrate are wrong.

## Stage 2 checkpoint

[participation-http-harness.md](participation-http-harness.md) records the real
HTTP setup, external transport boundary, fixture-write guard, deterministic
action driver, and the first three production regressions. Their order is the
successful-route revisit / Bias dead end, cross-Committal Distinguish collision,
then independent Disperse-slot collision. Seven tests are added to Core: four
harness controls and three intentionally red assertions of intended behavior.
No application semantics, existing tests, or suite/workflow selection are changed.

Report the actual Core validation results and stop before local-epoch,
spentness, or recovery fixes. An expected red regression must fail on the
reproduced behavior, not on fabricated setup, an unrelated exception, or an
exhausted action budget. Do not manipulate intermediate structural state to make
these tests pass.

## Stage 3 checkpoint

[local-exploration-epochs.md](local-exploration-epochs.md) records the exact
three-part position identity, provenance rules, canonical current-failure
predicate, immutable history, and safe-cutover limitations. The cross-Committal
Distinguish and independent Disperse HTTP regressions must pass. A further HTTP
journey proves a third Variant after genuinely spending the existing options;
it checks local reopening without changing historical failures or Disperse epochs.

The successful-route Bias revisit remains an ordinary red Core regression. No
Variant prerequisites, recovery selection, Tragedy override, suite membership,
or Actions workflows are loosened to obtain these results. Review the recorded
PostgreSQL Core, Full Integration, Query, and Load results before proceeding to
Stage 4. No Stage 3 deployment is implied by this checkpoint.
