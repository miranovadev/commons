# Participation API

The [Core Invariants](core-invariants.md) govern this shared contract for human
and automated Participants, including participant prompts and UI behaviour.
The operational rules below apply those normative semantic constraints.

Participant movement is lineage-constrained: forward progression follows an
explicit causal edge from the current Gate; recovery walks recorded causal
ancestry backward. Sharing a Commit Region, Context Horizon, register, phase
position, visible context or calculation population grants no movement.
**No edge means no movement.** The Homeostatic populations below are calculation
scopes, not alternative movement topologies.

The participation endpoint is scoped to a Substrate and Participant:

- GET /api/substrates/{substrate}/participants/{participant}/participate
- POST /api/substrates/{substrate}/participants/{participant}/participate
- POST /api/substrates/{substrate}/participants/{participant}/participate/enter
- GET /api/substrates/{substrate}/participants/{participant}/workspace (human display)

GET returns a resumable state. A ready state contains the active Cycle, the
current input plan and the Participant's Perspective bounds. Expected
incomplete states, such as a Cycle awaiting Potential selection, return a
successful response with status waiting and a machine-readable reason.

When a substrate has no proposition history, the waiting state advertises an
available `origin` entry. POSTing to `/participate/enter` with the Origin
statement, an Attestation and a Receipt instrument atomically establishes the
first Committal Proposition. Its bootstrap observables produce the initial
Closure Boundary, and the response places the Participant in a live Distinguish
Phase in the Settlement `signal` register.

    {
      "origin": {
        "statement": "The first commitment.",
        "attestation": {
          "direction": "support",
          "rationale": "Why this is a sound beginning."
        },
        "instrument": {
          "kind": "hyperlink",
          "reference": "https://example.test/evidence"
        }
      }
    }

Origin entry is serialized on the Substrate. A concurrent or repeated request
receives HTTP 409 and cannot create a second Origin. For an established
substrate, POST an empty JSON object to `/participate/enter`. Initial entry has
no observer position and ranks eligible Committal Propositions by Potential
Charge. Ordinary completion continues from the exact selected Committal.
Rest recovery considers only ancestral Committals on the recorded backward
lineage within the permitted Horizon, ranked using actual backward-distance
attenuation. Expressive positions count toward distance but are never entry
candidates. Same-region or same-Horizon membership cannot add a destination.
Successful entry persists the selected Committal Gate; contributions still
persist their resulting Gate within the Cycle. Rest recovery follows the causal
basin rules below.

