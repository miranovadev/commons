# Participation API

The [Core Invariants](core-invariants.md) govern this shared contract for human
and automated Participants, including participant prompts and UI behaviour.
The operational rules below apply those normative semantic constraints.

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
Rest recovery ranks ancestral Committals by Potential Charge observed from the
retained position, including substrate attenuation. Expressive positions inform distance but are never entry candidates.
Successful entry persists the selected Committal Gate; contributions still
persist their resulting Gate within the Cycle. Rest recovery follows the causal
ancestry rules below.

Potential Charge is `max(0, max(|T| - |T_c|))`, where `T` is the Committal
state's immutable Tension and `T_c` is the projected Tension of each measurable
Expressive continuation reachable before the next Committal state. If no
continuation reduces Tension, selection falls back to the highest current
Charge, `1 - |T|`. Ordinary re-entry attenuates both Potential and Charge by
`1 / (1 + d)^2`, where `d` is the shortest existing parent/child distance from
the retained position. Effective-score ties prefer shorter distance and then
stable Proposition ID; initial entry retains its Charge/ID tie-breaks.
Disconnected candidates cannot supply ordinary entry, including fallback.
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
population. Order shows the nearest Second-register ancestors along the current
Gate paths; other phases retain their single Read card. Mediate Gate privileges
the current Proposition and adds admissible reconciliation counterparts. Bias
Gate shows only the field-admissible unary choices, including the current
Proposition when eligible;
Lineage is available on demand; and Write separates existing actionable
Propositions from moves that create a new Proposition from the Gate. In phases 1–5,
an existing Write card is a valid Expressive descendant of the current Gate in the
phase's Write register, including same-register revisions. Bias also discovers
existing Writes across its Commit Region. Order separates admissible expressive Commit candidates in Gate from Committal Traverse results in Write. All Instruments are offered before Order. `write_options` is authoritative; `workspace.registers` remains
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
becomes available when that register is encountered again, revision naturally
occurs in a subsequent Cycle rather than revising the current Gate in place.

## Proposition history

Reclassify in Persist, Constrain and Bias preserves the source statement exactly. It accepts no authored content. The new classification references completed proposition-level Receipts without copying or retargeting observations; Attestations and Phase touches start fresh. Attestation-level evidence does not follow a reset judgment. Previously loaded evidence can count on the active classification, with the superseded source retaining its historical count without another active reinforcement application. Legacy content-changing edges are not backfilled.

Variant, Revise and Reconcile create new content/structure with fresh evidence, votes and touches. Commit alone retains the selected expressive source's participation history by reference. A committed source is consumed globally; the result's recorded evaluation remains frozen.

## Write once, then support

| Operation | Generation identity and prerequisites |
| --- | --- |
| Distinguish Variant | Participant + Gate + phase + Write register |
| Persist Variant | Foreign Gate, once per Participant/Gate across Cycles |
| Persist/Constrain Reclassify | Receipt and effective Attestation; any Participant; unique source/destination register globally |
| Disperse Variant | Once per Participant/Gate/Write register; owned Writes can later be revised |
| Mediate Reconcile | Directed pair of dominant Gate and counterpart, unique globally; inverse remains distinct |
| Bias Reclassify | Receipt, Attestation and lineage agreement; shared source/destination result plus existing personal region-row limit |
| Order Commit | Receipt, fresh effective Attestation and aligned Depth/Cadence; unique direct source globally |

Persist Variant and Reclassify retain their shared personal generation opportunity. Revising a result does not reset quotas. Another Participant may reuse a classification by attesting/traversing its existing Write. A successful Commit consumes only its direct source for all contribution actions; alternative branches and historical ancestry remain visible in their proper roles.

Instruments and Replay target mutable expressive Writes in phases 1–6. Variant children alone do not disqualify a target. A completed Receipt is required before Attestation; reading is optional. Receipt and Attestation are both required before reclassification. A newly instrumented but unattested Gate may require Rest until another participant attests it or another action becomes available. Database uniqueness and transaction-locked execution recheck the same rules advertised in the schema.

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

