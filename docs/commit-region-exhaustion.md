# Commit Region exhaustion and compatibility

> **Current implementation and migration record.** The second-fresh-boundary
> proof described below remains the deployed schema contract. The proposed
> [recursive exhaustion target](tragedy-resolution-target.md) instead evaluates
> remaining continuations atomically after terminal Writes. Existing historical
> proofs and migration provenance must not be rewritten.


A Commit Region is the recovery basin governed by its Committal. Recovery walks
backward through ancestral Commit Regions in causal order. The nearest
non-exhausted governing Committal is the recovery destination. Potential and
Charge do not select recovery destinations. Exhaustion permits recovery past
that Commitment. A boundary-governing region's exhaustion permits recovery
across its Context Horizon boundary, on the same actual recorded lineage.

## One authority

`commit_region_exhaustions` is the primitive proof. It identifies the governing
Committal, owning operational Horizon, original Tragedy and Receipt, later fresh boundary Phase and Proposition,
observed field version, immutable boundary field and exhaustion time.
`tragedies.governing_committal_id` is unique. Tragedy names the event; columns
record its scope and provenance. Renaming preserves event IDs and foreign keys. Horizon membership
is no longer unique. One Horizon can contain multiple independent Tragedies and
regional exhaustion proofs.

The first fresh expressive zero-Charge event creates Tragedy while leaving the
Region open. A later distinct fresh zero-Charge event in the same Region proves
exhaustion, independently of Receipt consumption, Potential or Rest. Ownership
comes from the result’s creating Cycle, never foreign causal ancestry.

`ExhaustionService` applies proof to the governing Committal and expressive nodes
whose creating Cycle has that immutable entry Committal. A Committal owns itself;
multiple Supersession parents do not make valid governance ambiguous. Historical
presentation uses the same authority.
`continuation_exhausted` can propagate from an exhausted outgoing region through
expressive sources only when every remaining outgoing route is exhausted or
currently failed. Live alternatives stop propagation; Committals remain hard
boundaries. No historical proposition, edge, or Receipt is deleted.

`exhausted_region_memberships` is an indexed, rebuildable membership projection
referencing those proofs by foreign key. It carries no independent exhausted
flag or lifecycle. The successful second-boundary transaction captures immutable
Cycle-owned expressive membership, including its own fresh result. Subsequent
normal actions are rejected. Maintenance rebuilds the same ownership projection.
Only explicit migrations predating #196 use the isolated legacy ancestry resolver. Reads use this index rather than recursively walking
ancestry once for every proposition; missing regional proof can never authorize
an indexed member's exhaustion.

## Boundary consequence

A boundary Commit seeds the child Horizon's opening Commit Region, although the
Commit's creating Phase belongs to the parent. Its regional exhaustion is the
crossing authority. There is no `context_horizons.exhausted_at` runtime column.
Opening-region state and time derive directly from `commit_region_exhaustions`
using the opening Committal (or the Origin bootstrap snapshot for the root).
API and workspace metadata expose `opening_region_exhausted_at`; diagnostics
expose `opening_region_exhausted`. Other live regions can continue there.
The projected and reference recovery paths check the same regional proof. Neither a local non-boundary proof nor an inactive node
is permission to cross an unrelated boundary.

## Historical regional migration

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
fresh expressive Charge-boundary birth can begin a new scoped Tragedy. Existing
Receipts and consumption remain readable under their established rules. Old
Receipt references that have no regional mapping still resolve their historical
statements, marked `historical_horizon_exhaustion` and
`regional_exhaustion_proven: false`; they do not imply current regional closure.
`commons:exhaustion-audit` reports archived Horizon Tragedy and exhaustion counts;
null-scoped Tragedies explain withheld migration. The old records are evidence,
not a second live authority.

The migration renames the retired pre-Horizon Origin-pruning `tragedies` table
to `legacy_origin_tragedies`, preserving all its rows, then renames
`horizon_tragedies` to `tragedies`. Existing proof, final-attempt and Receipt-scope
foreign keys follow the active table. The earlier scope-backfill migration
explicitly handles the table name at its historical stage.

Before dropping the duplicate Horizon timestamp, unmatched values are copied to
`legacy_horizon_exhaustion_markers`. Values exactly matching an archived
`horizon_exhaustions` timestamp are already reconstructible and need no extra
copy. The older `legacy_exhausted_at` archive remains untouched. Neither archive
has recovery/actionability authority. This preserves historical observations
without maintaining synchronized runtime state.

The Tragedy column audit retains `context_horizon_id`, `phase_id`, `participant_id`,
`terminal_proposition_id`, `opening_proposition_id`, `receipt_id`,
`observed_field_version` and `occurred_at` as immutable event/provenance facts.
`governing_committal_id` explicitly identifies the regional scope. None is a
mutable duplicate of current exhaustion state; reconstructibility by traversing
history is not a reason to remove an event's provenance.

The migration invalidates affected Potential projections and advances field versions.
Run the existing projection maintenance before resuming participants. Measurements
are not marked dirty by this migration. Once regional proof exists, a code-only
rollback cannot collapse it into one Horizon fact; restore a pre-rollout backup.
Without scoped events or regional proof, `down()` restores both historical table
names and the old timestamp from archived observations/proof before earlier
migrations run. Compatibility tests exercise the current Production schema and
this reversible empty-regional-state path.
No substrate reset or history deletion is required.

## Observability and acceptance

The regional proof exposes governing seed, Tragedy origin, Receipt consumption
and boundary relationship separately. `region.exhausted` carries the seed and
boundary consequence. Existing `horizon.tragedy` and `horizon.exhausted`
notification names remain for compatibility. Tragedy carries
`governing_committal_id` and `scope: commit_region`; the exhaustion notification
carries `governing_committal_id`, `opening_region_exhausted: true` and
`boundary_region_exhausted` (false for Origin, which has no parent boundary).
These are invalidations about that region, never proof that all Horizon regions
are exhausted. No additional persisted Horizon summary is maintained. No badge or graph UI work is included.

Focused fixtures prove C → B → A within one open Horizon, stronger sibling
exclusion, per-region first/later boundary events, crossing only an
exhausted boundary region, and survival of other live child-Horizon regions.
Existing stale-proof, independent warrant consumption, rollback, concurrency and numerical
projection regressions retain their safeguards. Migration fixtures distinguish
provable boundary mapping from ambiguous historical transport.


## Stage 6 witness migration

`2026_09_24_000000_record_exhaustion_boundary_field` adds nullable JSON `boundary_field`. New rows store the canonical event Tension/Charge. Null denotes the archived Rest/consumption proof described above; no historical row is rewritten. Audit uses fresh expressive ownership, distinct ordered birth provenance and boundary values for new rows. Rollback refuses to erase new boundary witnesses.
