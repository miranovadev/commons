# Substrate motor: review checkpoints

Stage 4 is approved. Stage 5 lifecycle work is described in
[the Stage 5 checkpoint](stage-5-lifecycle-correctness.md). Constitutional suite
reorganisation and cleanup remain outside this checkpoint.

Stage 0 investigated production exports and current implementation at
`170668a027d4b2b8c29051c66eebaf7e3054defd`. Stage 1 only separates test execution
and Actions; [testing.md](testing.md) records suite membership and invocation.
Stages 1 through 4 were reviewed and approved. Stage 5 is authorized for lifecycle
correctness, including successful-route continuity, Rest, Tragedy, visibility
and participant-source equivalence. Constitutional suite work and cleanup remain
a later review boundary.

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

## Stage 4 checkpoint

[Backward lineage recovery](backward-lineage-recovery.md) records actual participant
arrival provenance, ancestral-only candidate construction, raw edge attenuation,
same-lineage Horizon crossing, removal of the Tragedy override, and the explicitly
superseded sibling/descendant test contracts. Validate Core, Full Integration,
Query and Load without changing suite selection or ceilings. Record final results
and runtimes in the Stage 4 PR and stop. Bias remains a separate expected red
eligibility regression; no Stage 5 lifecycle or visibility changes are included.
