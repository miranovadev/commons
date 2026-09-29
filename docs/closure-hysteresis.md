# Closure hysteresis and Domain transitions

Closure, performed during Order — Write, evaluates each causally local expressive
Write independently from that Write's Depth/Cadence, lineage/history and
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

Depth compares consecutive committed Domain-run lengths. Cadence compares the
latest two direct ancestral inter-Commit intervals, including completed basin
participation. Neither uses Receipt count or Phase-touch weighting. Origin has
Depth/Cadence -1; the first ordinary Commit has both 0 with negative hysteresis.
Later candidates use measured history. See [Observables](observables.md).

Depth and Cadence are retained separately (seven-decimal precision). Each
positive/negative value selects that direction; zero retains the prior committed
direction. Both directions must match. Closure is `(Depth + Cadence) / 2`.
Receipt loads cannot change the components, Closure, directions or admissibility.
Persisted snapshots remain immutable, including historically reinforced Closure.
See [Reinforcement](reinforcement.md).

This applies [Invariant 4: Zero Does Not Erase Direction](core-invariants.md#4-zero-does-not-erase-direction)
and [Invariant 7: Reference Frames Must Remain Distinct](core-invariants.md#7-reference-frames-must-remain-distinct):
each component retains its own direction under the documented history rules.

Direction history follows the direct creating-Cycle entry Committal chain.
Unrelated branches and Supersession-parent unions do not supply committed Closure
history. Expressive work inherits its entry snapshot's structural components.
Unavailable historical intervals block prospective Commitment explicitly rather
than guessing a value. Frozen historical snapshots are never rewritten.

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
