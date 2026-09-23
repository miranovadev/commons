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

## Mediate integration checkpoint

Mediate combines its established local/regional comparison pool with foreign
homologous Constrain Reads. The prior Disperse Phase's causal Gate remains the
only participant-facing Read. Read retained Correspondence (including zero
hysteresis) qualifies counterparts; Gate Correspondence and Attestation lean do
not select them.

HomologousMediationService separates structural discovery from hydrated comparison.
ParticipationContext includes those candidate IDs and their dependencies only
during Mediate. Data hydration does not confer Region evidence scope, action
eligibility or movement. MediationAnchorService shares the existing causal anchor
definition without a dependency cycle between local and foreign discovery.

Immediate active canonical Disperse children in each eligible Read's own Region
and Horizon become operands. ReconciliationOptionService still applies availability
and directed-pair uniqueness. Foreign exhausted continuations are checked once
per distinct foreign Horizon using its complete data, rather than treating the
partial comparison working set as an entire Horizon.

Foreign operands are authorized only through Reconcile's other_proposition_id.
They do not become Traverse, Bias, Order, recovery or Cycle-entry choices.
ReceiptContextService excludes foreign operands from contextual evidence exposure,
and their plan entries omit Receipt/instrument references. Ordinary local and
ancestral evidence presentation remains unchanged.

A successful Reconcile preserves both Supersession parents. Its owner and Horizon
come from the acting Cycle; participant lineage follows the producing Phase Gate.
Foreign causal inputs remain separate from the acting Horizon's local Potential
geometry. No formulas, attenuation or PhasePositionService locality rules change.

## Validation and continuation

Public Mediate integration is implemented in this draft checkpoint. It must pass
CI and the remaining acceptance work before Stage 2 is considered complete.
The PR records exact tested SHAs and run results.

New tests exercise schema exposure and public contribution execution, parent
retention, Cycle owner/Horizon/Phase Gate, producing-Gate lineage, Receipt isolation,
Potential foreign_inputs, and stale counterpart rejection. Existing local
reconciliation tests remain intact.

Remaining work:
- Review the new CI results and fix failures without relaxing behavior assertions.
- Add a focused foreign-Horizon exhausted-continuation regression and verify
  directed-pair inverse/distinct behavior across Horizons.
- Check the substrate graph response itself retains the foreign parent edge;
  the current execution test checks persisted edges and graph ownership projection.
- Extend growth/performance checks to multiple foreign Horizons, then run Query
  Regression and Load/Concurrency and confirm aggregate validation.
- Review the final diff against the complete Stage 2 task brief, including all
  rejection and no-movement requirements, before marking ready.

Recognition is not movement. Reconcile creates the explicit causal bridge; the
result remains owned by the acting Cycle's Region and Horizon. Bias, Order,
Closure cross-reference and next-Cycle lineage handoff remain deferred beyond
Stage 2.
