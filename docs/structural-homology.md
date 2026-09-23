# Structural homology — Stage 2 foundation checkpoint

Operational locality and structural homology are separate relations.
`PhasePositionService` remains Horizon-strict. `HomologousPositionService`
compares structural coordinates without authorizing any action or movement.

## Committed coordinate

Committals must belong to the same substrate and have the same authoritative
creating-Horizon Domain. Walk their immutable creating-Cycle governors to their
nearest shared Committal, counting one edge per committed predecessor. They are
homologous when both distances are equal. A shared seed has distance zero.
The returned coordinate includes the nearest ancestor ID so callers and tests
can distinguish it from an arbitrary Origin-based comparison.

Only frozen committed history qualifies. Missing snapshots, disconnected roots,
cycles, foreign substrates and invalid governance/Horizon links fail closed.
An ungoverned root must be an explicit Origin bootstrap. Supersession distance,
Horizon sequence, Closure Depth, Cadence, ledger order and time play no role.

`CommittalLineageService::recordsForIds()` supplies the shared batched governance
expansion. Existing Closure callers use the same loader and retain their existing
calculation and error policies. Homology introduces no alternative ancestry edge.

## Expressive coordinate

Expressive positions must have the same substrate, actual creating-Horizon Domain,
producing Phase kind and canonical register for that Domain/Phase. Both the
Proposition register and producing Phase write register must be canonical.
Their valid Cycle-owned governors must be homologous Committals.

A boundary Committal remains in its creating Domain; expressions it governs in
its opened Horizon use that Horizon's Domain. Both comparisons are explicit.
Different Regions and Horizons are allowed. Same-Horizon positions remain valid.
Malformed labels cannot override authoritative Domain identity.

Candidate discovery filters substrate, Domain and canonical Phase/register before
batching governors. It does not independently query each candidate's ancestry.
The structural service deliberately leaves active/exhausted state, retained
Correspondence, directed-pair uniqueness and other action policies to its consumer.

## Implementation status and continuation

This checkpoint implements the relation and an internal foreign Mediate discovery
service. **Public Mediate options do not yet consume that discovery pool, and
cross-Horizon reconciliation is not enabled.**

`HomologousMediationService::poolFor()` uses the existing causal Constrain anchor,
selects foreign homologous Regions, compares retained Read Correspondence with
the existing hysteresis implementation, and collects immediate canonical Disperse
children in each Read's own Region and Horizon. A separate batched comparison
context supplies foreign ancestry and availability without extending the caller's
operational/evidence context. Committed Gates are excluded; other hard replacements
remain reusable under existing semantics. The method is discovery, not final action
authorization: exhausted-continuation and directed-pair checks still belong to the
operational integration. Tests explicitly prove the public options remain unchanged.

Remaining Stage 2 work:

1. Connect the discovered pool to MediationReadService's internal compatible Reads,
   preserving the causal anchor as the only participant-facing Read. Match retained
   Correspondence on the Read, including existing zero hysteresis.
2. Connect the discovered immediate active canonical Disperse children as foreign
   operands. Preserve availability, exhaustion and directed-pair uniqueness. In
   particular, ExhaustedContinuationService is Horizon-scoped: evaluate foreign
   continuations in their authoritative Horizon with complete supporting data.
   Keep local counterpart discovery and deduplicate the combined population.
3. Complete the hydration/presentation integration: a foreign candidate must not
   disappear merely because the original ParticipationContext lacks its active ID,
   and secondary comparison data must not become shared Receipt context. Trace
   schema and contribution validation so foreign Gates are authorized only
   as `other_proposition_id` for Reconcile, never as Traverse/entry/recovery choices.
4. Execute cross-Horizon Reconcile in tests. Prove both causal parents survive,
   owner and Horizon remain the acting Cycle's, and lineage follows its Phase Gate.
   Prove foreign Receipts are not imported and Potential records foreign inputs
   separately from local terrain. Check graph provenance and every rejection case
   in the Stage 2 task brief.
5. Run focused Mediate/Reconciliation, CycleRegionOwnership, PhasePositionInvariant,
   CausalOperationalScope and CausalRecovery coverage, then Query Regression and
   remaining aggregate suites as budget permits. Record exact results in the PR.

Recognition is not movement. Reconcile will create the explicit causal bridge;
its result remains owned by the acting Cycle's Region and Horizon. Bias, Order,
Closure cross-reference and next-Cycle lineage handoff are deferred beyond Stage 2.
