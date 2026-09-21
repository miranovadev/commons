# Core Invariants

The system must adhere strictly to these invariants, regardless of implementation.
If an implementation detail conflicts with an invariant, the invariant takes
precedence. These are normative constraints on The Commons model and govern the
more specific operational rules documented beneath them.

The authority order is: normative Commons invariants, then explicit phase and
operation semantics, then implementation representation. Implementation history
does not override the movement topology defined in Invariant 17.

Persistence, final-attempt Exhaustion, recursive recovery, Horizon Tragedy, and observer
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
its documented Committal selection. Recovery after Rest ranks only Committals
on the recorded backward lineage, with actual backward-distance attenuation.
Neither Potential nor a fallback can admit a sibling, descendant or unrelated
Committal. A prospective Commitment is not eligible until it exists.

Rest recovery walks backward from the actual participant position, using recorded
arrival provenance at multi-input Writes. Only ancestral Committals on that path
may compete, at their actual backward edge distance. Sibling and descendant
frontiers are excluded. The persisted Cycle Horizon constrains candidates until
explicit exhaustion permits continuing backward across its opening boundary.
Previous Horizons do not reset the position or distance to their provenance node.
Potential ranks structurally eligible entries; numeric zero never proves closure.
Origin remains unique and persistent. Its Horizon follows the same final-attempt proof. See the invariants below.

Potential is the strongest remaining viable continuation under absolute-Tension-reduction semantics, never an additive budget. Writes maintain affected region projections and propagate changed routing summaries through provenance. Ordinary reads consume the published field version; they never repair measurements or reconstruct the whole substrate. A missing or inconsistent projection is an explicit recoverable maintenance condition. See [Incremental Potential](incremental-potential.md).

Potential selects a Committal. That Committal determines the entry Horizon: its own creating Horizon, or the direct child explicitly opened by its boundary. Closure markers do not prohibit causal backtracking. Entry must never search arbitrary open descendants to make an old Gate fit. Persisted ownership remains `Proposition → Phase → Cycle → Context Horizon → Domain`; Gates must belong to or explicitly open their Cycle Horizon, and writes must use that Domain's vocabulary.

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
traversal eligibility. Traversal additionally requires the appropriate causal
relationship to the Participant's current Gate. No edge means no movement.

## 17. Lineage-Constrained Participant Movement

A positioned Participant may change structural position only by forward
progression through an explicit causal edge from its current Gate into a
legitimate continuation, or by recovery backward along its recorded causal
lineage. A structurally authorised contribution may create that forward edge;
Traverse follows an already-existing edge and never creates one.

There is no sideways movement. A Proposition is not a movement destination
merely because it shares a Commit Region, Context Horizon, register or producing
Phase position; is visible in context or included in a measurement/Homeostatic
population; has greater Potential; was previously committed; or is structurally
nearby. None of these properties creates a causal movement edge.

**No edge means no movement.**

These three contracts must remain distinct:

| Contract | Authorised scope | Movement consequence |
| --- | --- | --- |
| Movement topology | Forward causal continuations of the current Gate; recorded causal ancestry backward for recovery | Only these edges permit a change of position, subject to the operation's other rules |
| Homeostatic population | Regional Reads for Mediate and Gates for Bias; each causally local Write's own lineage/participation history for Closure | Calculation, comparison or selection does not create edges or relocate the Participant |
| Context visibility | Historical and evidentiary material admitted by the context contract | Visibility grants neither actionability nor movement |

Mediate — Read and Bias — Gate may calculate across the defined regional
populations. Only Mediate exposes compatible foreign-lineage Gates operationally,
as reconciliation operands, never movement destinations. Reconcile creates causal
edges from both inputs to its new result. Bias exposes only the current causal
Gate and that Gate's legitimate Write continuations; other calculated Gates are
not operational options.

Closure, performed during Order — Write, evaluates each causally local expressive
Write independently from its own Depth/Cadence, lineage/history and participation.
It is not a regional cross-lineage comparison. Every such Write remains visible
and retains its ordinary expressive actions. Closure inadmissibility removes
Commit eligibility, not the Write or its other valid actions. Only that Write's
own direct Committal continuation may be traversed. Measurement selection never
expands operational populations or movement topology.

An unrelated population member must never appear as a Traverse target. Visibility
does not imply actionability. Actionability does not imply movement unless the
operation itself is structurally authorised from the current Gate. Reconcile,
for example, may use an authorised counterpart to create a new result with an
explicit edge from the current Gate; it does not move the Participant to that
counterpart.