Expressive Traverse is available during Distinguish, Persist, Constrain and Bias after receipt existence and personal effective Attestation. It
explicitly chooses an existing local **Expressive Proposition** in
the current Write register. Targets are immediate valid descendants of the Gate in the
existing supersession graph, including historical superseded Propositions, and
require the viewing Participant's effective Attestation. Another Participant's
Attestation does not establish this path.
The current Gate and positions already visited as Gates in this Cycle are
excluded; later Cycles may revisit them. Traversal creates no Proposition,
Supersession or evidence and never selects globally. It advances the Phase as
selection did. Disperse and Mediate do not admit expressive Traverse. Order instead traverses an established Committal Write and ends the Cycle there.

In phases 1–3 and Bias, a receipted Write exposes the following direction slots:

| Effective personal Attestation | Refute side | Support side |
| --- | --- | --- |
| None | Refute | Support |
| Support | Refute | Traverse |
| Refute | Traverse | Support |

The specialized `attestation.create.anyOf` branches bind each direction to its
permitted Proposition IDs. Same-direction re-attestation is unavailable; the
opposite direction uses the existing Attestation supersession mechanism.
The card footer, form and inference schema use these same choices. In Disperse and Mediate an effective Attestation does not create a Traverse affordance; the opposite direction remains available. Order uses one Committal Traverse action without Attestation controls. Traverse
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
Tragic while their first Variant opportunity remains admissible. After generation,
existing Write support determines whether the region is Tragic. An empty
traversal set alone says nothing about complete action exhaustion.

The plan reports `traversal_options` and `instrument_options` separately from
`tragedy`. Complete Write and traversal target sets are not truncated by the
Horizon display limit. Recovery follows incoming causal ancestry and compares all
positive candidates after distance attenuation, as described below.

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

1. Follow incoming Supersession ancestry across all parent paths and Domain boundaries. Deduplicate ancestors at minimum edge distance.
2. Consider every ancestral Committal with a snapshot and positive remaining raw Potential. Exclude the exhausted position itself; descendants, siblings and disconnected regions are not candidates.
3. Rank by raw Potential times `1 / (1 + d)^2`, then attenuated Charge, shorter distance and stable Proposition ID. A farther high-Potential ancestor may win.
4. Resolve only the winning Committal's own Horizon or the direct child it explicitly opened. Create the new Cycle and Phase 1 in that Horizon's Domain, even when it has a historical closure marker. Never search arbitrary open descendants.

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
Every visited position contributes denominator weight. Raw Potential uses these
accumulated measurements without re-discounting continuation length. Another
Committal still stops the outgoing continuation search. Immutable snapshot
Tension remains the historical baseline. Ordinary re-entry additionally observes
candidate Potential and Charge from the retained Participant position using the
same inverse-square geometry. Initial entry has no positional attenuation;
recovery compares all positive causal ancestors with that same destination attenuation. See
[observables](observables.md) for the full calculation.

Order returns an actionable `ready` plan even when Commit is inadmissible.
`plan.commitment` and `commitment_preconditions_unsatisfied` preserve diagnostics
for missing Attestation, Receipt and Closure alignment. An existing Committal Write can still offer Traverse when Commit is refused.
If neither terminal action exists, the schema offers exclusive Rest. Automated wakes
ask inference to choose from exactly the same action schema; they never traverse
automatically. Stale phase tokens cannot apply another move.

## Mediate: Correspondence Read

Mediate's Read anchor is the Gate recorded on the preceding completed Disperse
Phase in the **same Cycle**. It is not Mediate's Gate, the latest Horizon card,
or a guessed ancestor. Missing Disperse history supplies no Read/counterpart
options; the server does not invent an anchor.

`read_options` exposes that anchor first, followed by Read-equivalent
members of its Commit Region with the same retained hysteretic Correspondence direction.
Parallel branches may have different immediate parents or expressive depths.
Zero or unmeasured Correspondence retains established direction, with primary
lineage fallback when none has been established and a positive root default.
This applies equally to the Read anchor and other candidate Reads. Existing Correspondence calculation
and attenuation are unchanged.

Gate candidates remain active immediate Expressive children of the exposed
Reads, at the First register position and within the same Commit Region.
Gate Correspondence and Chirality do not filter these children. The current
Gate must belong to that population; `gate_options` contains its counterparts.
Reconcile still copies the current Gate's statement and creates two hard edges.

## Shared Commit Region scope

