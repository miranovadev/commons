# Horizon proof invariant coverage

| Required invariants | Tests |
| --- | --- |
| 1–7: one Instrument Receipt, shared Horizon availability, persistent one-use consumption, foreign scope, concurrent winner | `HorizonReceiptLifecycleTest`, `ReceiptGatedReclassificationTest`, `FullEvidenceFlowTest`, `TransactionLockOrderTest` |
| 8–14: Phase 1/4 state machine, later-Cycle Traverse, no Receipt-driven Variant or healthy duplicate | `ExpressiveEvidenceGatingTest`, `EvidenceFirstInteractionTest`, `WriteOnceTest`, `InputPlanVisibilityTest`, `ParticipantActionContractTest`, `HorizonProofCycleTest` |
| 15–22: mandatory explicit Receipt, scope/position/tier/register validation, atomic attachment | `ReceiptGatedReclassificationTest`, `SharedReclassificationTest`, `ReclassificationEvidenceTest`, `PhasePositionInvariantTest`, `TransactionLockOrderTest` |
| 23–29: authoritative no-Potential first Rest, one Tragedy/Receipt, recovery and open Horizon | `AuthoritativeRestReasonTest`, `HorizonTragedyTest`, `ExhaustionTest`, `TransactionLockOrderTest` |
| 30–37: unused/exercised/successful/failed final attempt, exact-once closure, persistent visible opener | `FinalAttemptExhaustionTest`, `ExhaustedParticipantContextTest`, `TransactionLockOrderTest` |
| 38–47: recursive local proof, reference-only metadata, resolved evidence, Origin/history persistence, ordering, observer separation | `FinalAttemptExhaustionTest`, `CausalRecoveryTest`, `ExhaustedParticipantContextTest`, `ExhaustionTest`, `HorizonProofMigrationTest` |
| 48–53: generated/consumed Receipts, distinct Tragedy/Exhaustion, small after-commit events, avatars | `ExhaustionRealtimeTest`, existing realtime backend tests, workspace and realtime frontend tests |
| Migration compatibility and immutable ownership | `HorizonProofMigrationTest`, `ReceiptCapabilitiesMigrationTest`, `ReceiptContextMigrationTest`, `CausalHorizonMigrationTest` |
| Full cycles and numeric closure | `HorizonProofCycleTest`, `FullEvidenceFlowTest`, `OperationalClosurePathTest` |
| No historical-growth query explosion | `ExhaustionTest`, `CausalRecoveryTest`, `ParticipationPerformanceTest`, `PotentialProjectionTest` |

PostgreSQL-only tests use independent worker processes and observe actual lock waits; SQLite cannot validate those guarantees. The CI matrix runs PostgreSQL 18 with PHP 8.5, Node 24 frontend build/tests, a separate recovery/contention job and a participation performance profile. Report the exact integration commit's CI outcome when reviewing; intermediate stack stages intentionally precede later semantic fixture updates.
