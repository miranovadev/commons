# Substrate motor: review checkpoints

Stage 0 investigated production exports and current implementation at
`170668a027d4b2b8c29051c66eebaf7e3054defd`. Stage 1 only separates test execution
and Actions; [testing.md](testing.md) records suite membership and invocation.
Stage 1 was reviewed and approved. Stage 2 is authorized for the HTTP harness and
faithful red regressions only; semantic corrections require the next review.

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