This scope applies [Invariant 10: structural authority](core-invariants.md#10-operations-may-only-act-within-their-structural-authority):
measurements select within eligible populations and must not redefine them.

`CommitRegionService` resolves the nearest Committal ancestry, stopping the
backward walk at each Committal node. That seed starts a region of expressive
descendants across all forward branches; another Committal node terminates that
branch and seeds a new region. Superseded intermediates remain structural paths.
No migration, chronology cutoff, or Domain transition is required. A rootless
position or conflicting committal ancestry supplies no inferred region. Legacy
merges with conflicting seeds and their descendants are excluded from both
regions, rather than connecting otherwise isolated pools.

An immediate parent is one graph edge and is too narrow. A Context Horizon is
measurement/comparison context and can contain multiple Commit Regions.
Horizon membership does not define candidate membership, even if a branch's
ledger entries have different Horizon labels. Substrate isolation remains.

The reusable `RegisterPosition` mapping is extracted unchanged from structural
Correspondence: Activity/Signal = First (R), Frontier/Stance = Second (G), and
Canon/Concord = Third (B). Late phases share this structural service:

| Phase | Candidate position | Selection |
| --- | --- | --- |
| Mediate | Third / Read | Matches the Read anchor's retained Correspondence direction |
| Bias | Second / Gate | Retained direction agrees with causal ancestry excluding itself; receipt and attestation exist |
| Order | Third / Gate | Grounded, attested sources with aligned Depth/Cadence |

Active candidates exclude outgoing hard supersessions; soft Variant branches
remain active. A direct Commit edge consumes its source globally; other branches remain available.

## Bias Chirality Gate

`BiasGateService` compares each active head's retained direction to its own causal ancestry, excluding the head's votes and loads. Shared ancestors and Attestations are counted once across both reconciliation parents. Traversal stops at the governing Committal boundary; unrelated branches do not set the comparison. Ledger replay retains the preceding nonzero direction at zero, starting from the governing Committal direction. Superseded ancestors supply historical judgments without becoming additional active reinforcement copies.

Bias additionally requires a completed Receipt and an effective source Attestation. The action takes `action: proposition.bias` and `proposition_id`. It preserves exact content and proposition evidence, resets votes/touches, and creates one shared source/register classification. Existing Writes offer Instrument, Attest and—after personal Attestation—Traverse to Order.

## Order: Commit Gate and Committal Write

Read contains nearest Stance/Frontier ancestry. Gate contains uncommitted Concord/Canon sources satisfying receipt, effective Attestation and prospective Depth/Cadence checks. Write contains established Committal Concord/Canon results. Commit selects an authorized Gate `proposition_id`; Traverse selects a Committal Write `proposition_id`. Consumed sources are not duplicate contribution cards.

Commit copies the selected source's exact statement and participation history by reference, creates an immutable snapshot, and consumes the direct source globally. Later evidence reads cannot rewrite that snapshot. Reinforcement is optional weight; Depth/Cadence remain the admissibility gate. `plan.commitment` describes the current position and `commitment_options` describes each eligible Gate source. The same rules govern schema, UI and execution.

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

Reclassification is separate from Instrument execution. All phases before Order expose Instruments on eligible expressive Writes, including Distinguish and Mediate. Receipt existence permits Attestation; both Receipt and effective Attestation permit reclassification. Reclassify preserves proposition evidence but resets judgments. Variant, Revise and Reconcile need new evidence for their new Writes.

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

The waiting state has `plan: null`, entry availability and the completed Cycle identity. The workspace can retain completed cards as read-only context. Return to Participants opens the Substrate collection; Enter another Cycle uses the normal entry endpoint. Rest is the separate exhausted-region path and can trigger ancestral Committal recovery by attenuated Potential.

### Receipt Reads

`receipt.read` is available in phases 1–6 when qualifying encountered evidence can add to context. It does not advance the Phase or Gate. Loaded state persists across Cycles and Horizons. Existing Cite references in context cannot be newly loaded; equivalent receipts do not multiply reinforcement. Order has no Read or Replay action, while historical observations remain inspectable.

## Receipt capabilities and discovery

[Web Search and Receipt Replay](instruments.md) extend the existing Instrument
contract. `receipt.read` loads existing evidence without advancing the Phase.
`receipt.replay` uses the stored invocation, original eligible Instrument Write and
normal Instrument progression to create a new immutable observation. Neither
initial execution nor replay automatically loads a Receipt.
