# Branch Tragedy and Origin renewal

A Tragic Region exposes only Rest when no admissible non-Rest action remains.
This local condition alone does not retire anything. Recovery still searches the
current Domain's viable descendant frontier, then retreats through its causal
Horizon provenance. Complete exhaustion of an **Origin branch** is a distinct,
persisted Tragedy: the branch leaves active participation and becomes history.

## Branch identity

Origin is the immutable `origin-bootstrap-v1` commitment. Each direct Supersession
from Origin opens an `origin_branches` row identified by its target Proposition.
`origin_branch_memberships` projects that root's descendants across all edge types
and Commit/Horizon boundaries. Multiple branches can share one Horizon; retiring
that Horizon would incorrectly remove its viable siblings. Reconciled descendants
can belong to several roots and remain active while any of those roots is active.
The migration backfills membership with a deduplicating recursive traversal;
normal ledger writes extend membership from the source to the new result.

## Detection and short write boundary

The first validated **Rest contribution** is the normal retirement boundary:

1. Outside the contribution transaction, observe the entire branch's regional
   Potential, including every reachable Committal entry and Origin's continuations
   belonging to this branch. Apply the existing Rest exclusion to its exhausted
   position/path, but inspect every other Committal region independently, including
   descendant regions beyond that path. Any viable region vetoes pruning.
2. Acquire Substrate → Participant → Cycle → Phase and validate the current Rest.
3. Write Rest. If the observation's substrate `field_version` still matches,
   atomically set the exhausted root's `pruned_at` and insert its unique Tragedy.
4. Commit and release the lock. Calculate recovery without locks, then establish
   the selected Phase 1 with a separate short, version-checked entry write.

All branch measurement and graph traversal are outside the lock. The retirement
write does indexed version/identity checks, one conditional root update and one
event insert per exhausted root. It does not update descendants, collect analytics,
or relocate other participants. Ledger/evidence changes, new Phase observations,
measurement maintenance, and retirement invalidate the substrate field version.
A proof spanning a concurrent change is discarded. Several participants can
observe exhaustion; the conditional root transition and unique event root ensure
one retirement. Already-rested pre-upgrade participants use the same guarded
transition during preparation, with measurement outside its write transaction.

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
incomplete exhaustion observation. A concurrent field change may defer preparation
until the next fresh attempt; it never authorizes stale pruning.

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
observations, proof stability and whether Tragedy would occur. Running it never
retires a branch or changes a participant.
