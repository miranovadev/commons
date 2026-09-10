# Participation API

The participation endpoint is scoped to a Substrate and Participant:

- GET /api/substrates/{substrate}/participants/{participant}/participate
- POST /api/substrates/{substrate}/participants/{participant}/participate
- POST /api/substrates/{substrate}/participants/{participant}/participate/enter

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
substrate, POST an empty JSON object to `/participate/enter`. For a Participant without a position, Potential selects the Committal
Proposition with the highest Potential Charge and persists it as the initial
position. Subsequent Cycles start at `participants.current_proposition_id`;
they never run global entry selection again. Successful contributions persist
the resulting Gate, including the final commitment. Existing Participants are
backfilled from their latest Phase or completed Committal write.

Potential Charge is `max(0, max(|T| - |T_c|))`, where `T` is the Committal
state's immutable Tension and `T_c` is the projected Tension of each measurable
Expressive continuation reachable before the next Committal state. If no
continuation reduces Tension, selection falls back to the highest current
Charge, `1 - |T|`. Exact ties use the Proposition ID for stable ordering and do
not give precedence to lineage depth.

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

The plan also exposes a `workspace` projection for the active Cycle UI. It
names the active Domain and Context Horizon, returns the Horizon's provenancial
Committal Proposition separately, and groups the visible measurement population
by Classification Register. The human workspace presents these as operational
Read, Gate and Write rows. Mediate exposes the authoritative `read_options`
population; other phases retain their single Read card. Mediate Gate privileges
the current Proposition and adds admissible reconciliation counterparts. Bias
Gate shows only the field-admissible unary choices, including the current
Proposition when eligible;
Lineage is available on demand; and Write separates existing actionable
Propositions from moves that create a new Proposition from the Gate. An
existing Write card is an immediate valid Expressive descendant of the current
Gate in the phase's Write register. `write_options` is the authoritative forward
frontier, including historical descendants; `workspace.registers` remains broader
Read/inspection context and must never be merged into Write. Parents, siblings,
grandchildren, disconnected branches and same-register Horizon peers are not
Write cards. Revise remains limited to owned, revisable descendants. Variant,
Reclassify and Commit use the compact Supersession control between Gate and Write.
Reconcile is selected from an admissible counterpart card in Gate.

Expressive Propositions include an `impact` projection containing the parent
measurement as `from` and the expressive measurement as `to`. This makes the
size and direction of the structural transition visible; the destination is
not inferred from a centre point. The human rails use lighter indigo for
movement toward Prominence and darker indigo for movement toward Turbulence.

The plan also includes the viewing Participant's effective `attestation` on
each Proposition. A later eligible Write may change that direction while the
Proposition remains an immediate valid Write descendant. The replacement links to the
prior Attestation for auditability, derived measurements use only the effective
leaf, and at most one Attestation can be recorded for a Proposition in a given
Phase.

The provenance Proposition is deliberately not inserted into the new Horizon's
register population: it opens that Horizon from the preceding Closure Boundary.
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
becomes available when that register is encountered again, revision naturally
occurs in a subsequent Cycle rather than revising the current Gate in place.

## Proposition history

**Only Commit carries contribution history forward.** Supersession edges describe
structural ancestry and active-node/comparison behaviour. Neither a soft edge nor
a hard edge grants history inheritance.

Variant, Revise, Reclassify and Reconcile each create a new Expressive Proposition
with fresh Attestations, Receipts and Phase-touch history. This includes the
current non-Commit hard supersessions in Bias. Preserving a source's statement
does not carry its accumulated support or evidence onto the new Proposition.
Source contributions remain recorded against their original targets; they are
neither deleted, copied nor retargeted. Instrument executions and ledger entries
also keep their original contribution identities.

A newly written Proposition has zero inherited touches. When normal progression
opens the next Phase with that Proposition as Gate, it earns its first **new**
touch. Its creation Phase still observes the source Gate.

