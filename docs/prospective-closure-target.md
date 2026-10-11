# Prospective Closure: approved Stage 3 contract

> **Approved for Stage 3 implementation; not implemented here.** The current
> [birth-frozen Closure v2](observables.md#closure-v2-committed-historical-crystallization)
> and [Closure hysteresis](closure-hysteresis.md) remain descriptions of
> running code. The October 11 implementation instructions approve the
> replacement below and retain attenuation A. No historical backfill or
> deletion is authorised. Remaining implementation questions are recorded in
> [the Stage 3 review](prospective-closure-implementation-review.md).

## Observable independence and lifecycle

The three independent measurements resolve **before** any historical
attenuation is considered.

| Event | Correspondence | Chirality | Closure |
| --- | --- | --- | --- |
| Expressive birth | Resolve local structural comparison once | Initialise fresh local state | Initialise prospective state |
| Targeted Attestation create/change | Unchanged | Recalculate targeted proposition-local state | Unchanged until governing Cycle trigger |
| Genuine Cycle entry at governing Committal | Unchanged | Unchanged without an Attestation | Recalculate prospective state for **all** expressives governed by that Committal |
| Ordinary structural progression | No retroactive rewrite | No retroactive Attestation recalculation | No extra trigger except a defined Cycle entry |
| Commit | Preserve selected resolved expressive state and component directions | Preserve | Preserve |

Cycle-triggered Closure includes superseded expressive Propositions,
not merely active heads or the Phase-6 source. Each independently uses
its own selected causal history. Shared basin clock ownership does not
turn the Region into a pooled Proposition measurement population.

A new Cycle can change the prospective Phase-7 interval and hence
Closure without changing a previously resolved Correspondence or
Chirality. Later Attestations may change Chirality without changing
Closure until the next lawful Cycle-triggered evaluation. Tension/Charge
may consequently change; admissibility still evaluates underlying
component directions and ordinary authority, not Tension sign alone.

## Hierarchy and weighting

```text
Closure
├── Historical Extent (50%)
│   ├── Committal Depth (50%)
│   └── Historical Phase Profile (50%)
└── Historical Rhythm (50%)
    ├── Committal Cadence (50%)
    └── Historical Orientation (50%)
```

`Extent = (Depth + HistoricalPhaseProfile) / 2`

`Rhythm = (CommittalCadence + HistoricalOrientation) / 2`

`Closure = (Extent + Rhythm) / 2`

Equivalently, `Closure = (Depth + HistoricalPhaseProfile + Cadence +
HistoricalOrientation) / 4`. Persist the independently resolved primitives,
Extent, Rhythm and their retained directions, not just the final scalar.

Every slot retains its fixed weight. If a required comparison is
immature/unavailable, its contribution is **0**, but its maturity
metadata remains unavailable. This does not mean an observed zero or
justify reweighting the available component.

Committal Cadence requires at least two genuine committed intervals
per compared Domain to establish its CV-based comparison. Phase
Profile needs only one **genuine** interval for a Phase mean, not two.
All historical samples follow the relevant selected causal lineage and
committed ancestry. Immature Cadence remains explicitly unavailable with a
numeric contribution of zero: `Rhythm = (0 + HistoricalOrientation) / 2`.
It does not give Orientation the missing slot's weight.

Extent and Rhythm each retain independent component direction and
hysteresis. At numeric zero, use their lawful retained direction,
not a fabricated neutral third direction. Both positive/positive
and negative/negative agreement may satisfy the Closure component
coherence gate, subject to the other Commit requirements.

### Historical Orientation: signed local displacement, then arithmetic mean

The proposition-local arithmetic already implemented by
`LocalChiralityCalculator` is:

```text
Orientation(p)
    = (CurrentEffectiveLean(p) - PreviousComplementaryLineageLean(p)) / 2
```

The old product of current and previous leans is rejected. Preserve the
existing local Orientation lifecycle: a targeted Attestation create/change
resolves that Proposition's local state. Another Proposition's birth or a
Cycle entry does not independently remeasure it.

For the measured Domain D, define A(D) as Propositions that belong to the
selected causal measurement lineage and D, and are not hard-superseded
within the relevant selected historical population. Then:

```text
HistoricalOrientation(D)
    = sum(Orientation(p) for p in A(D)) / count(A(D))
```

These are independently resolved local Orientations. Do not multiply them
or apply a second signed displacement to their completed mean. A genuine
numeric zero contributes to both sum and count. An empty or unavailable
population is not an observed zero population; retain availability separately
from its neutral numeric contribution.

Shared Horizon or Commit Region membership never imports siblings into A(D).
The population supplying observations is distinct from the entire governed
expressive population receiving a Cycle-triggered Closure update.

Reuse authoritative event-born local Orientation state. Historical committed
populations retain their authoritative observation boundary: later Attestations
and hard supersessions cannot rewrite their selected members or values. In
particular, preserving a source's last authorised prospective Closure does not
permit replacing its historical inputs with newer Commit-time evidence.

If a diagnostic reconstructs an earlier local Orientation, it must process all
effective Attestations, replacements/supersession, original fixed Commit Region
polarity, event ordering, causal source identity and the observation boundary.
The last Attestation's sign alone is insufficient. The corrected October 9
145/145 replay is supporting evidence, not a production reconstruction or
backfill requirement.

### Historical Phase Profile

Reuse [C1's durable observations and selected temporal ancestry](phase-profile-target.md),
extended through the selected committed ancestry and grouped in their correct
historical Domains. Missing birth facts cannot be reconstructed from today's
population counts. The first expressive birth has a coordinate and no invented
interval; genuine zero intervals remain samples, and a single genuine interval
suffices for a Phase-local mean.

Same-Phase Revisions retain their preceding-Phase interval reference while
preserving the selected temporal predecessor chain. Reconcile follows its acting
local Gate's temporal history; its other genuine causal parent supplies no
temporal samples. Foreign-source Bias imports no incompatible basin clock and
creates no synthetic local Gate edge.

For two available nonnegative Phase means:

```text
similarity(a, b) = clamp(1 - 2 * abs(a - b) / max(a, b), -1, +1)
```

Both genuine zeros compare as +1. An unavailable comparison contributes numeric
zero with unavailable status. Average all seven fixed slots, including unavailable
slots; never reweight the remaining slots. Origin's constitutional reference
`[0, 0, 0, 0, 0, 0, 1]` is not a set of fabricated empirical intervals.

The prospective Phase-7 interval runs from the final selected expressive
observation to the potential Commit boundary using the existing governing-basin
clock and accepted boundary rules. It can change Closure at a genuine Cycle
entry without changing the previously resolved Phase-6 Correspondence.

### Independent directions

For each of Extent and Rhythm, nonzero values establish their own sign; numeric
zero retains that same component's established direction. Genuinely unestablished
state follows the established Closure bootstrap/reference frame. There is no
neutral third direction. Do not seed Extent by silently renaming old Committed
Cardinal Position direction or Rhythm by renaming old Cadence direction.

Commit requires agreement of Extent and Rhythm directions, with all ordinary
grounding and structural requirements. Both agreeing signs are valid. Numeric
Closure zero is not itself inadmissibility; favourable Tension or Charge cannot
bypass component coherence or grounding.

## Authorised event boundaries

Initial expressive Closure must use the complete authorised birth context,
including durable temporal observations and the selected genuine causal history.
Persist it within the existing contribution transaction.

Every genuine entry into a governing Committal updates all its expressive
Propositions, including active, superseded and historical ones, under the
Substrate transaction lock. Each uses its own selected measurement history.
Cycle completion, Rest, projection reads and unrelated structural writes are
not additional entry triggers. Supersession, Revision, Reconcile and Bias births
initialise their new result; they do not independently update older Closure.

An Attestation changes targeted local Chirality, not Closure. Its resolved
Orientation can enter Closure at the next authorised governing Cycle trigger.

## Prospective Phase 7 and Commit preservation

The producing expressive Phase-6 state carries its already-resolved
Correspondence. Prospective Phase 7 measures the Cycle interval up to
the potential Commit boundary and can affect Closure. Commit itself
does **not** manufacture an extra expressive measurement head or
perform an extra attenuation pass. The Committal preserves the
selected resolved Correspondence, Chirality and Closure, their
component values/directions and the field state established under
the chosen binding rule. The Committal's committed history remains
frozen; its fresh expressive descendants start their own observation
state. This is **Commit preservation**, not a generic property of
every hard Supersession.

Under the existing Substrate lock and stale-state protection, Commit validates
the selected source's current authoritative prospective Closure and ordinary
admissibility prerequisites. An intervening Cycle's updated state takes precedence
over a stale client preview. Commit introduces no measurement trigger; freeze
exactly the source state that passed validation, including component directions.

Historical snapshots and legacy birth records remain immutable. Stage 3 targets
new substrates: add the necessary new representation without backfilling,
converting or deleting experimental substrates. Do not reuse an old primitive's
field name for a different primitive. No legacy compatibility calculator is
required.

## Preserve attenuation A

The inverse-square kernel over the selected causal ancestry is
`w(d) = 1 / (1+d)^2`, deduplicating genuine shared ancestors at
minimum distance and respecting canonical Commit boundaries.
Neither candidate may pool unrelated sibling or Horizon members.

**A, approved production binding:** `T_A = (A(C) + A(Ch) + Cl) / 3`.
Correspondence and Chirality are attenuated independently; Closure
is local.

**B, unresolved diagnostic alternative:**
`T_B = A((C_local + Ch_local + Cl_local) / 3)`.
Local measurements bind into local Tension first, then Tension
is attenuated. A frozen Committal must retain the resulting
field state rather than silently recomputing a different formula.

Preserve `Charge = 1 - abs(Tension)` with the documented rounding and
Domain-transition semantics. B remains excluded from Stage 3. Natural field
changes caused by new Closure values are legitimate; do not tune authored
populations to restore earlier Emergence, Tragedy or Exhaustion trajectories.

## Diagnostic status

The C1/Closure replay and the synthetic catastrophe/revision tests support
investigation, not a guaranteed lawful natural Emergence. No current historical
Commit should be rewritten to match counterfactual values. The 145/145 local
Orientation replay, 13 opposed / 3 agreeing candidate directions and sequential
Cycle sweep describe particular diagnostics, not universal acceptance criteria.

Stage 3 excludes the proposed Tragedy/Resolution lifecycle, recursive Exhaustion,
movement/recovery, Receipt authority, homology, Correspondence C1 and Chirality
vNext changes. Production implementation and its PostgreSQL/SQLite, performance,
Core and complete Integration validation remain required before approval.
