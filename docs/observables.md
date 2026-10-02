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

## Unified late-phase admissibility

**Dominance selects candidate inputs. Admissibility governs Writes.**

| Phase | Observable | Homologous dominance selects | Native Write condition | Write |
| --- | --- | --- | --- | --- |
| Mediate | Correspondence | Reads, then their existing Disperse Gate operands | Shape Topology direction = Cardinal Position direction | Reconcile |
| Bias | Chirality | Existing Gates | Orientation direction = Attestation Cadence direction | Reclassify |
| Order | Closure | Existing frozen Commits | Committed Cardinal Position direction = Cadence direction | Commit |

Observable values describe relations. Population direction selects where
homeostatic pressure is applied. Component-direction agreement supplies a Write
condition alongside the phase's existing authority requirements. Neither observable
pole is preferred: Positive/Positive and Negative/Negative are equally coherent.
Numeric zero retains the established component history; it is not directionless.

Measurement population is distinct from actionable population. Mediate and Bias
resolve dominance over their full populations before filtering eligible sources.
Mediate additionally requires compatible combined Correspondence directions for
its binary operands. Bias has no pairing rule: local, mediated and homologous
authorities remain distinct, with mediated precedence independent of dominance.
An inadmissible local Bias source does not veto an authorized optional source.

Order begins at its existing expressive Gate/Write. Keep the established
prospective-Commit Closure preview, producing-Cycle history, cadence interval and
preview-versus-persisted guard. Each expressive birth freezes its own Closure using the same committed-history calculation. Each local Write remains visible when Commit is blocked. Existing
homologous Commits embody their historical admissibility; selection uses frozen
resolved directions and current structural constraints, never a fresh Closure
preview on those Commits. Existing compatibility behavior remains: absent or
opposed frozen component directions supply no resolved population direction and
no matching optional continuation. Do not reconstruct or repair frozen evidence.

Causal visibility is distinct from actionability. Local causal candidates stay
readable with their blocked Write absent from the schema; optional candidates
appear operationally only under their existing authority rules. The backend
exposes `correspondence_admissibility` for Mediate, `chirality_admissibility` for
Bias, and existing `commitment.closure` / `commitment_options[id].closure` for
Order. These retain the native component names and directions, so presentation
can explain a block without deriving directions from numeric values. The
Commitment envelope also reports separate grounding and structural requirements.

Admissibility constrains transitions, not outcomes. A valid Reconcile may reach
Bias with opposed Chirality components; a valid Reclassify may reach Order with
opposed Closure components. A valid Commit establishes a transition and frozen
snapshot, not a preferred future: descendants' observable directions and Domain
trajectory remain unconstrained by the parent's admissibility. All ordinary
calculations and the Domain transition formula remain authoritative.

Dispatch revalidates selected-source authority before accepting Writes; Order
also retains its Closure observation and persisted consistency guards. Rest is
available only when no admissible non-Rest action remains. Potential is descriptive
and neither creates nor suppresses Rest authority. No new Charge, Tragedy,
Exhaustion, Receipt, movement or recovery behavior follows from coherence.

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

Origin retains raw Correspondence `+1`. Ordinary structural comparison begins immediately in the first developed Domain after Origin, including unmatched structure against Origin's one-node morphology. Existing inverse-square attenuation remains unchanged.

After each proposition's raw/local Correspondence is calculated, the existing inverse-square structural attenuation described above remains unchanged.

```text
Correspondence
├── Shape Topology
└── Cardinal Position
```

The local head components are independently inspectable in `shape_topology` and
`cardinal_position`; only their rounded arithmetic mean forms raw Correspondence,
and only combined Correspondence receives structural attenuation. Their retained
`shape_topology_direction` and `cardinal_position_direction` follow their own
nonzero signs. At zero each independently retains its prior projected direction,
then the corresponding Cycle-entry snapshot direction if available, otherwise
Positive. Zero is never directionless. Origin's unique bounded component pair is
`(+1, +1)`, with Positive directions and mean `+1`.

