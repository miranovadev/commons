# Branch Tragedy and Origin renewal

A Tragic Region exposes only Rest when no admissible non-Rest action remains.
This local condition alone does not retire anything. Recovery still searches the
current Domain's viable descendant frontier, then retreats through its causal
Horizon provenance. Complete exhaustion of a **mature Origin branch** is a distinct,
persisted Tragedy: the branch leaves active participation and becomes history.

## Branch identity

Origin is the immutable `origin-bootstrap-v1` commitment. Each direct Supersession
from Origin to an **expressive** Proposition opens an `origin_branches` row identified
by that target. A direct Committal target is not a valid expressive branch root.
Existing invalid roots are never silently rewritten or retired by the new proof.
`origin_branch_memberships` projects that root's descendants across all edge types
and Commit/Horizon boundaries. Multiple branches can share one Horizon; retiring
that Horizon would incorrectly remove its viable siblings. Reconciled descendants
can belong to several roots and remain active while any of those roots is active.
The migration backfills membership with a deduplicating recursive traversal;
normal ledger writes extend membership from the source to the new result.

## Commit maturity and terminal exhaustion

**Tragedy maturity invariant:** An Origin-rooted lineage cannot be retired before
it has persisted through at least one Commit. Branch-level Tragedy occurs only
when recovery exhausts all later committed regions back to the lineage's earliest
Commit and that Commit has no remaining Potential. The expressive proposition
directly linking that lineage to Origin is then pruned from active participation
while all historical data remains.

Zero Potential on a fresh expressive root is not Tragedy. Local zero Potential
means recover/explore, not retire. No Commit, no Tragedy. Origin is generative and
is never itself pruned; its own Potential is not the terminal exhaustion test.

Maturity follows causal Supersession ancestry from the terminal position within
each recorded branch membership. A valid checkpoint is a Committal Proposition
with a non-bootstrap snapshot referencing its Commit edge from an expressive
source. Origin's snapshot and a bare Committal label do not count. A Commit on a
sibling path also cannot mature a still-uncommitted terminal path. The earliest
checkpoint is the first Commit on the root-to-terminal causal path, never the
oldest timestamp or smallest ID. Where multiple reconciled paths have distinct
first Commits, every checkpoint must be measured and exhausted.

Recovery keeps the existing downstream-Horizon priority and parent fallback.
Multiple Commits can share a Horizon; they remain distinct Potential entries.
The retirement proof checks remaining regions before accepting zero at the
first Commit. Positive Potential anywhere in a later/sibling committed region
vetoes retirement, as does a viable earliest checkpoint. The observer's exhausted
expressive path is excluded consistently with recovery, but a terminal Commit is
not removed from its own exhaustion proof. A boundary Commit is evaluated in the
child Horizon it opened. Missing measurements or checkpoint projections never
prove exhaustion.

Commit alone does not retire a branch. Retirement still requires a validated
Rest and an unchanged field version. Shallow branches remain active even if a
rested participant has no currently measurable recovery destination; their valid
generation, evidence and attestation options remain available to participants.

## Detection and short write boundary

The first validated **Rest contribution** is the normal retirement boundary:

1. Outside the contribution transaction, derive Commit ancestry using indexed
   causal edges and branch memberships. An immature lineage cannot retire.
   Read maintained branch-region summaries; any unchecked region with positive
   Potential or incomplete measurements vetoes pruning. Inspect projected local
   topology only for the terminal, root and earliest-Commit basins. Exclude Origin
   from the numeric proof, and require each earliest Commit's complete zero value.
2. Acquire Substrate → Participant → Cycle → Phase and validate the current Rest.
3. Write Rest. If the observation's substrate `field_version` still matches,
   atomically set the exhausted root's `pruned_at` and insert its unique Tragedy.
4. Commit and release the lock. Calculate recovery without locks, then establish
   the selected Phase 1 with a separate short, version-checked entry write.

Inspection reads causal ancestry and existing projections without locks, writes,
measurement derivation or a whole-substrate reconstruction. The retirement
write does indexed version/identity checks, one conditional root update, one
Tragedy insert per exhausted root, and durable projection invalidation. It does
not traverse descendants or relocate other participants. Ledger/evidence changes,
Phase observations, measurement maintenance and retirement advance the field version.
A proof spanning a concurrent change is discarded. The conditional transition and
unique event root permit only one retirement. Already-rested participants use
this same guarded transition during preparation.

A missing/unmeasurable continuation is not proof of zero Potential. A branch is
retired only when all its observed regional recovery values are current and
non-positive. Raw historical Potential on an exhausted Rest path remains history;
that path is already excluded by ordinary recovery admissibility.
Origin and historical commitment measurements are never overwritten to manufacture
eligibility. In particular, failure to find a candidate on one excluded recovery
path is insufficient proof of complete branch exhaustion.

