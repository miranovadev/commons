# Stage B: forward generation vs traversal validation

PR [#280](https://github.com/miranovadev/commons/pull/280). Tested correction: `0172cf92abfe9b66534345c0bb40abbafb1e5661`. Starting PR checkpoint: `80316ea7a24ceb510263dba1cba35d24894d50ee` (application/tests previously validated at `b6e701d2c70be8adfeeed3685f3df216435091e7`). Current main was rechecked and remains `eeee6a6d81c4a32f605624f4804775f46af17bf4`.

This report supersedes the [reconsideration correction checkpoint](evidence/participant-exploration-generation/reconsideration-checkpoint.md). That checkpoint restored natural Commit/recovery journeys but still counted Traverse in the shared head-opportunity predicate. A later documentation-only commit records these final results. Fresh database only; no production cook, deployment or merge.

## Final correction

| Category | Operations | Current contract |
| --- | --- | --- |
| Forward generation | Variant, Reconcile, Reclassify, Commit | New authorized active structure or newly lawful generation reopens the relevant head |
| Forward movement | Traverse | Navigation remains lawful; availability alone never reopens exploration |
| Reconsideration | Attestation, Revise | Remains independently lawful; resulting generating eligibility is re-evaluated |

`ContinuationOpportunityService::nonVariantGenerationAvailable` explicitly checks base Reconcile, Reclassify and Commit opportunities. It replaces the ambiguous `advancing` predicate. Fresh Variant stays outside its own prerequisites; existing reachable Variants remain part of active-head discovery. `ParticipantExplorationService::canGenerateFromHead` and the qualified-Rest witness share this predicate without recursive planning.

Rest planning now permits forward movement alongside a qualified completed encounter. Traversal destinations participate in the existing-target check: an unexplored reachable head still prevents an ancestor from claiming completion. A lawful homologous Order Traverse can coexist with a tested local dead end without turning foreign navigation into local causal ancestry. Unrecorded arrival, missing judgment/evidence, pending execution, stale/rolled-back actions and voluntary Rest still cannot manufacture Exploration Events.

No traversal authority, personal destination filter, arrival path, Region/Horizon boundary, Commit traversal rule, observable calculator, Cadence, Receipt authority, Instrument/Preparation allowance, Commit threshold or terminal lifecycle rule changed. Historical events remain immutable. No epoch, quota or shared spentness was introduced.

## Validation against the checkpoint

**No new regressions remain. Natural Commit and recovery retain the passing checkpoint, and all remaining failures match established main failures. The correction is ready for Claire’s review; it is not merged.** The [CI inventory](evidence/participant-exploration-generation/ci-summary.json) records exact jobs, counts and runtimes.

| Check | Current correction | Previous checkpoint | Evidence |
| --- | --- | --- | --- |
| Personal exploration / homologous navigation | **37 passed, 269 assertions** | New navigation/Rest regression and stronger destination/branch assertions | [Case inventory](evidence/participant-exploration-generation/focused-summary.json) |
| Core / PostgreSQL | **1,503 passed; 1 baseline failure; 15,511 assertions** | 1,502 passed; 1 baseline failure; 15,491 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40179542937) |
| SQLite migrations | **14 passed, 46 assertions** | 14 passed, 46 assertions | Core shard 1 |
| Integration | **31 passed; 3 baseline failures; all 34 cases executed, no skips; 7,194 assertions** | 31 passed; 3 baseline failures; 34 cases; 7,194 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40179547734), [inventory](evidence/participant-exploration-generation/integration-summary.json) |
| Phase Profile | **All 12 jobs passed** | All 12 jobs passed | [Run](https://github.com/miranovadev/commons/actions/runs/40179542901) |
| C3 Context | **107 tests / 1,027 assertions and recorded comparison / 1,390 assertions passed** | 107 tests / 1,027 assertions; recorded comparison / 1,390 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40179547671) |
| Frontend | **TypeScript/Vite and all 148 UI tests passed** | TypeScript/Vite and 148 UI tests passed | Core frontend job |
| Inspector | Passed | Passed | [Run](https://github.com/miranovadev/commons/actions/runs/40179547712) |
| Query | **13 passed; 9 baseline failures; 953 assertions** | 13 passed; 9 baseline failures; 953 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40179545369) |
| Load / PostgreSQL concurrency | **18 passed; 8 baseline failures; 178 assertions** | 18 passed; 8 baseline failures; 178 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40179545378) |

All nine requested natural Commit/recovery classes pass: `CommitSourceHistoryLifecycleTest`, `CommitDescendantHistoryLifecycleTest`, `ParticipationLifecycleRegressionTest`, `ParticipationRecoveryTest`, `ParticipationSourceEquivalenceTest`, `HorizonProofCycleTest`, `OperationalClosurePathTest`, `ParticipationHttpHarnessTest` and `FullEvidenceFlowTest`. `ParticipationTragedyLifecycleTest` also passes. Integration's complete inventory, failure identities and assertion count exactly match the checkpoint.

The 77-operation natural Commit driver, fixed HTTP transcript, causal observer route and exact Tragedy assertions are unchanged by this final correction. No synthetic Exploration Events were inserted into lifecycle journeys. The [previous lifecycle evidence](evidence/participant-exploration-forward/README.md) remains applicable. Query and Load were rerun at the same correction SHA as all application validation; no ceiling or assertion was relaxed.

