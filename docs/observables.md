# Observable calculation

Each Proposition's **raw/local** evaluation compares the lineage ending at it with
its provenance lineage in the immediately preceding, complementary Context
Horizon. The three measurements share that comparison boundary but are
calculated independently by the existing calculators. "Local" identifies the
Proposition being evaluated; it does not discard the graph context needed by
WL, the cross-domain lean comparison, or Depth/Cadence. Raw evaluations never
read another Proposition's accumulated measurement projection.

## Substrate-level structural attenuation

The persisted measurement is an accumulation of independently calculated raw
results. Starting at the current or prospective head (distance 0), walk incoming
Supersession edges, including each ancestral Committal Proposition and stopping
that branch there. A Committal head itself still expands its incoming lineage.
Shared ancestors count once, at their shortest structural distance. Unrelated
branches and other substrates are excluded. Participant display/read limits do
not change this geometry.

For Correspondence, Chirality, Depth and Cadence independently:

\[
a(d)=\frac{1}{(1+d)^2},\qquad
M=\frac{\sum_P m_{local}(P)\,a(d_P)}{\sum_P a(d_P)}
\]

Every visited position contributes denominator weight, including neutral local
values. Distances 0, 1, 2, 3 and 4 have weights 1, 1/4, 1/9, 1/16 and 1/25.
There is no recursive attenuation of a running aggregate and no final clamp.
For a two-position lineage, local values (head, parent) of (0, 1) yield 0.2;
(1, 0) yield 0.8; (1, 1) yield 1. Thus unanimous lineages retain full magnitude,
while distant signals amongst neutral positions are diminished. Closure remains
the mean of accumulated Depth and Cadence. Results are rounded to seven decimal
places after accumulation.

The inverse-square historical mass converges along a single chain. Branching
can add more mass; the shared denominator still keeps every result in [-1, 1].
Preview and actual Commitment use the same geometry and denominator, including
the prospective head. Hysteresis resolves the accumulated Depth/Cadence signs.

Tension, Charge and Potential consume these results without another structural
discount. Recovery retains its independent nearest-viable-distance-shell search
and existing Charge/ID tie-breaks. Within that shell, continuation length does
not discount Potential a second time.

For each raw comparison, the boundary Proposition remains a member of the closing Horizon. It acts as
the provenance root for the next Horizon without being duplicated into its
measurement population.

## Local Correspondence

Correspondence is a receipt-weighted, two-iteration Weisfeiler-Leman graph
kernel:

- Activity and Signal are labelled R.
- Frontier and Stance are labelled G.
- Canon and Concord are labelled B.
- Variant Supersessions are soft (S); all replacing Supersessions are hard
  (H).
- Incoming and outgoing directions are retained in the neighbourhood label.
- Cosine similarity is mapped from [0, 1] to [-1, 1].

This gives 1 to structurally consonant lineages and -1 to lineages with no
shared structural features.

## Local Chirality

Chirality first removes Propositions that have been hard superseded inside
each lineage. It then calculates each Domain's receipt-weighted attestational
lean:

\[
D = \frac{Support - Refute}{Support + Refute}
\]

The final value is D_current × D_previous. A Domain without an active
Attestation has a neutral lean of 0.

