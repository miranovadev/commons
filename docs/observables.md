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

Bias reconciliation reuses this raw weighted lean and hard-supersession active
set within each shared-parent family. It does not use the sign of the final
cross-domain Chirality product. Mediate uses the nonzero Correspondence sign
of the Gate lineage and requires matching sibling Correspondence. See
[participation](participation.md#mediate-and-bias-reconciliation) for the candidate boundary.

## Local Closure

Depth is the receipt-weighted length of the longest Proposition chain.
Cadence is the population coefficient of variation of the cycle intervals
between each Participant's consecutive Propositions. A population with no
intervals has a CV of 0.

Depth and Cadence are independently compared with:

\[
1 - 2\left(\frac{|A-B|}{\max(A,B)}\right)
\]

When both inputs are zero the result is persistent (1). Closure is the
average of the resulting Depth and Cadence values.

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
projections before use. Missing/unmeasurable Potential candidates remain
ineligible. This is version repair, not a new continuous evidence-invalidation
policy. No persistent raw cache is introduced.

Immutable commitment snapshots continue to preserve the values observed at
commitment time. New non-bootstrap commitments record inverse-square-lineage-v1.
Cadence still uses cycle intervals; Phase-touch Cadence is separate future work.
