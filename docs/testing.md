# Test suites and Actions

Core is the normal merge gate. It includes unit tests, ordinary feature and API
contracts, and essential substrate invariants. Tests are not moved out merely
because they take several seconds. An untagged test belongs to Core by default.

## Commands and workflows

Use the existing testing database configuration. CI uses PHP 8.5, PostgreSQL 18,
and Node 24. Run `npm ci` before Core or Full Integration so the real websocket
delivery smoke test has its client dependencies. Clear cached configuration with
`php artisan config:clear` when switching environments.

| Suite | Command | Contents | GitHub Actions trigger |
| --- | --- | --- | --- |
| Core | `composer test` or `composer test:core` | All PHP tests except `integration-full`, `query`, and `load`; initially 579 cases | Every PR, pushes to `main`/`production`, or manual **Core** run |
| Full Integration | `composer test:integration-full` | Core plus broader deterministic cycle coverage; initially 581 cases | Push to `nova/restore-substrate-motor` or `ci/integration/**`, or manual **Full Integration** run |
| Query | `composer test:query` | Query, topology, and population-growth budgets; initially 16 cases | Manual **Query Regression** run or explicit push to `ci/query/**` |
| Load | `composer test:load` | Independent PostgreSQL workers, lock ordering, competing writers; initially 22 cases | Manual **Load / Concurrency** run or explicit push to `ci/load/**` |
| All PHP | `composer test:all` | Every PHP test, including all expensive groups; initially 619 cases | Run locally, or run Full Integration, Query Regression, and Load / Concurrency at the same commit |

Counts include data-provider cases. Full Integration deliberately includes Core;
the other suites do not overlap. The underlying `phpunit.xml` still discovers all
tests: unqualified `php artisan test` continues to run everything. The old
`performance` group remains as a compatibility alias on
`ParticipationPerformanceTest`; use `query` for the complete query suite.

Core and Full Integration also run `npm run build` (including TypeScript checks)
and `npm run test:ui`. Query and Load do not install frontend dependencies.
All four workflows share `php-tests.yml` for the same database, PHP extensions,
dependency caching, SQL profiling, and statement timeout. Core and Full Integration
have a 30-minute job ceiling; Query and Load retain their 20-minute ceiling.
Each uploads JUnit results even when PHP tests fail.
The named Composer test commands disable Composer's five-minute process timeout
locally as well; GitHub Actions still enforces the suite's job ceiling.

Normal feature branches run Core when their PR opens or changes. There are no
path filters. The dedicated `ci/*` namespaces are explicit requests for extra
validation, not ordinary development branches. For example, to validate an exact
candidate before these new workflows exist on the default branch:

```bash
git push origin HEAD:refs/heads/ci/integration/my-change
git push origin HEAD:refs/heads/ci/query/my-change
git push origin HEAD:refs/heads/ci/load/my-change
```

Once the workflows are on the default branch, select the workflow in Actions,
choose **Run workflow**, and select the candidate branch. With GitHub CLI:

```bash
gh workflow run integration-full.yml --ref my-branch
gh workflow run query.yml --ref my-branch
gh workflow run load.yml --ref my-branch
```

Run Full Integration for substantive substrate changes. Run Query when changing
projections, working sets, queries, topology traversal, or context population
growth. Run Load when changing transactions, locks, optimistic revalidation,
worker coordination, receipts, or competing writes. Validate all three on a
release/integration candidate. Expensive workflows are not scheduled implicitly.
If repository rules require checks by name, the normal PR check is
`core / PHP 8.5 / PostgreSQL 18`; rules referring to the old matrix job names need
an explicit update when this CI change is adopted.

## Existing coverage placement

