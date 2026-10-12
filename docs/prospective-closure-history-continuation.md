# Stage 3: authorised history continuation

Starting checkpoint: reporting head `74e6adc`, tested at `1115837`: **895 Core passes / 248 failures (241 missing-history errors)**; **20 Integration passes / 12 failures**. The prior [lawful-fixture report](prospective-closure-lawful-fixtures.md), its 89-action Commit transcript and setup-path inventory remain authoritative evidence. Approved measurement semantics were not reconsidered.

## Construction and preserved invariants

### Action-exhaustion fixtures

`GovernedExpressionFixture` now provides a real, evidence-first Persist-to-Constrain development: actual Variant birth, Instrument, Attestation and Phase progression. Its `writePersist` step is also reusable before evidence, while the actual Persist Phase remains open. The existing Variant/Reclassify freshness test consumes the shared path with its original assertions retained.

`TragedyTest` tests exclusive Rest/action exhaustion; it does not itself require a natural Charge-zero event for every dataset. The repaired datasets use real constitutional entry, Variant birth and, where needed, grounded Constrain Reclassify followed by Disperse. They preserve the original schema, autonomous prompt, forbidden Rest, absence of traversal, unchanged position and no extra causal-write assertions. They do not manufacture a catastrophe or ordinary Committal by changing a Proposition tier.

The five original Tragedy dataset identities are:

- `Distinguish Variant without traversal`;
- `Persist Variant without traversal`;
- `Persist Instrument without traversal`;
- `Disperse first Variant`;
- `Constrain owned Gate Reclassify`.

These recover the original action-exhaustion assertions. The remaining nine datasets are still independently fixture-blocked; this is not complete Tragedy lifecycle coverage.

### Search contracts independent of historical topology

`PropositionSearchTest::test_schema_and_domain_apply_defaults_and_reject_invalid_limits` now uses an actual governed expression. Input normalization and invalid limits do not require a fabricated foreign Horizon or exact nine-hop ancestry.

`test_kind_migration_refuses_to_discard_recorded_proposition_search_history` records a real search against the governed expression, then runs the same migration rejection assertion. An empty observed collection is still an actual recorded search; no historical observation is invented. The exact-distance historical fixture remains unchanged.

### Separate post-Commit history coverage

`CommitDescendantHistoryLifecycleTest::test_real_commit_descendant_resets_evidence_and_searches_selected_history_without_authority` reuses the validated single-Commit operation pattern, then makes a genuine new Distinguish Variant. Its acceptance criteria are explicit:

- The Commit has a genuine expressive source and one committed predecessor (Origin); its structural homology coordinate is itself at distance zero, while Origin is not at equal committed depth.
- The new expressive is governed by the actual Commit, with a real Variant edge, immutable birth observation and Settlement creating Domain. Its committed ancestry is exactly Commit then Origin.
- Attestations, Receipts and participation source identity reset at the Variant. Its one touch comes from actual progression into Persist, not inherited history.
- Search traverses the selected causal lineage behind the actual Commit boundary. Bias, Mediate, local Disperse, Constrain, Persist and Distinguish are at exact distances **2,3,4,5,6,7**. IDs come from the recorded final traversal, not the search result or an invented graph. Reconcile's other operand and complementary-Domain Origin are excluded.
- A real Persist Write is created before instrumentation. Its search records the same six historical IDs at distances **3,4,5,6,7,8**. The memory Receipt remains unsigned/non-reinforcing, does not ground Commit, creates no causal edge and does not move the participant.
- Original Commit evidence references, full authorised Closure and frozen snapshot remain unchanged through these descendant operations.

This is separately named lawful coverage for parts of Commit participation, Search, CommittalClosure and structural ancestry. It does **not** recover the original manufactured multi-Commit, foreign-Horizon, arbitrary-distance or transitive direct Committal-to-Committal assertions. Those identities remain in the inventory.

An intermediate failure caught instrumentation targeting the preceding Gate instead of the current Persist Write. It was corrected by constructing the real Write before the action, not by bypassing operand authority.

## Attempted second Commit: specific limit

The first continuation at `05e1c8a` reached the known genuine Commit, then continued the same participant through **101 additional advertised actions**. Neither database reached a second Commit: SQLite **75.36s**, PostgreSQL **429.65s**, 198 assertions each including the first Commit's checks. These are failed construction attempts, not regression validation or evidence of an impossible second Commit.

