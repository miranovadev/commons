# Core Invariants

The Commons has exactly ten Core Invariants.

**This set does not grow or shrink.** New discoveries refine, clarify, or add
examples beneath one of these ten invariants. If a proposed semantic rule cannot
fit coherently within them, that is a warning to revisit the semantics rather
than add an eleventh invariant or remove an existing one.

The system must adhere strictly to these invariants regardless of implementation.
If an implementation detail conflicts with an invariant, the invariant takes
precedence. These are normative constraints on The Commons model and govern the
more specific operational rules documented beneath them.

The authority order is:

1. these ten Core Invariants;
2. explicit phase, observable and operation semantics;
3. implementation representation and historical code behaviour.

Implementation history does not override model semantics.

## 1. Measurements Stay Separate

Correspondence, Chirality, and Closure are irreducible and independent
measurements.

No label, projection, derived field, operation, cached value, implementation
convenience, population summary, evidentiary signal, or new abstraction may
become an implicit fourth measurement or silently substitute for another axis.

Support/Refute, Reinforcement, Potential, Charge, Tension, Phase, Domain,
Horizon, Commit Region, homology, actionability and participant movement are not
additional measurements.

Derived values may combine measurements only where their definition explicitly
does so. That combination does not collapse the underlying measurements into one
another.

### Examples

- Support/Refute labels Attestations; it is not a measurement axis.
- Receipt or Reinforcement state may affect evidence or authority but never
  Correspondence, Chirality or Closure merely by existing.
- Potential and Charge are derived descriptions; they do not become inputs to an
  observable unless that observable explicitly defines them as such.

## 2. Semantics Come Before Representation

Database columns, cached directions, projections, indexes, UI labels, services,
algorithms, graph layouts, fixtures, migration history and implementation
structures are representations of the model, not authorities over it.

If a representation conflicts with the documented semantics of The Commons, the
documented semantics take precedence.

Model truth must not be inferred merely from the current shape of the codebase,
from a convenient query, from a persisted compatibility field, or from behaviour
that only exists because an implementation once encoded it that way.

A migration, cache or projection may preserve historical representation without
making that representation current semantic truth.

### Examples

- A disposable membership table does not redefine immutable Cycle ownership.
- A UI grouping does not create operational scope.
- A compatibility column may remain readable without becoming live authority.
- A calculated layout may describe structure without creating causal edges.

## 3. Terms Keep Their Meaning

A defined Commons term must retain the same semantic meaning wherever it is used.
Context may determine its value, scope, polarity or relational interpretation,
but must not silently redefine the concept itself.

This applies especially to Proposition, Expressive Proposition, Committal
Proposition, Phase, Cycle, Attestation, Receipt, Support, Refute, Commit Region,
Context Horizon, Domain, Correspondence, Chirality, Closure, Tragedy,
Exhaustion, Reinforcement, Potential and movement.

Labels must not be used as shortcuts for a different structural concept.

### Structural identity is not label identity

Register identity does not confer Phase identity. Repeated register labels across
the seven-phase Cycle remain structurally distinct. Eligibility for phase
operations is determined by producing Phase position and causal role, not by a
matching register name alone.

Support/Refute must not be used as aliases for positive/negative outside their
Attestation context.

A historical field whose name refers to an old semantic primitive must not be
silently reused to store a new primitive under the old name.

## 4. Established State Persists Until Lawfully Changed

Established state is not erased merely because the current numeric or local
state becomes neutral, inconvenient, stale-looking or temporarily divergent.

Where the model defines hysteresis, numeric value and retained direction are
distinct properties. An established positive or negative direction persists
through a current numeric value of zero until the documented transition rule
changes it.

Immutable structural ownership and boundary identity likewise persist unless the
model explicitly defines a new event that creates a new state. Existing state is
not reinterpreted from later context.

### Zero does not erase direction

A zero value must not automatically be interpreted as directionless, a neutral
direction, absence of prior direction, or permission to discard directional
history.

### Boundaries remain boundaries

A Cycle's entry identity, creating Horizon, Proposition-to-Phase ownership,
Phase-to-Cycle ownership, Horizon parentage and boundary provenance are not
retroactively changed because later structure would be easier to explain under a
different frame.

A later event may create a new Horizon, Region, direction or state. It does not
rewrite the event that established the earlier one.

## 5. Provenance Defines Identity and Scope

When The Commons needs to know what something is, where it belongs, or which
frame governs it, actual provenance is authoritative.

Causal ancestry, creating Phase, creating Cycle, immutable Cycle entry,
governing Committal, Context Horizon ancestry, boundary provenance and recorded
participant arrival are used where their respective semantics require them.

Proximity, recency, visibility, matching labels, graph convenience, population
membership or implementation accessibility cannot substitute for provenance.

### Commit Region ownership

A persisted Committal owns itself. An Expressive Proposition belongs to the
immutable entry Committal of its creating Cycle. Supersession records causal
provenance and may be multi-parent without making Region ownership ambiguous.

Region-level semantics derive from the governing Committal where the model
defines that frame, including fixed Attestation polarity.

