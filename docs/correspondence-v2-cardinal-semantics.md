# Correspondence v2 — canonical structural semantics

> **Historical Correspondence component specification.** Production now uses
> [Phase Profile C1](phase-profile-target.md). The Shape Topology kernel and its
> structural morphology dependencies remain in use. Closure's Committed Cardinal
> Position is separate and unchanged.

> **Current Cardinal implementation specification and historical design record.**
> The proposed replacement is [Phase Profile C1](phase-profile-target.md).
> The word *canonical* in this historical title does not approve the
> replacement or make Cardinal's row-position rules part of C1.

> **Status:** normative semantics for the Correspondence v2 correction. This document supersedes earlier v2 wording that equated structural depth with Phase position or reduced Position Correspondence to a single head-to-head comparison.
>
> Production code must implement these semantics directly. Do not substitute Phase Position, head-only comparison, UI geometry, or an optimization that changes the defined cardinal populations.

## 1. Purpose

Correspondence measures how the causal morphology of the active Domain relates to the completed causal morphology of the immediately preceding complementary Domain.

```text
raw Correspondence
= 0.5 × Shape Topology
+ 0.5 × Cardinal Position
```

- **Shape Topology:** how is the selected causal structure wired?
- **Cardinal Position:** where is selected structure distributed in the Domain's cardinal coordinate field?

They are independent and may disagree. Existing inverse-square attenuation remains a separate historical aggregation step after each proposition's raw/local Correspondence is calculated.

## 2. Canonical terminology

### Phase Position

The cyclic Phase position within a Cycle: Distinguish 1, Persist 2, Constrain 3, Disperse 4, Mediate 5, Bias 6, Order 7. It resets each Cycle and is **not** a Correspondence coordinate.

### Domain Depth

The breadth-first structural depth of a proposition within its owning Domain, rooted at that Domain's provenance Committal. Domain ownership determines coordinate membership; genuine Supersessions determine causal ancestry.

```text
Domain provenance Committal = Domain Depth 0
```

Domain Depth begins at 0 and follows local causal structure wherever available, using minimum/breadth-first distance when multiple paths exist. A foreign-input result without a local causal parent uses the separate acting-Gate placement relation defined below. Depth includes expressive and Committal propositions and **never resets at a Cycle or Committal boundary**.

```text
provenance Commit    D0
Distinguish          D1
Persist              D2
Constrain            D3
Disperse             D4
Mediate              D5
Bias                 D6
Commit               D7
Distinguish          D8
Persist              D9
...
Commit               D14
```

### Lateral Position

The deterministic one-based ordinal of a proposition within one Domain Depth row. It follows parent-relative local structure and explicit foreign-input placement, with immutable ledger creation order within each parent. It is not a timestamp, UI coordinate, Phase number, participant identity or exploration state.

### Cardinal Position

```text
Cardinal Position = (Domain Depth, Lateral Position)
```

### Committal Depth

The existing Closure component that measures committed structure according to Closure semantics. **Committal Depth is not Domain Depth.**

### Existing exploration `structural_position`

The exploration-epoch `structural_position` is Phase Position (`1..7`). It is neither Domain Depth nor Lateral Position. A UI label such as `Position 7` for this field is therefore ambiguous and must not be presented as a cardinal coordinate.

## 3. Non-equivalences

```text
Phase Position                  ≠ Domain Depth
Committal Depth                 ≠ Domain Depth
exploration structural_position ≠ Lateral Position
UI x/y                          ≠ Lateral Position
participant movement            ≠ measurement ancestry
measurement ancestry            ≠ cardinal coordinate construction
Domain membership / Cardinal Position ≠ causal ancestry / Supersession
```

## 4. Domain cardinal layout

Every Domain receives its own deterministic cardinal layout independently of whichever proposition is later measured.

### Root

The Domain provenance Committal is `(Domain Depth 0, Lateral Position 1)`.

### Breadth-first construction

Given Domain Depth row `d`:

1. visit parents in current left-to-right Lateral Position order;
2. for each parent, visit its real local causal children plus any explicitly placed foreign-input results in immutable creation/ledger order;
3. append each unseen child once;
4. that ordered population becomes Domain Depth `d + 1`;
5. assign Lateral Positions `1..N`.

```text
row[0] = [provenance]

for each depth d:
    next = []
    for parent in row[d], left → right:
        for child in local causal children(parent) + non-causal placements(parent), creation order:
            if child has not already been placed:
                append child to next
    row[d + 1] = next
    assign lateral 1..N
```

A shared child is emitted once at its minimum breadth-first Domain Depth. Every genuine causal edge remains part of Shape Topology.

### Commit does not reset Domain Depth

