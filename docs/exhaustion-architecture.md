# Horizon proof architecture

The normative lifecycle is [Tragedy and final-attempt Exhaustion](branch-tragedy.md).

| Component | Responsibility |
| --- | --- |
| `RestReasonService` | Derive no-move versus evidenced no-Potential Rest from authoritative local state |
| `HorizonFailureService` | Observe versioned local failure; publish only for a completed, matching Rest |
| `ExplorationEpochService` | Derive current spent state and advance only the successful Variant's position epoch |
| `TragedyService` | Record the first no-Potential Rest, unique Receipt and exact opening-Committal recovery |
| `HorizonReceiptService` | Shared availability and atomic one-use attachment under Substrate/Receipt locks |
| `ExhaustionService` | Require exercised Tragedy Receipt and later no-Potential Rest; persist closure and actionability |
| `TragedyEvidenceService` | Store local exhausted Committal references; resolve evidence on load |
| `RecoveryBasinResolver` | Follow maintained local frontiers and parent provenance |
| `ParticipantHistoricalContext` | Keep relevant exhausted positions visible, newest-first, without action targets |
| `ExhaustionAuditService` | Read-only proof consistency diagnostics |

Potential is maintained incrementally. Failure publication marks only affected local paths and Horizon projections dirty. Exhaustion marks the opening Committal through the authoritative `horizon_exhaustions.opening_proposition_id` relation; its Proposition, snapshot, edges and evidence remain unchanged. Parent propagation updates summaries through provenance rather than scanning for universal branch closure.

`continuation_failures` records immutable local expressive failures at their
Horizon, producing position and exploration epoch. Ordinary tested downstream
failure may publish spent evidence while Potential remains, without Tragedy.
Only a successful Variant advances its own position's epoch; old failures stop
blocking actions but remain historical. See [exploration epochs](exploration-epochs.md). It does not substitute for a Horizon Exhaustion record. Historical `exhaustion_states`, `origin_branches.pruned_at`, retirement timestamps and old Tragedies are archived compatibility data; they do not gate new substrate dynamics.

The graph may filter these views for observers. Participant actionability and historical context use explicit structural position and proof relations independently of graph filters. Realtime carries IDs and small state changes, with existing fetch/cache refresh and avatar movement.

See [rollout compatibility](horizon-proof-migration.md), [locking](transaction-locking.md), and [invariant coverage](horizon-proof-tests.md).
