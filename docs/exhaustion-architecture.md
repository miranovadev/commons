# Commit Region proof architecture

The normative lifecycle is [Charge-boundary Tragedy and exhaustion](branch-tragedy.md).

| Component | Responsibility |
| --- | --- |
| `RestReasonService` | Derive no-move versus evidenced no-Potential Rest from authoritative local state |
| `HorizonFailureService` | Observe versioned local failure; publish only for a completed, matching Rest |
| `ExplorationEpochService` | Derive current spent state and advance only the successful Variant's position epoch |
| `TragedyService` | Observe fresh expressive canonical Charge; create first Tragedy/Receipt or delegate later boundary exhaustion |
| `HorizonReceiptService` | Shared availability and atomic one-use attachment under Substrate/Receipt locks |
| `ExhaustionService` | Publish unique later-boundary proof and Cycle-owned historical membership; enforce regional actionability |
| `TragedyEvidenceService` | Resolve historical exhausted Committal references for warrant presentation, never loading |
| `TragedyMemoryService` | Bounded canonical committed-lineage memory, independent of evidence and warrant authority |
| `RecoveryBasinResolver` | Constrain candidates to ancestral Committals on the recorded backward lineage; cross the opening boundary only when boundary-governing region exhaustion permits |
| `ParticipantHistoricalContext` | Keep relevant exhausted positions visible, newest-first, without action targets |
| `ExhaustionAuditService` | Read-only proof consistency diagnostics |

Potential is maintained incrementally. Failure publication marks only affected local paths and Horizon projections dirty. Exhaustion marks the governing Committal through the authoritative `commit_region_exhaustions.governing_committal_id` relation; its Proposition, snapshot, edges and evidence remain unchanged. Parent propagation updates summaries through provenance rather than scanning for universal branch closure.

Summary propagation and Homeostatic populations are calculation scope, not
Participant movement. Recovery starts at the actual position and counts recorded
backward edges; it never discovers sibling/descendant candidates or resets to a
Horizon provenance node. Forward movement requires the current Gate's explicit
causal continuation. Commit Region or Horizon membership supplies no such edge.
No edge means no movement.

`continuation_failures` records immutable local expressive failures at their
Horizon, producing position and exploration epoch. Ordinary tested downstream
failure may publish spent evidence while Potential remains, without Tragedy.
Only a successful Variant advances its own position's epoch; old failures stop
blocking actions but remain historical. See [exploration epochs](exploration-epochs.md). It does not substitute for a Commit Region Exhaustion proof. Historical `horizon_exhaustions`, `exhaustion_states`, `origin_branches.pruned_at`, retirement timestamps and old Tragedies are archived compatibility data; they do not gate new substrate dynamics.

The graph may filter these views for observers. Participant actionability and historical context use explicit structural position and proof relations independently of graph filters. Realtime carries IDs and small state changes, with existing fetch/cache refresh and avatar movement.

See [rollout compatibility](horizon-proof-migration.md), [locking](transaction-locking.md), and [invariant coverage](horizon-proof-tests.md).

The governing Committal is the basin identity. `tragedies` records the event with a unique nullable `governing_committal_id` replacing
the Horizon uniqueness constraint. Null-scoped historical rows do not authorize
regional warrants. Boundary exhaustion state is read from this
same regional authority without a duplicate Horizon timestamp, and never globally retire a Horizon's other regions.
See [migration and authority](commit-region-exhaustion.md).