Recovery begins at the actual current position and walks recorded arrival
provenance backward. Only ancestral Committals on that path may compete, using
actual backward edge distance. The current Horizon confines the search until
exhaustion permits crossing its opening boundary, after which the same lineage
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
| Mediate uses the traversed-to Read's retained Correspondence direction, within its eligible Commit Region and register position. | 4, 7, 10 | [Mediate: Correspondence Read](participation.md#mediate-correspondence-read) |
| Bias compares each head with its own causal lineage aggregate, excluding the head. | 4, 7, 10 | [Bias Chirality Gate](participation.md#bias-chirality-gate) |
| Zero-valued hysteretic observables retain direction according to their documented histories and transition conditions. | 4, 7 | [Value and retained direction](attestation-polarity.md#value-and-retained-direction); [Closure hysteresis](closure-hysteresis.md) |
| Commit preserves original evidence and provenance when inheriting participation state. | 8, 9 | [Commit participation inheritance](observables.md#commit-participation-inheritance) |

See [Reinforcement](reinforcement.md) for qualification, signature normalization,
server-observed version stability, local Closure and Commit requirements.

When ambiguity arises, prefer the interpretation that preserves these invariants
rather than inferring semantics from current code behaviour.



## Persistence, exhaustion, recovery, and observation

**Potential invariant:** Potential measures currently instantiated opportunity. Zero Potential does not by itself prove a Horizon exhausted.

**Tragedy invariant:** Tragedy is the first no-Potential Rest within a Horizon. It creates exactly one Tragedy Receipt and opens the Horizon's final evidence-bearing attempt.

**Exhaustion invariant:** A Horizon exhausts only after its Tragedy Receipt has been exercised and a subsequent no-Potential Rest on that consumed classification's actual participant lineage proves the final attempt also failed. An unrelated later Rest is insufficient.

**Receipt invariant:** Ordinary Receipts are discovered and consumed within the Commit Region governed by the observed Proposition's first governing Committal, with existing qualification rules and no Horizon-wide fallback. They ground Propositions, persist historically and may be consumed exactly once. Attestations judge Propositions; historical Attestation-owned Receipts remain readable through their Proposition. Generation costs an action; metadata visibility is free; attachment costs an action and changes structure. Participant loads persist independently of prompt visibility and retain participant-specific Reinforcement. Full evidence enters cognition only for loaded Receipts grounding the current participant-facing Read. Eligible Gate load/unload desired states accompany the normal contribution atomically without another wake. An unconsumed Tragedy Receipt follows its triggering Participant's legitimate recovery and is shared throughout the destination Commit Region until exercised; it never changes recovery selection.

**Reclassification invariant:** Phase 3 and Phase 6 reclassification require an available Receipt. Reclassification atomically attaches/consumes the Receipt. No Receipt, no Reclassification. Tragedy is exceptional in generation, not in consumption.

**Generation invariant:** Variant availability is controlled by structural viability, not Receipt lifecycle and not Participant count. A further Variant requires all current expressive routes at that structural position to be spent in its current exploration epoch, each with its own completed Instrument and effective Attestation, with positive remaining Horizon Potential Charge. A healthy route blocks redundant generation. Each new Variant repeats that sequence; no prior Rest or fixed sibling cap applies. See the [confirmed clarification](horizon-proof-variant-clarification.md).

**Final-attempt allowance:** Stage 5 explicitly extends only the numerical requirement above: an open Horizon's unconsumed Tragedy authorizes one shared Distinguish/Disperse Variant at zero Potential. Existing structural and evidence prerequisites remain mandatory. The successful Variant atomically records that unique attempt and advances its own epoch. Receipt creation, loading and consumption do not change epochs. See [the lifecycle rationale](branch-tragedy.md#attainable-final-attempt).

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

**Exhausted successful continuation:** After explicit child-Horizon Exhaustion, an incoming successful route remains contextual history without being falsified as failure. This propagates through an expressive source only when all its other outgoing continuations are exhausted or failed at their exact current epoch. Live alternatives and Committals stop propagation. Resolved routes are not action targets or viable generation options, and their historical gain does not supply exploration capacity.

**Observation invariant:** Instrument generates evidence; Attestation becomes available only when evidence exists; Attestation becomes Traverse on the next Cycle under the existing rule.

**Persistence invariant:** Exhausted Committals persist permanently and remain contextually visible where structurally relevant. They expose no actions. Historical context is ordered newest-first by creation date. Exhausted means learned-from, not forgotten.

**Recovery invariant:** Exhaustion proof propagates upward through provenance locally, without global graph-wide recomputation. Tragedy uses normal backward-lineage recovery from the terminal position, without an opener override. Exhaustion subsequently permits continuing backward into the previous Horizon on the same lineage. Other Participants move through their normal lifecycle.

**Observer invariant:** Active/exhausted filtering in the graph is presentation-only and must not alter Participant cognition or substrate dynamics.

**Phase-position invariant:** Labels classify. Position governs.

The backend derives Rest reason. Only `no_remaining_potential` can create Tragedy or Exhaustion; `no_available_move` cannot. Neither is a Participant assertion.

See [the proof lifecycle](branch-tragedy.md), [migration and compatibility](horizon-proof-migration.md), and [the integrated phase rules](flow-integration.md).

## Epoch-scoped expressive exploration

The [exploration epoch invariants](exploration-epochs.md#invariants) are normative.
Expressive propositions become spent relative to a Horizon, structural position
and exploration epoch; Horizons become exhausted permanently. Failure history
is immutable. Only a successful Variant advances its own position's epoch.
Spent propositions remain contextual evidence without Traverse or Attestation
targets; a later epoch restores normal eligibility without erasing history.

