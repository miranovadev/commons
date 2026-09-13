# Observable calculation

The [Core Invariants](core-invariants.md) govern these calculations. In
particular, measurement independence (1), representation versus semantics (2),
and distinct reference frames (7) constrain every projection described below.

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

For Correspondence, Chirality, locally formed Closure, Depth and Cadence independently:

\[
a(d)=\frac{1}{(1+d)^2},\qquad
M=\frac{\sum_P m_{local}(P)\,a(d_P)}{\sum_P a(d_P)}
\]

Every visited position contributes denominator weight, including neutral local
values. Distances 0, 1, 2, 3 and 4 have weights 1, 1/4, 1/9, 1/16 and 1/25.
There is no recursive attenuation of a running aggregate and no final clamp.
For a two-position lineage, local values (head, parent) of (0, 1) yield 0.2;
(1, 0) yield 0.8; (1, 1) yield 1. Thus unanimous lineages retain full magnitude,
while distant signals amongst neutral positions are diminished. Closure propagates its already-formed local value; it is not recomputed from
attenuated evidence counts or merely averaged from accumulated Depth/Cadence.
Results are rounded to seven decimal places after accumulation.

The inverse-square historical mass converges along a single chain. Branching
can add more mass; the shared denominator still keeps every result in [-1, 1].
Preview and actual Commitment use the same geometry and denominator, including
the prospective head. Hysteresis resolves the accumulated Depth/Cadence signs.

Tension, raw Charge and raw Potential consume these accumulated results without
re-discounting continuation length. This evidence accumulation is distinct from
observing a candidate from a Participant's position; it does not already encode
that observer-to-candidate distance.

### Potential observed from a Participant position

Ordinary Cycle entry ranks eligible Committal Propositions by Potential Charge
as observed from the Participant's retained position, including substrate
attenuation. Raw global Potential Charge alone does not determine entry.

For a candidate at shortest existing parent/child edge distance `d`, effective
Potential Charge is `raw Potential Charge × a(d)`. Charge fallback uses
`raw Charge × a(d)` with the same inverse-square weight defined above. This
weights the destination score once; it neither recalculates the candidate's
measurements from the observer nor changes immutable Commitment snapshots.

Distance crosses Committal boundaries and counts historical Supersession edges,
with each position visited once at its minimum distance. Only paths within the
same Substrate count. Disconnected candidates cannot provide entry or fallback.
The retained position has distance zero if it is itself Committal; an Expressive
position informs distance but never becomes a candidate. Rank all reachable
candidates by effective Potential, then effective Charge, then shorter distance
and stable Proposition ID. Compare scores before rounding them for presentation.

A Participant without a position uses the existing initial Potential/Charge/ID
selection; no observer distance is invented. Rest recovery remains a separate
nearest-viable-distance-shell search with its existing positive-Potential,
exhausted-position exclusion and Charge/ID rules. It does not apply ordinary
entry's destination weighting or zero-Potential fallback.

For each raw comparison, the boundary Proposition remains a member of the closing Horizon. It acts as
the provenance root for the next Horizon without being duplicated into its
measurement population.

## Local Correspondence

Correspondence is a reinforcement-weighted, two-iteration Weisfeiler-Leman graph
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
each lineage. It then calculates each Domain's reinforcement-weighted attestational
lean:

\[
D = \frac{\sum_i w_i d_i}{\sum_i w_i}
\]

Here d_i is the signed direction of each effective Attestation under its fixed
Commit Region polarity, and w_i is its counted Reinforcement weight. Support can map
to either sign. The final value is D_current × D_previous. A Domain without an active
Attestation has a neutral lean of 0.

Bias reuses this raw weighted lean over the active Stance (Frontier in Emergence)
population in the current Commit Region, deduplicated across expressive branches. Other registers do not supply votes to this Gate.
The single aggregate direction admits only Propositions with an effective
hysteretic direction matching it, including the current Gate when eligible.
A zero aggregate retains the population's own preceding direction from ledger
history. An individual Proposition also retains its preceding effective direction
at zero; absent evidence, it defaults to its governing Committal direction. Neither condition
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
incoming Proposition path length (counting the head). Reinforcement does not
change that structural length. Historical nodes establish path topology rather
than extra active-head observations.

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

When both inputs are zero the result is persistent (1). With local component
values D and C, counted participants N and contribution/receipt ratio q (default 3),
local Closure is `(D + C + N/q) / (2 + N/q)`. This bounded persistence-weighted
mean preserves the old two-component mean at N=0. See [Reinforcement](reinforcement.md)
for qualification and why local Closure must form before attenuation.

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
Attestations still ground the committed state. Counted Reinforcement follows Commit-only source references and deduplicates
Participants, never raw Receipt events. Lean uses active loaded Receipt contributions
and deduplicates Attestation IDs shared by multiple Commit heads. Evidence is resolved from current
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

Only qualifying Cite or verified Hyperlink evidence currently loaded by a Participant contributes active Reinforcement.
Unloaded evidence, legacy signatures without verification provenance and unsupported types do not count.
The default contribution-to-Receipt ratio is 3:1. A Substrate can override it
through the observables.contribution_receipt_ratio configuration value.

The first Context Horizon has no complementary Domain to compare. Its
documented bootstrap result is maximum Turbulence, represented by -1 for
Correspondence, Chirality, and the initial Depth/Cadence values. The Origin has
no counted Reinforcement, so its initial Closure is also -1. Later qualifying
reinforcement can change local Closure even without a complementary Domain.

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
commitment time. New non-bootstrap commitments record receipt-context-lineage-v6.

See [Attestation polarity and direction history](attestation-polarity.md) for the
normative distinction between measurement values, retained directions and labels.
