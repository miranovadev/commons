# Test suites and Actions

Core is the normal merge gate. It includes unit tests, ordinary feature and API
contracts, and essential substrate invariants. Tests are not moved out merely
because they take several seconds. An untagged test in the discovered Unit/Feature directories belongs to Core by default.

## Commands and workflows

Use the existing testing database configuration. CI uses PHP 8.5, PostgreSQL 18,
and Node 24. Run `npm ci` before Core or Full Integration so the real websocket
delivery smoke test has its client dependencies. Clear cached configuration with
`php artisan config:clear` when switching environments.

| Suite | Command | Contents | GitHub Actions trigger |
| --- | --- | --- | --- |
| Core | `composer test` or `composer test:core` | All PHP tests except `integration-full`, `query`, and `load`; 617 cases at the focused Stage 6 checkpoint | Every PR, pushes to `main`/`production`, or manual **Core** run |
| Full Integration | `composer test:integration-full` | Core plus broader deterministic cycle coverage; 619 cases at the focused Stage 6 checkpoint | Push to `nova/restore-substrate-motor` or `ci/integration/**`, or manual **Full Integration** run |
| Query | `composer test:query` | Query, topology, and population-growth budgets; 16 cases | Manual **Query Regression** run or explicit push to `ci/query/**` |
| Load | `composer test:load` | Independent PostgreSQL workers, lock ordering, competing writers; 22 cases | Manual **Load / Concurrency** run or explicit push to `ci/load/**` |
| All PHP | `composer test:all` | Every mandatory PHP test, including Query/Load; 657 unique cases at focused Stage 6 | Run locally, or run Full Integration, Query Regression, and Load / Concurrency at the same commit |
| Constitutional | `composer test:constitutional` | 13 focused production-path API invariant cases, all in Core | Selection over Core; no extra automatic job |
| Manual Domain replay | `composer test:replay-domain` | Two preserved complete natural journeys, outside mandatory discovery | Explicit manual invocation only |

Counts include data-provider cases. Full Integration deliberately includes Core;
Query and Load do not overlap with it. The underlying `phpunit.xml` discovers
`tests/Unit` and `tests/Feature`: unqualified `php artisan test` runs every mandatory
case. `tests/Replay` is deliberately outside discovery under the revised Stage 6
acceptance strategy. Its two full Domain journeys retain their complete assertions
and unchanged scripts for explicit manual/reference/performance use. The old
`performance` group remains as a compatibility alias on
`ParticipationPerformanceTest`; use `query` for the complete query suite.

Core and Full Integration also run `npm run build` (including TypeScript checks)
and `npm run test:ui`. Query and Load do not install frontend dependencies.
All four workflows share `php-tests.yml` for the same database, PHP extensions,
dependency caching, SQL profiling, and statement timeout. Core and Full Integration
have 60-minute and 90-minute job ceilings respectively for complete aggregate
validation; Query and Load retain their 20-minute ceiling.
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

The original Stage 1 separation preserved the then-existing fixtures and assertions.
Stages 2–5 subsequently corrected recovery and lifecycle contracts; their focused
HTTP proofs remain Core. Service-level cycle fixtures remain separate evidence.

New correctness-critical HTTP lifecycle tests should normally remain in Core
without an expensive-suite group. Stage 2's seven tests use the
`http-participation` group for focused runs; ordinary Core still includes them.
See [participation-http-harness.md](participation-http-harness.md) for their
expected red regressions and HTTP-only fixture discipline.
Historical Stage 3 checkpoint: it added one more HTTP case plus nine provenance/migration cases. Its
expected discovery counts are Core 596, Full Integration 598, Query 16, Load 22,
and All PHP 636. At that checkpoint only the successful-route Bias revisit was intentionally red;
the two epoch collisions and the repeated local reopening journey passed.
Stage 5 subsequently resolved Bias revisit; it is now green with no expected-red exception.
See [local-exploration-epochs.md](local-exploration-epochs.md).
Use method-level groups in mixed classes. Keep `integration-full`,
`query`, and `load` mutually exclusive, and do not use runtime alone to choose.
The Load suite currently proves bounded concurrency scenarios; it does not yet
provide a sustained many-participant, memory, latency, or websocket-pressure soak.

## Checking selection without running scenarios

Stage 6's `constitutional` selection contains focused production-path invariants.
Full Domain-round-trip minimization is stopped. Both complete natural traces and
fixed replays are preserved under `tests/Replay`, outside mandatory CI. Fresh
substrates running naturally will validate the emergent whole lifecycle.
Use `composer test:constitutional` for the 13 focused cases, or
`composer test:replay-domain` for both optional long replays. See
[the acceptance map](constitutional-integration.md) for the API proofs, completed
validation and limits of controlled primitive fixtures. Runtime growth is documented
and explicitly deferred to a separate performance effort.

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