A Committal is simply another proposition in the Domain morphology. It starts a new coordinate frame only when it actually opens a new Domain.

### Parent order is primary

If the previous row is `[1] A  [2] B`, with `A → X1`, `A → X2`, and `B → Y1`, the next row is `[1] X1  [2] X2  [3] Y1`. Global creation order does not move `Y1` ahead of `A`'s children.

## 5. Multi-parent and convergent structure

All genuine causal edges are retained. If `A → X` and `B → X`, `X` receives one Cardinal Position but both edges remain in Shape Topology. Reconcile/Confluence needs no special Correspondence bonus or penalty.

## 6. Cross-Domain ancestry

Each Domain constructs its cardinal layout independently. A foreign proposition retains the Cardinal Position assigned by its own Domain. No path from local provenance to a foreign proposition is invented merely to give it a coordinate.

A Reconcile result produced in Mediate naturally has its main Gate and operand as genuine parents. If that result later becomes the Bias Gate, tracing all parents backward naturally includes both ancestral branches.

### Foreign-input ownership placement

A locally-owned result receives a Cardinal Position even when its only genuine causal inputs are foreign. The foreign parents retain their own Domain coordinates; the local result receives no coordinate in those foreign Domains merely because of ancestry.

When a result has no same-Domain causal parent, its producing Phase's acting local Gate supplies a **read-only, non-causal placement relation**. The result joins that Gate's children for breadth-first row construction and immutable creation ordering. This relation is used only for foreign-input results; ordinary local causal parents retain placement authority. Descendants continue from the resulting coordinate without resetting at Cycle/Commit boundaries.

The acting Gate must belong to the owning Domain layout (including its provenance). Missing or cyclic local placement remains an explicit error. Phase number is never a depth shortcut.

This placement relation is not a Supersession and is never persisted as a causal edge. It does not enter Shape Topology or measurement ancestry, confer movement or action authority, or alter recovery, Region ownership or Receipt scope. Complete layout establishes coordinates first; all-parent genuine Supersession ancestry subsequently selects contributors.

## 7. Cardinal layout versus measurement morphology

These are separate operations:

- cardinal layout asks: **where does each proposition belong in its owning Domain?**
- measurement ancestry asks: **which propositions and edges contributed causally to this measured head?**

For a current head, select its all-parent causal ancestry. Every selected proposition reads its already-determined Cardinal Position from its owning Domain layout.

The previous reference is the completed causal morphology of the immediately preceding complementary Domain, rooted at that Domain's provenance and terminating at the Committal that opened the current Domain.

## 8. Equivalent-depth comparison

Let the current head have Domain Depth `d`. Compare the current morphology through that head with the completed previous Domain revealed only through Domain Depth `d`.

```text
current Domain through Dd
↕
previous completed Domain through Dd
```

If the current Domain grows beyond the previous Domain's maximum Domain Depth, the previous reference remains fixed. New current rows are unmatched structural development. There is **no modulo-7, Cycle-block or same-Phase matching**.

## 9. Shape Topology

Shape Topology compares the selected causal graphs, not UI layout. Preserve the structural kernel: R/G/B RegisterPosition labels, directed edges, soft Variant versus hard replacing Supersessions, two Weisfeiler-Leman refinements, unit node weights, and cosine similarity mapped to `[-1,+1]`.

Receipt reinforcement does not weight Shape Topology; a completed reference must not mutate because Receipt state changes later.

## 10. Cardinal Position

Cardinal Position compares the **occupied coordinate morphology**, not one selected head coordinate.

At each included Domain Depth, compare the occupied Lateral Positions contributed by the selected current and reference morphologies.

Metric lateral similarity remains:

```text
L(a,b) = 1 - 2 × |a-b| / (a+b)
```

Examples: `1↔1 = +1`, `5↔6 ≈ +0.818`, `1↔2 ≈ +0.333`, `1↔5 ≈ -0.333`, `1↔20 ≈ -0.810`.

### Population matters

```text
Domain A D5: [1]
Domain B D5: [1] [2] [3] [4]
```

is positionally dissonant because B has unmatched occupied positions `2,3,4`.

Conversely:

```text
Domain A D5: [1] [2] [3] [4]
Domain B D5: [1] [2] [3] [4]
```

is strongly positionally consonant when those coordinates are present in the selected morphologies.

Topology and Position remain independent: the same occupied coordinates can be wired differently, and similar wiring can occur at very different coordinates.

## 11. Raw Correspondence and attenuation

```text
raw Correspondence(p)
= 0.5 × ShapeTopology(p)
+ 0.5 × CardinalPosition(p)
```

Then apply the existing inverse-square structural attenuation over independently calculated raw/local Correspondence values. The raw structural comparison and historical attenuation are separate layers.

