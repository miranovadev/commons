# Core Invariants

The system must adhere strictly to these invariants, regardless of implementation.
If an implementation detail conflicts with an invariant, the invariant takes
precedence. These are normative constraints on The Commons model and govern the
more specific operational rules documented beneath them.

The authority order is: normative Commons invariants, then explicit phase and
operation semantics, then implementation representation. Implementation history
does not override the movement topology defined in Invariant 17.

Persistence, final-attempt Exhaustion, recursive recovery, regional Tragedy, and observer
separation are normative; see [the exhaustion invariants](#persistence-exhaustion-recovery-and-observation).

## 1. The Measurements Are Independent

Correspondence, Chirality, and Closure are irreducible and independent
measurements. No label, projection, derived field, operation, cached value,
implementation convenience, or new abstraction may become an implicit fourth
measurement or silently substitute for another axis.

Support/Refute must not be treated as a measurement axis.

## 2. Representation Is Not Semantics

Database columns, cached directions, projections, UI labels, services, algorithms,
and implementation structures are representations of the model, not authorities
over it. If a representation conflicts with the documented semantics of The
Commons, the documented semantics take precedence.

Model truth must not be inferred merely from the current shape of the codebase.

## 3. Canonical Terms Retain Canonical Meaning

A defined Commons term must retain the same semantic meaning wherever it is used.
Context may determine its value, scope, or relational interpretation, but must not
silently redefine the concept itself.

This applies especially to Proposition, Committal Proposition, Expressive
Proposition, Attestation, Receipt, Support, Refute, Commit Region, Context Horizon,
Correspondence, Chirality, and Closure. Terminology must not drift between
documentation, implementation, participant prompts, and UI behaviour.

## 4. Zero Does Not Erase Direction

Numeric value and hysteretic direction are distinct properties. An established
positive or negative direction persists through a current numeric value of zero
until the documented hysteretic conditions for a directional transition are
satisfied.

A zero value must not automatically be interpreted as directionless, a neutral
direction, an absence of prior direction, or permission to discard directional
history. This invariant applies wherever direction is explicitly hysteretic.

## 5. Committal Provenance Governs the Region

Commit Region ownership is determined by immutable Cycle entry: a Committal owns itself,
and an expressive Proposition belongs to its creating Cycle's immutable
`entry_committal_id`. Supersession records causal provenance, which may have
multiple parents from different Regions without making ownership ambiguous.
The creating Cycle independently determines `context_horizon_id`; neither
Region nor Horizon membership grants participant movement. See
[Commit Region ownership](commit-region-ownership.md).

Every Commit Region is governed by its Committal Proposition. Region-level
semantics derive from that Committal provenance rather than arbitrary Expressive
ancestry. This includes, where applicable:

- the region's fixed supporting Attestation direction;
- the corresponding refuting Attestation direction;
- the default effective Attested direction for Attestation-less Expressive
  Propositions.

Expressive ancestry must not silently replace Committal ancestry where the model
defines the Commit as the governing reference.

Commit Region membership defines calculation/context scope only where a Commons
operation explicitly requires it. It does not define Participant movement and
never creates a traversal edge. No edge means no movement.

## 6. Local Change Does Not Rewrite the Frame

Expressive Propositions may diverge from the governing Committal direction.
Measurements may change, Attestations and Receipts may accumulate, and local
effective direction may change through hysteresis.

None of these local changes may retroactively redefine the Commit Region itself,
including its fixed Support/Refute polarity or other semantics established by
the governing Committal Proposition. A local state transition changes the local
state, not the frame in which that state is interpreted.

## 7. Reference Frames Must Remain Distinct

Values or directions belonging to one relational frame must not be substituted
for another merely because they appear similar. The implementation must preserve
distinctions between Correspondence direction, effective Attestation direction,
aggregate Attestation direction, Commit Region polarity, Proposition ancestry,
Committal ancestry, participant trajectory, and population-level state.

In particular:

- a Proposition's direction must not automatically become an aggregate population
  direction;
- an Expressive parent's direction must not replace the governing Committal
  direction;
- Support/Refute must not be used as aliases for positive/negative outside their
  Attestation context;
- Correspondence must not be substituted for Chirality or Attestation polarity.

Operations may relate different reference frames only where the model explicitly
defines that relationship.

## 8. Provenance Is Never Erased

Supersession changes active topology without rewriting history. Propositions,
contributions, Attestations, Receipts, authorship, and lineage must retain their
provenance even when superseded, reclassified, revised, reconciled, committed,
or no longer active.

An operation may transform how an earlier contribution participates in the
current state, but must not make prior contribution history appear never to have
existed.

## 9. Evidence Must Not Be Invented or Reinterpreted

Attestations and Receipts must retain the meaning defined when they are created.
Derived calculations may aggregate, attenuate, compare, or project evidence
according to the documented model. They must not:

- manufacture evidence that was never supplied;
- reinterpret an existing Attestation as meaning something different;
- fabricate provenance;
- infer Support/Refute semantics from instantaneous numeric sign alone;
- rewrite contribution meaning to simplify implementation.

Derived state is evidence processing, not evidence creation.

## 10. Operations May Only Act Within Their Structural Authority

Visibility, proximity, graph reachability, or implementation accessibility does
not by itself imply operational eligibility. Each Commons operation must act
only upon Propositions permitted by its documented structural scope.

Relevant constraints may include phase, register, Commit Region, Context Horizon,
lineage, ancestry, ownership, provenance, gate semantics, and participant position.
Measurements and derived values may select, compare, or rank candidates within
a valid structural scope, but must not silently redefine that scope.

A calculation candidate is not thereby a movement candidate. Visibility does
not imply actionability, and actionability implies movement only when the
operation is structurally authorised from the current Gate (Invariant 17).

## 11. Cycles Begin at Committal Propositions

A Cycle begins from a Committal Proposition. Expressive Propositions cannot serve
as Cycle entry points. After ordinary completion, the next Cycle continues at
the exact Committal reached through the current Gate's causal continuation;
there is no fresh global selection. First entry has no prior position and uses
its documented Committal selection. Recovery after Rest selects the nearest
eligible non-exhausted Committal on the recorded backward lineage. Neither
Potential nor Charge can skip a nearer unresolved basin. A prospective Commitment
is not eligible until it exists.

Recovery proceeds backward through committed basins in causal order. A nearer
committed basin must be resolved before an older committed basin becomes eligible.
Recorded arrival provenance determines the path at multi-input Writes. Siblings,
descendants and unrelated branches are excluded. The persisted Cycle Horizon
constrains recovery until its boundary-governing Commit Region's exhaustion permits crossing its opening
boundary along that same path. Previous Horizons do not substitute their opener
for the next qualifying Committal. Numeric zero never proves exhaustion; the
existing Tragedy, shared final-attempt and Receipt-consumption proof remains
required. Commitment creates a recovery boundary. Exhaustion permits recovery
past it. Measurement attenuation, Potential and Charge formulas are unchanged.
Origin remains unique and persistent. Its Commit Region follows the same final-attempt proof. See the invariants below.

Potential is the strongest remaining viable continuation under absolute-Tension-reduction semantics, never an additive budget. Writes maintain affected region projections and propagate changed routing summaries through provenance. Ordinary reads consume the published field version; they never repair measurements or reconstruct the whole substrate. A missing or inconsistent projection is an explicit recoverable maintenance condition. See [Incremental Potential](incremental-potential.md).

Initial entry uses Potential; Rest recovery uses causal order. The selected Committal determines the entry Horizon: its own creating Horizon, or the direct child explicitly opened by its boundary. Closure markers do not prohibit causal backtracking. Entry must never search arbitrary open descendants to make an old Gate fit. Persisted ownership remains `Proposition → Phase → Cycle → Context Horizon → Domain`; Gates must belong to or explicitly open their Cycle Horizon, and writes must use that Domain's vocabulary.

Under normal Phase progression, there may be at most six consecutive Expressive
Proposition tiers between Committal Propositions. A new Committal Proposition may
seed fresh Phase 1 opportunities when structural viability permits; Participant count does not determine their number.

## 12. Receipts and Reinforcement Are Distinct

Only qualifying evidence currently loaded by a Participant produces active
Reinforcement. Creating a Cite Receipt records evidence; it does not itself grant Reinforcement. A Participant may
contribute at most one counted Reinforcement to a Proposition; repeated Receipts
and shared signatures remain historical events rather than extra measurement weight.
Loaded state persists across Cycles and Context Horizons until cleared. Clearing
removes active Reinforcement without deleting the Receipt or its history.

## 13. Reinforcement Forms State Before Attenuation Propagates It

Reinforcement contributes to a Proposition's intrinsic measurements before
structural attenuation is applied. Attenuation affects propagation of a measured
state, not formation of that state. Receipts and Reinforcement are never discounted
by distance before entering the Proposition's own calculation.

## 14. Commitment Requires Grounding, Attestation and Closure

Commit requires a completed Receipt, an effective Attestation on the selected classification, and aligned Depth/Cadence with hysteresis. Loaded Reinforcement adds weight but is not an admissibility minimum. Origin initialization records its explicit evidence before freezing. Each direct expressive source is committed once globally; its Committal result is immutable and subsequent development takes place through Variants.

## 15. Reinforcement Does Not Suppress Branching

Competing Propositions and reconciliations may coexist and develop independently.
Reinforcement must not globally consume source Propositions or prohibit reuse of
organically reachable hard-superseded inputs. Multiple alternatives earn their own
persistence within the existing phase, register and region rules.

## 16. Register Identity Does Not Confer Phase Identity

Register labels classify Propositions but do not define structural hierarchy.
Eligibility for phase operations is determined by producing phase position and
causal role. Repeated register labels across the seven-phase Cycle remain
structurally distinct, across Participants and Cycles alike.

Order may Commit only an Expressive candidate at the Bias-produced Closure
position for its Domain and Commit Region. A Constrain Canon or Concord remains
contextual region material; grounding and favourable Closure cannot promote it.
Mediate reads Constrain and reconciles Disperse children; Bias consumes Mediate.
See the [complete phase-role matrix](phase-positions.md).

Phase-position compatibility and Commit Region membership do not confer
traversal eligibility. Ordinary Traverse additionally requires the appropriate causal
relationship to the Participant's current Gate. Order alone also admits the
explicit, operationally validated homologous Committal rule below.

## 17. Lineage-Constrained Participant Movement

A positioned Participant changes structural position through an authorized
operation: forward causal progression, recovery backward along recorded causal
lineage, or Order's explicit homologous Committal Traverse. Bias additionally permits an authorized homologous Reclassify:
the selected source creates one real edge to the new acting-Phase classification,
which becomes the forward position. There is no movement to the foreign Gate
itself and no invented edge from the old Phase Gate. A structurally authorised contribution may create that forward edge;
Ordinary Traverse follows an already-existing edge. Order's additional Traverse
selects a qualified existing Committal without creating an edge.

There is no unrestricted sideways movement. A Proposition is not a movement destination
merely because it shares a Commit Region, Context Horizon, register or producing
Phase position; is visible in context or included in a measurement/Homeostatic
population; has greater Potential; was previously committed; or is structurally
nearby. None of these properties creates a causal movement edge.

**Population comparison alone never creates movement.** The narrowly scoped Order
rule authorizes an actual Traverse; structural visibility does not.

These three contracts must remain distinct:

| Contract | Authorised scope | Movement consequence |
| --- | --- | --- |
| Movement topology | Forward causal continuations; recorded ancestry backward for recovery; explicit Order homologous Committal Traverse | Only authorized operations change position; Traverse never creates an edge |
| Homeostatic population | Homologous Reads for Mediate, Gates for Bias, existing Committal Writes for Order | Comparison creates no edges or movement; each consumer separately authorizes actions |
| Context visibility | Historical and evidentiary material admitted by the context contract | Visibility grants neither actionability nor movement |

Mediate — Read aggregates independent measurements over homologous Reads;
Bias — Gate aggregates independent numeric Chirality over homologous Gates.
Mediate adds qualified Reconcile operands; Bias adds qualified Reclassify sources.
Both preserve their ordinary local causal options. Reconcile creates causal edges
from both inputs; Reclassify creates one edge from the selected source to the new
acting-Cycle classification. Foreign Gates are not Read or Traverse destinations
and never import Receipt stock. The Cycle entry continues to own the catalogue,
Closure/Cadence history and result accounting.

Closure, performed during Order — Write, evaluates each causally local expressive
Write independently from its own Depth/Cadence, lineage/history and participation.
It is not a regional cross-lineage comparison. Every such Write remains visible
and retains its ordinary expressive actions. Closure inadmissibility removes
Commit eligibility, not the Write or its other valid actions. That Write's direct Committal continuation remains independently traversable. Stage 5 additionally permits an active homologous existing Order Committal whose frozen Depth/Cadence agree in the dominant resolved population direction. Strict committed Write coordinates and validated Origin ancestry are required; this exception does not broaden ordinary Traverse elsewhere. Neither population direction nor membership grants Commit eligibility or changes Closure measurement.

A population member outside the explicit Order homologous-Committal rule must never gain Traverse from comparison alone. Visibility
does not imply actionability. Actionability does not imply movement unless the
operation itself is structurally authorised from the current Gate. Reconcile,
for example, may use an authorised counterpart to create a new result with an
explicit edge from the current Gate; it does not move the Participant to that
counterpart.

Recovery begins at the actual current position and walks recorded arrival
provenance backward and stops at the nearest eligible non-exhausted Committal.
An older basin cannot compete until the nearer one is resolved. The current Horizon confines the search until
its boundary-governing region's exhaustion permits crossing its opening boundary, after which the same lineage
continues into previous Horizons. Recovery never resets to a Horizon provenance
node, searches siblings or descendants, or takes an unused parent as a shortcut.

## Relationship to More Specific Rules

These invariants govern more detailed operational rules; they do not replace
those rules or their documented conditions. The following are applications of
the invariants, and their linked definitions remain authoritative within these
constraints:

| Specific rule | Governing invariants | Detailed definition |
| --- | --- | --- |
| Support/Refute label Attestations rather than Propositions or measurement axes. | 1, 3, 7, 9 | [Attestation polarity](attestation-polarity.md) |
| Attestation-less Expressive Propositions default from the governing Committal Proposition; local divergence does not change the region's fixed polarity. | 5, 6, 7 | [Committal defaults and Origin's label choice](attestation-polarity.md) |
| Mediate preserves ordinary local causal options and adds operands from homologous Reads expressing dominant population Correspondence. | 4, 7, 10 | [Mediate: Correspondence Read](participation.md#mediate-correspondence-read) |
| Bias preserves the current Gate’s local evidence/ancestry rule and adds dominant-Chirality homologous Reclassify sources using only entry-Region Receipts. | 4, 7, 10 | [Bias Chirality Gate](participation.md#bias-chirality-gate) |
| Zero-valued hysteretic observables retain direction according to their documented histories and transition conditions. | 4, 7 | [Value and retained direction](attestation-polarity.md#value-and-retained-direction); [Closure hysteresis](closure-hysteresis.md) |
| Commit preserves original evidence and provenance when inheriting participation state. | 8, 9 | [Commit participation inheritance](observables.md#commit-participation-inheritance) |

See [Reinforcement](reinforcement.md) for qualification, signature normalization,
server-observed version stability, local Closure and Commit requirements.

When ambiguity arises, prefer the interpretation that preserves these invariants
rather than inferring semantics from current code behaviour.



## Persistence, exhaustion, recovery, and observation

**Potential invariant:** Potential measures demonstrated tension-reducing capacity. It does not decide whether expression is possible. Zero Potential neither suppresses an otherwise structurally valid Variant nor by itself proves a Commit Region exhausted. Charge and Potential algebra remain unchanged.

**Tragedy invariant:** Tragedy is the first fresh expressive proposition within a Commit Region whose resolved field state reaches zero Charge (`1 - |Tension|`, using the canonical projection precision). Potential Charge does not trigger Tragedy. The resulting proposition's creating Cycle determines Region ownership, including foreign-input writes. One Tragedy and one Receipt retain that first terminal identity; repeated boundary contact does not itself exhaust the Region. Committals, Rest and maintenance do not trigger it.

**Retained exhaustion invariant (pending Stage 6):** A Commit Region exhausts only after its Tragedy Receipt has been exercised and a subsequent no-Potential Rest on that consumed classification's actual participant lineage proves the final attempt also failed. An unrelated later Rest is insufficient. The region's exhaustion permits recovery past its governing Committal. If that Committal opens a child Horizon, the same proof permits crossing that boundary along actual backward lineage; it does not exhaust the child's other regions.

**Receipt invariant:** Ordinary Receipts are discovered and consumed within the Commit Region governed by the observed Proposition's first governing Committal, with existing qualification rules and no Horizon-wide fallback. They ground Propositions, persist historically and may be consumed exactly once. Attestations judge Propositions; historical Attestation-owned Receipts remain readable through their Proposition. Generation costs an action; metadata visibility is free; attachment costs an action and changes structure. Participant loads persist independently of prompt visibility and retain participant-specific Reinforcement. Full evidence enters cognition only for loaded Receipts grounding the current participant-facing Read. Eligible Gate load/unload desired states accompany a successful forward Phase contribution atomically without another wake; Rest and standalone Read cannot carry those options. An unconsumed Tragedy Receipt follows legitimate recovery from its current unresolved basin by any Participant and is shared throughout the destination Commit Region until exercised; it never changes recovery selection.

**Reclassification invariant:** Phase 3 and Phase 6 reclassification require an available Receipt. Reclassification atomically attaches/consumes the Receipt. No Receipt, no Reclassification. Tragedy Receipt creation follows an expressive Charge-boundary birth; consumption rules are unchanged in Stage 2.

**Generation invariant:** Variant availability is controlled by structural viability, independently of Potential Charge, Receipt lifecycle and Participant count. A further Variant requires all current expressive routes at that structural position to be spent in its current exploration epoch, each with its own completed Instrument and effective Attestation. Expression is allowed to increase Tension. A healthy route blocks redundant generation. Each new Variant repeats that sequence; no prior Rest or fixed sibling cap applies. See the [confirmed clarification](horizon-proof-variant-clarification.md).

**Retired final-attempt allowance:** Variant is structurally available independently of Potential. The special allowance service and dispatcher recording are removed. `tragedy_final_attempts` is retained only for historical migrations and provenance, with no live actionability or Tragedy authority.

**Successful-route continuity:** Commitment does not destroy an already-existing
causal route. When Bias is entered through the exact Gate whose causal
continuation produced a frozen classification, that classification remains
traversable through its existing causal edge, subject to the ordinary traversal
prerequisites. Order may then traverse the Commit produced directly from that
classification. A frozen classification elsewhere in the same Commit Region or
Context Horizon is not traversable unless it is the actual causal continuation
of the current Gate. Successful-route continuity preserves an existing edge;
it never manufactures one. Neither step creates content, votes or failure
evidence, and the frozen source remains immutable.

**Exhausted successful continuation:** After explicit outgoing Commit Region Exhaustion, an incoming successful route remains contextual history without being falsified as failure. This propagates through an expressive source only when all its other outgoing continuations are exhausted or failed at their exact current epoch. Live alternatives and Committals stop propagation. Resolved routes are not action targets or viable generation options, and their historical gain does not supply exploration capacity.

**Observation invariant:** Instrument generates evidence; Attestation becomes available only when evidence exists; Attestation becomes Traverse on the next Cycle under the existing rule.

**Persistence invariant:** Exhausted Committals persist permanently and remain contextually visible where structurally relevant. They expose no actions. Historical context is ordered newest-first by creation date. Exhausted means learned-from, not forgotten.

**Recovery invariant:** Exhaustion proof propagates upward through provenance locally, without global graph-wide recomputation. Tragedy uses normal backward-lineage recovery from the terminal position, without an opener override. Region exhaustion permits continuing backward to the previous ancestral Commitment; exhausting a boundary-governing region permits crossing into the parent Horizon on that same lineage. Other Participants move through their normal lifecycle.

**Observer invariant:** Active/exhausted filtering in the graph is presentation-only and must not alter Participant cognition or substrate dynamics.

**Phase-position invariant:** Labels classify. Position governs.

The backend derives Rest reason. Neither Rest reason creates Tragedy. The legacy `no_remaining_potential` exhaustion witness still requires an existing Tragedy and consumed continuation, pending Stage 6. Neither is a Participant assertion.

See [the proof lifecycle](branch-tragedy.md), [migration and compatibility](horizon-proof-migration.md), and [the integrated phase rules](flow-integration.md).

## Epoch-scoped expressive exploration

The [exploration epoch invariants](exploration-epochs.md#invariants) are normative.
Expressive propositions become spent relative to a Horizon, structural position
and exploration epoch; Commit Regions become exhausted permanently. Failure history
is immutable. Only a successful Variant advances its own position's epoch.
Spent propositions remain contextual evidence without Traverse or Attestation
targets; a later epoch restores normal eligibility without erasing history.