## Established main failures

Main's SHA is unchanged from the independent baseline verification. The complete prior evidence and counts are preserved in the checkpoint report.

- **Core:** `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` expects Settlement but reaches Emergence at `NaturalBoundaryHistory.php:63`. [Main run](https://github.com/miranovadev/commons/actions/runs/40166689571).
- **Integration:** both data sets of `FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_a_lawful_commit` exhaust the unchanged aligned-driver budget; `NaturalChargeBoundaryLifecycleTest::test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor` has the same Settlement/Emergence assertion. The independent main selection ran 10 cases: 7 passed, 3 failed, 2,218 assertions. [Inventory](evidence/participant-exploration-forward/baseline-comparison.json).
- **Query:** one dense Order payload exceeds the unchanged 45,000-byte ceiling; eight performance fixtures lack authorized prospective Closure. [Main run](https://github.com/miranovadev/commons/actions/runs/40166689511).
- **Load:** missing Closure fixtures, duplicate identical-transition Receipt state, a worker failing to reach its intended lock, and a graph/detail direction expectation account for the existing eight failures. [Main run](https://github.com/miranovadev/commons/actions/runs/40166689535).

The current Query and Load failure headings match both the previous checkpoint and the independent main runs. The dense Order payload remains **54,511 bytes**, identical to the checkpoint (main: 55,062), above the unchanged 45,000-byte ceiling. Integration's three exact case identities and Core's Charge-boundary assertion are unchanged. See the [baseline comparisons](evidence/participant-exploration-generation/comparison.json). The additional validation-branch Core run has the same sole baseline failure; its duplicate Phase Profile run also passes all jobs.

## Focused acceptance coverage

| Final handoff cases | Evidence |
| --- | --- |
| 1: Traverse-only explored head | New `HomologousOrderTest::test_navigation_only_head_can_complete_exploration_without_losing_homologous_traverse`: actual recorded Bias arrival, real qualified Rest/event publication, still-lawful homologous Traverse, explored status in cold and hydrated evaluation |
| 2: Traverse back to explored destination | Personal completed-Rest test proves base Traverse remains lawful while personal traversal excludes the explored destination and the history remains applicable |
| 3–4: unexplored branches and new active descendants | Branch test proves lawful Traverse reaches an unexplored sibling, Rest/fresh Variant remain unavailable until that frontier is explored, and the original head stays explored; full frontier precedes display limits |
| 5–6: reconsideration without generation | Existing reversal/unrelated-update and actual Revise tests preserve explored status, independent action selection and immutable history |
| 7: reconsideration enables generation | Actual Attestation enables Bias Reclassify; controlled Correspondence enables Reconcile through recorded personal arrival; isolated historical input plus actual Attestation enables Commit grounding |
| 8: qualified Rest and safeguards | New navigation/Rest test plus unproven-arrival, untested, evidence-shortage, pending-execution, stale, rollback, frozen-arrival, idempotence and immutable-event tests |
| 9–10: noncircular Variant and independent histories | Personal Variant test evaluates all existing heads before fresh generation, preserves old events, blocks another Variant after a new head, and retains the second Participant's independent opportunities |
| 11: movement, Receipt and structural authority | Navigation/Rest test asserts unchanged Proposition, Supersession and Receipt-consumption counts; existing homologous rejection tests and natural Commit/Receipt/source/terminal suites remain intact |
| 12: human/autonomous agreement | New navigation/Rest schema is identical in workspace and autonomous plans; fixed HTTP/provider schema equivalence and prepared contribution contracts remain intact |

The new homologous test explicitly uses authored component geometry and isolated Closure output, then real arrival and Rest operations. It is not a natural Commit proof. Existing isolated `ExplorationHistory` inputs and controlled observable fixtures remain labelled; natural lifecycle validation uses real operations and calculators.

## Performance and limits

The local focused frontier check remains **21 queries at 2 and 8 heads after context hydration**, then **zero** extra queries on a warm repeat. No persistent cache or epoch was introduced. These checks do not establish a worst-case bound for large personal histories across distinct foreign scopes.

C3's ten recorded operational plans use **71–77 queries**, compared with **71–76** at the checkpoint. Eight cases add one query and two are unchanged; the [per-case comparison](evidence/participant-exploration-generation/comparison.json) records this explicitly. Those recorded snapshots contain no reconstructed personal history and are cognitive projections, not lifecycle replays. Navigation can now reach the qualified-Rest check rather than excluding Rest immediately.

The slowest current Core PHP shard took **445.72 seconds (7m26s)**, versus 568.06 seconds at the checkpoint; it still exceeds the five-minute target. The longest Integration shard reports **429.73 seconds** including its runner wrapper. Query took **615.48 seconds** versus 538.38 seconds previously, and Load took **83.22 seconds**. These are observed CI execution times, not controlled performance benchmarks or proof of a speed improvement/regression.

The existing broad Query failures and dense payload overrun remain performance limitations. No comparison population was truncated, query/payload ceiling raised, or workflow timeout increased.

Changed PHP passes Pint; whitespace checks pass. All natural assertions, workflow limits, mathematical calculators and query/payload ceilings are retained.
