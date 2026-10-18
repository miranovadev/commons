# C3 validation report

Tested implementation: `9152e91b71f3b7df3ade207a8e37e12f99bd0e9f`.
Base: `2b0612a150dea6e106b72cb9b2227d07593a78e4`.
Any subsequent documentation-only head retains this tested implementation.

## Results

- [C3 workflow](https://github.com/miranovadev/commons/actions/runs/39120409267): **106 focused tests passed, 979 assertions**. Recorded PostgreSQL comparison: **1 passed, 1,391 assertions**. The assertion count decreased from the pre-batching comparison because each SQL statement is checked for mutation and citation projection executes fewer statements.
- [Phase Profile](https://github.com/miranovadev/commons/actions/runs/39120406336): **all 12 jobs passed**, including PostgreSQL/SQLite contracts, fixtures, committed history, lifecycle, Stage 4 and final review.
- [Core](https://github.com/miranovadev/commons/actions/runs/39120406366): **1,191 passed, one confirmed baseline failure, 13,896 assertions** across all five shards. Shards 1/2/4/5 passed (176/248/241/278 tests); shard 3 had 248 passed and the existing ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary failure. Frontend passed. Shard 1 completed successfully on independent retry (job 117656715142) after matrix cancellation. Its separate 14-test/46-assertion preflight also passed and is not included in the suite total.
- [Integration](https://github.com/miranovadev/commons/actions/runs/39120409194): **23 passed, 11 baseline failures**, 18,960 assertions across four shards. All eleven complete failure blocks match the PostgreSQL baseline after removing timestamps, randomized IDs and interleaved Composer output.

Baseline verification: commit `7008675b93d9be8e41f7e3313525d1d240d8779e` tested by [Integration run 39000438290](https://github.com/miranovadev/commons/actions/runs/39000438290) has exactly the same tree as the main base: `381d2ac8fac466a750815301c3417e8d593c62d5`. This comparison uses actual failure output, not just test names. The original main Core attempt also contains the same settlement/emergence assertion at NaturalBoundaryHistory.php:63. Its later rerun failed to download frontend-build and supplies no additional test evidence. See [baseline-comparison.json](evidence/participant-context-c3/baseline-comparison.json).

Exact Integration failures:

- ParticipationHttpHarnessTest::test_real_http_actions_reach_an_operational_commit
- ParticipationLifecycleRegressionTest::test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit
- ParticipationLifecycleRegressionTest::test_distinguish_under_another_committal_does_not_advance_origins_epoch
- ParticipationLocalEpochTest::test_a_third_distinguish_variant_reopens_only_its_own_slot_through_http
- ParticipationRecoveryTest::test_successful_revisit_keeps_its_untraversed_commit_out_of_backward_recovery
- ParticipationSourceEquivalenceTest::test_connection_driven_and_manual_participation_share_the_complete_action_contract
- ParticipationTragedyLifecycleTest::test_natural_bootstrap_crossover_remains_open_and_rest_keeps_the_region_without_catastrophe
- FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_a_lawful_commit, datasets 0 and 1
- NaturalChargeBoundaryLifecycleTest::test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor
- OperationalClosurePathTest::test_receipt_backed_full_cycle_commits_with_real_hysteretic_closure_calculations

## Invariance and scope of evidence

Recorded-state comparisons preserve all Read/Gate/Write arrays and action schemas exactly. Every cognitive Receipt reference resolves once. Full snapshots of all 47 restored domain tables are unchanged after operational plan construction and cognitive projection. Dedicated projection tests snapshot measurements, canonical records, Supersessions, Attestations, personal loads, consumption, terminal records, cycles, phases, ownership and ledger state; query logging rejects mutations. Seven register questions and provider strict response schema retain their contracts.

The actual mocked provider request verifies the exact designation clarification, participant id/callsign/designation, custom instructions, questions, available actions and changed cache routing. Designation strings are not inserted into Proposition statements or Write questions. No paid model sampling was performed and no behavioural improvement is claimed.

CognitiveEvidenceHorizonTest uses isolated recorded graphs for branching, committed ancestry, terminal cutoffs and same/foreign-basin Reconcile. ParticipantReceiptC3Test also dispatches real Origin entry, Variant/Persist, Instrument and Attestation operations to produce a terminal encounter; numerical measurement outputs are explicitly controlled. The real planner removes its closed Write. Conditional direct visibility is tested separately against that same terminal record in Read, Gate and Write: this verifies evidence retention without forcing operational visibility. These are lifecycle components, not proof that the failing natural end-to-end trajectories succeed.

## Actual PHP/PostgreSQL measurements

PHP 8.5.11; PostgreSQL 18.6. Ten recorded cases from the October 18 capture and larger recorded populations, restored into a disposable database. Prose and URLs are redacted with equal compact JSON byte length; callsigns remain unique and URLs remain syntactically valid. These are recorded-state reprojections, not lifecycle replays or historical prompt reconstructions. No provider calls.

Three repetitions per projection, median runtime. Plans are built once and supplied identically to C2/C3. Prompt bytes include instruction and content strings but exclude provider transport and strict response schema. Token counts are bytes/4 estimates over redacted text, not tokenization or billing measurements. Numerical performance is runner-specific. The fixture contains 1,125 Propositions across the populated captures and 576 Receipts.

| Case | Eligible/loaded/bodies C3 | Evidence bytes C3 | Prompt bytes C2 → C3 | Approx. tokens C3 | Queries C2 → C3 | Median ms C2 → C3 | Duplicate associations removed |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Luna current Origin | 4/4/4 | 25719 | 8729 → 34645 | 8662 | 17 → 22 | 15.115 → 23.248 | 0 |
| Nova current first branch | 9/5/5 | 22108 | 25683 → 35551 | 8888 | 12 → 24 | 9.211 → 29.83 | 4 |
| Luna other branch | 7/6/6 | 24266 | 15699 → 34732 | 8683 | 12 → 24 | 8.865 → 26.609 | 1 |
| Luna before portfolio Disperse | 5/4/4 | 15554 | 11999 → 21668 | 5417 | 14 → 27 | 9.817 → 27.794 | 2 |
| Vela current committed entry | 2/1/1 | 1418 | 6401 → 7941 | 1986 | 20 → 28 | 17.132 → 26.27 | 0 |
| Greater-depth Committal | 6/5/5 | 35390 | 43972 → 51049 | 12763 | 23 → 28 | 23.462 → 31.395 | 0 |
| Larger recorded population 01m50kz8gy5jg8e36mbqykje79 | 17/14/14 | 83260 | 23290 → 96857 | 24215 | 12 → 24 | 9.731 → 73.179 | 7 |
| Larger recorded population 01m50kznxd8y46rf7xhn1t954m | 50/38/38 | 186659 | 16921 → 203949 | 50988 | 50 → 29 | 347.342 → 377.866 | 24 |
| Larger recorded population 01m53kyyvav7ftb9q7fpjjrnwy | 71/46/45 | 223589 | 12387 → 240847 | 60212 | 12 → 24 | 9.303 → 197.104 | 16 |
| Larger recorded population 01m53kz9cs3mg8zzvkyvhdke3e | 70/39/37 | 162975 | 13063 → 179341 | 44836 | 12 → 24 | 9.566 → 245.209 | 8 |

Every measured packet has zero duplicate catalogue entries. Raw aggregate results, separate plan timings and source digest are in [performance-9152e91.json](evidence/participant-context-c3/performance-9152e91.json).

The citation-heavy case previously executed 62 queries and took 546.003 ms median; it now executes 29 queries and takes 377.866 ms. Receipt counts, loaded bodies, evidence bytes and prompt bytes are unchanged. Timing is across separate runs and is not a controlled microbenchmark. The cause addressed was construction of the operational ParticipationContext solely to resolve citation content. Cognitive citation resolution now uses two batched subject queries and the same evidence formatter, with same-substrate filtering. The parity test covers Proposition and Attestation targets, preserved rationale, missing subjects and foreign subjects. [Pre-batching measurements](evidence/participant-context-c3/performance-7091ef8.json) remain available.

## Limitations

Context growth is substantial and intentional: maximum sampled prompt 240,847 bytes (approximately 60,212 tokens by bytes/4). There are no caps, rankings, summaries or automatic unloads. Loaded count can exceed body count for scoped memory or unavailable evidence.

The resolver loads substrate topology once before bounding reachability and calculates authoritative terminal closure once per projection. This avoids per-Receipt graph traversal, but topology hydration still scales with the substrate. Canonical association projection also filters the batched Receipt population per selected source. Those implementation costs remain; the remaining runtime has not been attributed with a CPU profiler. No performance target has changed the agreed selection semantics.

Natural lifecycle paths named in the baseline failures remain unvalidated. Local PHP/PostgreSQL execution was unavailable in this workspace; runtime evidence comes from GitHub Actions. No production records were changed.

Unresolved basin ownership selects no additional horizon sources: no governor is inferred. Independently visible propositions retain their direct evidence. The incomplete-record test constructs missing ownership initially and never mutates established Cycle ownership.
