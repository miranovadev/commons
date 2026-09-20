# Test suites and Actions

Core is the fast developer safety net on every branch push. Integration validates
complete API/lifecycle journeys on PRs targeting `main`. Classification follows
purpose: an untagged test in `tests/Unit` or `tests/Feature` belongs to Core by default.
Seconds of runtime alone are not grounds for making correctness coverage manual.

## Commands, triggers and budgets

Use the existing testing database configuration. CI uses PHP 8.5, PostgreSQL 18
and Node 24. Locally, run `npm ci` and `npm run build` before Core so its real
websocket smoke client and application asset checks have their dependencies.
Clear cached configuration with `php artisan config:clear` when switching environments.

| Suite | Command | Cases | GitHub Actions trigger | Healthy / hard runtime |
| --- | --- | ---: | --- | --- |
| Core | `composer test` or `composer test:core` | 595 | Every branch push | <5m / 10m |
| Integration | `composer test:integration` | 24 | PRs targeting `main` only | <15m / 20m |
| Query | `composer test:query` | 16 | Manual **Query Regression** | — / 20m |
| Load | `composer test:load` | 22 | Manual **Load / Concurrency** | — / 30m |
| All PHP | `composer test:all` | 657 | Explicit local invocation | No separate workflow |
| Constitutional | `composer test:constitutional` | 13 | Optional selection across Core/Integration | No separate workflow |
| Domain replay | `composer test:replay-domain` | 2 | Explicit manual invocation | Outside mandatory discovery |

Counts include provider cases and describe the Part 2 checkpoint. The four main
PHP selections are mutually exclusive and collectively equal default discovery.
Integration no longer repeats Core. `test:integration-full` is a compatibility
alias for `test:integration`, with the same disjoint selection.

`phpunit.xml` discovers `tests/Unit` and `tests/Feature`; unqualified
`php artisan test` includes Query and Load. The complete 600/630-contribution
natural captures, assertions and fixed scripts remain under `tests/Replay`,
outside default discovery as previously approved. The `performance` group on
`ParticipationPerformanceTest` remains an alias; use `query` for the complete suite.

Core runs TypeScript checks, one Vite build and all frontend contract tests once.
Five PHP shards then share the real build artifact. Only the shard containing
`ReverbTransportTest` installs the locked websocket client dependencies; it must
deliver a real websocket notification. Integration, Query and Load have no
frontend-build or frontend-test dependency.

All four suites use `php-tests.yml` for the same PostgreSQL service, PHP extensions,
Composer cache and 30-second SQL statement timeout. Core's frontend job is capped
at 2m and each dependent PHP shard at 8m; the dependent execution budgets sum to
10m. Integration/Query/Load jobs are capped at 20m/20m/30m. Runner queue time is
separate from execution. Composer's process timeout is disabled for named local
commands; Actions enforces the job budgets.

Query's optional `profile_sql` dispatch input prints slow SQL/EXPLAIN diagnostics
and defaults off. Query-count assertions remain enabled independently. Every PHP
job retains JUnit, a selected-case inventory, event timings and process duration
even after ordinary test failure. Empty selections, missing cases and skipped
cases cannot silently produce a successful shard.

## Manual validation

Once the workflows are on the default branch, select **Run workflow** in Actions
and choose the candidate branch, or use:

```bash
gh workflow run query.yml --ref my-branch
gh workflow run query.yml --ref my-branch -f profile_sql=true
gh workflow run load.yml --ref my-branch
```

Query and Load have no branch-push or PR trigger, including `ci/query/**` and
`ci/load/**`. Run Query for query/context/topology or population-growth changes.
Run Load for transactions, locks, worker coordination and competing writes.
Integration runs automatically for PRs targeting `main`; it has no dispatch or
special branch namespace. There are no path filters or duplicate Core PR runs.

If repository rules pin check names, configure the Core frontend check, all five
Core PHP shards and the Integration check. Existing rules referring to
`core / PHP 8.5 / PostgreSQL 18` or the old matrix names need an explicit update
when this CI architecture is adopted; this PR does not change repository rules.

## Coverage placement

| Tests | Placement and reason |
| --- | --- |
| Phase-position, lineage, recovery, receipt, Closure, exhaustion, final-attempt and stale-schema invariants | Core: focused behavioral proofs and essential regressions |
| `ParticipationHttpHarnessTest` except its complete-cycle method | Core: stale writes, receipt/rest safety and the direct-fixture-write guard |
| `ParticipationLifecycleRegressionTest`, `ParticipationLocalEpochTest`, `ParticipationRecoveryTest`, `ParticipationSourceEquivalenceTest`, `ParticipationStaggeredEpochTest`, `ParticipationTragedyLifecycleTest`, `ParticipationVisibilityLifecycleTest` | Integration: complete authored HTTP journeys with before/after contracts and positive/negative participant actions |
| `ParticipationHttpHarnessTest::test_real_http_actions_reach_an_operational_commit`, `OperationalClosurePathTest` | Integration: complete production journeys and real Closure calculations |
| `HorizonProofCycleTest`, `FullEvidenceFlowTest` | Integration: deterministic service-level cycle composition, including shared evidence across three participants |
| `ParticipantActionContractTest` exhaustive execution provider and Rest round-trip | Integration: all relevant phase/state transport/validation/execution combinations, including rejection of well-formed unavailable actions |
| `ParticipantActionContractTest` static transport and reported union-error regression | Core: focused schema regression checks |
| `ParticipantWakeTest`, `OptimisticParticipationTest`, `ReverbTransportTest` | Core: worker/retry contracts and one real websocket transport smoke test |
| Migration tests | Core: schema/migration correctness |
| `ParticipationPerformanceTest` and existing method-level `query` groups | Query: reference comparisons, bounded queries, hydration and 500/1,000-node historical growth |
| `HorizonProofConcurrencyTest` and existing method-level `load` groups in `TransactionLockOrderTest` | Load: independent PostgreSQL workers, lock waits and competing structural writes |

Use method-level groups in mixed classes. Keep `integration`, `query` and `load`
mutually exclusive. New short API/invariant checks default to Core; complete
multi-phase lifecycle journeys belong to Integration and need focused Core
regressions for their critical rules. Core must not acquire long setup journeys
when a small positioned fixture proves the intended invariant. Integration's HTTP
harness retains its real production requests and direct-fixture-write prohibition.
It starts each independent scenario from a freshly migrated schema to avoid
PostgreSQL physical/statistical state left by preceding rolled-back journeys.

The Load suite proves bounded concurrency scenarios; it is not yet a sustained
many-participant, memory, latency or websocket-pressure soak. Full Domain replay
minimization remains stopped. See [constitutional-integration.md](constitutional-integration.md)
for the historical acceptance map and [ci-runtime-investigation.md](ci-runtime-investigation.md)
for the Part 2 measurements, coverage preservation and completed validation.

## Inspecting selection and timing

```bash
composer test:core -- --list-tests
composer test:integration -- --list-tests
composer test:query -- --list-tests
composer test:load -- --list-tests
composer test:all -- --list-tests
python3 scripts/ci/run-suite.py core --shards 5 --shard 1 --audit
```

The Python command executes the selected shard after auditing discovery. Shards
are built from PHPUnit's current class inventory; historical class durations are
scheduling weights, not a test allowlist. New classes are included automatically.
The audit checks that the union of the four suites equals default discovery and
that no case has multiple owners. Each runner verifies executed case counts and
rejects skips. Normal Composer commands remain serial and support `--filter`.

With PHPUnit 12, repeat `--exclude-group` for separate groups; the existing Composer
commands carry the selection flags so local commands and CI agree.
