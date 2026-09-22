# Commit Region ownership: Stage 1

Commit Region ownership is explicit Cycle governance. A persisted Committal owns
itself. An expressive Proposition belongs to the immutable `governing_committal_id`
of its creating Phase's Cycle. Supersession ancestry records causal provenance
and may be multi-parent. Multi-parent causal provenance does not imply ambiguous
operational ownership.

Context Horizon ownership independently comes from the creating Cycle's
`context_horizon_id`. Movement remains lineage-constrained and is not granted by
Region or Horizon membership. No edge means no movement. A multi-input result's
producing Phase Gate records the traversed route; foreign inputs do not create
movement shortcuts.

## Resolution and failure policy

`CommitRegionService::seedIdsFor()` resolves persisted ownership with one bounded
join, without walking Supersession parents. It validates the governor's existence,
Committal tier, substrate, frozen snapshot, and creating/entry Horizon relationship
from #196. Boundaries govern the direct child they opened; ordinary Committals
govern their own Horizon. Missing or malformed expressive governance yields null.
There is no newest-Commit, participant-position, Horizon-sequence or parent fallback.

`seedFor()`, `ParticipationContext::seed()`, and the context's memoized seed map
share that resolver. The working set explicitly hydrates governors as dependencies.
Region populations contain active expressive Propositions with that governor,
including superseded intermediates for historical calculations. Operation-specific filters still determine
Read, Gate and Write admissibility independently.

## Consumer audit

| Consumer | Ownership authority and retained responsibility |
| --- | --- |
| CommitRegionService | Shared single/batched resolver and governed membership |
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
| BiasGateService | Shared governed population; register/phase/direction filters unchanged |
| PhaseGenerationService | Existing option services; no expanded phase admissibility |
| CommitmentAdmissibilityService | Existing regional options and evidence/Closure checks |
| PotentialChargeService | Existing scoped projection and proof consumers |
| PotentialProjectionService | Cycle-owned node identities; foreign inputs remain separate from local geometry |
| PotentialRegionCalculator | Region owner qualifies expressive measurements; formulas and attenuation unchanged |
| ReferencePotentialChargeService | Same owner qualification in the diagnostic traversal |
| PropositionPresentationService | Existing region/evidence consumers; no new options |
| GraphProjectionService | Batched authoritative ownership even when disposable terrain is stale |
| CycleEntryService | Existing entry and recovery selection; no new destinations |
| ParticipantHistoricalContext | Batched governance filter replaces candidate ancestry reconstruction |
| ActiveParticipantLineage | Unchanged producing-Gate provenance traversal |
| ContextHorizonProjector | Unchanged Cycle-authoritative membership and rebuild |
| CommittalLineageService | Already Cycle-authoritative for Closure; unchanged |

## Historical compatibility and projection refresh

The Tragedy scope and regional Exhaustion migrations predate the governance
migration. `Maintenance\LegacyCommitRegionOwnership` preserves their conservative
historical ancestry resolution only while the governance column is absent. Once
the column exists it delegates to live governance, including fail-closed nulls;
it never repairs missing governance by guessing. Live consumers do not use it.

Potential's disposable projection version changes to identify the new ownership
representation. Existing projections must be rebuilt through the established
maintenance path before they can be certified current. The refresh migration
invalidates all Horizon projections together and rebuilds the disposable Exhaustion
membership index, so a partial refresh cannot certify stale ownership. Frozen Commitment
snapshots, Receipts, proof records and Supersession edges are not rewritten.

## Stage boundary

This change does not enable cross-Horizon Mediate. Candidate scope, structural
matching, Domain rules, phase admissibility, Bias, Traverse, recovery, Tragedy,
Exhaustion, Receipt qualification and Closure remain under their existing rules.
Synthetic foreign-parent tests exercise representation only.
