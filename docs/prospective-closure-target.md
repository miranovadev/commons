# Prospective Closure and field binding: proposed target

> **Agreed candidate architecture, not deployed.** The current
> [birth-frozen Closure v2](observables.md#closure-v2-committed-historical-crystallization)
> and [Closure hysteresis](closure-hysteresis.md) remain descriptions of
> running code. This document states the intended replacement for review;
> it does not authorise snapshot backfill or production recalculation.

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

## Candidate hierarchy and weighting

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

Every slot retains its fixed weight. If a required comparison is
immature/unavailable, its contribution is **0**, but its maturity
metadata remains unavailable. This does not mean an observed zero or
justify reweighting the available component.

Committal Cadence requires at least two genuine committed intervals
per compared Domain to establish its CV-based comparison. Phase
Profile needs only one **genuine** interval for a Phase mean, not two.
Historical Orientation must be reconstructed from effective
Attestation populations and their supersession, not approximated
using only the last Attestation's sign. All historical samples follow
the relevant selected causal lineage and committed ancestry.

Extent and Rhythm each retain independent component direction and
hysteresis. At numeric zero, use their lawful retained direction,
not a fabricated neutral third direction. Both positive/positive
and negative/negative agreement may satisfy the Closure component
coherence gate, subject to the other Commit requirements.

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

Historical snapshots and legacy birth records are immutable and must
not be reinterpreted from today's mutable population. Migration and
new representation require separate approval.

## Attenuation is unresolved

The inverse-square kernel over the selected causal ancestry is
`w(d) = 1 / (1+d)^2`, deduplicating genuine shared ancestors at
minimum distance and respecting canonical Commit boundaries.
Neither candidate may pool unrelated sibling or Horizon members.

**A, deployed baseline:** `T_A = (A(C) + A(Ch) + Cl) / 3`.
Correspondence and Chirality are attenuated independently; Closure
is local.

**B, unresolved diagnostic alternative:**
`T_B = A((C_local + Ch_local + Cl_local) / 3)`.
Local measurements bind into local Tension first, then Tension
is attenuated. A frozen Committal must retain the resulting
field state rather than silently recomputing a different formula.

Both use `Charge = 1 - abs(Tension)` with the documented rounding
and Domain-transition semantics. They can disagree on the Tension
sign and whether Charge reaches zero. Neither numerical convenience,
higher Charge nor an easier transition selects a winner. Do not
replace A in normative deployed documentation until a separate
semantic decision and implementation review.

## Diagnostic status

The C1/Closure replay and the synthetic catastrophe/revision tests
support investigation, not a guaranteed lawful natural Emergence.
A production-valid participation cook remains a distinct future
test. No current historical Commit should be rewritten to match
counterfactual values.
