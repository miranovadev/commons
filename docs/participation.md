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
Read, Gate and Write rows. Read exposes one Proposition; Gate privileges the
current Proposition and adds reconciliation siblings during Mediate and Bias;
Lineage is available on demand; and Write separates existing actionable
Propositions from moves that create a new Proposition from the Gate. An
existing Write card is an immediate valid Expressive descendant of the current
Gate in the phase's Write register. `write_options` is the authoritative forward
frontier, including historical descendants; `workspace.registers` remains broader
Read/inspection context and must never be merged into Write. Parents, siblings,
grandchildren, disconnected branches and same-register Horizon peers are not
Write cards. Revise remains limited to owned, revisable descendants. Variant,
Reclassify and Commit use the compact Supersession control between Gate and Write.
Reconcile is selected from a sibling card in Gate.

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
3. Rank only that shell by attenuated Potential Charge, then existing Charge
   and Proposition-ID tie-breaking. A farther high-Potential region cannot win.
4. Persist the winner as the new Cycle's Committal Gate.

The retained position is excluded from a candidate's continuation search, so
recovery does not borrow its signal by returning through the exhausted observer.
Repeated Rest can progress outward as each region's continuation is exhausted.
No viable candidate leaves the participant resting in place; there is no global
or zero-Potential fallback. GET exposes `entry.selection: recovery`, availability,
prospective destination, distance and Potential Charge, but never moves anyone.
The existing `entry.mode: potential` transport is retained for entry clients.

Attenuation preserves main's existing `1 / (1 + structural_distance)` formula.
The distance includes the route to the Committal source and its outgoing
Expressive continuation; another Committal stops that continuation search.
Immutable snapshot Tension and persisted expressive measurements remain the
inputs. Initial entry keeps its existing global Potential/Charge ranking because
it has no observation position. Recovery is separate from initial entry.
The public conceptual documentation's inverse-square influence model is not
introduced by this navigation change.

Order returns an actionable `ready` plan even when Commit is inadmissible.
`plan.commitment` and `commitment_preconditions_unsatisfied` preserve diagnostics
for missing Attestation, Receipt and Closure alignment. Other admissible actions remain available when Commit is refused; only their
complete exhaustion exposes Rest. Automated wakes
ask inference to choose from exactly the same action schema; they never traverse
automatically. Stale phase tokens cannot apply another move.

## Mediate and Bias reconciliation

`gate_options` contains admissible sibling alternatives alongside the current
Gate. Candidates are Expressive Propositions in the Gate's register and Horizon,
sharing an immediate parent with it. The Gate, its parents, direct Write children,
unrelated heads and hard-superseded candidates are excluded. Soft variants retain
active status, following the existing Chirality semantics. A parent in the previous
Horizon may provide shared provenance, but counterpart populations stay within the
Gate's Horizon. Unmeasured structural directions are not assumed.

- **Mediate:** the current Gate's persisted Correspondence measures its lineage
  against its complementary provenance lineage. Its nonzero sign determines the
  relevant consonant/dissonant direction; a candidate's Correspondence must match.
  Missing measurable projections are repaired through the normal observable service.
- **Bias:** for each shared parent, gather its descendants in the Gate's Horizon,
  stopping expansion at Committal boundaries. The shared parent is provenance, not
  an alternative vote. Remove hard-superseded nodes and use the existing
  receipt-weighted effective Attestation lean across the remaining Expressive heads.
  A sibling's own effective Attestation lean must share that aggregate sign.
  This is the raw current lean, not Chirality's cross-domain product. Multiple
  parent contexts are evaluated independently and their valid candidates deduplicated.

Zero Correspondence, a tied aggregate lean or a neutral candidate lean supplies
no dominant direction. The normal option limit is applied after these filters.
The schema, human Gate card footers and inference share the resulting candidate IDs.
No reconciliation cards or buttons are inserted into Write.

```json
{
  "action": "proposition.reconcile",
  "other_proposition_id": "01..."
}
```

Reconcile accepts no `statement`. It creates a new Expressive Proposition in the
Phase's Write register with the current Gate's statement copied **exactly**,
including whitespace, and hard `reconcile` Supersession edges from both the Gate
and the selected sibling. The sibling contributes structure, not synthesis text.
The usual Proposition/edge ledger entries and measurement recalculation follow.
The dispatcher rechecks candidate admissibility before writing. Existing immutable
history and Attestation/Receipt ownership are preserved.

## Bias grounding

Bundled reclassification/evidence gathering (`reclassify_with_receipt`, called
`proposition.reclassify_with_instrument` in the previous schema) is removed.
Reclassify has no Instrument field and creates no Receipt. Phase 3 now offers
plain owner-constrained `proposition.reclassify`.

During Bias, `instrument.execute` targets existing local Expressive Propositions
in the Write register, including reconciled historical nodes and the Gate when
eligible. The normal Instrument lifecycle creates a Receipt attached to that
exact Proposition. It remains attached across Cycles, so grounding in Cycle N
can enable Commitment on a later revisit. Reconciliation does not inherit or
copy evidence; effective Attestation, completed Receipt and the existing
Depth/Cadence Closure gate remain mandatory.

The additive migration records Rest and replaces the unique Cycle/kind index
with a nonunique index to allow multiple deliberate Order traversals. Rolling
back after repeated Order occurrences requires resolving those occurrences
first; the migration does not discard participation history.