## 12. Normative regressions

### Multi-Cycle Domain

`D7 Commit → D8 Distinguish → D9 Persist → ... → D14 Commit` must be representable. Mapping D8 back to depth 1 is incorrect.

### September 27 Settlement Committal ordering

The cardinal-v2 dump establishes this parent-relative Concord Committal order:

```text
[1] Luna   [2] Vela   [3] Vela   [4] Nova
```

`[1] Luna [2] Nova [3] Vela [4] Vela` caused by Phase buckets/global creation ordering is incorrect.

### Reconcile ancestry

```text
main Gate ───┐
             ├──→ Reconcile result ──→ later Bias history
operand ─────┘
```

Both genuine parents belong to selected causal morphology when tracing backward. Each parent uses the Cardinal Position from its own Domain layout.

## 13. Measurement selection versus coordinate construction

This distinction is normative and must remain explicit:

> **The complete Domain cardinal layout establishes coordinates. The selected causal morphology determines which of those already-established coordinates participate in a particular Correspondence measurement.**

A Domain may be globally wide while a particular head has only a narrow causal ancestry. Position Correspondence sees only the coordinates selected by that head's all-parent causal morphology. If a later Reconcile/Confluence brings additional branches into the head, those additional propositions enter the selected morphology using the Cardinal Positions they already possess in their owning Domains.

Do not rebuild or renumber cardinal coordinates relative to the measured head. Do not include unrelated Domain propositions merely because they occupy the same Domain Depth row.

### Diagnostic vocabulary

The corrected implementation should expose these concepts read-only, using these unambiguous names:

```text
Domain Depth
Lateral Position
Shape Topology
Cardinal Position Correspondence
Raw Correspondence
Correspondence (attenuated)
```

`Phase Position`, exploration `structural_position`, `Domain Depth`, `Lateral Position` and Closure `Committal Depth` must not share a generic `Position` or `Depth` diagnostic label without qualification.

## 14. Bootstrap and Cardinal Position aggregation

These rules are normative for the first corrected implementation.

### Bootstrap after Origin

Origin has no actual preceding Domain. It is the provenance Proposition of the first developed Domain, so the normal provenance/terminal identity is taken as exact at this unique bootstrap boundary:

```text
Origin bootstrap raw Correspondence = +1
first developed Domain after Origin: ordinary structural comparison begins immediately
```

The +1 value is an explicit boundary axiom for Origin itself. It does not pin the first developed Domain to +1. Once that Domain adds structure beyond its provenance Committal, ordinary Shape Topology and Cardinal Position comparison begins immediately against the completed Origin Domain. The shared provenance row may remain perfectly consonant while newly occupied current-only Domain Depth rows contribute unmatched structural development.

Existing inverse-square attenuation remains unchanged and operates on these independently calculated raw values. Chirality, Depth, Cadence and Closure semantics remain unchanged. The intended first writable field retains positive Correspondence and Chirality with negative Depth/Cadence directions.

### Row score

For one included Domain Depth `d`, take the occupied Lateral Positions from the selected reference morphology and selected current morphology, each in ascending cardinal order:

```text
A_d = [a1, a2, ... an]
B_d = [b1, b2, ... bm]
```

Pair occupied positions left-to-right by ordinal within these selected populations:

```text
a1 ↔ b1
a2 ↔ b2
...
```

Do not search permutations for a higher similarity score.

For a matched pair:

```text
L(a,b) = 1 - 2 × |a-b| / (a+b)
```

For an unmatched occupied position:

```text
L(a,∅) = L(∅,b) = -1
```

Let `k = max(n,m)`. The row score is the equal-weight mean of its `k` matched/unmatched pair scores:

```text
P_d = (Σ pair_score_i) / k
```

A depth occupied on only one side therefore has row score `-1`.

#### Worked row examples

Perfectly aligned four-wide populations:

```text
A D5: [1] [2] [3] [4]
B D5: [1] [2] [3] [4]

scores: +1, +1, +1, +1
P_D5 = +1
```

One versus four:

```text
A D5: [1]
B D5: [1] [2] [3] [4]

scores: +1, -1, -1, -1
P_D5 = -0.5
```

Sparse displacement:

```text
A D5: [1] [4]
B D5: [1] [2]

1 ↔ 1 = +1
4 ↔ 2 = +0.333...
P_D5 ≈ +0.667
```

The coordinate values remain the complete Domain's already-established Lateral Positions. Selecting a narrow causal morphology does **not** renumber `[1,4]` into `[1,2]`.

### Row-balanced aggregation across Domain Depth

