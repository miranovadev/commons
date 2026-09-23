# Cycle entry identity — Homeostatic homology epic, Stage 1

`cycles.entry_committal_id` is the immutable Committal from which the Cycle
began. `Cycle::entryCommittal()` exposes that relationship. Origin bootstrap is
the sole production Cycle without an entry Committal.

Entry preserves the existing Receipt catalogue scope, Cadence interval ownership,
preceding committed lineage for prospective Closure, Commit Region/Potential
accounting for the Cycle's writes, and associated recovery basin. A Committal owns
itself; expressive results are accounted to their producing Cycle's entry Region.

Entry is history and resource identity, not a claim that every causal input must
belong to that lineage. Participant position is separate causal state. For a Cycle
entered at A that uses foreign input B and creates Committal C, the committed
predecessor remains A → C. Supersession retains the actual causal inputs, including
B, without changing entry or the creating Context Horizon.

## Representation and migration

The original governance and Cadence backfill migrations retain their historical
column names. The entry migration renames the existing column in place; it does
not infer new values from participant position, Supersession or Horizon state.
It also renames the completion index, the PostgreSQL foreign-key constraint,
and the PostgreSQL/SQLite immutability triggers and PostgreSQL trigger function.
Nullable bootstrap identity, restrictive deletion, timestamps and all stored IDs
are preserved. There is no second column or live compatibility alias.

The new migration is ordered at `2026_09_22_210000`, after the original entry and
Cadence backfills and before `2026_09_22_220000_refresh_cycle_owned_regions`, which
calls current application ownership services. On an existing installation that
already applied the refresh, Laravel applies the pending rename without rerunning
that refresh. On a fresh or older installation, historical backfills run against
their original schema before live ownership reads use the renamed column.

Rollback restores the historical column, index, foreign-key constraint and
immutability guard without rewriting history. Run migrations with application
writers and queue workers quiesced and deploy matching code/schema together:
this is a coordinated rename, not a rolling dual-schema deployment. No production
migration is performed as part of this PR.

Regional authority keeps its own terminology and representation:
`tragedies.governing_committal_id`,
`commit_region_exhaustions.governing_committal_id`, exhaustion membership and proof
payloads are unchanged. Frozen Commitment snapshots, evidence, lineage edges,
participant positions and published Potential state are untouched by the rename.

## Validation and boundary

`CycleEntryIdentityTest` covers explicit entry, the Eloquent relation, Origin null,
Eloquent and bulk immutability, replacement rejection, substrate/Horizon isolation,
foreign-key rejection, reversible migration, index/constraint preservation, and
the original fail-closed backfill using the recorded entry Phase rather than the
participant's current position. Core runs it on PostgreSQL and alongside the
existing Closure migration checks on SQLite.

`RegionalExhaustionMigrationTest` runs the rename over populated Tragedy,
final-attempt, consumed Receipt and exhaustion history, checking that evidence,
regional authority, measurements and published state remain unchanged. Existing
Closure, ownership, Mediate, recovery and lifecycle tests retain their assertions
using the renamed Cycle field.

This stage changes no action availability, homology eligibility, measurement,
Receipt rules, recovery, movement or projection formulas. The general Homeostatic
population and Phase 5–7 behavior changes remain deferred to their reviewed stages.
