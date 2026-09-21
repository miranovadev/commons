# Commit Region exhaustion and compatibility

A Commit Region is the recovery basin governed by its Committal. Recovery walks
backward through ancestral Commit Regions in causal order. The nearest
non-exhausted governing Committal is the recovery destination. Potential and
Charge do not select recovery destinations. Exhaustion permits recovery past
that Commitment. A boundary-governing region's exhaustion permits recovery
across its Context Horizon boundary, on the same actual recorded lineage.

## One authority

`commit_region_exhaustions` is the primitive proof. It identifies the governing
Committal, owning operational Horizon, Tragedy, consumed Receipt, subsequent
terminal Phase and Proposition, observed field version and exhaustion time.
`horizon_tragedies.governing_committal_id` is unique; the old table name is retained
to preserve foreign keys and immutable historical identity. Horizon membership
is no longer unique. One Horizon can contain multiple independent Tragedies and
regional exhaustion proofs.

Rest qualification and the final exploration allowance use the current region's
existing Potential calculation. The numerical algorithm, measurement attenuation
and projection mathematics are unchanged. The region's evidence-bearing Rest,
shared final allowance, actual Receipt consumption and subsequent failure on the
consumed classification's actual lineage remain necessary. A local actionless
terminal or numerical zero alone is insufficient. Unknown/ambiguous governing
membership cannot supply a regional proof.

`ExhaustionService` applies proof to the governing Committal and expressive nodes
whose first governing Committal is that seed. It stops at later Commits and does
not union ambiguous ancestry. Historical presentation uses the same authority.
`continuation_exhausted` can propagate from an exhausted outgoing region through
expressive sources only when every remaining outgoing route is exhausted or
currently failed. Live alternatives stop propagation; Committals remain hard
boundaries. No historical proposition, edge, or Receipt is deleted.

`exhausted_region_memberships` is an indexed, rebuildable membership projection
referencing those proofs by foreign key. It carries no independent exhausted
flag or lifecycle. Final failure captures the governed members from the existing
version-checked regional projection before the write lock; the proof and its
membership publish atomically after revalidation. New writes cannot extend an
exhausted region. Historical migration derives membership from immutable raw
Commit Region ancestry. Reads use this index rather than recursively walking
ancestry once for every proposition; missing regional proof can never authorize
an indexed member's exhaustion.

## Boundary consequence

A boundary Commit seeds the child Horizon's opening Commit Region, although the
Commit's creating Phase belongs to the parent. Its regional exhaustion is the
crossing authority. `context_horizons.exhausted_at` is a derived opening-region
summary (Origin summarizes its bootstrap region). It is not an actionability
filter for all positions in that Horizon. Other live regions can continue there.
The projected and reference recovery paths check the underlying regional proof,
not the timestamp alone. Neither a local non-boundary proof nor an inactive node
is permission to cross an unrelated boundary.

## Migration

Migration `2026_09_22_000000_scope_exhaustion_to_commit_regions` adds the new proof
relation and nullable unique Tragedy scope. It preserves every old
`horizon_exhaustions` row and every original Tragedy, Receipt, consumption, and
witness field. It does not infer exhaustion of every region formerly owned by an
exhausted Horizon.

`RegionalExhaustionBackfill` assigns an old Tragedy to a region only when its
terminal has an unambiguous governing seed in the recorded operational Horizon,
its Rest witness agrees, its effective Receipt scope has not moved elsewhere,
and any consumption/final allowance belongs to that same region. An old
exhaustion additionally needs its own matching Rest witness, the same region,
valid consumption, and the consuming classification on its actual backward
lineage. The copied proof preserves the original time and field version.
A boundary-governing seed satisfying these checks safely supplies the boundary
consequence; a different non-boundary seed supplies only its own local proof.

Ambiguous or transported legacy proofs remain archived. They do not acquire
regional authority or silently mint a replacement final allowance. A future
legitimate regional no-Potential Rest can begin a new scoped Tragedy. Existing
Receipts and consumption remain readable under their established rules. Old
Receipt references that have no regional mapping still resolve their historical
statements, marked `historical_horizon_exhaustion` and
`regional_exhaustion_proven: false`; they do not imply current regional closure.
`commons:exhaustion-audit` reports archived Horizon Tragedy and exhaustion counts;
null-scoped Tragedies explain withheld migration. The old records are evidence,
not a second live authority.

The migration rebuilds the Horizon boundary summaries from mapped regional
proof, invalidates affected Potential projections and advances field versions.
Run the existing projection maintenance before resuming participants. Measurements
are not marked dirty by this migration. Once regional proof exists, a code-only
rollback cannot collapse it into one Horizon fact; restore a pre-rollout backup.
No substrate reset or history deletion is required.

## Observability and acceptance

The regional proof exposes governing seed, Tragedy origin, Receipt consumption
and boundary relationship separately. `region.exhausted` carries the seed and
boundary consequence. Existing `horizon.exhausted` notifications remain for the
derived opening-region summary. No badge or graph UI work is included.

Focused fixtures prove C → B → A within one open Horizon, stronger sibling
exclusion, per-region shared final attempts and consumption, crossing only an
exhausted boundary region, and survival of other live child-Horizon regions.
Existing stale-proof, unrelated-consumption, rollback, concurrency and numerical
projection regressions retain their safeguards. Migration fixtures distinguish
provable boundary mapping from ambiguous historical transport.
