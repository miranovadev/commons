# Participation performance

`InputPlanService` creates one explicit `ParticipationContext` for each read or
write-boundary validation. It is never cached globally or retained between actions.
Reads take no write locks and do not persist projection repair. Contribution
validation builds only the admissibility schema; response serialization runs
following commit. Full-cycle wakes reuse that fresh response for the next inference
instead of immediately hydrating it a second time. See [transaction-locking.md](transaction-locking.md).

## What was stalling

Standalone plans took an exclusive Substrate lock before hydrating all substrate
history. Missing/obsolete measurements were repaired inside that read transaction.
Contributions then built their next full state before releasing their own root
lock. Synchronous hyperlink verification/search also held that lock during network
I/O. Four full-cycle workers therefore queued human reads behind shared writes and
other reads. Query-count tests alone did not expose hydration or lock waiting.

## Working set

The context now hydrates the current Horizon's propositions plus recursive causal
ancestors, Commit source history and required Horizon comparison boundaries.
An indexed, batched ID walk visits each dependency once. A recursive SQL
prototype produced pathological PostgreSQL plans after preceding data growth;
the complete dense-test sequence runs in CI to cover that workload sensitivity.
Personal generation history filters Supersession source IDs directly and is
memoized per source set within the calculation. This avoids a redundant parent
join that PostgreSQL could repeatedly scan for every Bias candidate. Traversal
and revision options reuse the context's effective personal Attestations.
Memberships, frames, evidence, receipt contexts/events, phase-touch aggregates and
commitment snapshots are restricted to those IDs. Parent and child adjacency and
per-subject evidence are indexed and memoized within the calculation.

Incoming edges retain the complete selected ancestry. Outgoing hard edges remain
visible even when their successors are outside the working set, so historical
source retirement is preserved. Outgoing Variant edges to excluded historical
branches are not hydrated. Off-path citations resolve their exact same-substrate
subject separately; they do not pull its entire graph into the plan. Persistent
personal citation knowledge is projected as reference IDs across Horizons, so
leaving a lineage does not make already-loaded citation content new again. Compound
Cycle/Horizon and Proposition/Phase indexes support working-set entry, including
legacy unledgered candidates. Horizon membership and Receipt-event indexes support
scoped workspace and evidence reads.

All calculators, attenuation, register equivalence, hard/soft supersession rules,
ledger-prefix evidence replay and Commit-only inheritance remain authoritative.
The complete batched plan/schema is compared against the unbatched calculation
for Mediate, Bias and Order. No participant display/token limit truncates evidence
needed for measurements.

## Reproduce

```bash
php artisan test --filter ParticipationPerformanceTest
php artisan test --filter OptimisticParticipationTest
php artisan test --filter ParticipationSnapshotTest
php artisan test --filter TransactionLockOrderTest
```

The scale regression starts from 43 propositions, multiple Committals, two Horizons,
three participants, reclassification/Revise/Reconcile/Commit history, Attestations
and Receipts. It adds 1,000 historical sibling propositions in the earlier Horizon,
each with an Attestation, Receipt, participant load and ledgered context event.
It reports context construction, plan/workspace query count and wall time. The test
asserts unchanged hydrated working rows, query counts and complete plan after that
history growth, rather than imposing a machine-sensitive millisecond limit.

Measured locally on PHP 8.5.10 / SQLite, same deterministic 1,043-proposition fixture:

| Measure | Main (`b7fe201`) | Optimistic working set |
| --- | ---: | ---: |
| Hydrated propositions + Attestations + Receipts + loads + events | 5,100 | 100 |
| Context construction | 1,057.88 ms | 25.38 ms |
| Participant plan | 4,919.63 ms | 308.47 ms |
| Workspace plan | 9,989.49 ms | 403.74 ms |
| Context queries | 34 | 37 |
| Plan queries | 77 | 63 |
| Workspace queries | 89 | 75 |

The largest improvement is reduced hydration/CPU work; shared generation-history
checks also reduce query counts on warm plans.
Working-set queries follow newly discovered dependency batches. The original scale run
exhausted a 128 MB local PHP limit; baseline timing above used a 1 GB benchmark
limit and reported 142.5 MB peak. Application runtime settings are unchanged.
These are isolated local fixture measurements, not production latency promises.
PostgreSQL CI exercises real locks and emits its own benchmark measurements.
Its separate profile job also compares plans against the unbatched reference.
Set `COMMONS_PROFILE_SQL=1` when running the scale test to print the slowest plan
queries and PostgreSQL execution plans for statements exceeding 100 ms.
The contribution diagnostic reports 74.41 ms in the write transaction out of
109.69 ms total for its small fixture; response context construction begins only
after commit. This is a transaction-boundary assertion, not a latency guarantee.

## Incremental Potential and remaining scale limits

[Incremental Potential](incremental-potential.md) supersedes the former whole-substrate
recovery and read-time measurement fallback. Initial selection reads indexed entries;
Rest recovery walks maintained region frontiers upward. Writes dirty affected regions
through reverse dependencies and publish prepared measurements after releasing the
contribution lock. Ordinary reads never derive measurements.

- Current-Horizon candidates and their actual ancestry can still grow. The context
  is bounded causally, not to a fixed row count; Horizon metadata is restricted to
  the hydrated working set. A very large single live Horizon still has a cost.
- Recovery depends on provenance depth, local topology and nondominated frontier
  width. It no longer hydrates unrelated historical Horizons.
- Writes retain existing target validation and immutable Commit snapshot work in
  the guarded transaction. New region-wide measurement and Potential calculation
  runs outside it; prepared rows publish in bounded batches under a version check.
- Direct historical graph inspection remains explicit and can traverse history.
  Active graph cards consume maintained Potential.
