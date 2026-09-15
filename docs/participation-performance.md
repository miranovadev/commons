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
| Context construction | 1,057.88 ms | 19.53 ms |
| Participant plan | 4,919.63 ms | 306.47 ms |
| Workspace plan | 9,989.49 ms | 436.70 ms |
| Context queries | 34 | 37 |
| Plan queries | 77 | 80 |
| Workspace queries | 89 | 92 |

This is a hydration/CPU improvement, not a query-count reduction on warm plans.
Working-set queries follow newly discovered dependency batches. The original scale run
exhausted a 128 MB local PHP limit; baseline timing above used a 1 GB benchmark
limit and reported 142.5 MB peak. Application runtime settings are unchanged.
These are isolated local fixture measurements, not production latency promises.
PostgreSQL CI exercises real locks and emits its own benchmark measurements.
The contribution diagnostic reports 74.41 ms in the write transaction out of
109.69 ms total for its small fixture; response context construction begins only
after commit. This is a transaction-boundary assertion, not a latency guarantee.

## Deliberate remaining work

- Current-Horizon candidates and their actual ancestry can still grow. The context
  is bounded causally, not to a fixed row count; it still loads the small collection
  of Horizon metadata for the substrate. Restricting a large active Horizon to
  region candidates would require a separate closure-completeness proof.
- Initial Potential ranking and Rest recovery still traverse substrate topology to
  rank eligible Committals. Their reads no longer repair projections or lock; when
  used to create a Cycle, selection/validation is part of that write transition.
- Evidence changes still invalidate substrate projection versions. Only requested
  values are recomputed by a read, in memory. A dependency-indexed invalidation or
  background projection scheduler could reduce repeated cold computation further.
- Writes still serialize shared admissibility, ledger sequencing and projection
  maintenance at the Substrate boundary. Response building and all network/model
  waits have been removed from that interval; writes are not claimed constant-time.
- Historical hydration improvements do not change graph pagination or the
  independent human graph API, which already observes without projection repair.