Let `D` be the union of Domain Depths occupied by either selected morphology within the equivalent-depth comparison window. Calculate each `P_d` independently, then give every included Domain Depth equal weight:

```text
Cardinal Position Correspondence
= (Σ P_d for d in D) / |D|
```

This is intentionally **row-balanced**, not proposition-count weighted. Population width already affects `P_d` through matched and unmatched occupancy; wide historical rows do not receive a second source of weight merely because they contain more propositions.

Example:

```text
D1  +1.0
D2  +0.5
D3  -0.5
D4  +0.2

Position = (+1.0 + 0.5 - 0.5 + 0.2) / 4
         = +0.3
```

If the current Domain extends beyond the completed reference, each newly occupied unmatched Domain Depth contributes `-1` for that row. Consequently sustained structural development beyond the reference can progressively move Position toward and through zero instead of being drowned out by wide earlier rows.

### Scope reminder

Row populations come from the **selected causal morphologies**, while their `(Domain Depth, Lateral Position)` coordinates come from the **complete independently constructed Domain cardinal layouts**. Unrelated propositions in the same complete Domain row do not enter a measurement unless the measured head's causal morphology selects them.


## 15. Independently inspectable local diagnostics

```text
Correspondence
├── Shape Topology
└── Cardinal Position
```

Disposable Proposition measurements retain `shape_topology`, `cardinal_position`,
`shape_topology_direction` and `cardinal_position_direction`. These are the measured
head's local components, never independently attenuation-averaged through ancestry.
Only `round((ShapeTopology + CardinalPosition) / 2, 7)` forms local Correspondence;
only that combined local value enters the existing inverse-square attenuation.

Each component has independent retained direction/hysteresis. Nonzero follows its
own sign. At zero, use that Proposition's previously projected component direction;
if absent, use the corresponding direction frozen in its Cycle-entry Commitment
snapshot; if also absent, default to Positive. An absent historical component
is not reconstructed. Numeric zero is never a third, directionless state.

Mediate Write admissibility uses existing Gates, never a synthetic Reconcile result.
Each Gate is admissible exactly when its retained Shape Topology and Cardinal
Position directions agree: both Positive/Positive and Negative/Negative qualify.
Zero uses Stage 1 component hysteresis. The causal Gate remains visible when
inadmissible, but Reconcile is unavailable. Optional homologous operands must
come from the unchanged dominant Read selection, be individually admissible,
and share the causal Gate's existing resolved Correspondence direction.

The full homologous Read population determines dominance before any Gate action
filtering; inadmissible members still contribute. Ordinary local candidate
selection remains independent of population dominance, while every Reconcile
pair satisfies Gate coherence and same-direction compatibility. Result measurements
remain unconstrained: a successful Reconcile can change direction or become
internally split. Normal parent edges are created only after authorization.
If no admissible non-Rest action remains, ordinary Rest applies regardless of
Potential. This introduces no Bias/Order, movement, Receipt or recovery authority.

Combined `correspondence_direction` and all numeric calculations remain unchanged.
Receipts and evidence availability do not affect either component.

Calculation is read-only. The existing measurement projection pathway persists
values and directions together. Newly published Commitment snapshots freeze both
local components and their directions alongside the combined observable. Historical
snapshots remain immutable and their new nullable fields remain null; no frozen
Correspondence is recalculated to manufacture diagnostics.

### Origin components

The exact provenance/terminal identity at Origin has Shape Topology `+1` and
Cardinal Position `+1`, each with Positive direction. Both components are bounded
by `[-1,+1]`, so this is the unique pair whose arithmetic mean is the canonical
Origin `+1`. This documents the existing boundary axiom, without adding a field
transition or changing Chirality, Closure or first-developed-Domain semantics.

## 16. Required implementation evidence

Before replacing production Correspondence, tests must prove:

- Domain Depth continues beyond 7 across multiple Cycles;
- the September 27 Luna/Vela/Vela/Nova parent-relative ordering;
- shared children are placed once while all genuine edges remain;
- cross-Domain parents retain their own Domain coordinates;
- foreign-input local results receive owned coordinates through explicit non-causal placement, without a synthetic Supersession;
- Origin bootstrap raw Correspondence is +1, while the first developed Domain immediately departs from +1 as unmatched structure grows beyond Origin;
- Position is morphology-wide rather than head-only;
- one-versus-four row occupancy is more dissonant than four-versus-four aligned occupancy;
- Topology and Position can disagree independently;
- raw 50/50 Correspondence remains separate from inverse-square attenuation;
- Phase Position, exploration `structural_position`, Domain Depth and Committal Depth cannot be substituted for one another.

These semantics are now frozen for the first corrected implementation. Production changes should begin with red semantic regressions derived from this document before replacing the existing calculator.