Bias reuses this raw weighted lean over the active Stance (Frontier in Emergence)
population in the current Commit Region, deduplicated across expressive branches. Other registers do not supply votes to this Gate.
The single aggregate direction admits only Propositions with an effective
hysteretic direction matching it, including the current Gate when eligible.
A zero aggregate lean retains the Gate's inherited direction, and a Proposition
with zero local lean retains its own established direction, falling back to its
primary parent only if none exists. Neither condition
changes the numeric Chirality value from zero. It does not use the sign of the
final cross-domain Chirality product. Unary hard reclassification removes the selected source from the active
Stance field; the fresh Concord has none of its historical votes. This removes a
contribution in the dominant direction and tends the field toward balance,
without promising monotonic absolute-lean reduction for every discrete choice. Mediate uses the previous Disperse Gate's retained Correspondence direction
against Read-equivalent members of its Commit Region. Bias uses Gate-equivalent
members of that same structural scope; Closure uses Write-equivalent members.
The Context Horizon remains comparison context. See
[participation](participation.md#shared-commit-region-scope).

## Local Closure

Each local Closure evaluation observes the active heads in its relevant lineage
comparison, using the same hard-supersession rule as Chirality. Soft Variant
sources remain active; hard-superseded sources do not remain independent head
observations. Unrelated Horizon heads are not included.

Depth is the mean structural extension of these heads: each head's longest
incoming Proposition path length (counting the head) multiplied by its Receipt
reinforcement weight. Historical nodes establish path topology, but their own
Receipts do not independently reinforce an active head unless inherited through
Commit. This retains structural development while removing the historical
population as the set of observations.

Cadence is the population coefficient of variation (standard deviation / mean)
of **Phase touches per active head**. Each persisted Phase whose Gate is a
Proposition contributes one touch, including the open Phase. Repeated Phases
count separately; completing or rereading a Phase adds no touch. Neither cycle
sequence, event spacing nor wall-clock duration enters the calculation. An empty
population, all-zero counts or a single head has CV 0. Untouched active heads
remain in the population with count 0.

For example, Gates A, B, B, C, B give counts A=1, B=3, C=1. Distributed counts
(2,2,3,2) have CV approximately 0.19245, whereas concentrated counts (1,1,9,1)
have CV approximately 1.15470. These observations exist before any non-Origin
Commitment; Cadence never waits for an inter-Commit interval.

Depth and Cadence are independently compared with:

\[
1 - 2\left(\frac{|A-B|}{\max(A,B)}\right)
\]

When both inputs are zero the result is persistent (1). Closure is the
average of the resulting Depth and Cadence values.

The comparison remains similarity to the complementary lineage, rather than a
new absolute concentration threshold. Matching distributions resolve positive;
different CVs can resolve negative. When the reference CV is zero and the current
CV is nonzero, the existing comparison resolves -1.

Both components still enter the shared measurement-layer weighted mean exactly
once. Earlier positions have their own local active-head comparisons and remain
present as distance-attenuated ancestry; no historical population is added as a
second set of head observations or attenuated again in Closure.

## Commit participation inheritance

A prospective or persisted Committal head inherits its Gate's participation
state through incoming **Commit edges only**. Its touch count combines its own
Phases with the Gate's Phase history, transitively across consecutive Commits.
There is no synthetic extra touch for the proposed Commit. Both components use
the prospective post-Commit head set, and preview performs no writes.

Effective Attestations and completed Receipts remain accessible through the same
inheritance resolver. They retain their original IDs, authors, targets and
supersession history; no evidence rows are copied. Completed Receipts on effective
Attestations still ground the committed state. Proposition reinforcement retains
its non-Citation, signed-Receipt rule, including completed inherited Proposition
Receipts. Each head's reinforcement counts each original Receipt once, and lean
deduplicates Attestation IDs shared by multiple Commit heads. Evidence is resolved from current
effective state; immutable Commitment snapshots preserve the observation made
at Commitment.

`PropositionStateService` resolves history through the explicit `commitSources`
relationship, including the in-memory source declared by Commit preview.
Generic `parents` describe topology only. Variant, Revise, Reclassify, Reconcile
and every other non-Commit hard supersession start with fresh contribution
history, regardless of whether the statement is preserved. A later Commit cannot
reach back across one of those resets to inherit earlier history.

Fresh history does not remove structural ancestry or its attenuated influence.
It means the new Proposition has no inherited Attestations, Receipts or Phase
touches. Opening a new Phase on it adds a new touch normally; source Phases and
contribution records remain attached to their original Propositions.

## Reinforcement and bootstrap

Only uniquely identified, non-Citation Receipts provide calculation weight.
The default contribution-to-Receipt ratio is 3:1. A Substrate can override it
through the observables.contribution_receipt_ratio configuration value.

The first Context Horizon has no complementary Domain to compare. Its
documented bootstrap result is maximum Turbulence, represented by -1 for
Correspondence, Chirality, and Closure.

Use LocalMeasurementService::calculate() for a raw result and
PropositionMeasurementService::recalculate() for the accumulated disposable
projection. The additive calculator_version migration marks old projections
as obsolete; normal measurement repair and Potential reads refresh obsolete
projections before use. A new or changed Phase Gate invalidates disposable
projections in its substrate because touches can affect either side of a
comparison. Completion without a Gate change does not invalidate them.
Missing/unmeasurable Potential candidates remain ineligible. No persistent raw
cache is introduced; prospective Closure always evaluates current Phase history.

Immutable commitment snapshots continue to preserve the values observed at
commitment time. New non-bootstrap commitments record active-head-phase-touch-v2.

### Retained directions

`MeasurementDirectionService` resolves the current nonzero value, then the
Proposition's own established direction, then its primary parent's lineage.
Zero and unmeasured values never erase an available direction. The primary parent
uses the existing Supersession creation order, with ID as a deterministic tie
breaker. Root defaults are positive for Correspondence, Chirality and lean, and
negative for Closure.

`proposition_directions` holds durable directional memory independently of the
replaceable `proposition_measurements` rows. Projection writes atomically retain
Correspondence, Chirality and Closure directions; Attestation and Receipt ledger
writes also retain local lean, without requiring a plan read. Numeric values,
attenuation and the cross-Domain Chirality product remain unchanged. The upgrade
preserves recoverable existing projection signs and current effective lean; historical signs already erased
by a zero projection cannot be recovered and use lineage/default fallback.

The audit also covers Closure's component directions and Tension. Depth and
Cadence already retain the latest evaluated committed ancestor's directions at
zero (negative defaults), including reconciliation ancestry, and gate on their
alignment rather than their numeric average. Preview remains read-only. Committed
Tension already retains the active Horizon's Domain at zero. Prominence and
Turbulence are nonnegative magnitude projections used numerically, with no
zero-based candidate exclusion. Existing component, projection and Tension
formulae and committed-history rules are unchanged.