Potential Charge is `max(0, max(|T| - |T_c|))`, where `T` is the Committal
state's immutable Tension and `T_c` is the projected Tension of each measurable
Expressive continuation reachable before the next Committal state. If no
continuation reduces Tension, selection falls back to the highest current
Charge, `1 - |T|`, for initial entry. Recovery stops at the nearest eligible
non-exhausted ancestral Committal before computing its diagnostic Potential and
Charge with `1 / (1 + d)^2`, where `d` is actual recorded backward edge distance.
Greater values at older ancestors cannot skip that unresolved basin. Ordinary completion retains
its exact Committal without ranking. Generic shortest-parent/child ranking is a
diagnostic calculation, not movement authority or a recovery fallback.
See [observer-relative Potential](observables.md#potential-observed-from-a-participant-position).

POST applies exactly one contribution:

    {
      "phase_id": "01...",
      "contribution": {
        "action": "proposition.variant",
        "statement": "A new distinction."
      }
    }

The phase ID is a concurrency token. A stale or repeated submission receives
HTTP 409 and cannot progress the Phase twice. The contribution, its ledger
entries, Phase completion and creation of the next Phase share one database
transaction.

The contribution object is validated against the JSON Schema returned in the
current plan. The React interface uses the same schema to render its controls.
ULID inputs accept both upper- and lowercase Crockford encodings; Laravel's
persisted ULIDs are normally lowercase.

The separate `/workspace` endpoint exposes a human presentation plan with a
`workspace` projection for the active Cycle UI. `/participate` and `/plan` use the
[compact autonomous contract](participant-context.md), with no Horizon populations.
The human projection names the active Domain and Context Horizon, returns the
current Commit Region's governing Committal Proposition, and groups the visible measurement population
by Classification Register. The human workspace presents these as operational
Read, Gate and Write rows. Mediate exposes the authoritative `read_options`
population. Order shows the nearest Mediate-position ancestors along the current
Gate paths; other phases retain their single Read card. Mediate Gate privileges
the current Proposition and adds admissible reconciliation counterparts. Bias
Gate preserves the current causal Proposition and adds authorized dominant-Chirality homologous Reclassify sources;
Lineage is available on demand; and Write separates existing actionable
Propositions from moves that create a new Proposition from the Gate. In phases 1–5,
an existing Write card is a valid Expressive descendant of the current Gate in the
phase's Write register, including structurally valid revisions. Bias Traverse
offers only causal Write continuations of the current Gate. Other regional
Writes may be calculation or permitted historical context; they are not
Traverse cards. Order retains every causally local expressive Write in Write,
including inadmissible and frozen sources, alongside their own traversable direct
Committal results. Commit is available per admissible expressive Write; Closure
refusal does not remove that Write or override its ordinary expressive rules.
All Instruments are offered before Order. `write_options` is authoritative; `workspace.registers` remains
broader inspection context and must never be merged into selectable Write cards.
Revise remains limited to owned, revisable descendants. Commit cards and the
Commit form select an explicit authorized `proposition_id`.

Expressive Propositions include an `impact` projection containing the parent
measurement as `from` and the expressive measurement as `to`. This makes the
size and direction of the structural transition visible; the destination is
not inferred from a centre point. The human rails use lighter indigo for
movement toward Prominence and darker indigo for movement toward Turbulence.

The plan also includes the viewing Participant's effective `attestation` on
each Proposition. A later eligible Write may change that direction while the
Proposition remains a valid Write descendant. The replacement links to the
prior Attestation for auditability, derived measurements use only the effective
leaf, and at most one Attestation can be recorded for a Proposition in a given
Phase.

The governing provenance is displayed separately from register browsing. It may
be a later same-Horizon Commit, not the Horizon-opening Closure Boundary.
Register propositions are returned newest-first and bounded by the
Participant's current read limit. Each compact Proposition includes its
observable values, so the active workspace does not need to fetch the
substrate's complete public graph before it can render.

    {
      "workspace": {
        "domain": "settlement",
        "horizon": { "id": "01...", "sequence": 2 },
        "provenance": { "id": "01...", "register": "canon" },
        "registers": {
          "signal": [],
          "stance": [],
          "concord": []
        }
      }
    }

Revision is bound to a specific `proposition_id`. The server only advertises
owned, unsuperseded expressive Propositions in the current write register and
explicitly excludes the Gate. Because a Proposition written during one Cycle
becomes available when that producing phase position is encountered again, revision naturally
occurs in a subsequent Cycle rather than revising the current Gate in place.

## Proposition history

Reclassify at Phase 3 Constrain and Phase 6 Bias preserves the exact source statement and requires explicit `proposition_id` and `receipt_id`. It atomically attaches the selected available Commit Region Receipt, consumes it once, and creates the classification. Attestations and phase touches start fresh. Other historical source evidence remains in place.

Variant, Revise and Reconcile create new content/structure with fresh votes and touches. Commit retains the selected source's participation history by reference and freezes its evaluation. Receipt visibility and consumption are separate from optional loading for Reinforcement.

## Structural generation and evidence

| Position/state | Available progression |
| --- | --- |
| Empty Phase 1 or Phase 4 opportunity | Variant |
| Existing Proposition without its own completed Instrument | Instrument, even if other shared Receipts exist; no Attest |
| Own completed Instrument and available Commit Region Receipt | Attest |
| Personal Attestation from an earlier Cycle | Same-side Traverse |
| Phase 2 | Structural Variant, governed by viable existing results |
| Phase 3 | Reclassify an attested Phase-2 source with an available Receipt |
| Phase 5 | Existing directed reconciliation and Correspondence rules |
| Phase 6 | Reclassify an attested Phase-5 source with Receipt and lineage agreement |
| Phase 7 | Existing grounded, freshly attested and Closure-aligned Commit or Committal Traverse |

Variant eligibility does not depend on Receipt generation/consumption or Participant count. A further Variant requires all existing routes at its structural position to be spent in the current epoch, with their own completed Instrument and effective Attestation, plus positive remaining Horizon Potential Charge. A viable existing route blocks redundant generation. Only successful Variant advances that position's epoch; other positions remain unchanged. Spent routes remain visible without Traverse, Attestation or redundant Instrument targets. See [exploration epochs](exploration-epochs.md). Each new Variant repeats this sequence. No prior Mediate Rest or fixed sibling cap applies; see the [confirmed clarification](horizon-proof-variant-clarification.md).

Instruments target mutable expressive Writes in phases 1–6. Phase 1/4 offer Instrument when a Write lacks its own completed Instrument or no shared Receipt remains available. Existing evidence makes Attestation available without a transfer from its author or a paid visibility action. Transactional execution rechecks the same rules advertised in the schema.

## Traverse and authoritative Rest

The earlier selection action (`participant.select`, implemented on main as
`proposition.select`) is now `proposition.traverse`. There is no compatibility
alias: no persisted action contract requires one. The old automatic
`participant.traverse` exhaustion operation is removed too.

```json
{
  "phase_id": "01...",
  "contribution": {
    "action": "proposition.traverse",
    "proposition_id": "01..."
  }
}
```

Expressive Traverse is available during Distinguish, Persist, Constrain, Disperse and Bias after personal effective Attestation in an earlier Cycle. Receipt consumption does not erase that established path. It
explicitly chooses an existing local **Expressive Proposition** in
the current Write register. Targets are immediate valid descendants of the Gate in the
existing supersession graph, including historical superseded Propositions, and
require the viewing Participant's effective Attestation. Another Participant's
Attestation does not establish this path.
Same-region and same-position Writes which are not causal descendants of the
current Gate are not Traverse targets. Context visibility, phase compatibility,
Homeostatic selection and an existing Commit elsewhere cannot supply that edge.
The current Gate and positions already visited as Gates in this Cycle are
excluded; later Cycles may revisit them. Traversal creates no Proposition,
Supersession or evidence and never selects globally. It advances the Phase as
selection did. Mediate does not admit expressive Traverse. Order preserves that direct Committal continuation and additionally permits dominant-direction homologous existing Committals under its strict committed Write rule below. Both complete the Cycle at the selected Committal; this narrow exception creates no synthetic edge and does not loosen Traverse in other Phases.

Commitment preserves successful routes: at Bias, a frozen classification remains
traversable only from the exact Gate whose causal continuation produced it,
subject to ordinary traversal prerequisites. Order can then follow its direct
Commit edge. A frozen classification elsewhere in the same Horizon or Commit
Region remains ineligible from this Gate. Continuity preserves an existing edge;
it never manufactures one.

In phases 1–4 and Bias, a receipted Write exposes the following direction slots:

| Effective personal Attestation | Refute side | Support side |
| --- | --- | --- |
| None | Refute | Support |
| Support | Refute | Traverse |
| Refute | Traverse | Support |

The specialized `attestation.create.anyOf` branches bind each direction to its
permitted Proposition IDs. Same-direction re-attestation is unavailable; the
opposite direction uses the existing Attestation supersession mechanism.
The card footer, form and inference schema use these same choices. In Mediate an effective Attestation does not create a Traverse affordance; the opposite direction remains available. Order uses one Committal Traverse action without Attestation controls. Traverse
creates neither a Proposition nor a Supersession and advances through the
existing personal path. Reconciliation counterparts belong to Gate, as described below.

Write card footers reserve three regions: Refute (or its Traverse replacement)
on the left, other authorized actions such as Revise and Instrument in the
middle, and Support (or its Traverse replacement) on the right. Empty regions
remain in place, and middle actions wrap without moving the directional slots.
The UI renders Rest only from the authoritative schema, never from its own
interpretation of the rest_available flag or visible cards.

**No available move** means the current Phase has zero admissible Phase-advancing actions. The backend separately derives `no_remaining_potential` from an evidenced failure. Only that reason can create Tragedy or Exhaustion.
The server first applies all phase-native, ownership, target and Commitment
constraints, then specializes the complete action schema. When no Phase-advancing
action remains it sets `rest_available: true` and injects `participant.rest`.
Informational Receipt metadata does not block Rest. Rest changes no Receipt load state.

Rest is never a preference over a Phase-advancing action. An available
Variant, Reclassify, Revise, Attestation, phase-admissible Traverse, Reconcile, Instrument or Commit
prevents Rest. In particular, Distinguish, Persist and Disperse cannot offer
Rest while their first Variant opportunity remains admissible. After generation,
existing Write support determines whether local actions remain. An empty
traversal set alone says nothing about complete action exhaustion.

The plan reports `traversal_options` and `instrument_options` separately from
`rest_available`. Complete Write and traversal target sets are not truncated by the
Horizon display limit. A successful Commit is a recovery candidate only if it
lies on the Participant's recorded backward lineage. A nearer unresolved
Committal prevents recovery to an older ancestor regardless of numerical values. Regional
measurement populations and maintained frontiers do not add recovery candidates.

Only an actionless Phase exposes `participant.rest`:

```json
{
  "phase_id": "01...",
  "contribution": { "action": "participant.rest" }
}
```

Rest creates no expressive contribution or evidence, completes the Phase and
Cycle, records `cycles.rested_at`, and immediately prepares the next Cycle when
a viable recovery destination exists. Otherwise the current position is retained
and the next wake or `/participate/enter` retries recovery:

1. Begin at the Participant's actual terminal position and completed Cycle Horizon. Walk incoming causal edges backward through recorded arrival provenance. At a multi-input Write, follow the Participant's recorded arrival Gate, never an unused parent shortcut.
2. Stop at the nearest eligible non-exhausted ancestral Committal within the current Horizon. The retained Committal may itself be the distance-zero boundary. Same-region members, siblings, descendants and unvisited branches are ineligible.
3. Do not inspect older Potential or Charge to choose a destination. An unresolved nearer basin must receive its attempt even at zero numerical Potential. Explicit exhaustion permits continuing past it. Diagnostic values for the selected destination retain inverse-square weighting by actual backward edge distance, including revisions and historical intermediates.
4. Remain within the current Horizon until exhaustion permits crossing its opening boundary, which must occur on the same recorded path. Continue backward through previous Horizons on that lineage, retaining the original distance origin. Never reset to a Horizon provenance node or search siblings or descendants. Each Horizon requires its own exhaustion proof; numeric zero alone is insufficient.
5. The selected ancestral Committal determines the new Cycle's Horizon: its own creating Horizon for a non-boundary Commit, or its explicitly opened direct child for a boundary. Do not pair an old Gate with an arbitrary descendant Horizon.

Fresh open basins are not exhausted merely because their Potential is zero.
Tragedy uses this same backward recovery, without an opener override. When no
eligible candidate exists, the Participant remains at its retained position;
there is no global or sideways fallback. No edge means no movement. See
[recovery basins](recovery-basins.md). GET exposes `entry.selection: recovery`, availability,
prospective destination, distance and Potential Charge, but never moves anyone.
The existing `entry.mode: potential` transport is retained for entry clients.

Attenuation is a substrate-level measurement rule: independently evaluated local
Proposition measurements are averaged with inverse-square weights
`1 / (1 + structural_distance)^2` while walking from the active head to provenance.
Every visited position contributes denominator weight. Raw Potential uses these
accumulated measurements without re-discounting continuation length. Another
Committal still stops the outgoing continuation search. Immutable snapshot
Tension remains the historical baseline. Recovery selects the nearest eligible non-exhausted ancestral Committal before
computing its diagnostic Potential and Charge with the same inverse-square
weighting and actual backward edge distance. These diagnostic values cannot skip
a nearer unresolved basin. Initial entry has no positional attenuation.
Calculation geometry does not grant movement. See
[observables](observables.md) for the full calculation.

Order returns an actionable `ready` plan even when Commit is inadmissible.
`plan.commitment` and `commitment_preconditions_unsatisfied` preserve diagnostics
for missing Attestation, Receipt and Closure alignment. An existing Committal
produced directly from the current classification Gate can still offer Traverse
when a new Commit is refused. A Committal elsewhere in the region cannot.
If neither terminal action exists, the schema offers exclusive Rest. Automated wakes
ask inference to choose from exactly the same action schema; they never traverse
automatically. Stale phase tokens cannot apply another move.

## Mediate: Correspondence Read

Mediate's Read anchor is the Gate recorded on the preceding completed Disperse
Phase in the **same Cycle**. It is not Mediate's Gate, the latest Horizon card,
or a guessed ancestor. Missing Disperse history supplies no Read/counterpart
options; the server does not invent an anchor.

`read_options` exposes only that causal anchor. Its ordinary local comparison
path is preserved: eligible regional Reads matching its retained Correspondence
can still supply their immediate Disperse Gates, regardless of the population's
dominant direction.

Separately, `HomeostaticPopulationService` resolves every homologous Constrain
Read at the same substrate, proposition Domain, canonical Phase/register, tier
and committed depth. The anchor is counted once; Regions and Horizons may differ.
Each Read retains its own lineage-derived Correspondence. Their equal-weight
numeric mean determines the dominant population direction. The anchor identifies
the coordinate, not the direction. The entries' creating Domains are not an
additional restriction.

Active homologous Reads expressing that dominant direction supply additional
active, canonical, immediate Disperse Gate children in each Read's own Region
and Horizon. At numeric zero a member uses its retained Correspondence direction;
unavailable measurements cannot qualify through homology. A zero population mean
retains prior population direction when supplied, or starts from Correspondence's
positive default; matching members may still supply operands. Ordinary local
eligibility remains independently available. Measurements and attenuation do not
change, and the aggregate is never written back into them.

Gate Correspondence and Chirality do not select these children. The current
causal Gate must remain in the ordinary eligible Gate pool; `gate_options`
contains its counterparts. Availability, exhaustion and directed-pair uniqueness
still apply. Reconcile copies the current Gate's statement and creates two hard
causal edges. Its result uses the acting Cycle's Phase, entry Region and Horizon.

Homologous Gates are Reconcile operands only. They do not become Read choices,
Traverse destinations or Receipt catalogue sources. The acting Phase Gate remains
traversed provenance; recognition alone never moves the Participant.

## Shared Commit Region scope

This scope applies [Invariant 10: structural authority](core-invariants.md#10-operations-may-only-act-within-their-structural-authority):
measurements select within eligible populations and must not redefine them.

Commit Region membership defines calculation/context scope only where a Commons
operation explicitly requires it. It does not define Participant movement and
never creates a traversal edge. Context visibility, Homeostatic populations and
movement topology are separate contracts. No edge means no movement.

`CommitRegionService` resolves ownership from immutable Cycle entry. A
Committal owns itself. An expressive Proposition belongs to its creating Cycle's
`entry_committal_id`; Supersession records causal provenance independently.
Multiple parent Regions do not erase a valid owner. Missing or invalid Cycle entry
fails closed without ancestry inference. Causal history preserves superseded
intermediates; active populations, movement and operational admissibility remain
separate contracts.
See [Commit Region ownership](commit-region-ownership.md).

An immediate parent alone is too narrow to define a Homeostatic calculation
population; this does not widen movement beyond the current Gate's authorised
causal continuations. A Context Horizon is measurement/comparison context and
can contain multiple Commit Regions.
Horizon membership does not define candidate membership, even if a branch's
ledger entries have different Horizon labels. Substrate isolation remains.

The reusable `RegisterPosition` mapping is extracted unchanged from structural
Correspondence: Activity/Signal = First (R), Frontier/Stance = Second (G), and
Canon/Concord = Third (B). These are vocabulary slots, not phase positions.
[Invariant 16](core-invariants.md#16-register-identity-does-not-confer-phase-identity)
requires an independent producing Phase check. Phase-position compatibility
and region membership still do not confer traversal eligibility. The Homeostatic
scopes are:

| Homeostatic effect | Calculation population | Evaluation |
| --- | --- | --- |
| Mediate — Read | Homologous Constrain-produced Third / Reads, including the causal Read | Dominant population Correspondence adds operands; ordinary local causal options remain independently eligible |
| Bias — Gate | Homologous Mediate-produced Second / Gates | Dominant numeric Chirality adds Reclassify sources; the current Gate keeps its local evidence/ancestry rule; all action prerequisites still apply |
| Closure, performed during Order — Write | Each causally local Bias-produced expressive Write | Its own Depth/Cadence, lineage/history and participation; grounded and attested for Commit |

Mediate may inspect homologous Reads across Regions/Horizons and expose their
compatible Disperse Gates as reconciliation counterparts only. Bias may expose
dominant-matching homologous Gates as Reclassify sources, preserving the current
Gate's local option. Closure is not a regional cross-lineage
comparison: it evaluates each causally local Write independently. The Participant
remains on its own causal lineage; calculation membership supplies no operational
target or movement authority.
Any Phase-advancing operation must independently be authorised from the current
Gate and follow or create its legitimate causal continuation.

Active candidates exclude outgoing hard supersessions; soft Variant branches
remain active. A direct Commit edge consumes its source globally; other branches remain available.

## Bias Chirality Gate

For ordinary local eligibility, `BiasGateService` compares the current head's retained direction to its own causal ancestry, excluding the head's votes and loads. Shared ancestors and Attestations are counted once across both reconciliation parents. Traversal stops at the governing Committal boundary; unrelated branches do not set the comparison. Ledger replay retains the preceding nonzero direction at zero, starting from the governing Committal direction. Superseded ancestors supply historical judgments without becoming additional active reinforcement copies.

Bias additionally requires a completed Receipt and an effective source Attestation. The action takes `action: proposition.reclassify`, `proposition_id` and `receipt_id`. It preserves exact content, attaches/consumes the selected Receipt, resets votes/touches, and creates one shared source/register classification. `BiasGateService::forPhase()` remains the existing regional evidence calculation;
`operationalFor()` preserves its eligible current Gate and adds qualified sources
from `HomologousBiasService`. The latter uses the shared Homeostatic services to
aggregate every homologous Gate's own numeric Chirality, including the current
Gate once. Positive and negative means follow sign; zero retains supplied prior
population direction or uses Chirality's negative default. No population state
is persisted or written into proposition measurements.

Additional sources must be active and unexhausted and pass ordinary reclassification
prerequisites. They need not satisfy the local current Gate's evidence/ancestry
comparison: their distinct qualifying comparison is dominant numeric Chirality.
The selected Receipt must be completed, unconsumed and in the acting Cycle's
immutable entry catalogue. Source and Receipt may originate in different Regions
or Horizons. The selected source's Receipt stock stays untouched.

The result belongs to the acting Bias Phase, Horizon and entry accounting Region.
Its sole reclassify edge comes from the selected source, and the participant
advances to that new classification. `CausalWriteService` recognizes this exact
Phase's recorded result without inventing a second edge from its old Gate.
No generic foreign Read, Traverse or Receipt catalogue is granted.

Existing causal Write continuations of the current Gate offer their permitted
Instrument and Attestation actions and, after earlier-Cycle personal
Attestation, Traverse to Order. Homologous Gates are Reclassify sources only;
their existing Bias-produced Writes do not become this Participant's Traverse
options. A foreign-source classification owned by another Cycle-entry Region
cannot be adopted as a local Write merely through its Supersession edge. Historical material on the
Participant's own lineage remains visible under the existing history rules.
The same restriction applies to frozen successful classifications. No edge
means no movement.

## Order: local Commit and additional homologous Committal Traverse

Read contains nearest Mediate-produced Stance/Frontier ancestry. Closure,
performed during Order — Write, evaluates each local expressive Write's own
Depth/Cadence, lineage/history and participation. Order's recorded Gate is the
selected Bias Write. That Write and its existing same-position revision paths
are local; another classification sharing its Stance/Frontier parent is not.
`CausalWriteService` supplies this population without scanning regional Writes.

Every local expressive Write stays in `write_options`, even when Closure diverges
or other Commit prerequisites are missing. Closure only gates Commit; it does
not make a Write invisible or override its ordinary expressive action rules.
Order permits local Commit and direct Committal Traverse under their ordinary rules, independently of population direction. It also offers active existing homologous Order Committals matching the population's dominant resolved Closure direction. The Write coordinate requires the same substrate, creating Domain, canonical Phase/register, tier and full committed depth from the validated shared Origin; different Horizons and Regions are permitted. Frozen Depth/Cadence disagreement is unresolved and contributes no directional lean. Agreement contributes its retained direction; a population tie uses explicit prior direction or Closure's negative default. See [Homeostatic populations](homeostatic-populations.md#stage-5-order-committed-continuations).

This adds no Attestation, Instrument or revision at Order. Commit still requires every local prerequisite. Frozen sources remain immutable. Homologous comparison imports no Receipt stock or Read scope and writes no causal edge. The action chooses the next Cycle entry directly: created Committal for Commit, selected existing Committal for Traverse. The previous Cycle's entry never changes.

Commit copies the selected source's exact statement and participation history by reference, creates an immutable snapshot, and consumes the direct source globally. Later evidence reads cannot rewrite that snapshot. Reinforcement is optional weight; Depth/Cadence remain the admissibility gate. `plan.commitment` describes the current position and `commitment_options` describes every local expressive Write, including inadmissible ones. The same rules govern schema, UI and execution.

## Binary Reconcile

```json
{
  "action": "proposition.reconcile",
  "other_proposition_id": "01..."
}
```

Mediate alone exposes Reconcile. It accepts no `statement`. It creates one fresh Expressive Proposition in
the Phase's Write register with the current Gate's statement copied exactly and
two hard `reconcile` edges, one from each source. It inherits no Attestations,
Receipts or Phase touches. Normal progression earns a new target touch.
The dispatcher rechecks admissibility before any writes. The Proposition/edge
ledger records, source history ownership and measurement recalculation remain
unchanged.

## Bias grounding

Reclassification is separate from Instrument execution. All phases before Order expose Instruments on eligible expressive Writes, including Distinguish and Mediate. Receipt existence permits Attestation; both Receipt and effective Attestation permit reclassification. Reclassify attaches the selected regional Receipt and resets judgments. Variant, Revise and Reconcile need new evidence for their new Writes.

A fresh Bias classification may reach Order before anyone attests it. If neither a qualifying Commit Gate nor an established Committal Write exists, Rest is valid. Another Participant can later attest the classification through Bias or an earlier eligible Write encounter. Receipt loading remains optional before Order and never advances a Phase.

## Human Attestation controls

In the main Participant workspace, Support, Refute and Traverse submit directly
through the existing participation endpoint. Pending submissions disable the
workspace controls; errors are shown without advancing the local view.
Other contribution actions retain their forms.

Click a writable Proposition card to open its inspector and submit an Attestation
with an optional rationale. The drawer's direction choices use the same schema
as the card footer. Cards show separate effective Support and Refute counts from
all participants, including Commit inheritance, beside the field values. These
counts do not establish the current participant's traversal permissions.
Violet Support spins clockwise and teal Refute counterclockwise; reduced-motion
preferences disable animation.

## Terminal Order and explicit next-Cycle entry

Order admits only `proposition.commit` and `proposition.traverse`, or exclusive Rest when neither remains. Commit and Committal Traverse both complete the Cycle at the exact Committal result. Natural completion leaves `rested_at` unset. The next explicit entry begins Phase 1 at that same Proposition, respecting its applicable Context Horizon; no normal completion relocates the Participant.

The waiting state has `plan: null`, entry availability and the completed Cycle identity. The workspace can retain completed cards as read-only context. Return to Participants opens the Substrate collection; Enter another Cycle uses the normal entry endpoint. Rest is the separate backward-lineage recovery path: the nearest eligible non-exhausted ancestral Committal within the permitted Horizon is selected, regardless of older values. Region-wide calculation or visibility cannot relocate the Participant.

### Receipt memory and Instrument replay

Gate `receipt_options.load/unload` changes persistent Participant memory only
alongside a successful forward contribution. Loaded state survives Cycles and
Horizons; bodies activate only when grounding the current Read. Catalogue and
Proposition references remain metadata without standalone Load controls.

[Instrument replay](instruments.md) uses `instrument.execute` with
`instrument: {"replay_receipt_id": "R"}` and the exact schema-authorized
`proposition_id`. It restores the stored invocation, preserves ordinary eligibility
and equivalent-evidence exclusions, and creates a new Proposition-grounded Receipt
with replay provenance. Neither fresh execution nor replay automatically loads
source or result. There are no standalone Receipt Read or Replay action families.

No-Potential failure is observed outside the Rest write lock and published with an exact field-version guard. First failure records Tragedy and its unique Receipt, keeping the Horizon open. A subsequent no-Potential Rest can record Exhaustion only after that Receipt has been consumed by Reclassify. Compact historical alternatives remain visible and non-actionable. See [Tragedy and final-attempt Exhaustion](branch-tragedy.md).

Gate Receipt load/unload desired states accompany a forward Phase contribution (never Rest) and take cognitive effect in the next Phase. Only loaded evidence grounding the participant-facing Read activates a full body; other Receipt references are metadata. See the normative [Receipt model](receipt-region-context.md), including the shared recovery-scoped Tragedy exception.

A recorded unary Bias classification may resolve its causal provenance to the
selected source's Region while retaining the acting Cycle's accounting entry.
`ActiveParticipantLineage` recognizes that recorded bridge, including through
later revisions; unrecorded foreign edges still fail the provenance check.
Potential retains the edge as a foreign input and excludes it from local terrain,
just as for existing foreign Reconcile inputs. No Potential formula changes.


