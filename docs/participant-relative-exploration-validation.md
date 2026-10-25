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

Validation is being completed on the PR head. Results from intermediate commits are not treated as final-head passes.

Confirmed local comparisons against unchanged base:

- `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` fails its existing Settlement/Emergence assertion on both base and implementation.
- The dense Order context exceeds the existing 45,000-byte ceiling on base (55,062 bytes) and the intermediate implementation (54,511 bytes). The ceiling is unchanged.
- `ParticipationPerformanceTest` encounters missing authorized prospective Closure on unchanged base. Those reads remain prohibited; no synthetic Closure reconstruction is introduced.
- `CommitSourceHistoryLifecycleTest` passes on base but its unchanged all-Refute driver stalls under personal exploration. A personally encountered owned Disperse head has a newly lawful Revise in the next Cycle; its ancestors therefore stay explorable. The old driver ignores that action and keeps traversing. This is a changed trajectory, not an established baseline failure or a completed natural Commit proof.

## Remaining validation boundaries

The new personal proofs establish independent Rest, frontier propagation and lawful reopening. They do not establish a replacement naturally developed single-participant Commit transcript. Existing mathematical and lifecycle assertions are retained so CI reports that gap explicitly. A future live cook is not required for this PR, and no production cook was run.

Query validation includes cold frontier growth and warm request-local reuse. Comparison population mathematics is unchanged; additional batch paths are checked against ordinary discovery. Full Query and Load results, including independent existing failures, are recorded with the final PR validation summary.