Mediate alone now uses retained component agreement as existing-Gate Write
admissibility (either sign), with same resolved Correspondence direction required
for both operands. This does not constrain the Reconcile result. Combined
`correspondence_direction` remains authoritative for current consumers. Components
are Receipt-neutral; calculations perform no writes, and disposable measurement
projection owns persistence. New Commitment snapshots freeze values and directions.
Historical snapshots keep nullable diagnostics and are never reconstructed or
recalculated to populate these fields.

## Local Chirality

Chirality first removes Propositions that have been hard superseded inside
each lineage. It then calculates each Domain's Receipt-neutral attestational
lean:

\[
D = \frac{\sum_i d_i}{n}
\]

Here d_i is the signed direction of each effective Attestation under its fixed
Commit Region polarity, and n is the effective Attestation count. Support can map
to either sign. Orientation is round(D_current × D_previous, 7). A Domain without an active
Attestation has a neutral lean of 0.


Chirality v2 is `round((Orientation + Attestation Cadence) / 2, 7)`.
Both components use exactly the same causal `LineageComparison`, never whole-Horizon or global populations.
Orientation uses effective Attestation state, hard-Supersession active positions and Commit-source inheritance.
Attestation Cadence instead counts every historical Attestation act targeting propositions in each compared DomainLineage, including replaced Attestations.

The participation basins represented by a DomainLineage are the canonical
`entry_committal_id` values of its propositions' creating Cycles, together with its represented Committals. Each counted Cycle must enter that compared Domain/Horizon through one of these explicit seeds. Count **all**
persisted Cycles entering those basins, including unfinished, Attestation-only,
contribution-free and Rested Cycles. A Committal remaining in the Domain also represents its own basin immediately; a boundary's outgoing Cycles enter the child Domain and do not count in the parent's population.
Origin additionally represents its actual parentless producing Cycle. Never count
unrelated null-entry Cycles, sibling basins or a whole Horizon merely by membership.

If neither side has a Cycle population, Attestation Cadence is 0. If exactly one
side has a population, it is -1. With both populations present, each rate is
historical Attestation acts / Cycles. Two observed zero rates give +1; otherwise
Cadence is `round(clamp(1 - 2 * abs(currentRate - previousRate) / max(currentRate, previousRate), -1, 1), 7)`.
Population absence is distinct from an observed zero rate.

These are live observations: later Attestations and Cycle entries may change a
later calculation. There is no completion, timestamp or first-observation cutoff.
Receipts, loads, clears, instruments and rationale contribute no weight or activity.
Only the combined local Chirality undergoes existing inverse-square attenuation.
Local components remain inspectable independently; they are not separately attenuated.

Orientation and Attestation Cadence have independent retained directions. Nonzero
values establish their sign; zero retains the prior component direction, falling
back to that component's entry Commitment direction and finally its primitive default: Orientation Negative, Attestation Cadence Positive. Combined Chirality has its own retained direction and a Positive no-history default. Component disagreement
blocks a Bias Reclassify Write from that existing source; it grants no movement.
Coherent Positive/Positive and Negative/Negative sources both satisfy this additional
condition. Numeric zero uses retained component directions, never a directionless state. Frozen CommitmentSnapshots,
including old snapshots without component diagnostics, are never recalculated.

Origin requires a creator-selected effective Attestation before its snapshot.
It has Orientation 0, one current bootstrap Cycle and no previous population: Cadence -1 and Chirality -0.5. Its component
directions are Negative and Negative respectively. With Correspondence +1 and
Closure -1, existing projection produces negative Tension (approximately -1/6)
and naturally opens Settlement. The genuine Attestation establishes the positive
label frame before measurement; no Receipt or projection exception is required.
Aligned first-Settlement evidence can then produce positive Chirality and a
natural first-Commit Emergence crossover, as covered for both Origin labels in
`FreshBootstrapTest`. Ordinary Commit grounding still requires effective Attestation and
qualifying evidence, plus the existing structural and Closure conditions.

Bias compares each active Stance/Frontier head's retained direction with the
unweighted Attestation lean of its own causal ancestry. It excludes the candidate's own
contribution, deduplicates both reconciliation paths, and stops at the governing
Committal. At zero, each direction retains its own preceding nonzero history,
initialized from that Committal. Historical ancestors remain comparison context
without duplicating effective Attestations. This is independent of the sign of
the Orientation component of Chirality. Bias requires a Receipt and effective
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
Bias retains its causal Gate and may expose actionable mediated or dominant
homologous sources. Closure, performed during Order —
Write, is not a region-wide cross-lineage comparison. Calculation membership never
creates operational options or traversal edges. Local expressive Writes remain
visible independently of Closure; Commit requires full admissibility and only
each Write's own direct Committal may be traversed.

