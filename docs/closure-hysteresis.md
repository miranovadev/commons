# Closure hysteresis and Domain transitions

Order evaluates the proposed Commit node and edge with the same post-Commit
active-head state used by actual Commitment. The head inherits its Gate's Phase
touch history, effective Attestations and completed Receipts through the Commit
edge. Cadence therefore does not reset to an untouched head or use a different
pre-Commit population from Depth. Reads do not create
temporary propositions, ledger entries, memberships, or snapshots. Dispatch
reevaluates under the substrate lock; snapshot creation checks the actual
candidate too. Failure rolls back the whole Commit write.

Depth observes active heads' structural extension and reinforcement. Cadence
observes their persisted Phase-touch distribution, not cycle or inter-Commit
timing. Hard-superseded history supplies ancestry but is not an independent
active-head observation. Both local components pass through the existing
inverse-square lineage weighted mean once; Closure adds no attenuation.

Settlement can develop positive prospective components with only the Origin
Commitment in history: matching development and touch variability are observable
before the proposed Commit. Changed development and concentrated touch
distributions can develop negative components in Emergence. The original
cross-lineage comparison, independent directions and hysteresis remain in force.

Depth and Cadence are retained separately (seven-decimal precision). Each
positive/negative value selects that direction; exactly zero retains the prior
committed direction. Both resolved directions must match. Their average remains
Closure, but an average alone cannot establish admissibility.

Direction history is the latest evaluated **ancestor** snapshot by substrate
ledger sequence, not the latest Commit on an unrelated branch. At a
reconciliation, all parents contribute ancestry; the latest evaluated ancestor
wins deterministically. Expressive reads and writes do not advance this
committed hysteretic state. With no evaluated ancestor, both directions default
negative, including bootstrap.

The additive migration stores Depth, Cadence, both resolved directions, the
source of direction history, and pre-Commit Tension on new immutable snapshots.
Old snapshots retain null components: neither their historical values nor their
Domain transitions are guessed or rewritten. The first evaluation whose ancestry
contains only old snapshots explicitly reports `negative-default`. Subsequent
evaluations inherit the newly recorded component directions.

`plan.commitment.closure` exposes candidate values, directions, and history.
`requirements.closure` is an actual boolean and `closure_enforced` is true.
Grounding remains mandatory. Divergence keeps Commit inadmissible while retaining other admissible Phase actions (Rest only on complete action exhaustion),
even when receipts and attestations are present. Adding evidence is not a bypass
for incompatible Closure structure. No new Order actions or phase skipping are
introduced.

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
grounding, and successful Commit after real participation changes the touch
distribution. Preview and the persisted snapshot agree, and only Commit carries
the final earned history. No Closure calculation, threshold or hysteresis rule
is changed to make this path succeed.