Expressive ancestry must not silently replace Committal ancestry where Committal
provenance governs.

### Context Horizon ownership

Proposition membership follows its creating structure:

`Proposition → Phase → Cycle → Context Horizon → Domain`

A Horizon opener identifies a boundary; it does not duplicate that boundary
Proposition into the child's measurement history or rewrite its creating
ownership.

### Participant path provenance

Where a multi-input Write creates several causal parents, participant recovery
uses the recorded arrival path where required. An unused parent cannot become a
shortcut merely because it is also an ancestor of the result.

## 6. Local Change Does Not Rewrite History

A local state transition changes the local state. It does not retroactively
rewrite the frame, erase valid history, destroy independent alternatives, or make
a prior event appear never to have happened.

Supersession changes active topology without deleting provenance. Reclassify,
Revise, Reconcile, Commit, Tragedy, Exhaustion, Reinforcement changes and local
measurement changes preserve the historical facts from which they arose.

Competing Propositions and branches may coexist and develop independently where
the structural rules permit them.

### Frames are not rewritten by their contents

Expressive Propositions may diverge from their governing Committal. Measurements,
Attestations and Receipts may change locally. None of these changes retroactively
redefines the Commit Region's established frame.

### Historical proofs remain historical

Tragedy and Exhaustion proofs remain historical events once lawfully created.
Later positive Charge, Receipt consumption, recovery, recalculation or
maintenance does not erase the event.

A current projection may change without rewriting the historical evidence from
which an earlier decision was made.

### Branching is not globally consumed

Using or reinforcing one valid route does not globally consume unrelated valid
routes unless an explicit operation defines that consequence.

## 7. Keep Reference Frames and Lineages Separate

Values, directions, populations, ownership scopes, causal histories and
participant perspectives belong to specific reference frames. They may only be
related where the model explicitly defines that relationship.

A Proposition's direction is not automatically a population direction. An
Expressive parent's direction does not replace the governing Committal's
direction. Correspondence does not substitute for Chirality. Attestation polarity
does not substitute for an observable pole.

### Measurement lineage is absolute

**EVERY MEASUREMENT IS CONFINED TO THE CAUSAL LINEAGE BEING MEASURED.**

A Context Horizon, Domain, Commit Region, Phase, homologous population, visible
workspace or other grouping may bound, label, classify, compare or contextualize
a lineage. Shared membership in one of those structures does **not** make its
members a measurement population.

Measurements must never aggregate unrelated sibling or neighbouring lineages
merely because they share a Horizon, Domain, Region, Phase, register, participant
population or ledger period.

A lawful multi-parent operation such as Reconcile may join causal ancestry.
Where a measurement follows that resulting lineage, the joined ancestry is
present because the operation created real causal provenance, not because the
lineages shared a container.

The Context Horizon is a causal/reference boundary. It is never, by itself, a
license to compare every Proposition or Cycle inside it.

### Population comparison is different from measurement ancestry

Homeostatic operations may compare already-independent measurements over an
explicitly defined homologous population. That population comparison does not
change how each member's measurement was calculated.

For example, Mediate may compare independent Correspondence results and Bias may
compare independent Chirality results over their authorised populations. The
population is a consumer of measurements, not a replacement ancestry for them.

### Visibility is a separate frame

Historical and evidentiary material may be visible without belonging to a
measurement lineage, an actionable population or a participant movement path.

## 8. Authority Must Be Explicitly Granted

Visibility, proximity, graph reachability, homology, evidence availability,
measurement value, population dominance, ownership adjacency or implementation
accessibility does not by itself imply operational authority.

Each Commons operation may act only upon Propositions and evidence permitted by
its documented structural scope.

Relevant constraints may include Phase, register, Commit Region, Context Horizon,
lineage, ancestry, ownership, provenance, Gate semantics, evidence requirements,
admissibility and participant position.

Measurements and derived values may select, compare or rank candidates **within
a scope that is already authorised**. They must not silently create or broaden
that scope.

### Commitment is granted by its full contract

Ordinary Commit requires the grounding, effective Attestation, structural
position and Closure conditions defined by the current Commit semantics. Origin
uses its distinct bootstrap grounding contract.

No favourable observable value, reinforcement count or implementation shortcut
may waive a required authority condition.

### Receipts, Reinforcement and memory have separate authority

A Receipt may provide evidence or a specific warrant according to its type and
scope. Participant memory may expose information. Reinforcement may record
qualifying uptake.

None inherits another's authority merely because the underlying evidence is
related.

## 9. Evidence Must Be Truthful

Evidence, observations and historical facts must retain the meaning they had
when created.

Attestations, Receipts, contribution history, authorship, catastrophe proofs,
Exhaustion proofs, immutable observations and provenance must not be invented,
silently reconstructed, relabelled or reinterpreted to simplify implementation
or recover a desired outcome.

Derived state is evidence processing, not evidence creation.

### The system must not

- manufacture an Attestation, Receipt, causal edge or historical observation that
  never occurred;