### Bias Write admissibility

Dominance selects candidate inputs; admissibility governs whether Bias may Write
from an existing Gate. `ChiralityGateService` compares retained
`orientation_direction` and `attestation_cadence_direction`. It never measures a
prospective Reclassification. Ordinary local evidence/ancestry agreement, structural
eligibility, effective Attestation and Receipt authority remain required separately.

The full homologous Gate population determines dominant numeric Chirality before
component coherence filters actionable sources. An inadmissible member still
contributes its measurement. Exact mediated incoming doorways remain independent
of that dominance and retain precedence over homologous authority. Local, mediated
and same-Region homologous sources use ordinary own-Region evidence; foreign
homologous escape still requires the existing unresolved own-Region Tragedy warrant.
There is no source/current-Gate direction pairing requirement and an inadmissible
causal Gate does not veto an independently eligible optional source.

The causal Gate remains visible with backend `chirality_admissibility` diagnostics;
ineligible optional sources are absent from the actionable set. The diagnostic's
`chirality_direction` retains its own previous direction at zero, then the entry
Commitment direction, and finally Positive only without history, separately from the ordinary local evidence/ancestry direction rule.
Dispatch checks the selected source again before result creation, causal edges,
classification registration or Receipt consumption. The result is measured normally
and need not preserve source direction or component agreement. Rest applies when
no admissible non-Rest action remains, at either zero or positive Potential.

## Closure v2: committed historical crystallization

Correspondence observes current Domain morphology. Chirality observes the current/recent
attestational relation under its existing live population semantics. Closure observes
committed historical crystallization **once, at Proposition birth**. This applies equally
to Origin, expressive Propositions and new Committals. Traversal, later Cycle entries,
Attestations, downstream Commits and alternate branches cannot change that observation.

The hierarchy and initial equal weights are:

- Closure = 0.5 × Committed Cardinal Position + 0.5 × Committal Cadence.
- Committed Cardinal Position = 0.5 × Committal Depth + 0.5 × Lateral Committal Position.

The creating Cycle's `entry_committal_id` defines the direct committed ancestry.
Supersession-parent unions, latest-Horizon Commits and abandoned descendants do not
supply historical samples. Full ancestry is used; there is no decay, truncation or
rolling window. Those choices are intentionally deferred until long-run empirical
traces can justify a memory-reduction kernel.

### Committal Depth

Count all committed occurrences of each creating Domain in the direct ancestry,
plus the newly born Proposition as one current-Domain position observation. E → S → E
therefore has counts E=2, S=1, not a consecutive E run of one. Compare with
`1 - 2 * abs(a - b) / max(a, b)`, clamped to [-1,+1]. Origin is -1; the first
post-Origin observation is 0, preserving the existing C1 bootstrap. Where no
opposite-Domain committed history exists, comparative Depth remains 0.

### Committed lateral geometry

Use only Committals, connected through their creating Cycle entry Committals.
Breadth-first rows expand parents left-to-right; committed siblings follow their
immutable ledger sequence. Existing sibling positions occupy gaps. Expressive
Domain morphology, Phase positions and Supersession placement are not reused.

At each birth, temporarily append the observed Proposition after existing committed
children of its entry Committal. This is a read-only position observation, not a
causal edge. Expressive observations never reserve slots. If A has committed child
B, expressive P and Q born under A both observe slot 2. A later Committal C observes
slot 2; the next expressive birth under A observes slot 3. Frozen observations are
never renumbered when a left sibling later acquires descendants.

Historical samples are the **frozen positions** of previous same-Domain Committals
in the direct ancestry. Their arithmetic mean is a positive real centroid; [1,2]
has centroid 1.5. Compare the new coordinate and centroid with
`1 - 2 * abs(a - b) / (a + b)`, clamped to [-1,+1]. Origin has constitutional
Lateral -1. After Origin, fewer than two previous same-Domain committed lateral
observations yields Lateral 0 with normal direction hysteresis and a null centroid.
A singleton mean is calculable but does not establish a historical tendency.
Comparison activates with two or more prior observations. The current Proposition
is never included in its historical centroid; unrelated structure supplies no samples.

