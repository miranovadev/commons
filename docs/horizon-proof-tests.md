# Horizon proof invariant coverage

| Required invariants | Tests |
| --- | --- |
| 1–7: one Instrument Receipt, shared Horizon availability, persistent one-use consumption, foreign scope, concurrent winner | `HorizonReceiptLifecycleTest`, `ReceiptGatedReclassificationTest`, `FullEvidenceFlowTest`, `TransactionLockOrderTest`, `HorizonProofConcurrencyTest` |
| 8–14: Phase 1/4 state machine, later-Cycle Traverse, no Receipt-driven Variant or healthy duplicate | `ExpressiveEvidenceGatingTest`, `EvidenceFirstInteractionTest`, `WriteOnceTest`, `InputPlanVisibilityTest`, `ParticipantActionContractTest`, `HorizonProofCycleTest` |
| 15–22: mandatory explicit Receipt, scope/position/tier/register validation, atomic attachment | `ReceiptGatedReclassificationTest`, `SharedReclassificationTest`, `ReclassificationEvidenceTest`, `PhasePositionInvariantTest`, `TransactionLockOrderTest`, `HorizonProofConcurrencyTest` |
| 23–29: authoritative no-Potential first Rest, one Tragedy/Receipt, recovery and open Horizon | `AuthoritativeRestReasonTest`, `HorizonTragedyTest`, `ExhaustionTest`, `TransactionLockOrderTest`, `HorizonProofConcurrencyTest` |
| 30–37: unused/exercised/successful/failed final attempt, exact-once closure, persistent visible opener | `FinalAttemptExhaustionTest`, `ExhaustedParticipantContextTest`, `TransactionLockOrderTest`, `HorizonProofConcurrencyTest` |
| 38–47: recursive local proof, reference-only metadata, resolved evidence, Origin/history persistence, ordering, observer separation | `FinalAttemptExhaustionTest`, `CausalRecoveryTest`, `ExhaustedParticipantContextTest`, `ExhaustionTest`, `HorizonProofMigrationTest`, `HorizonProofCompatibilityTest` |
| 48–53: generated/consumed Receipts, distinct Tragedy/Exhaustion, small after-commit events, avatars | `ExhaustionRealtimeTest`, existing realtime backend tests, workspace and realtime frontend tests |
| Migration compatibility and immutable ownership | `HorizonProofMigrationTest`, `HorizonProofCompatibilityTest`, `ReceiptCapabilitiesMigrationTest`, `ReceiptContextMigrationTest`, `CausalHorizonMigrationTest` |
| Full cycles and numeric closure | `HorizonProofCycleTest`, `FullEvidenceFlowTest`, `OperationalClosurePathTest` |
| No historical-growth query explosion | `ExhaustionTest`, `CausalRecoveryTest`, `ParticipationPerformanceTest`, `PotentialProjectionTest` |

PostgreSQL-only tests use independent worker processes and observe actual lock waits; SQLite cannot validate those guarantees. The [test workflows](testing.md) use PostgreSQL 18 and PHP 8.5. Core includes Node 24 frontend build/tests and deterministic recovery contracts; database races run in Load / Concurrency and performance profiles run in Query Regression. Report the exact integration commit's CI outcome when reviewing; intermediate stack stages intentionally precede later semantic fixture updates.

## Regional authority refinement

`CausalRecoveryTest::test_same_horizon_regions_exhaust_independently_and_recovery_unwinds_c_b_a` proves separate C/B Tragedies, actual final Variants and Receipt consumption, C → B → A recovery, and live sibling/ancestor preservation inside an open Horizon. `test_boundary_region_exhaustion_permits_crossing_without_retiring_other_child_regions` proves the boundary consequence and continued child-region operation. `RegionalExhaustionMigrationTest` proves conservative historical mapping, ambiguity retention, and no accidental Origin closure from a non-boundary proof. Existing concurrent Rest/final failure/Receipt races retain their locks and regional uniqueness checks.
