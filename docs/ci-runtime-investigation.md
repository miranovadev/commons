# Part 2: CI runtime investigation and architecture

Scope: test infrastructure, classification and CI only. Application code, migrations,
substrate semantics and the observed cross-lineage traversal bug are unchanged.
All runtime evidence below uses PostgreSQL 18 and PHP 8.5 on GitHub Actions.

## Measured baseline, before test changes

| Suite / source run | PHP cases | PHP time | Job time |
| --- | ---: | ---: | ---: |
| [Core, main](https://github.com/miranovadev/commons/actions/runs/35466343419) | 617 | 5,061.88s | 86m01s |
| [Full Integration](https://github.com/miranovadev/commons/actions/runs/35436695301) | 619 | 6,692.19s | See source job |
| Query, same companion run | 16 | 150.89s | See source job |
| Load, same companion run | 22 | 52.64s | See source job |

The Core run used `b05ff270`; Part 1's merge `c01b7a12` changes documentation only.
The companion run is separately identified because runner conditions and its commit
differ. These are observations, not a controlled comparison between those runners.
Per-class totals and the twenty slowest cases in each suite are preserved in
[`evidence/ci-runtime-baseline.json`](evidence/ci-runtime-baseline.json).

Core's frontend install/build/UI tests took 9s/17s/27s, plus 3s to set up Node:
56s total. PostgreSQL startup was 22s, PHP setup 6s and Composer installation 5s.
PHP accounts for about 98% of the 86-minute job. Frontend tuning cannot explain or
solve the main delay. `package.json` also ran the identical `vite build` twice.

| Core hotspot | Cases / purpose | Seconds |
| --- | --- | ---: |
| `ParticipationTragedyLifecycleTest` | Complete natural Tragedy/final-attempt/exhaustion journey | 4,142.63 |
| `ParticipationLocalEpochTest` | Complete third-Variant local-epoch journey | 195.10 |
| `ParticipationLifecycleRegressionTest` | Three successful-route / independent-slot journeys | 67.02 |
| `ParticipationRecoveryTest` | Recovery traversal and successful revisit | 62.24 |
| `ParticipationSourceEquivalenceTest` | Manual/connection execution equivalence | 49.97 |
| `ParticipantActionContractTest` | Exhaustive schema/transport/execution contracts | 41.42 |
| `OperationalClosurePathTest` | Whole cycle with real Closure calculations | 39.61 |
| `ParticipationHttpHarnessTest` | Whole cycle plus four short HTTP safety checks | 33.61 |

Tragedy alone is 81.8% of Core PHP time, with 8,684 assertions. The remaining
HTTP journey classes collectively consume another 423s. All other Core classes
take 496s; this is why classification alone still needs bounded parallel execution.
Integration redundantly executes **all 617 Core cases**, adding only two distinct
cases. The full union is 657 unique cases: 619 automatic, 16 Query and 22 Load.

## PostgreSQL order dependence and fixture cost

The [earlier bounded SQL diagnostic](https://github.com/miranovadev/commons/actions/runs/35435715360)
captured 183 Tragedy requests: 598.21s wall, 573.17s SQL, 63,065 queries.
Slow SELECT plans use nested loops and repeatedly estimate one row. The test
database contains rolled-back/dead rows from preceding tests, while table stats
report zero visible live rows. This identifies database execution as the dominant
amplifier; it does not establish an application query fix or prove which individual
PostgreSQL statistic caused each plan.

The [Part 2 controlled investigation](https://github.com/miranovadev/commons/actions/runs/35482578999)
keeps the current test's actions and assertions unchanged:

* Fresh isolated Tragedy passes all **8,684 assertions in 123.82s**. Its 223 requests
  use 83,740 queries: 122.44s request wall and 88.25s SQL. Initial framework/migration
  setup takes 0.91s.
* Replaying the aggregate prefix with a fresh schema before each HTTP scenario
  reaches Tragedy's request 218 in 117.43s (84.45s SQL), with no SELECT exceeding
  the diagnostic's initial 200ms threshold. The deliberately bounded whole-prefix
  command expires at 510s before the final five requests. **This diagnostic is not
  an aggregate pass.** The full Integration acceptance run must prove completion.
* Fifteen HTTP scenario setups in that run total 16.23s, approximately 1.02–1.18s
  each. Re-migration is small compared with the thousands of seconds lost when
  complete journeys share PostgreSQL physical/statistical state.

`RefreshDatabase` rolls back logical changes but does not restore a newly migrated
database's physical/planner state. The HTTP harness now requests a fresh migration
before each independent scenario. It retains transactions, all authored actions,
the real production services and the direct-fixture-write guard. There are no
planner hints, disabled indexes, application caches or altered calculations.
The test-driven journey helper also reuses the GET it just performed when posting
the next contribution, eliminating an identical consecutive GET with no intervening
action. The fixed scripts already did this.

Fixtures are deliberately different by purpose: Core's positioned graphs exercise
individual invariants; HTTP journeys build state through production requests.
Receipt fixtures create participant/cycle/phase, execution and loaded-evidence
state; exhaustive action contracts repeat these across phase/provider cases.
Those costs remain visible in per-test timing rather than being hidden by sharing
mutable state. Long 600/630-contribution replay captures remain unchanged under
`tests/Replay`, outside default discovery as previously approved.

## SQL profiling overhead

`COMMONS_PROFILE_SQL` has one reader, inside the Query-group historical-growth test.
It prints three slow queries and optionally executes `EXPLAIN ANALYZE` above 100ms.
It does **not** enable global SQL profiling in Core or Integration. Query-count
assertions use their existing scoped listeners regardless of this flag.

The investigation's eight-case Query subset passes 133 assertions both off and on.
Wall times are 107.04s and 219.80s on separate runners; that difference is confounded
by runner speed and must not be attributed to profiling. In the enabled run the
diagnostic block itself takes about 0.18s and executes one extra EXPLAIN whose
reported execution is about 147ms. Query counts and hydrated rows remain identical.
Optional EXPLAIN output now defaults off and is available only as a manual Query
workflow input. SQL-count regression assertions remain enabled.

## Resulting architecture

| Suite | Trigger | Healthy target | Hard budget | Purpose |
| --- | --- | --- | --- | --- |
| Core | Every branch push | <5m | 10m total job budgets | Focused invariants, short HTTP checks, frontend contracts/build |
| Integration | PRs targeting `main` | <15m | 20m | Complete API/lifecycle journeys and exhaustive action execution |
| Query Regression | Manual only | — | 20m | Query counts, hydration and historical-population regression |
| Load / Concurrency | Manual only | — | 30m | Contention, locks, concurrency and scale |

Core builds and tests the frontend once, then shares the real build artifact with
three PHP shards. Its frontend budget is 2m and each following PHP job is capped
at 8m, so the dependent job budgets sum to 10m. Runner queue time is reported
separately from execution. Integration does not repeat frontend work or Core tests.

PHPUnit discovery remains authoritative. `scripts/ci/run-suite.py` obtains each
suite from Composer, partitions complete classes by measured historical duration,
and includes new classes automatically. Durations are scheduling weights, never an
allowlist. A Core check proves the four selections are disjoint and their union
equals default discovery. Each shard publishes its selected case inventory, JUnit,
event timings and process duration, and checks that every selected case executed.

The intended partition is **595 Core + 24 Integration + 16 Query + 22 Load = 657**:

* Seven `Api/Participation*` journey classes gain the Integration group, preserving
  all fixed scripts and assertions.
* Only the complete-cycle method in `ParticipationHttpHarnessTest` moves; stale
  writes, receipt/rest safety and the fixture-write guard remain Core.
* `OperationalClosurePathTest` joins the two existing full-cycle Integration cases.
* Only the exhaustive execution provider and Rest round-trip in
  `ParticipantActionContractTest` move. Static phase transport and the reported
  union-error regression remain Core. Integration additionally verifies that
  well-formed actions available in other phase/state contracts are rejected here
  when absent, without changing the phase; positive execution assertions remain.
* All focused lineage, phase-position, recovery, receipt, Closure, exhaustion and
  final-attempt invariants remain Core. No correctness case moves to manual suites.

No test is deleted or replaced. The demonstrated duplication removed is suite
execution and an identical frontend build, not presumed semantic redundancy.
`test:integration-full` remains a compatibility alias for `test:integration`.
`test:constitutional` is an optional cross-suite selection, not a CI job.

## Acceptance evidence

Validation pending. The dedicated PR must record completed PostgreSQL runs and
actual case inventories for all four suites before acceptance. Query and Load can
be deliberately validated through a temporary investigation caller while browser
authentication is deferred; that caller must be absent from the final PR.
No cross-lineage traversal repair is included or started.
