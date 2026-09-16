# Rest recovery basins

Rest used to rank every ancestral Committal together. It could repeatedly select
Origin on the strength of historical expressive measurements, while a viable
downstream Commit on a neighbouring branch was not even a candidate.

## Scope from topology

The completed Cycle's `context_horizon_id` supplies the starting basin. Without a
Cycle context, an expressive position uses its creating Horizon; a Committal
uses its actual entry Horizon. A boundary belongs to its creating Horizon but
opens the direct child into which it provides entry.

`ProjectedRecoveryService` follows the explicit Horizon parent tree, reading
maintained descendant frontier summaries before local entries. A viable descendant shadows its ancestors; sibling branches
remain peers. If a branch's descendants are exhausted, that branch's own basin
can supply candidates. This produces a frontier, not a global active node.

The Participant's current subtree has priority over its parent's other branches.
Only if that whole subtree is exhausted does recovery climb to the immediate
parent and consider its unvisited descendants before the parent's own candidates.
Every basin is inspected at most once per observation. Missing or cyclic parent
provenance is an error. No Horizon or participation history is rewritten.

For example, Settlement H2 may have Emergence children H3a and H3b. A Participant
resting in H2 can recover into either viable child. One already in viable H3a
stays on that branch, regardless of H3b's age or score. If H3a has a viable H4a,
H4a supersedes H3a in the frontier. H4a and H3b compete by the field when recovery
starts from H2: depth itself grants no ranking bonus.

## Candidates and field

A basin's entry candidates are its opening boundary Committal plus non-boundary
Committals created in that Horizon. A boundary that opens a child belongs to the
child's entry set, never to both sets. Disconnected propositions and the exhausted
position cannot be recovery destinations.

Candidates must have positive raw Potential. Potential still measures the
greatest available reduction in absolute Tension among measured expressive
continuations before the next Committal. Observation is scoped to the basin;
cross-Domain expressive edges cannot lend it historical Potential. The exhausted
position and continuations reachable only through it remain excluded.

Ranking retains the existing `1 / (1 + distance)^2` observer attenuation over
minimum parent/child edge distance. Ties use effective Charge, shorter distance,
and stable proposition ID. This geometry makes connected local/sibling branches
comparable after topology has established eligibility. There is no recency,
sequence, depth, or arbitrary positive-Potential threshold.

Origin remains a candidate for its direct Settlement child during provenance
retreat. Complete branch exhaustion is now explicitly retired by Tragedy, after
which structural Origin renewal is available without fabricating a Potential score.
See [Branch Tragedy](branch-tragedy.md) for the active/history boundary.

## Continuity and limits

An active Cycle is never moved by someone else's Commit. Its saved Horizon and
Domain remain authoritative until completion. Successful Commit continuity still
starts immediately at the selected Committal, even at zero Potential. Initial
unpositioned entry and local Phase traversal retain their existing rules.

Recovery observes shared state on each attempt. Branch-wide retirement is explicit;
local route exhaustion remains participant-relative. Scope removes historical competition
while downstream Potential exists, but is not a guarantee that every local route
is productive: measured historical intermediates can still contribute Potential
inside a basin. If a live run still repeats sterile routes within the same viable
basin, that requires separate evidence and a participant-relative refinement.

## Inspecting the next run

`php artisan commons:recovery-inspect <participant-id>` performs a hypothetical
Rest selection without moving the Participant or writing measurements. It reports
the starting Horizon, inspected basins and eligible counts, frontier candidate
IDs, selection and scores, and the number of upward provenance hops. It emits no
routine production logging and adds no persisted state.

Look for fewer Origin recoveries while downstream basins have positive Potential,
more shared participation in later Domains, and Potential-correlated distribution
within the selected frontier. Historical retreat remains expected when downstream
Potential is exhausted. The diagnostics explain why a retreat happened; a newer
Commit's existence alone does not make it viable.

## Complete branch exhaustion

See [Branch Tragedy and Origin renewal](branch-tragedy.md). Local/Domain exhaustion
still retreats through provenance. Only complete Origin-branch exhaustion permits
explicit pruning. Retired branches leave the active field but remain inspectable;
Origin then supports a fresh Distinction after viable recovery options are exhausted.
Branch retirement does not move other participants: their own Rest enables recovery.

## Maintained terrain

[Incremental Potential](incremental-potential.md) defines the projection lifecycle,
version certificate, dependency boundary, and explicit backfill. Reads never derive
measurements. The old full resolver remains an explicit diagnostic/reference oracle.
`commons:potential-inspect <participant-id> --verify` compares projected and full
reference selection, distance, and attenuated Potential.
