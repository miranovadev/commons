# Observable calculation

The [Core Invariants](core-invariants.md) govern these calculations. In
particular, measurement independence (1), representation versus semantics (2),
and distinct reference frames (7) constrain every projection described below.

Movement is governed independently by
[Invariant 17](core-invariants.md#17-lineage-constrained-participant-movement).
Measurement ancestry, a calculation population and visible evidence do not
define Participant movement. Forward movement follows the current Gate's causal
continuation; recovery follows recorded ancestry backward. No edge means no
movement.

Correspondence and Chirality compare each Proposition's raw/local lineage with its
provenance in the preceding complementary Context Horizon. Closure has a distinct
committed-lineage path: Horizons supply Domain labels, never comparison populations.
Raw evidence evaluations never read accumulated measurement projections.

## Substrate-level structural attenuation

The persisted measurement is an accumulation of independently calculated raw
results. Starting at the current or prospective head (distance 0), walk incoming
Supersession edges, including each ancestral Committal Proposition and stopping
that branch there. A Committal head itself still expands its incoming lineage.
Shared ancestors count once, at their shortest structural distance. Unrelated
branches and other substrates are excluded. Participant display/read limits do
not change this geometry.

For Correspondence and Chirality independently:

\[
a(d)=\frac{1}{(1+d)^2},\qquad
M=\frac{\sum_P m_{local}(P)\,a(d_P)}{\sum_P a(d_P)}
\]

Every visited position contributes denominator weight, including neutral local
values. Distances 0, 1, 2, 3 and 4 have weights 1, 1/4, 1/9, 1/16 and 1/25.
There is no recursive attenuation of a running aggregate and no final clamp.
For a two-position lineage, local values (head, parent) of (0, 1) yield 0.2;
(1, 0) yield 0.8; (1, 1) yield 1. Thus unanimous lineages retain full magnitude,
while distant signals amongst neutral positions are diminished. Results are rounded
to seven decimal places. Closure uses the head's committed structure directly;
expressive ancestry does not attenuation-average Depth, Cadence or Closure.

The inverse-square historical mass converges along a single chain. Branching
can add more mass; the shared denominator still keeps every result in [-1, 1].
Preview and actual Commitment use the same geometry and denominator, including
the prospective head. Closure hysteresis instead resolves the direct committed components.

Tension, raw Charge and raw Potential consume these accumulated results without
re-discounting continuation length. This evidence accumulation is distinct from
observing a candidate from a Participant's position; it does not already encode
that observer-to-candidate distance.

### Potential observed from a Participant position

Initial entry has no position and uses its documented Potential/Charge/ID
selection. Ordinary completion continues at the exact Committal reached through
the current Gate's causal continuation, without a new global ranking.

Rest recovery walks backward from the actual terminal position through recorded
arrival provenance and stops at the nearest eligible non-exhausted Committal.
Older ancestors cannot compete while that basin remains unresolved. After
selection, its diagnostic effective Potential is `raw Potential × a(d)` and
effective Charge is `raw Charge × a(d)`, where `d` is actual backward edge
distance. These values do not select the destination. Measurements and immutable
snapshots are unchanged.

Every backward edge counts, including revisions and historical intermediate
nodes. A multi-input Write follows the Participant's recorded arrival, not an
unused parent shortcut. A retained eligible Committal has distance zero;
Expressive positions inform distance but are not entry candidates. Current-Horizon
scope holds until exhaustion permits crossing its opening boundary. The walk
then continues on the same lineage into previous Horizons, retaining distance
from the original terminal position. It never resets to a Horizon provenance
node or searches siblings, descendants or unvisited branches.

Fresh open basins are not exhausted merely because their Potential is zero.
Explicitly exhausted structure is excluded from operational Potential; exhaustion
of a child does not prove its parent exhausted. Generic `selectFrom()` remains a
diagnostic ranking utility over shortest parent/child geometry. That broader
geometric reachability grants no recovery or traversal eligibility. No edge
means no movement. See [recovery basins](recovery-basins.md).

For each raw comparison, the boundary Proposition remains a member of the closing Horizon. It acts as
the provenance root for the next Horizon without being duplicated into its
measurement population.

## Local Correspondence

> **Canonical v2 semantics:** [Correspondence v2 — canonical structural semantics](correspondence-v2-cardinal-semantics.md).
> That document is normative for the pending correction and supersedes the earlier Phase-bucket/head-only v2 interpretation.

Raw/local Correspondence has two equally weighted structural components:

```text
raw Correspondence
= 0.5 × Shape Topology
+ 0.5 × Cardinal Position
```

Shape Topology compares the selected all-parent causal morphology. Cardinal Position compares the occupied `(Domain Depth, Lateral Position)` morphology. Domain Depth is breadth-first depth from the Domain provenance Committal and continues across Cycle/Commit boundaries without resetting; it is not Phase Position and not Closure's Committal Depth.

Each Domain constructs its cardinal layout independently. Measurement ancestry then selects already-positioned propositions, including genuine foreign parents with coordinates from their own Domains. No synthetic causal or placement edge is introduced.

The completed immediately preceding complementary Domain is the structural reference and is revealed only through the current head's Domain Depth. Position is morphology-wide rather than a single head-to-head ordinal comparison. Within each included Domain Depth, selected occupied Lateral Positions are paired left-to-right; unmatched occupancy contributes `-1`; that row's pair scores are averaged. Cardinal Position Correspondence then equally averages the row scores across included Domain Depths. Wide rows therefore affect their own row score without receiving extra historical weight.

Origin retains its documented raw Correspondence of `-1`. The first developed Domain after Origin has raw structural Correspondence `0` because no developed complementary morphology exists; it is not compared against Origin's one-node structure. Existing inverse-square attenuation alone carries and fades Origin's historical influence. Once a subsequent Domain has a developed complementary predecessor, ordinary structural comparison begins.

After each proposition's raw/local Correspondence is calculated, the existing inverse-square structural attenuation described above remains unchanged.
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

Bias compares each active Stance/Frontier head's retained direction with the
weighted aggregate of its own causal ancestry. It excludes the candidate's own
contribution, deduplicates both reconciliation paths, and stops at the governing
Committal. At zero, each direction retains its own preceding nonzero history,
initialized from that Committal. Historical ancestors remain comparison context
without duplicating active reinforcement. This is independent of the sign of
the final cross-domain Chirality product. Bias requires a Receipt and effective
Attestation, then preserves content and proposition evidence while resetting the
new classification's votes and touches. It promises no population-wide lean target.
Mediate uses the previous Disperse Gate's retained Correspondence direction
against Read-equivalent members of its Commit Region. Bias uses Gate-equivalent
members of that same structural scope. Closure evaluates each causally local
Write's own lineage/history and participation independently.
The Context Horizon remains comparison context. See
[participation](participation.md#shared-commit-region-scope).

Mediate — Read and Bias — Gate may calculate over regional populations. Only
Mediate exposes compatible foreign Gates operationally as reconciliation operands;
Bias exposes only its current causal Gate. Closure, performed during Order —
Write, is not a region-wide cross-lineage comparison. Calculation membership never
creates operational options or traversal edges. Local expressive Writes remain
visible independently of Closure; Commit requires full admissibility and only
each Write's own direct Committal may be traversed.

## Committed-lineage Closure

Closure follows the direct chain from a Committal through its creating Phase and
Cycle to `entry_committal_id`. No Supersession-parent union, latest-Horizon
Commit, sibling or abandoned descendant supplies Closure history.

Depth compares the current consecutive Domain-run length with the immediately
preceding opposite-Domain run length, counting only ancestral Committals and the
candidate. Recovery retains shared ancestors and excludes abandoned descendants.

Cadence compares the latest two direct ancestral inter-Commit intervals. For A → B,
freeze the number of other Cycles entered at A and completed before B commits, plus B's
producing Cycle exactly once. Rest counts. Other unresolved Cycles do not count.
All expressive paths within A's basin count; other entry Committals, including siblings
and descendants, do not. Every real interval is at least one. Cadence continues
across Domain boundaries. Later A → B2 may include more completed participation,
without changing the earlier frozen A → B interval.

Both similarities are `1 - 2 * abs(a - b) / max(a, b)`, bounded to [-1, +1]
and rounded to seven decimal places. Origin sets Depth and Cadence to -1. C1
explicitly sets both to 0 and retains Origin's negative directions through normal
zero hysteresis. C2 onward is measured. Commitment always requires direction
alignment; there is no bootstrap eligibility exception.

With structural components D and C, counted participants N and the existing
contribution/receipt ratio q (default 3), Closure is `(D + C + N/q) / (2 + N/q)`.
The participant cap, Receipt qualification, Commit-source inheritance and
reclassification rules remain in ReinforcementService. This weight is applied at
the measured head, without propagating Closure through expressive ancestors.
Expressive propositions inherit their Cycle entry Committal snapshot's structural components;
they do not extend committed Domain runs or invent intervals. Persisted Committals
retain their historical snapshot values.

`cadence_interval` is immutable on the successor CommitmentSnapshot, including C1.
The historical backfill uses original Cycle entry, producing identity and strict completion cutoffs;
other-Cycle timestamp ties leave the interval unavailable. Existing observables
are never rewritten. An unavailable predecessor interval blocks prospective
Commitment explicitly; it is not replaced with an old formula or neutral guess.
See [the implementation and transition report](closure-basin-cadence-investigation.md).

## Commit participation inheritance

A prospective or persisted Committal head inherits its Gate's participation
state through incoming **Commit edges only**. Its touch count combines its own
Phases with the Gate's Phase history, transitively across consecutive Commits.
Touches remain participation history, but do not supply Closure. Preview performs
no writes and includes its producing Cycle exactly once in the prospective interval.

Effective Attestations and completed Receipts remain accessible through the same
inheritance resolver. They retain their original IDs, authors, targets and
supersession history; no evidence rows are copied. Completed Receipts on effective
Attestations still ground the committed state. Counted Reinforcement follows Commit sources and reclassification receipt links,
deduplicating Participants rather than raw Receipt events. Superseded classifications
retain historical counts without duplicating active measurement contributions. Lean uses active loaded Receipt contributions
and deduplicates Attestation IDs shared by multiple Commit heads. Evidence is resolved from current
effective state; immutable Commitment snapshots preserve the observation made
at Commitment.

`PropositionStateService` resolves history through the explicit `commitSources`
relationship, including the in-memory source declared by Commit preview.
Generic `parents` describe topology. Variant, Revise and Reconcile start with
fresh contribution history. Reclassify resets Attestations and Phase touches,
and explicitly attaches the selected available Horizon Receipt, consuming it once.
The exact source content is unchanged. A later Commit resolves those links without recovering reset votes
or attestation-level evidence.

Fresh history does not remove structural ancestry or its attenuated influence.
Variant, Revise and Reconcile have no inherited Attestations, Receipts or Phase
touches; Reclassify has only the explicit proposition-evidence exception. Opening a new Phase on it adds a new touch normally; source Phases and
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
comparison. A Cycle with an entry Committal completing also invalidates affected basin previews, including Rest.
Missing/unmeasurable Potential candidates remain ineligible. No persistent raw
cache is introduced; prospective Closure uses current completed-basin participation plus its producing Cycle.

Immutable commitment snapshots continue to preserve the values observed at
commitment time. New non-bootstrap commitments record `committal-closure-v10`.

See [Attestation polarity and direction history](attestation-polarity.md) for the
normative distinction between measurement values, retained directions and labels.