### Committal Cadence

The birth interval counts all Cycles already entered in the exact entry-Committal /
creating-Horizon region, including the producer once. Empty, unfinished, rested,
Attestation-only and completed Cycles count equally. Completion timestamps play no
role. Origin's interval is the primitive 1. A Committal freezes its observed interval
on its snapshot; future development under it starts a fresh entry scope. Later
traversal through old regions never extends an already frozen interval.

Group the full ancestral frozen intervals by **creating Domain**, and append the
current birth interval to its Domain. For each Domain with at least two samples,
compute population standard deviation divided by the arithmetic mean: CV = σ/μ.
Do not use sample standard deviation. Compare CVs with
`1 - 2 * abs(CV_A - CV_B) / max(CV_A, CV_B)`, clamped to [-1,+1]. Two zero CVs
produce +1; exactly one zero CV produces -1. Equal relative variability at different
absolute scales produces equal CV.

Origin Cadence is constitutionally -1 with Negative direction. Thereafter, if either
Domain has fewer than two samples, Cadence is 0 with normal direction hysteresis.
A singleton is not evidence of regularity. This depends on available histories,
not Commit ordinal numbers. Observable scores are rounded to seven decimals;
centroids and intermediate CVs retain floating-point precision.

### Birth persistence, hysteresis and Commitment

The Proposition creation transaction serializes with Cycle entry and Commitment
under the Substrate lock. It stores the Closure observation and its historical
inputs in `closure_births`, protected against updates. Correspondence and Chirality
continue through their existing measurement pathways; Closure reads its birth
record. Disposable measurement repair may restore Closure from that durable record.
If it is absent or obsolete, repair fails closed instead of using today's population.
There is no legacy backfill from ambiguous timestamps.

Each component retains its own direction: nonzero uses its sign; zero retains the
direct committed predecessor's direction; absent history uses Negative. This includes
Committed Cardinal Position and its Depth/Lateral children. Commit requires aligned
Committed Cardinal Position and Cadence directions, plus unchanged grounding and
structural requirements. Receipt loads cannot change Closure.

Order preview evaluates a temporary new Committal using the current birth state.
The actual new Committal receives its own birth observation and measurements before
its CommitmentSnapshot freezes them. Its Closure can intentionally differ from its
expressive source's earlier Closure. Preview and actual birth are checked under the
existing write transaction. No Cycle-entry invalidation of historical Closure is added.

The Tension/Charge/Potential formulas, Domain transitions, movement, Tragedy,
Exhaustion, Recovery, Rest and action visibility remain unchanged. This correction
does not introduce continuation/path viability.

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
retain historical counts. Lean gives each effective signed Attestation one contribution
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
Reinforcement count describes qualifying evidence uptake. It never changes
Correspondence, Chirality or Closure, and has no measurement ratio.

Origin retains raw Correspondence +1 and Depth/Cadence/Closure -1; Chirality v2 is -0.5 from population asymmetry.
The first developed Domain immediately uses ordinary structural Correspondence
comparison. Chirality uses the equal mean of Orientation and live Attestation Cadence described above.
Receipt loading does not change these rules.

Use LocalMeasurementService::calculate() for a raw result and
PropositionMeasurementService::recalculate() for the accumulated disposable
projection. The additive calculator_version migration marks old projections
as obsolete; normal measurement repair and Potential reads refresh obsolete
projections before use. A new or changed Phase Gate invalidates disposable
projections in its substrate because touches can affect either side of a
comparison. A new Cycle entry changes future birth observations and affected Commit previews; existing Closure observations remain immutable. Completion does not advance Cadence.
Missing/unmeasurable Potential candidates remain ineligible. No persistent raw
cache is introduced; new birth and Commit-preview observations use current basin entries, including the producing Cycle.

Immutable commitment snapshots continue to preserve the values observed at
commitment time. New commitments record the current calculator version.

See [Attestation polarity and direction history](attestation-polarity.md) for the
normative distinction between measurement values, retained directions and labels.