The continuation develops expressions, rests at Constrain/Mediate, and returns to older recorded history. Its last Commit check has Extent **-0.1285715 Negative**, Rhythm **-0.25 Negative**, and coherent Closure **-0.1892858**. The actual rejection is `source_already_committed`; `active_region_candidate` and `write_opportunity` are false, while the other requirements are true. Closure was not the final blocker.

The exact attempted test and both actual traces are retained in `evidence/prospective-closure-history-continuation`. The unproven expectation is withdrawn from normal test discovery and retained explicitly as an attempted scenario, not silently renamed into a passing second-Commit test. No original test was removed or skipped.

`HorizonCycleFixture` factors its existing advertised-action loop into `driveAuthorisedCycle`; its original defaults and assertions remain intact. This is a continuation mechanism, **not a validated arbitrary-depth or multi-Commit fixture builder**. No requested Domain/depth parameter or forced transition was added. Further histories need an independently authored operation policy that actually reaches the required state; increasing the action budget or copying the first snapshot would not establish it.

## Validation

Tested code head: **`a6a809fa260b01366065f02e021a744f02688169`**. The reporting commit adds documentation/evidence only; these results refer to the tested code head.

| Complete suite | Checkpoint | Final | Assertions | Workflow wall time |
| --- | --- | --- | ---: | --- |
| [Core](https://github.com/miranovadev/commons/actions/runs/38302202439) | 895 pass / 248 fail | **902 pass / 241 fail** | 10,865 | **9m11s** |
| [Integration](https://github.com/miranovadev/commons/actions/runs/38302208404) | 20 pass / 12 fail | **21 pass / 12 fail** | 17,528 | **7m18s** |

All **895 previously passing Core identities** remain passing. Seven original masked cases now execute and pass; no original assertion failure was newly exposed, no previous pass failed and no Core identity was added or removed. The original 554 masked population is now **315 passing / five exposed failures / 234 still masked**. Stage 2 remains the recorded 1,117 pass / eight fail from 1,125 cases; the current count is not a substitute for comparing those identities.

All **32 original Integration outcomes** remain unchanged. The new separately named scenario adds one passing case. Exact discovered/selected/executed multiset accounting holds for **1,143 Core and 33 Integration cases**, with zero skips. No historical test was removed. The unsuccessful new second-Commit experiment is retained outside discovery with its explicit failed result; it was never established coverage.

[Final PostgreSQL/SQLite matrix](https://github.com/miranovadev/commons/actions/runs/38302202339):

| Selection | PostgreSQL | SQLite | Assertions PG / SQLite | PHP seconds PG / SQLite |
| --- | --- | --- | ---: | ---: |
| Phase Profile C1 | 48 pass | 48 pass | 300 / 300 | 47.45 / 20.56 |
| Prospective Closure | 14 pass | 14 pass | 85 / 85 | 10.64 / 4.79 |
| Observable/ledger | 98 pass / 42 fail | 98 pass / 42 fail | 1,359 / 1,359 | 96.31 / 34.45 |
| Expanded fixture selection | 156 pass / 28 fail | 154 pass / 28 fail / two existing PG-only skips | 1,845 / 1,839 | 225.63 / 49.99 |
| Original genuine Commit | 1 pass | 1 pass | 107 / 107 | 118.79 / 29.07 |
| New Commit descendant/search | 1 pass | 1 pass | 129 / 129 | 90.74 / 15.36 |

The new scenario also passes within complete Integration (70.86s). The two SQLite skips are existing PostgreSQL-specific checks in the expanded selection, not newly skipped coverage. Focused selections overlap the complete suites and must not be added to their totals. The separate Core SQLite migration selection remains **12 pass / six CommittalClosure missing-history failures**, 45 assertions, 3.60s.

Scoped Pint (28 files), whitespace, frontend/UI and [Inspector browser](https://github.com/miranovadev/commons/actions/runs/38302208011) pass. The focused jobs retain their eight-minute maximum; Core retains its two-minute frontend/eight-minute PHP job limits; Integration retains four isolated PostgreSQL shards and its 20-minute maximum. No CI timeout or coverage accounting rule changed.

Checkpoint workflow times were Core 9m19s / Integration 9m42s. Final times are 8s / 2m24s shorter, respectively, despite the additional Integration case. Runner variation prevents attributing this to a production speedup. Core remains above its preferred five-minute target but below its ten-minute ceiling; Integration is below its preferred fifteen-minute target. Both complete suites reached final summaries.

[Per-test inventories and comparisons](evidence/prospective-closure-history-continuation/README.md) retain original identities, classifications, assertions, runtime, the seven recoveries and the original 118 setup-path groups.

| Remaining category | Core | Integration |
| --- | ---: | ---: |
| A: unchanged Stage 2 failure | 3 | 7 |
| B: approved Stage 3 trajectory/event consequence | 4 | 4 |
| C: confirmed new implementation defect | 0 confirmed | 0 confirmed |
| D: incomplete/unsupported fixture history | 234 | 1 |
| Total | **241** | **12** |

Core A remains ChargeBoundaryTragedy's ordinary unmocked boundary, ChiralityPopulationHistory's real-dispatch replacement and ClosureHysteresis's historical read-only preview. Core B remains the two Reinforcement pole datasets (genuine Cycle-entry changes), ClosureHysteresis negative-alignment boundary and its source-polarity transcript (Extent **0.0714286 Positive**, Rhythm **-0.25 Negative**). No new substantive regression is exposed by this continuation. Four Stage 2 failures remain masked: ObservableService matching lineages, HomologousBias zero-retention and ordinary reclassification, and LineageAttenuation raw calculation.

Integration A remains NaturalChargeBoundaryLifecycle, both ParticipationLifecycleRegression cases, ParticipationLocalEpoch, ParticipationRecovery successful revisit, ParticipationSourceEquivalence and ParticipationTragedyLifecycle. These are unchanged Stage 2 outcomes, including the known C1 transcript incompatibility. Integration B remains both FreshBootstrap polarities, OperationalClosurePath and ParticipationHttpHarness: Extent **0 Negative** / Rhythm **0.1875 Positive**, so those four still do not complete Commit. Integration D remains HomologousMediation's source-less ordinary committed ancestry.

**234 Core missing-history errors still mask substantive assertions. Full Stage 3 regression sign-off remains unavailable.** Zero confirmed new defects does not exclude defects hidden behind those setup failures.

## Remaining boundaries

| Family | Remaining requirement |
| --- | --- |
| CreatesHomologousPositions / HomologousMediation | Multiple genuinely developed branches at the required equal committed depths and canonical Phase/register positions. The original depth test additionally asserts direct Committal sources/Gates and a total of five raw edges, which genuine expressive development cannot preserve. Keep it as historical consumer context; author a separate multi-branch lifecycle scenario. The new ancestry checks establish only one ordinary Commit. |
| laterHorizonFixture and consumers | An actually observed Domain transition, not a requested opposite Domain or copied snapshot. The first Commit and new descendants remain Settlement; the attempted second Commit supplies no later-Horizon evidence. |
| Remaining Tragedy datasets | Real owned/unowned descendants, revision or Mediate counterpart and evidence/exhaustion states at the specified Phase. Some authored setups add parents after birth or mutate an expressive tier. The repaired ordinary action-exhaustion cases do not validate these independent contracts. |
| Remaining CommitParticipationState | Effective replacements and reset variants across further genuine Commit boundaries. The explicit direct Committal-to-Committal transitive scenario cannot be relabelled as a genuine Commit. New descendant coverage proves a fresh Variant reset, not the whole dataset matrix. |
| Remaining PropositionSearch | Original exact distances, foreign-Horizon selection, stale history and changed observation identity. Late-added ancestors cannot be justified by the new six-step selected ancestry scenario. |
| CommittalClosure | Authored comparative intervals, repeated/alternating Domains and twelve-depth query bound need real histories, or explicit isolation as primitive/read-model tests. The retired birth-only/fallback contracts differ from authorised prospective state. New frozen-history assertions do not recover those older numeric assertions. |

These are incomplete authored histories/fixture contracts, not open mathematical questions. No proof is claimed that the required lawful histories cannot exist. No broader production defect is excluded while substantive assertions remain masked.

## Scope

Only tests, focused validation commands and documentation/evidence changed. No production implementation, migration, archived substrate data, measurement, causal selector, admissibility, Receipt authority, movement/recovery, attenuation or canonical preservation changed. The four documented Integration Commit blocks and original ParticipationLocalEpoch transcript remain unchanged. Its third-Variant replacement is still outstanding.

PR #255 remains unmerged for Claire's review. The remaining concern is incomplete fixture coverage, not a request to redesign the approved model.
