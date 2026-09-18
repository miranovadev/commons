# Exploration epochs and spent continuations

A tested expressive route can fail without exhausting its Horizon. Failure is an
immutable witness, scoped by the creating Horizon, structural Phase position,
and exploration epoch. Labels classify. Position governs.

## Invariants

- **Spent invariant:** An expressive proposition is spent only relative to a
  Horizon, structural position, and exploration epoch.
- **Historical invariant:** Failure records are immutable historical facts.
  Advancing an epoch never clears or rewrites them.
- **Epoch invariant:** A successful new Variant is the only event that advances
  the exploration epoch for that structural position.
- **Staggered reopening invariant:** Distinguish and Disperse epochs advance
  independently. Reopening one structural position does not reopen another.
- **Multi-position failure invariant:** A downstream failed lineage may mark
  multiple generative positions as spent at once, each against the current
  epoch of its own structural position.
- **Traversal invariant:** A proposition spent in the current epoch is not
  eligible for Traverse or further Attestation.
- **Generation invariant:** When all current expressive continuations at a
  structural position are spent and Horizon Potential remains, Variant becomes
  available, subject to the existing evidence prerequisites.
- **Healthy-lineage invariant:** A viable existing continuation remains
  actionable and must not cause redundant Variant generation.
- **Potential invariant:** Potential governs whether another generative probe
  may remain. It does not itself mark paths spent and does not itself prove
  Horizon Exhaustion.
- **Tragedy invariant:** Tragedy remains the first no-Potential Rest in a Horizon.
- **Exhaustion invariant:** Horizon Exhaustion remains permanent and
  Horizon-scoped via `context_horizons.exhausted_at`.
- **Visibility invariant:** Spent expressive propositions remain contextual
  evidence even while non-actionable.
- **Phase-position invariant:** Labels classify. Position governs.

## Failure and generation

`HorizonFailureService` inspects an authoritative Rest plan outside the write
lock. An ordinary failure requires a later downstream dead end (Mediate or
later), completed evidence and effective judgment. On recovery, an earlier
actionless Phase may reuse current-epoch failure proof for all its Gate's children,
with effective Gate judgment; an empty Write alone is not proof. An unobserved actionless
Phase, numeric zero Potential alone, or a sibling Variant is insufficient.
Generative Distinguish/Disperse ancestors additionally require their own
completed Instrument, produced Receipt and effective Attestation.

The existing backward walker follows only the tested causal lineage and its
immediate child checks. A live child prevents failure propagating to its parent.
Current-epoch child failures may support propagation; old failures may not.
Committals and Horizon boundaries stop propagation. Each accepted expressive
node records its own producing position and that position's current epoch.
Publishing ordinary failure does not create a Tragedy Receipt or exhaust a
Horizon.

At Distinguish and Disperse, every existing opportunity must be spent, properly
instrumented, evidenced and effectively attested before a further Variant is
available, and positive Horizon Potential must remain. A healthy evidenced
route still blocks redundant generation. There is no action priority, sibling
quota, participant-count quota, or receipt-consumption shortcut. The initial
empty-position behavior is unchanged. Persist retains its structural rules;
its successful Variants also advance only the Persist epoch.

| Event | Epoch effect |
| --- | --- |
| Successful `proposition.variant` | Advance only the creating Horizon + position |
| Failed, rejected or rolled-back Variant | None |
| Instrument, Receipt, Attestation, Traverse | None |
| Reclassify, Reconcile, Revise, Commit | None |
| Rest, Tragedy, Exhaustion | None |

For example, a Mediate failure can record A at Distinguish epoch 3 and X at
Disperse epoch 7. Creating B at Distinguish advances 3 to 4; A's earlier failure
remains historical, while X remains spent at epoch 7. Creating Y at Disperse
advances 7 to 8 and only then reopens X.

## Persistence and rollout

`horizon_exploration_states` has one row per Horizon + structural position.
A missing row is epoch zero; reads do not create rows. A Variant creates or
increments the row inside its existing substrate-locked contribution
transaction, so epoch and authored proposition roll back together.

`continuation_failures` is keyed by Horizon + position + epoch + proposition.
`phase_id` identifies the failed downstream Phase, while `structural_position`
comes from the failed proposition's creating Phase. Repeated publication is
idempotent. The table retains historical epochs without clearing rows.

Migration `2026_09_18_000600` copies every legacy failure's original ID references
and timestamp unchanged, derives its producing position, and assigns epoch zero.
It rebuilds the primary key transactionally on PostgreSQL and SQLite. No old
Variant count is inferred. Existing Horizons begin at epoch zero; the first
post-rollout Variant advances its own position to one. No Horizon exhaustion
marker or Tragedy history is moved or rewritten.

Pause contribution workers for the coordinated application/schema rollout,
apply the migrations and deploy the complete integration. Migration
`2026_09_18_000700` advances the field version and records durable measurement
dirt for Horizons with existing failures, preventing old projections from
certifying a field that excluded those propositions. Run
`php artisan commons:potential-refresh` (also scheduled maintenance), or explicitly
`php artisan commons:potential-rebuild <substrate>` for each affected Substrate,
verify projections are current, then resume workers. This is a schema/key change; do not run old writers against
the new schema. The staged PRs are review boundaries, not rolling deployments.
Rollback refuses to discard failure or epoch history: retain a pre-rollout backup
and prefer a forward fix after new exploration starts.

## Visibility, concurrency and cost

Continuation failures do not belong in the global active/exhausted scope.
Historical propositions remain in the participant's structurally relevant
Write, Read and lineage context. Current spent state is a small annotation;
action target sets exclude current failures. Exhausted history is reserved for
permanent Horizon closure. Spent evidence remains part of the measured field;
being spent does not force numeric Potential to zero.

The contribution path revalidates eligibility under the existing substrate
write lock. The first racing Variant changes the epoch and creates an untested
opportunity; a competitor must pass the new state, preventing duplicate probes
at that opportunity. Failure publication verifies field version, Phase identity,
completed Rest, and every captured position epoch under the same lock.

Spent reads are indexed, bounded by the existing candidate/working set, and
memoized only within one calculation. There is no graph-wide scan, global cache,
or graph hydration in epoch advancement. Failure inspection remains outside the
critical write section. Realtime events carry compact IDs and position/epoch
metadata; existing participant position broadcasts still move graph avatars.
