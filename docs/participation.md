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

## Local navigation

The current Gate is the active Cycle's Read position. Existing Write targets
must be at that position or one supersession edge away; the existing register,
ownership and active-head rules still apply. Reclassified originals remain
readable and eligible for local Attestation under the existing register rules.

A phase whose specialized action schema is empty reports `status: waiting`,
`reason.code: no_admissible_write`, and a `navigation` object containing
`available`, `phase_id`, `next_proposition_id`, and the observed `potential`.
This includes Order when the existing commitment evidence check fails.
Creation actions such as Variant still count as admissible even when there
are no existing Write cards. State reads never move a Participant.

POST a navigation request to the existing participation endpoint:

```json
{
  "phase_id": "01...",
  "contribution": { "action": "participant.traverse" }
}
```

Traversal is handled separately from expressive schema validation. Under the
same Participant/Phase locks, the server rechecks exhaustion and selects one
immediate neighbour; callers cannot specify a destination. It persists the
new position and completes the exhausted Phase and Cycle. The response offers
`entry.mode: position`; the next `/enter` starts a normal Cycle there. Stale
phase tokens cannot move twice. Automated wakes perform this step without an
LLM call and resume from the position on a later wake.

Parents and children in the existing supersession graph are both traversable,
regardless of supersession strength. Each immediate direction is ranked by the
greatest positive Potential observable through it, without returning through
the current position. Breadth-first traversal gives structural distance and
terminates even on cycles. Disconnected propositions cannot contribute a
signal. Only the winning immediate neighbour can become the new position.

There was no implemented attenuation formula in the backend. This first
version applies `1 / (1 + structural_distance)` to each measurable Tension
reduction. Distance includes the route to the source and the outgoing route to
its Expressive continuation; another Committal stops that continuation search.
Committal sources use immutable snapshot Tension; Expressive sources use their
persisted measurements. Initial entry retains its existing Potential/Charge
ranking because it has no observation position yet. Navigation does not use a
zero-Potential Charge fallback: no positive local signal leaves the Participant
waiting in place, able to be reconsidered on a subsequent request or wake.
Equal positive scores use Proposition ID order. No previous-direction mode or
route history is introduced.

Perspective exposes the active attenuation formula, with value 1 and distance
0 at the current position. Measurements and the readable workspace remain
substrate projections; attenuation changes navigation's observation of them.
The implementation loads the substrate graph and evaluates connected routes
in memory, as the existing Potential service does. It introduces no persisted
navigation graph or pathfinding cache. This favours a small first version;
large-substrate performance and possible oscillation as measurements change
remain things to observe.