- reinterpret an existing Attestation as meaning something different;
- infer Support/Refute meaning solely from an instantaneous numeric sign;
- fabricate provenance or a historical measurement population;
- backfill an event-born observation from present mutable state unless an
  explicitly approved migration semantics proves that reconstruction;
- rewrite a contribution's historical meaning to satisfy a newer implementation;
- treat unavailable history as a genuine observed zero.

Historical compatibility data may remain readable while being explicitly
non-authoritative for current semantics.

## 10. Movement Follows Authorised Structure

A positioned Participant changes structural position only through an authorised
operation.

Ordinary forward movement follows real causal continuation from the current
Gate. Recovery walks recorded causal ancestry backward. Narrowly defined
operations may authorise additional movement, such as the explicit Order
homologous-Committal Traverse, but the exception belongs to that operation and
does not create a general sideways-movement rule.

**No edge means no movement unless an explicit operation itself defines the
authorised transition.**

There is no unrestricted sideways movement.

A Proposition is not a movement destination merely because it:

- shares a Commit Region, Context Horizon, Domain, register or Phase position;
- is visible in context;
- belongs to a Homeostatic comparison population;
- has greater Potential or a more attractive measurement;
- is homologous;
- is historically committed;
- is structurally nearby.

Comparison can identify a candidate for an operation that is already authorised.
It does not itself create movement authority.

### Population comparison never creates movement

Mediate, Bias and Order may use explicitly defined Homeostatic populations.
Membership or dominance in those populations grants no movement unless the
specific operation separately authorises an action.

### Recovery follows the actual backward path

Recovery begins at the participant's actual current position and walks recorded
arrival provenance backward. It does not search siblings, descendants, attractive
neighbours or unused parents.

The current Horizon confines recovery until the governing boundary conditions
permit crossing its opening boundary. Crossing continues on that same recorded
lineage; recovery does not reset to an arbitrary Horizon opener.

### Cycles begin from Committal positions

A Cycle begins from a Committal Proposition under the applicable entry contract.
Expressive Propositions are not Cycle entry points.

After ordinary completion, the next Cycle continues from the actual Committal
reached by causal progression. Initial entry and recovery use their separately
defined selection contracts; they do not provide a global movement shortcut.

## Relationship to More Specific Rules

These ten invariants govern more detailed operational rules; they do not replace
those rules or their documented conditions.

The invariant numbers are permanent. Specific examples should be added beneath
the appropriate invariant or to this relationship table rather than creating a
new Core Invariant.

| Specific rule | Governing invariant(s) | Detailed definition |
| --- | --- | --- |
| Support/Refute label Attestations rather than Propositions or measurement axes. | 1, 3, 7, 9 | [Attestation polarity](attestation-polarity.md) |
| Zero-valued hysteretic observables retain their established direction. | 4, 7 | [Attestation polarity](attestation-polarity.md); [Closure hysteresis](closure-hysteresis.md) |
| Commit Region ownership comes from immutable Cycle entry and the governing Committal. | 5 | [Commit Region ownership](commit-region-ownership.md) |
| Context Horizon membership comes from creating Cycle ownership and does not create causal movement. | 5, 10 | [Causal Context Horizons](causal-context-horizons.md) |
| Every observable is calculated from its own causal lineage; shared Horizon/Region membership never substitutes for lineage ancestry. | 7 | [Observable calculation](observables.md) |
| Homologous populations compare independent measurements; population membership does not rewrite those measurements. | 7, 8 | [Structural homology](structural-homology.md) |
| Receipts, participant memory and Reinforcement have distinct evidentiary/authority roles and do not enter measurements merely by existing. | 1, 7, 8, 9 | [Receipt context](receipt-region-context.md); [Reinforcement](reinforcement.md) |
| Mediate and Bias may expose specifically authorised foreign operands/sources without turning those foreign Gates into movement destinations. | 5, 8, 10 | [Participation](participation.md) |
| Register labels do not confer Phase identity or operation eligibility. | 3, 5, 8 | [Phase positions](phase-positions.md) |
| Commit requires its complete grounding and admissibility contract; Origin has its explicitly distinct bootstrap contract. | 8, 9 | [Observable calculation](observables.md); [Attestation polarity](attestation-polarity.md) |
| Supersession and later state changes preserve historical contributions and provenance. | 6, 9 | [Proposition observation](proposition-observation.md) |
| First/later fresh Charge-boundary events create Tragedy/Exhaustion proofs without deleting prior history. | 5, 6, 9 | [Branch Tragedy](branch-tragedy.md); [Commit Region exhaustion](commit-region-exhaustion.md) |
| Recovery follows the recorded backward lineage and crosses Horizon boundaries only when the governing boundary permits it. | 5, 8, 10 | [Backward lineage recovery](backward-lineage-recovery.md); [Recovery basins](recovery-basins.md) |
| Visibility, actionability, calculation population and movement remain distinct contracts. | 7, 8, 10 | [Participant context](participant-context.md); [Participation](participation.md) |

When ambiguity arises, prefer the interpretation that preserves all ten
invariants together rather than inferring semantics from current code behaviour.

If a proposed semantic rule appears not to fit within these ten, stop and review
the proposed semantics before changing the invariant set.
