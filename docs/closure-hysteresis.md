# Closure hysteresis and Domain transitions

Closure, performed during Order — Write, evaluates each causally local expressive
Write independently from that new Committal's committed geometry/Cadence, lineage/history and
participation. It is not a region-wide cross-lineage comparison or candidate
enumeration. All local expressive Writes remain visible and retain valid
expressive actions wherever the Phase rules permit them. Only Writes with
all Commitment prerequisites expose Commit. A Write's own direct Committal child
may expose Traverse; unrelated regional Writes and Commits cannot. No edge means
no movement.

Order evaluates a hypothetical Commit using the same creating-Cycle governance
and committed-lineage geometry as actual Commitment. Commit inherits effective
Attestations and completed Receipts by reference for grounding. Preview writes no
propositions, edges or snapshots; dispatch reevaluates under the substrate lock.

Closure v2 is frozen at each Proposition birth. Its outer components are
Committed Cardinal Position and Committal Cadence, equally weighted. Committed
Cardinal Position equally combines full-ancestry Committal Depth and Lateral
Committal Position against the frozen same-Domain historical centroid. Cadence
compares per-Domain population CVs over full branch-local interval histories.
See [the canonical Closure definition](observables.md#closure-v2-committed-historical-crystallization).

Each component has independent hysteresis. Nonzero uses its own sign; zero retains
the direct committed predecessor's direction; absent history uses Negative.
Committed Cardinal Position and Cadence directions must agree for Commit.
Depth and Lateral expose their own retained directions as child diagnostics.
Origin components are -1 and its interval is 1. Missing comparative history produces
0 under the documented bootstrap rules, never a new direction default.

An expressive birth observes a temporary next committed slot without reserving it.
Its stored Closure never changes. Preview evaluates the prospective **new Committal**
at today's interval and slot. The actual Committal freezes its own birth measurement,
which can differ from its expressive source's older observation. Preview adds no
Proposition, causal edge or reserved slot. Grounding and movement are unchanged.
Missing durable birth state fails closed; current population is never a historical
reconstruction. Receipt loads cannot change frozen Closure.

`plan.commitment.closure` exposes candidate values, directions, and history.
`requirements.closure` is an actual boolean and `closure_enforced` is true.
Grounding remains mandatory. Divergence keeps Commit inadmissible while retaining other admissible Phase actions (Rest only on complete action exhaustion),
even when receipts and attestations are present. Adding evidence is not a bypass
for incompatible Closure structure. This scope correction does not add Phase
actions: Order currently permits Commit and direct Committal Traverse. The
expressive Write population is not filtered by either action's availability.

After a successful Commit, existing projection and Tension rules decide whether
the Domain changes. Alignment permits Commitment; it does not itself imply a
Closure Boundary. Zero Tension retains the active Domain. Participation responses
include the participant's `last_commitment` with before/after Domain and Tension,
component values and directions, and a `closure_boundary` flag, plus current
position, Domain, and horizon sequence. The latest participant Commit remains
visible after the cycle ends and is labeled as historical, not current global
Tension.

## Human Attestations

`attestation_options` explicitly lists eligible local expressive targets in the
write register during Persist/Constrain, including superseded propositions.
The display window and active-head selection list no longer determine the
Attestation contract. Human cards and form selectors both use the server schema's
target enum. This restores Support/Refute for eligible targets absent from the
active write heads, without exposing disallowed targets or adding Attestations
to other phases. Both support and refute remain submissions through the existing
validated participation endpoint.

## Deployment and review

Run `php artisan migrate --force` and rebuild the frontend on deployment. The
schema cache version is bumped so pre-hysteresis action schemas are not reused.

For a manual review:

1. In Persist or Constrain, inspect an eligible local expressive proposition in
   the Write register. Support and Refute should open the Attestation form with
   that target and direction selected, including when it is no longer an active
   selection head. Submit through the normal contribution flow.
2. At Order, inspect the prospective component values and resolved directions.
   A grounded but divergent candidate must remain uncommittable while other admissible Phase actions remain available; Rest appears only on complete action exhaustion.
3. Commit an aligned, grounded candidate. Check the latest Commitment panel for
   Domain/Tension before and after, then refresh after the cycle ends: the result
   should remain visible. A non-boundary Commit must leave the horizon unchanged.

`ClosureHysteresisTest` constructs deterministic aligned, divergent, zero-value,
and multi-parent histories. `npm run test:ui` covers the shared schema-target
contract and rendered human Support/Refute controls.

## Real participation coverage

`OperationalClosurePathTest` complements the component fixtures with a complete
[collaborative lifecycle](operational-closure-path.md). It exercises refusal for
missing final-Concord evidence, refusal for divergent Depth/Cadence after
grounding, and successful Commit after real participation changes committed
Cadence. Preview and the persisted snapshot agree, and only Commit carries
the final earned history. No Closure calculation, threshold or hysteresis rule
is changed to make this path succeed.
