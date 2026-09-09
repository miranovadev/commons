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
Read, Gate and Write rows. Read and Gate each expose one active Proposition;
Lineage is available on demand; and Write separates existing actionable
Propositions from moves that create a new Proposition from the Gate. An
existing Write card exposes Support and Refute when Attestation is admissible,
and Revise only when that Proposition is owned and revisable. Moves which
create a new Proposition are exposed as a compact Supersession control between
Gate and Write rather than being presented as existing Proposition cards.

Expressive Propositions include an `impact` projection containing the parent
measurement as `from` and the expressive measurement as `to`. This makes the
size and direction of the structural transition visible; the destination is
not inferred from a centre point. The human rails use lighter indigo for
movement toward Prominence and darker indigo for movement toward Turbulence.

The plan also includes the viewing Participant's effective `attestation` on
each Proposition. A later eligible Write may change that direction while the
Proposition remains an active expressive head. The replacement links to the
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

Traverse explicitly chooses an existing local **Expressive Proposition** in
the current Write register. Targets are immediate parents or children in the
existing supersession graph, including historical superseded Propositions.
The current Gate and positions already visited as Gates in this Cycle are
excluded; later Cycles may revisit them. Traversal creates no Proposition,
Supersession or evidence, never selects globally, and may coexist with
creation, Attestation, Instruments and Commitment. It advances the Phase as
selection did. At Order it opens another Order occurrence, preserving the
Cycle until Commit or Rest. Each occurrence has a fresh phase concurrency token.

**Tragedy** means no valid Expressive traversal remains, independently of
whether a new Proposition could be created or Commit could succeed. The plan
reports `tragedy`, `traversal_options`, and `instrument_options`; its specialized
schema is authoritative for both human controls and inference. These complete
local target sets are not truncated by the display limit for active Write heads.

Only a Tragic Region exposes `participant.rest`:

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
for missing Attestation, Receipt and Closure alignment. Traverse or Rest prevents
Commit refusal from becoming an artificial permanent deadlock. Automated wakes
ask inference to choose from exactly the same action schema; they never traverse
automatically. Stale phase tokens cannot apply another move.

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