## Participant and Origin lifecycle

Other participants retain their exact position, Cycle and Phase. A stale prepared
contribution can receive a 409; a refreshed historical-position plan exposes only
Rest and no historical candidate/evidence packet. Their own Rest ends that Cycle
and permits ordinary recovery. Pruning never teleports a participant.

Recovery excludes retired nodes from observation and candidate selection, retaining
historical edges only to measure the distance back out of a retired position.
Other viable basins/branches retain priority. If none remains, retirement permits
structural entry at Origin's actual causal Horizon for ordinary Phase 1 Distinguish.
This `origin_renewal` selection carries the actual measured Potential and Charge,
even when zero; it does not put Origin in numeric competition with viable work.
Generation history excludes retired results, so a prior personal Origin Variant
does not permanently consume a renewed generative opportunity. Fresh descendants
open new branch roots and never clear an old root's pruning timestamp.

Healthy, fully proven branch exhaustion therefore leads to retirement and a valid
new entry. `recovery_unavailable` is reserved for cases where no valid entry can be
established: malformed/missing Origin or provenance, unmeasurable structure, or an
incomplete exhaustion observation. A concurrent field change or pending branch observation may defer preparation
until the next fresh attempt (`recovery_pending`); it never authorizes stale pruning.

## Active and historical surfaces

There is no global model scope and no historical deletion. Normal graph collection
and metrics use active membership. `GET /api/substrates/{slug}/propositions?history=1`
returns historical nodes, and direct Proposition inspection remains historical.
Retirement sends a substrate invalidation so connected graphs refresh their active
collection. Participant options, inference context, instrument mutability and
recovery exclude historical branches. Immutable evidence inherited by a still-live
shared descendant remains historical evidence; it does not reactivate its roots.

`tragedies` records the branch root, substrate, discovering participant/Phase,
terminal position, timestamp, observed version, branch opening time, proposition
count and regional exhaustion observations. Original propositions, supersessions,
attestations, receipts, commitment snapshots, Horizons, Cycles and ledger entries
remain available for future runtime, depth, participation and evidence analytics.

`php artisan commons:recovery-inspect <participant-id>` is read-only. Its
`branch_exhaustion` section reports branch identities, existing pruning, regional
observations, proof stability and whether Tragedy would occur. Each live branch
includes `has_commit` (on the terminal's causal ancestry), `committed_regions`,
`earliest_commit_id`, `earliest_commits`, `current_terminal`,
`earliest_commit_potential`, `would_tragedy` and `reason`. The principal reasons
are `immature_branch`, `viable_ancestor` and `earliest_commit_exhausted`;
`incomplete_projection` and `invalid_branch_root` also prevent retirement.
The inspection is hypothetical: an exhausted proof still requires validated Rest. Running it never
retires a branch or changes a participant.

## Existing premature retirements

This correction introduces no migration or history rewrite. Previously pruned
branches and Tragedy evidence remain historical facts; they are not resurrected.
This read-only query identifies likely premature Tragedies with no non-Origin
Commit snapshot in that branch at retirement time (including the single-root
pattern). Later snapshots on shared descendants cannot conceal an early failure.

```sql
SELECT b.substrate_id, b.root_proposition_id, t.terminal_proposition_id,
       b.opened_at, b.pruned_at, t.occurred_at,
       (b.root_proposition_id = t.terminal_proposition_id) AS terminal_equals_root
FROM origin_branches b
JOIN tragedies t ON t.root_proposition_id = b.root_proposition_id
WHERE b.pruned_at IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM origin_branch_memberships m
    JOIN propositions p ON p.id = m.proposition_id AND p.tier = 'committal'
    JOIN commitment_snapshots c ON c.proposition_id = p.id
    JOIN supersessions s ON s.id = c.supersession_id
      AND s.type = 'commit' AND s.target_proposition_id = p.id
    WHERE m.root_proposition_id = b.root_proposition_id
      AND c.calculator_version <> 'origin-bootstrap-v1'
      AND c.evaluated_at <= t.occurred_at
  )
ORDER BY b.substrate_id, b.opened_at;
```

Filter by `b.substrate_id` to audit a particular test substrate. This is diagnostic
only; any restoration would require separate explicit maintenance.

## Rollout

For this maturity correction, deploy application/participant workers together and
restart old workers. Existing memberships and Potential projections remain valid.
No new backfill is required. For first installation of the original branch tables:

Apply the migration and deploy the application/participant workers together.
Quiesce participant writes during the membership backfill and restart old workers
before resuming: pre-release code does not maintain branch membership or field
versions. The migration is additive and uses restrictive foreign keys. It does
not prune existing data; the next validated Rest or preparation of an already
rested participant performs the first version-checked retirement.

Potential projections require the explicit backfill described in [Incremental Potential](incremental-potential.md). Ordinary inspection never repairs missing terrain.
