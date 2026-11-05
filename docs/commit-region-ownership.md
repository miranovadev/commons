# Commit Region ownership and Cycle entry

A Committal owns itself. An expressive Proposition takes its governing Committal
and Context Horizon from its producing Phase. Ordinarily these are the entry
Committal and Horizon of the Participant's actual Cycle.

When B is the receiving operand of an A-owned Reconciliation X, choosing X
at Mediate records B as the Gate of the completed Phase and X as the Gate of
Bias in that same Cycle. Bias records the governing Cycle of X's producing
Phase in the immutable `phases.crossover_cycle_id`. Order inherits that
context on local progression. If the Bias Gate is the exact source of a
foreign Reclassification Y, choosing Y gives Order the governing Cycle of
Y's producing Phase instead. This also applies when Y's author crossed
earlier in their own Cycle. Rows from
before crossover have a null witness. The generated `operating_cycle_id`
resolves the appropriate ownership join for either case. Actor identity,
Phase sequence and participation history always use `phases.cycle_id`.

On Rest the Participant recovers through the current Region's nearest eligible
Committal hierarchy. A's governor can be eligible even when the Participant
arrived through B. Its ordinary viability and exhaustion checks apply, including
when A and B share a Horizon. A successful recovery records a genuine new Cycle
entry. The original Cycle keeps its B entry.

The migration can be rolled back before the first crossed Phase. Once a crossing
has been recorded, retain the Phase context for historical ownership. Future
admission can be disabled separately in Mediate and Bias option discovery.

## Resolution and failure policy

`CommitRegionService::seedIdsFor()` resolves persisted ownership with one bounded
join, without walking Supersession parents. It validates the entry Committal's existence,
Committal tier, substrate, frozen snapshot, and creating/entry Horizon relationship
from #196. Boundaries govern the direct child they opened; ordinary Committals
govern their own Horizon. Missing or malformed expressive entry identity yields null.
There is no newest-Commit, participant-position, Horizon-sequence or parent fallback.

`seedFor()`, `ParticipationContext::seed()`, and the context's memoized seed map
share that resolver. The working set explicitly hydrates entry Committals as dependencies.
Region populations contain active expressive Propositions with that entry Committal,
including superseded intermediates for historical calculations. Operation-specific filters still determine
Read, Gate and Write admissibility independently.

## Consumer audit

| Consumer | Ownership authority and retained responsibility |
| --- | --- |
| CommitRegionService | Shared single/batched resolver and entry-owned membership |
| ParticipationContext | Shared memoized map; parent graph retained for provenance and measurements |
| ReceiptRegionService | Shared map; observed Proposition identity and existing Horizon qualification |
| ExhaustionService | Resolves terminal/Gate ownership through CommitRegionService; stored regional proof remains authoritative |
| TragedyReceiptScopeService | Shared seed resolver; existing recovery transport and consumption rules |
| TragedyReceiptScopeBackfill | Shared resolver after #196; isolated legacy resolver before the column exists |
| RegionalExhaustionMembership | Governed membership; indexed projection of existing regional proof |
| RegionalExhaustionBackfill | Same historical compatibility split; proof witnesses unchanged |
| ExhaustionAuditService | Compares stored regional identity with terminal's resolved owner |
| CommitRegionPolarityService | Delegates ownership; retains Commit-source polarity inheritance |
| AttestationEvidenceService | Shared region/polarity resolution; evidence rules unchanged |
| BiasGateService | Shared entry-owned population; register/phase/direction filters unchanged |
| PhaseGenerationService | Existing option services; no expanded phase admissibility |
| CommitmentAdmissibilityService | Existing regional options and evidence/Closure checks |
| PotentialChargeService | Existing scoped projection and proof consumers |
| PotentialProjectionService | Cycle-owned node identities; foreign inputs remain separate from local geometry |
| PotentialRegionCalculator | Region owner qualifies expressive measurements; formulas and attenuation unchanged |
| ReferencePotentialChargeService | Same owner qualification in the diagnostic traversal |
| PropositionPresentationService | Existing region/evidence consumers; no new options |
| GraphProjectionService | Batched authoritative ownership even when disposable terrain is stale |
| CycleEntryService | Existing entry and recovery selection; no new destinations |
| ParticipantHistoricalContext | Batched entry filter replaces candidate ancestry reconstruction |
| ActiveParticipantLineage | Unchanged producing-Gate provenance traversal |
| ContextHorizonProjector | Unchanged Cycle-authoritative membership and rebuild |
| CommittalLineageService | Already Cycle-authoritative for Closure; unchanged |

## Historical compatibility and projection refresh

The Tragedy scope and regional Exhaustion migrations predate the original Cycle
entry backfill. `Maintenance\LegacyCommitRegionOwnership` preserves their
conservative historical ancestry resolution before that backfill. After the
entry rename it delegates to live entry ownership, including fail-closed nulls;
it never repairs missing entry identity by guessing. Live consumers do not use it.
See [Cycle entry identity](cycle-entry-identity.md) for migration order and rollback.

Potential's disposable projection version changes to identify the new ownership
representation. Existing projections must be rebuilt through the established
maintenance path before they can be certified current. The refresh migration
invalidates all Horizon projections together and rebuilds the disposable Exhaustion
membership index, so a partial refresh cannot certify stale ownership. Frozen Commitment
snapshots, Receipts, proof records and Supersession edges are not rewritten.

## Original ownership checkpoint boundary

This change does not enable cross-Horizon Mediate. Candidate scope, structural
matching, Domain rules, phase admissibility, Bias, Traverse, recovery, Tragedy,
Exhaustion, Receipt qualification and Closure remain under their existing rules.
Synthetic foreign-parent tests exercise representation only.

The subsequent Cycle-entry rename changes representation only. #200 Mediate
behavior and all existing operational eligibility remain unchanged.