| Tests | Placement and reason |
| --- | --- |
| `HorizonProofCycleTest`, `FullEvidenceFlowTest` | `integration-full`: broader deterministic service-level cycle journeys, including shared evidence across three participants |
| `ParticipationPerformanceTest` | `query`: reference comparisons, query budgets, branch growth, and 1,000-node historical population |
| `PropositionReadTest::test_500_node_graph_has_a_bounded_read_only_query_budget` | `query`: 500-node read budget; other API/read contracts remain Core |
| `PotentialProjectionTest::test_rest_recovery_queries_do_not_grow_with_unrelated_historical_propositions` | `query`: historical-population scaling; ordinary projection, retry, and durable-work contracts remain Core |
| `ParticipantContextTest::test_dense_instrument_and_commitment_contexts_fit_the_same_budget`, `test_ten_to_five_hundred_unrelated_horizon_nodes_cannot_grow_inference` | `query`: dense packet budgets and 500-node context growth; packet/schema correctness remains Core |
| `ExhaustionTest::test_horizon_proof_does_not_grow_with_unrelated_historical_terrain`, `HorizonFailureInvariantTest::test_failure_inspection_is_read_only_and_does_not_scan_unrelated_history` | `query`: 1,000-node historical-terrain checks; all other exhaustion/failure contracts remain Core |
| `HorizonProofConcurrencyTest` | `load`: independent PostgreSQL processes competing for structural writes |
| `TransactionLockOrderTest` except `test_fresh_admissibility_rejects_a_previously_valid_schema_as_a_retryable_conflict` | `load`: real lock waits and independent writers; the deterministic stale-schema rejection remains Core |
| `OperationalClosurePathTest` | Core despite its cost: the real Closure calculation and commitment smoke path protect an essential invariant |
| `ParticipantActionContractTest`, `ParticipantWakeTest`, `OptimisticParticipationTest`, deterministic recovery/epoch/receipt/exhaustion tests | Core: schema/dispatcher contracts, wake/retry/queue behavior, and substrate correctness |
| `ReverbTransportTest` | Core: one real websocket delivery is a transport smoke test, not sustained websocket pressure |
| `ReceiptContextMigrationTest` | Core: migration correctness; no longer redundantly repeated in the query job |

This separation preserves all existing fixtures, assertions, and test bodies.
It does not endorse the existing recovery semantics or turn service-level cycle
fixtures into HTTP lifecycle coverage. Those corrections need their own red
regressions and review.

New correctness-critical HTTP lifecycle tests should normally remain in Core
without an expensive-suite group. Stage 2's seven tests use the
`http-participation` group for focused runs; ordinary Core still includes them.
See [participation-http-harness.md](participation-http-harness.md) for their
expected red regressions and HTTP-only fixture discipline.
Stage 3 adds one more HTTP case plus nine provenance/migration cases. Its
expected discovery counts are Core 596, Full Integration 598, Query 16, Load 22,
and All PHP 636. Only the successful-route Bias revisit is intentionally red;
the two epoch collisions and the repeated local reopening journey must pass.
See [local-exploration-epochs.md](local-exploration-epochs.md).
Use method-level groups in mixed classes. Keep `integration-full`,
`query`, and `load` mutually exclusive, and do not use runtime alone to choose.
The Load suite currently proves bounded concurrency scenarios; it does not yet
provide a sustained many-participant, memory, latency, or websocket-pressure soak.

## Checking selection without running scenarios

Stage 6 adds the `constitutional` selection over essential HTTP invariants.
The single- and multiple-participant Domain round trips are still under
construction; this selection is not yet complete Stage 6 evidence.
Existing suite placement is unchanged.
Use `composer test:constitutional` for the essential selection, or
`composer test:integration-full -- --group=constitutional` for all constitutional
cases. See [the acceptance map](constitutional-integration.md) for each Epic
scenario, its API evidence and the limits of controlled primitive fixtures.

Stage 5's cancelled Core/Full runs are incomplete; its unchanged Tragedy journey
subsequently passed independently on PostgreSQL and was accepted as the missing
semantic evidence. The final Epic checkpoint still requires a deliberate complete
all-suite result with an appropriate timeout. Targeted passes do not imply that
a cancelled aggregate run completed.

Composer passes extra options through to PHPUnit:

```bash
composer test:core -- --list-tests
composer test:integration-full -- --list-tests
composer test:query -- --list-tests
composer test:load -- --list-tests
composer test:all -- --list-tests
```

With PHPUnit 12, repeat `--exclude-group` for separate groups. A comma-separated
value is one literal group name and would silently leave expensive tests in Core.
The Composer scripts carry the selection flags so local runs and CI agree.
