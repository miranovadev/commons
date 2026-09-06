# Observable calculation

The observable projection compares the lineage ending at a Proposition with
its provenance lineage in the immediately preceding, complementary Context
Horizon. The three measurements share that comparison boundary but are
calculated independently.

The boundary Proposition remains a member of the closing Horizon. It acts as
the provenance root for the next Horizon without being duplicated into its
measurement population.

## Correspondence

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

## Chirality

Chirality first removes Propositions that have been hard superseded inside
each lineage. It then calculates each Domain's receipt-weighted attestational
lean:

\[
D = \frac{Support - Refute}{Support + Refute}
\]

The final value is D_current × D_previous. A Domain without an active
Attestation has a neutral lean of 0.

## Closure

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

Use PropositionMeasurementService::recalculate() to calculate and replace a
Proposition's disposable measurement projection. Immutable commitment
snapshots continue to preserve the values observed at commitment time.
