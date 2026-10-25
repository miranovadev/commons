# Stage B validation and acceptance coverage

Base: `eeee6a6d81c4a32f605624f4804775f46af17bf4`. PR: [#280](https://github.com/miranovadev/commons/pull/280). Fresh databases only. No merge or deployment is part of this change. Cadence, observable calculations, canonical snapshots, evidence allowances, Commit and terminal lifecycle mathematics are unchanged.

## Coverage map

| Handoff scenarios | Executable coverage |
| --- | --- |
| 1–5: personal encounters, independent actors and Cycle intervals | `ParticipantExplorationTest`, `ParticipantExplorationHttpTest`, `ExplorationRealtimeTest` |
| 6–11: all active heads, revisions, inaccessible branches and Reconcile routes | `ParticipantExplorationTest` branching with display limit 1, hard revision, local/foreign Reconcile cases; existing `StructuralSourceTest` and movement contracts |
| 12–17: H1, relevant evidence, unrelated changes and recovery | `ParticipantExplorationTest` actual Attestation dispatch, new Receipt enabling Bias, unrelated Receipt, new Cycle and immutable history cases |
| 18–23: Variant order and concurrency | Personal service/HTTP journeys, `ExpressiveEvidenceGatingTest`, PostgreSQL `HorizonProofConcurrencyTest` competing fresh Variants |
| 24–29: atomic immutable publication | Service and realtime rollback, stale guard, frozen arrival, idempotence, append-only database trigger tests |
| 30–36: shared authority and preserved contracts | Existing input/schema, Preparation, C3, source equivalence, homology, Receipt, Phase Profile, Commit and recovery suites; aggregate limitations below remain visible |

An acceptance mapping identifies the responsible tests; it is not a claim that every aggregate lifecycle trajectory passes.

## Fixture and schema changes

- Global-failure and epoch tests were replaced by personal service/HTTP encounters. Events are created through actual Rest for lifecycle proofs. `ExplorationHistory` supplies explicitly labelled historical inputs only for isolated selectors.
- Evidence/Potential unit contracts use a personal exploration dependency boundary when testing their independent prerequisites. Production exploration is tested separately without that substitution.
- C3’s historical snapshot excludes the three removed exploration tables. It does not convert shared failures into personal events. Both compared cognitive projections receive the same current operational plan and all restored graph/evidence rows must remain unchanged.
- The historical Cycle-entry migration test rolls back the later exploration trigger before rebuilding `cycles`. PostgreSQL trigger functions use `CREATE OR REPLACE` so repeated fresh database creation is supported.
- Stale duration entries for removed tests were removed. CI membership discovery, action budgets, query limits and workflow ceilings are unchanged.
- The original natural Commit driver and fixed authored action traces remain intact. Experimental changes to its judgment policy did not establish an equivalent Commit journey and are not included.

## Validation record

Completed results and exact tested revision are recorded below. Results from intermediate commits are not treated as final-head passes.

Confirmed local comparisons against unchanged base:

- `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` fails its existing Settlement/Emergence assertion on both base and implementation.
- The dense Order context exceeds the existing 45,000-byte ceiling on base (55,062 bytes) and the intermediate implementation (54,511 bytes). The ceiling is unchanged.
- `ParticipationPerformanceTest` encounters missing authorized prospective Closure on unchanged base. Those reads remain prohibited; no synthetic Closure reconstruction is introduced.
- `CommitSourceHistoryLifecycleTest` passes on base but its unchanged all-Refute driver stalls under personal exploration. A personally encountered owned Disperse head has a newly lawful Revise in the next Cycle; its ancestors therefore stay explorable. The old driver ignores that action and keeps traversing. This is a changed trajectory, not an established baseline failure or a completed natural Commit proof.

## Remaining validation boundaries

The new personal proofs establish independent Rest, frontier propagation and lawful reopening. They do not establish a replacement naturally developed single-participant Commit transcript. Existing mathematical and lifecycle assertions are retained so CI reports that gap explicitly. A future live cook is not required for this PR, and no production cook was run.

Query validation includes cold frontier growth and warm request-local reuse. Comparison population mathematics is unchanged; additional batch paths are checked against ordinary discovery. Full Query and Load results, including independent existing failures, are recorded with the final PR validation summary.

## Completed validation

Functional revision: `54382d99ae824ac2c105d5958042236cd652c8fa`. The subsequent documentation-only commit records these results; it does not change the tested application or tests.

| Check | Result | Evidence |
| --- | --- | --- |
| Core, PostgreSQL | **1,498 passed; 1 failed**, 15,465 assertions. The single Charge-boundary failure reproduces on unchanged base. Four shards fully pass. | [Candidate](https://github.com/miranovadev/commons/actions/runs/40167563621), [base](https://github.com/miranovadev/commons/actions/runs/40166689571) |
| SQLite migration contracts in Core | 14 passed, 46 assertions | Candidate Core, shard 1 |
| Integration | **21 passed; 13 failed**; all 34 discovered cases executed, no skips, 3,292 assertions | [Complete inventory](https://github.com/miranovadev/commons/actions/runs/40167566267) |
| Phase Profile matrix | Contracts, fixture repairs, final review and terminal stage-four groups pass on SQLite and PostgreSQL. The two natural Commit-history tests fail on both databases (4 of 12 jobs). | [Candidate](https://github.com/miranovadev/commons/actions/runs/40167563620), [base matrix passed](https://github.com/miranovadev/commons/actions/runs/40156693087) |
| C3 | 107 focused tests passed / 1,027 assertions; recorded comparison passed / 1,390 assertions across 10 cases | [Run](https://github.com/miranovadev/commons/actions/runs/40167566276) |
| Frontend | TypeScript/Vite build and all 148 tests passed | Candidate Core frontend job |
| Inspector browser review | Passed | [Run](https://github.com/miranovadev/commons/actions/runs/40167566266) |
| Query | **13 passed; 9 failed**, 949 assertions. The same 9 cases fail on base (12 passed there). The added personal-frontier query contract passes. | [Candidate](https://github.com/miranovadev/commons/actions/runs/40167565960), [base](https://github.com/miranovadev/commons/actions/runs/40166689511) |
| Load / PostgreSQL concurrency | **18 passed; 8 failed**, 178 assertions. The same 8 failures reproduce on base. Competing Variants correctly recheck the newly created unexplored opportunity. | [Candidate](https://github.com/miranovadev/commons/actions/runs/40167565884), [base](https://github.com/miranovadev/commons/actions/runs/40166689535) |

Additional local checks passed: 107 exploration/homology cases (921 assertions); 74 Revision/Preparation cases (609 assertions); 66 recovery/terminal/realtime caller cases (998 assertions); the personal HTTP and realtime journeys (2 cases, 153 assertions). The selected navigation suite passed 52 cases with its existing SQLite migration skip (434 assertions). These selections overlap with CI and are not summed as unique coverage. Changed PHP files passed Pint and whitespace checks.

### Performance evidence and limits

With a hydrated `ParticipationContext`, the first personal frontier evaluation uses **21 SQL queries for both 2 and 8 heads** on SQLite and PostgreSQL. Repeating the same evaluation within that context uses **zero additional queries**. Context hydration is outside this particular counter. The C3 recorded operational plans use 70–76 SQL queries across ten captured cases; their personal exploration history is intentionally empty.

These measurements do not establish a worst-case bound for large personal histories spanning distinct foreign Bias/Order scopes. The existing broader performance suite still cannot complete its eight missing-Closure fixtures; the dense Order payload remains 54,511 bytes against the unchanged 45,000-byte budget (base: 55,062). No limit was raised, no population mathematics was truncated, and no production load/cook was run.

### Changed trajectories and review status

This PR is **draft and not ready to merge**. In addition to the independently verified baseline failures, existing full Commit and recovery trajectories require reconciliation with the new model. This is not presented as a green Integration result.

Direct unchanged-base comparisons establish new failures in:

- `CommitSourceHistoryLifecycleTest` (base passes; new owned-Revise opportunity keeps the driver on the old route).
- `CommitDescendantHistoryLifecycleTest` (the same driver; its base Phase Profile matrix passes).
- `ParticipationLifecycleRegressionTest::test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit`.
- `ParticipationRecoveryTest::test_successful_revisit_keeps_its_untraversed_commit_out_of_backward_recovery`.
- `ParticipationSourceEquivalenceTest::test_connection_driven_and_manual_participation_share_the_complete_action_contract`.

The last three were rerun together on unchanged base and passed all **3,776 assertions**. Their fixed action sequences are retained, rather than regenerated or skipped. The shared NaturalBoundaryHistory fixture is independently confirmed failing on base. The remaining Integration failures below are recorded without claiming an individual baseline comparison.

All failed Integration cases:

- `Tests\Feature\FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_a_lawful_commit#0`
- `Tests\Feature\FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_a_lawful_commit#1`
- `Tests\Feature\Api\ParticipationLifecycleRegressionTest::test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit`
- `Tests\Feature\Api\ParticipationRecoveryTest::test_successful_revisit_keeps_its_untraversed_commit_out_of_backward_recovery`
- `Tests\Feature\CommitDescendantHistoryLifecycleTest::test_real_commit_descendant_resets_evidence_and_searches_selected_history_without_authority`
- `Tests\Feature\HorizonProofCycleTest::test_one_participant_builds_and_commits_through_both_receipt_boundaries_over_cycles`
- `Tests\Feature\Api\ParticipationSourceEquivalenceTest::test_connection_driven_and_manual_participation_share_the_complete_action_contract`
- `Tests\Feature\CommitSourceHistoryLifecycleTest::test_commit_preserves_a_genuine_expressive_source_and_its_evidence_references`
- `Tests\Feature\NaturalChargeBoundaryLifecycleTest::test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor`
- `Tests\Feature\OperationalClosurePathTest::test_receipt_backed_full_cycle_commits_with_real_hysteretic_closure_calculations`
- `Tests\Feature\Api\ParticipationHttpHarnessTest::test_real_http_actions_reach_an_operational_commit`
- `Tests\Feature\Api\ParticipationTragedyLifecycleTest::test_closed_crossover_alone_does_not_authorize_catastrophe_on_an_untested_continuation`
- `Tests\Feature\FullEvidenceFlowTest::test_three_participants_share_receipts_and_commit_without_redundant_healthy_lineages`

No code changes were made to make these mathematical or authored lifecycle assertions pass artificially. The focused new exploration proofs are complete; a replacement natural Commit transcript and adaptation of the affected fixed journeys remain validation work before merge.