Commit is the historical continuation of its expressive Gate. It resolves that
Gate's effective Attestations, completed direct/Attestation Receipts and Phase
touches by reference, preserving Closure/Cadence continuity without copying rows.
The same Commit-only history rule applies in preview and execution. Inheritance
stops at every non-Commit transition: committing a revision or reconciliation
preserves only the history earned by that new Proposition, not its predecessors'
history. See [observables](observables.md#commit-participation-inheritance).

## Traverse, Tragedy and Rest

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

Traverse is available only during Distinguish, Persist and Constrain. It
explicitly chooses an existing local **Expressive Proposition** in
the current Write register. Targets are immediate valid descendants of the Gate in the
existing supersession graph, including historical superseded Propositions, and
require the viewing Participant's effective Attestation. Another Participant's
Attestation does not establish this path.
The current Gate and positions already visited as Gates in this Cycle are
excluded; later Cycles may revisit them. Traversal creates no Proposition,
Supersession or evidence and never selects globally. It advances the Phase as
selection did. Disperse, Mediate, Bias and Order do not admit Traverse: those
phases perform dispersal, reconciliation, orientation/grounding and Closure
resolution respectively.

In phases 1–3, every immediate valid Write descendant exposes two direction slots:

| Effective personal Attestation | Refute side | Support side |
| --- | --- | --- |
| None | Refute | Support |
| Support | Refute | Traverse |
| Refute | Traverse | Support |

The specialized `attestation.create.anyOf` branches bind each direction to its
permitted Proposition IDs. Same-direction re-attestation is unavailable; the
opposite direction uses the existing Attestation supersession mechanism.
The card footer, form and inference schema use these same choices. In phases
4–7 an effective Attestation does not create a Traverse affordance; the
opposite Attestation direction remains available. Traverse
creates neither a Proposition nor a Supersession and advances through the
existing personal path. Reconciliation counterparts belong to Gate, as described below.

Write card footers reserve three regions: Refute (or its Traverse replacement)
on the left, other authorized actions such as Revise and Instrument in the
middle, and Support (or its Traverse replacement) on the right. Empty regions
remain in place, and middle actions wrap without moving the directional slots.
The UI renders Rest only from the authoritative schema, never from its own
interpretation of the Tragedy flag or visible cards.

**Tragedy** means the current Phase has zero admissible non-Rest actions.
The server first applies all phase-native, ownership, target and Commitment
constraints, then specializes the complete non-Rest action schema. Only when
that action set is empty does it set `tragedy: true` and inject `participant.rest`
as the sole action. Otherwise `tragedy` is false and Rest is absent from both
the schema and UI. Human controls and autonomous inference receive the same result.

Rest is never a participant preference or a competing action. An available
Variant, Reclassify, Revise, Attestation, phase-admissible Traverse, Reconcile, Instrument or Commit
prevents Tragedy. In particular, Distinguish, Persist and Disperse cannot be
Tragic while their unconditional Variant action remains admissible. An empty
traversal set alone says nothing about complete action exhaustion.

The plan reports `traversal_options` and `instrument_options` separately from
`tragedy`. Complete Write and traversal target sets are not truncated by the
Horizon display limit. Recovery still uses bidirectional neighbours and the
same distance/ranking semantics.

Only a genuinely exhausted Phase exposes `participant.rest`:

```json
{
  "phase_id": "01...",
  "contribution": { "action": "participant.rest" }
}
```

Rest creates no expressive contribution or evidence, completes the Phase and
Cycle, records `cycles.rested_at`, and preserves the current position. The next
`/participate/enter` rechecks recovery under the normal entry locks:

1. Search outward over existing parent/child edges in breadth-first distance shells.
2. Stop at the nearest positive distance containing measured Committal Propositions
   with positive Potential Charge. Unmeasured, zero-Potential and disconnected
   candidates cannot supply recovery.
3. Rank only that shell by Potential Charge from upstream measurements, then existing Charge
   and Proposition-ID tie-breaking. A farther high-Potential region cannot win.
4. Persist the winner as the new Cycle's Committal Gate.

The retained position is excluded from a candidate's continuation search, so
recovery does not borrow its signal by returning through the exhausted observer.
Repeated Rest can progress outward as each region's continuation is exhausted.
No viable candidate leaves the participant resting in place; there is no global
or zero-Potential fallback. GET exposes `entry.selection: recovery`, availability,
prospective destination, distance and Potential Charge, but never moves anyone.
The existing `entry.mode: potential` transport is retained for entry clients.

Attenuation is a substrate-level measurement rule: independently evaluated local
Proposition measurements are averaged with inverse-square weights
`1 / (1 + structural_distance)^2` while walking from the active head to provenance.
Every visited position contributes denominator weight. Potential uses these
accumulated measurements without another observer-distance or continuation-length
discount. Another Committal still stops the outgoing continuation search.
Immutable snapshot Tension remains the historical baseline. Initial entry keeps
its existing global Potential/Charge ranking; recovery preserves its nearest
viable region search. See [observables](observables.md) for the full calculation.

Order returns an actionable `ready` plan even when Commit is inadmissible.
`plan.commitment` and `commitment_preconditions_unsatisfied` preserve diagnostics
for missing Attestation, Receipt and Closure alignment. Other admissible actions remain available when Commit is refused; only their
complete exhaustion exposes Rest. Automated wakes
ask inference to choose from exactly the same action schema; they never traverse
automatically. Stale phase tokens cannot apply another move.

## Mediate: Correspondence Read

Mediate's Read anchor is the Gate recorded on the preceding completed Disperse
Phase in the **same Cycle**. It is not Mediate's Gate, the latest Horizon card,
or a guessed ancestor. Missing Disperse history supplies no Read/counterpart
options; the server does not invent an anchor.

`read_options` exposes that anchor first, followed by active siblings that:

- share at least one immediate parent with the anchor;
- occupy the anchor's Classification Register and Context Horizon;
- belong to the same Substrate;
- have Correspondence with the same nonzero sign as the anchor.

Sibling expansion is exactly one edge down from the shared parent, never a
recursive family walk or a whole-Horizon search. Multiple shared parents are
unioned and results deduplicated. Zero or unmeasurable anchor Correspondence
provides no direction for **additional** Reads; the original Read remains.
The usual accumulated Correspondence measurement is used, including its existing
structural attenuation. No extra distance threshold or attenuation is added.

The Gate population consists of all active, immediate Expressive children of
those exposed Reads, in the current Gate's register, Horizon and Substrate.
**Gate Correspondence and Chirality do not filter these children.** The current
Gate must belong to that population. `gate_options` contains the other members
that can reconcile with it; the current Gate is already exposed as `gate`.
Hard-superseded alternatives are excluded; soft branches remain active.
No display/read-count or generic option limit truncates either operational set.

In Settlement, this means corresponding Concord parents expose their Signal
children, which can reconcile into fresh Stance even when they have different
immediate parents. Emergence uses Canon, Activity and Frontier equivalently.
The UI renders `read_options` directly, and inference receives the same plan.
The schema and dispatcher both derive counterparts through `MediationReadService`.
Schema cache version 13 retires the previous cached Gate-Correspondence contract.

## Bias Chirality Gate

Bias is directional selection. `BiasGateService` supplies the authoritative
`gate_options` used by the UI, inference/schema and execution:

1. Resolve the current Gate's forward family and the forward families of its
   immediate parents in the same Substrate and Context Horizon. Retain the
   existing lineage boundary: stop expansion at Committal nodes, without scanning
   unrelated Horizon regions. Include the Gate itself; no sibling is required.
2. Deduplicate overlapping families before computing one aggregate. Keep only
   active Expressive Stances (Frontiers in Emergence). Outgoing hard Supersession
   makes a source inactive; soft Variant branches leave it active. Candidates
   may be deeper descendants, not just immediate siblings. Other registers and
   Propositions beyond Committal/Horizon boundaries supply neither votes nor choices.
3. Calculate the receipt-weighted effective Attestation lean using the existing
   Chirality calculator. Its nonzero direction admits only Stances whose own
   effective lean has that direction. This is the raw current lean, not the
   cross-Domain Chirality product, and adds no second attenuation. Neutral fields
   expose no Bias choice. Display/read-count limits do not truncate this field.
4. The Participant chooses one admissible source:

```json
{
  "action": "proposition.bias",
  "proposition_id": "01..."
}
```

The server copies that source's statement exactly into one fresh Expressive
Concord (Canon in Emergence), and records one hard `reclassify` edge. The action
is distinct from owner-authored `proposition.reclassify`: Bias selection is
field-authorized and does not require source ownership. Reusing the existing
edge type describes the same unary classification change without introducing
a new persisted topology. No `other_proposition_id` or `statement` is accepted.
The dispatcher rechecks the same eligibility under the substrate lock before writes.

The source is no longer active. Its accumulated Attestations, Receipts and Phase
touches remain its own; the target inherits none. The new Order Phase earns one
new target touch, which is not inherited history. Removing the dominant-direction
source reduces that direction's contribution to the active Stance comparison
population. The fresh Concord must earn its own support, evidence and participation
history before Commitment. Only Commit carries history across the boundary.

Schema cache version 14 retires the prior Bias contract. The inference transport
keeps Bias Gate IDs separate from Instrument/Attestation Write IDs even though
these actions use the same `proposition_id` property. The UI labels the unary
choice **Bias**, and shows eligible sources as choices rather than privileging
an inadmissible current Gate. Existing Write Attestation and Instrument actions
remain available when their own target rules allow; neither is a prerequisite
for the Bias action. Late-phase rebuilding/Closure integration remains PR #4.

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

Bundled reclassification/evidence gathering (`reclassify_with_receipt`, called
`proposition.reclassify_with_instrument` in the previous schema) is removed.
Reclassify has no Instrument field and creates no Receipt. Phase 3 now offers
plain owner-constrained `proposition.reclassify`.

During Bias, `instrument.execute` targets existing local Expressive Propositions
in the Write register, including existing historical Concord/Canon and the Gate when
eligible. The normal Instrument lifecycle creates a Receipt attached to that
exact Proposition. It remains attached across Cycles, so grounding in Cycle N
can enable Commitment on a later revisit. Bias and Reconcile do not inherit or
copy evidence; effective Attestation, completed Receipt and the existing
Depth/Cadence Closure gate remain mandatory.

The additive migration records Rest and replaces the unique Cycle/kind index
with a nonunique index to allow multiple deliberate Order traversals. Rolling
back after repeated Order occurrences requires resolving those occurrences
first; the migration does not discard participation history.

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
