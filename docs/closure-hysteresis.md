# Closure hysteresis and Domain transitions

Order now evaluates the proposed Commit node and edge, including its participant
cycle for Cadence, in an in-memory lineage comparison. Reads do not create
temporary propositions, ledger entries, memberships, or snapshots. Dispatch
reevaluates under the substrate lock; snapshot creation checks the actual
candidate too. Failure rolls back the whole Commit write.

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
Grounding remains mandatory. Divergence leaves Order blocked (not traversable),
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
