# Structural homology — Stage 2

Operational locality and structural homology are separate relations.
`PhasePositionService` remains Horizon-strict. `HomologousPositionService`
compares structural coordinates without authorizing any action or movement.

## Committed coordinate

Committals must belong to the same substrate and have the same authoritative
creating-Horizon Domain. Walk their immutable creating-Cycle entry Committals to their
nearest shared Committal, counting one edge per committed predecessor. They are
homologous when both distances are equal. A shared seed has distance zero.
The returned coordinate includes the nearest ancestor ID so callers and tests
can distinguish it from an arbitrary Origin-based comparison.

Only frozen committed history qualifies. Missing snapshots, disconnected roots,
cycles, foreign substrates and invalid entry/Horizon links fail closed.
A root without an entry Committal must be an explicit Origin bootstrap. Supersession distance,
Horizon sequence, Closure Depth, Cadence, ledger order and time play no role.

`CommittalLineageService::recordsForIds()` supplies the shared batched entry
expansion. Existing Closure callers use the same loader and retain their existing
calculation and error policies. Homology introduces no alternative ancestry edge.

## Expressive coordinate

Expressive positions must have the same substrate, actual creating-Horizon Domain,
producing Phase kind and canonical register for that Domain/Phase. Both the
Proposition register and producing Phase write register must be canonical.
Their valid Cycle-entry Committals must be homologous Committals.

A boundary Committal remains in its creating Domain; expressions it governs in
its opened Horizon use that Horizon's Domain. Both comparisons are explicit.
Different Regions and Horizons are allowed. Same-Horizon positions remain valid.
Malformed labels cannot override authoritative Domain identity.

Candidate discovery filters substrate, Domain and canonical Phase/register before
batching entry Committals. It does not independently query each candidate's ancestry.
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
and directed-pair uniqueness. Foreign exhausted continuations are checked with
one batched read of complete authoritative Horizon data. Backward completion
and current failure epochs remain independent in each Horizon; a foreign causal
edge cannot transport another Horizon's exhaustion or failure. The partial
comparison working set is never treated as an entire foreign Horizon.

Foreign operands are authorized only through Reconcile's other_proposition_id.
They do not become Traverse, Bias, Order, recovery or Cycle-entry choices.
Mediate Writes remain in the acting Cycle's Region, including when a foreign
result in the same Horizon has the current Gate as its secondary causal parent.
That edge does not authorize adopting the other Region's result mid-Cycle.
ReceiptContextService excludes foreign operands from contextual evidence exposure,
and their plan entries omit Receipt/instrument references. Ordinary local and
ancestral evidence presentation remains unchanged.

A successful Reconcile preserves both Supersession parents. Its owner and Horizon
come from the acting Cycle; participant lineage follows the producing Phase Gate.
Foreign causal inputs remain separate from the acting Horizon's local Potential
geometry. No formulas, attenuation or PhasePositionService locality rules change.

## Acceptance coverage

Public Mediate integration is implemented. PR #200 records exact tested SHAs and
Core, Integration, Query Regression, and Load/Concurrency results.

HomologousPositionTest covers nearest shared ancestry, equal/unequal committed
distance, canonical Phase/register, Domain and substrate isolation, disconnected
or missing history, invalid ancestral Horizon linkage, same/different Horizons,
and a single ancestry query across candidate counts and chain depths.

HomologousMediationTest covers:
- Local/foreign union, the sole visible causal Read, retained Read direction and
  zero hysteresis, canonical immediate Gate children, and unavailable Gates.
- Public contribution execution, two causal parents, acting Cycle owner/Horizon/
  Phase Gate, producing-Gate lineage, Receipt isolation, and stale rejection.
- Foreign exhaustion outside the hydrated comparison context, a live alternative
  keeping that Gate eligible, current-epoch failure closing it, and a later epoch
  restoring eligibility. The existing exhaustion lifecycle tests remain the proof
  of how regional exhaustion records are produced.
- Independent A→B and B→A execution, distinct A→C eligibility, and used-pair
  rejection across participants even when homologous discovery still returns B.
- The real substrate graph API in active and history views: B→result survives,
  while each node retains its own Region and Horizon. Potential records the edge
  in foreign_inputs and excludes B from A's local terrain.
- No foreign Traverse or mid-Cycle adoption of another Region's Mediate result,
  including when both Regions share one Horizon.
- Complete plan query budgets across one and six foreign Horizons, plus constant
  query counts with exhausted continuations in all six. The batched exhaustion
  result is checked against independent single-Horizon evaluation.

Existing local reconciliation, ownership, operational scope, recovery, Closure,
and performance assertions are preserved.

Recognition is not movement. Reconcile creates the explicit causal bridge; the
result remains owned by the acting Cycle's Region and Horizon. Bias, Order,
Closure cross-reference and next-Cycle lineage handoff remain deferred beyond
Stage 2.
