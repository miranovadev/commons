# Structural homology

Operational locality and structural homology are separate relations.
`PhasePositionService` remains Horizon-strict. `HomologousPositionService`
compares structural coordinates without authorizing any action or movement.

## Committed coordinate

Committals must belong to the same substrate and have the same authoritative
creating-Horizon Domain. Homology compares their committed depth: the number of
immutable creating-Cycle entry edges back to Origin. Complete valid histories
must terminate at the same Origin; disconnected roots fail closed. Nearest shared
ancestor and distance remain diagnostic output, not an additional constitutional
eligibility condition. Canonical position comparison also checks producing Phase,
register and tier; committed Write positions are Order results.

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
Their valid Cycle-entry Committals must have the same committed depth and Origin.
The entries' creating Domains may differ: the expressions' own Domain is decisive.

A boundary Committal remains in its creating Domain; expressions entered there in
its opened Horizon use that Horizon's Domain. Both comparisons are explicit.
Different Regions and Horizons are allowed. Same-Horizon positions remain valid.
Malformed labels cannot override authoritative Domain identity.

Candidate discovery filters substrate, Domain and canonical Phase/register before
batching entry Committals. It does not independently query each candidate's ancestry.
The structural service deliberately leaves active/exhausted state, retained
Correspondence, directed-pair uniqueness and other action policies to its consumer.

## Bias integration (epic Stage 4)

`HomologousBiasService` discovers homologous Mediate Gates and consumes their
independent numeric Chirality through the shared population/direction services.
It contributes only additional dominant-matching active, unexhausted sources;
`BiasGateService` independently preserves the current Gate's old local rule.
`PhaseGenerationService` and dispatch still require an effective source
Attestation, entry-catalogue Receipt and unused source/register classification.

The selected source can belong to another Region/Horizon. Receipt consumption is
from the acting Cycle-entry catalogue, and the new classification belongs to the
acting Phase, Horizon and accounting Region. Its sole reclassify edge starts at
the selected source. `CausalWriteService` recognizes that exact Phase's recorded
classification as its produced continuation, without adding a fictitious edge
from the old Gate or adopting foreign results as local Writes. Generic Traverse,
visible Read, foreign evidence and PhasePositionService are unchanged.

## Mediate integration (epic Stage 3)

Mediate combines its established local/regional comparison pool with foreign
homologous Constrain Reads. The prior Disperse Phase's causal Gate remains the
only participant-facing Read. The full homologous Read population, including
that anchor once, determines dominant Correspondence from independently measured
values. Matching Reads contribute additional Gate operands; the ordinary local
path is never vetoed by a contrary population direction. A zero population mean
retains explicit population history or uses Correspondence's positive default.
Member zero hysteresis remains independent of population history. Gate Correspondence
and Attestation lean do not select counterparts.

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
and their plan entries omit Receipt/instrument references. This masking uses
actual Region ownership: a same-Region Gate discovered through homology keeps its
ordinary local evidence presentation. Ancestral evidence presentation is unchanged.

A successful Reconcile preserves both Supersession parents. Its owner and Horizon
come from the acting Cycle; participant lineage follows the producing Phase Gate.
Foreign causal inputs remain separate from the acting Horizon's local Potential
geometry. No formulas, attenuation or PhasePositionService locality rules change.

## Acceptance coverage

Public Mediate integration is implemented. PR #200 records exact tested SHAs and
Core, Integration, Query Regression, and Load/Concurrency results.

HomologousPositionTest covers diagnostic shared ancestry, equal/unequal committed
depth, canonical Phase/register, Domain and substrate isolation, disconnected
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
the #200 checkpoint.


## General Homeostatic population (epic Stage 2)

The reusable population and dominant-direction layers are documented in
[Homeostatic populations](homeostatic-populations.md). They include the current
position, compare the propositions' own Domain and committed depth, and aggregate
independently derived measurements without writing them. Stage 3 now consumes this aggregate for additional Mediate operands and removes
the temporary entry-creating-Domain restriction. Proposition Domain and committed
depth remain authoritative. Stage 4 adds Bias sources as described above; Order
population integration remains deferred to Stage 5.


Stage 3 coverage includes both signs of disagreement between causal Read and field,
the matching case, same-Region expansion, default-directed zero fields, Reads without
Gate children contributing to the population, mixed entry creating Domains,
stale population rejection, and public non-dominant-causal-Read reconciliation.
Existing graph, Receipt, directed-pair, foreign exhaustion and query-budget proofs
remain in force. [Homeostatic populations](homeostatic-populations.md) documents
the shared aggregation and hydration boundary.

Stage 4 coverage in `HomologousBiasTest` proves additive local/dominant options,
non-dominant and unattested rejection, zero defaults without state writes, public
Reclassify in both Region directions, entry-only Receipt consumption and provenance,
result ownership and forward progression, stale rejection, wrong coordinates and
substrates, no generic Traverse, same-Horizon result isolation, complete foreign
exhaustion and bounded plan queries across growing populations.

A recorded unary Bias classification may resolve its causal provenance to the
selected source's Region while retaining the acting Cycle's accounting entry.
`ActiveParticipantLineage` recognizes that recorded bridge, including through
later revisions; unrecorded foreign edges still fail the provenance check.
Potential retains the edge as a foreign input and excludes it from local terrain,
just as for existing foreign Reconcile inputs. No Potential formula changes.

