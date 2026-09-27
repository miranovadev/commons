# Correspondence v2 — canonical structural semantics

> **Status:** normative semantics for the Correspondence v2 correction. This document supersedes earlier v2 wording that equated structural depth with Phase position or reduced Position Correspondence to a single head-to-head comparison.
>
> Production code must not fill an explicitly unresolved item by inference. Resolve the semantic question here first, then implement it.

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

The breadth-first causal depth of a proposition within its Domain morphology, rooted at that Domain's provenance Committal.

```text
Domain provenance Committal = Domain Depth 0
```

Domain Depth begins at 0, follows the real causal graph outward from provenance, uses minimum/breadth-first distance when multiple causal paths exist, includes expressive and Committal propositions, and **never resets at a Cycle or Committal boundary**.

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

The deterministic one-based ordinal of a proposition within one Domain Depth row. It is derived from causal morphology, not timestamps, UI geometry, Phase number, participant identity or exploration state.

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
```

## 4. Domain cardinal layout

Every Domain receives its own deterministic cardinal layout independently of whichever proposition is later measured.

### Root

The Domain provenance Committal is `(Domain Depth 0, Lateral Position 1)`.

### Breadth-first construction

Given Domain Depth row `d`:

1. visit parents in current left-to-right Lateral Position order;
2. for each parent, visit its real causal children that belong to this Domain in immutable creation/ledger order;
3. append each unseen child once;
4. that ordered population becomes Domain Depth `d + 1`;
5. assign Lateral Positions `1..N`.

```text
row[0] = [provenance]

for each depth d:
    next = []
    for parent in row[d], left → right:
        for child in local causal children(parent), creation order:
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

**No synthetic placement or causal edge may be introduced to make coordination easier.** If a valid operation creates a locally-owned proposition that is not causally reachable from its Domain provenance at all, stop and resolve that topology semantic explicitly.

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

## 13. Explicitly unresolved before implementation

### Bootstrap after Origin

Origin has no developed complementary morphology. We have **not** frozen the first developed Domain's raw structural Correspondence rule. Do not accidentally compare a developed Domain against Origin's one-node morphology. Resolve and document bootstrap semantics first.

### Exact aggregation of sparse/unequal cardinal populations

The semantics are frozen: compare occupied cardinal populations at equivalent Domain Depths; lateral displacement is metric; unmatched positions contribute disagreement; no head-only comparison; no Phase/Tier/Register gate may replace the cardinal coordinate relation; and no algorithm may search for the pairing that merely maximizes similarity.

The exact deterministic pairing/aggregation formula for sparse or unequal occupied lateral populations still requires explicit approval. Add worked examples here before coding it.

## 14. Future hypothesis — Correspondence alignment

> **Non-normative. Do not implement as part of the v2 correction.** Record component values/directions first and evaluate this hypothesis against live substrate behavior.

Shape Topology and Cardinal Position may eventually supply a Correspondence admissibility relation analogous in form, but not necessarily in operational meaning, to Closure component alignment.

The hypothesis is that **directional agreement between the two Correspondence components represents structurally coherent correspondence**, regardless of whether that shared direction is consonant or dissonant:

```text
Topology +   Position +   → aligned consonance   → potentially admissible
Topology -   Position -   → aligned dissonance   → potentially admissible
Topology +   Position -   → opposed structure    → potentially inadmissible
Topology -   Position +   → opposed structure    → potentially inadmissible
```

Under this hypothesis, two parents that are consonant in both topology and cardinal position may be joined because both structural dimensions agree. Two parents that are dissonant in both may also be joined: reconciliation can coherently combine two structures that disagree completely with the reference in the same direction. A topology/position disagreement instead means the structural dimensions themselves do not agree about the relation.

This is **not** a positivity gate. Negative/negative alignment is as coherent as positive/positive alignment.

Do not yet add an admissibility Gate, zero-direction hysteresis, Reconcile filtering or movement/authority change. The corrected v2 implementation should expose enough read-only diagnostics to observe Shape Topology and Cardinal Position independently so later runs can test whether aligned/opposed component states actually correspond to meaningful Mediate/Reconcile behavior.

Chirality remains an independent observable. This hypothesis does not imply that Chirality requires decomposition or an analogous gate.

## 15. Required implementation evidence

Before replacing production Correspondence, tests must prove:

- Domain Depth continues beyond 7 across multiple Cycles;
- the September 27 Luna/Vela/Vela/Nova parent-relative ordering;
- shared children are placed once while all genuine edges remain;
- cross-Domain parents retain their own Domain coordinates;
- no synthetic placement edge is created;
- Position is morphology-wide rather than head-only;
- one-versus-four row occupancy is more dissonant than four-versus-four aligned occupancy;
- Topology and Position can disagree independently;
- raw 50/50 Correspondence remains separate from inverse-square attenuation;
- Phase Position, exploration `structural_position`, Domain Depth and Committal Depth cannot be substituted for one another.

Only after these semantics and the two unresolved items are frozen should production code be changed.