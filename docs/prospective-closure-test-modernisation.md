# Stage 3 test-contract modernisation

This supersedes the requirement in the previous fixture reports to preserve obsolete test identities, assertion counts and arbitrary committed graph shapes. Claire authorised a consolidated modernisation after the `82408fc` reporting checkpoint (tested code `a6a809f`: Core 902/241; Integration 21/12). Production semantics remain unchanged.

## Coverage boundary

`component-contract` identifies authored database/read-model inputs and explicit numerical isolation. These tests exercise selectors, projections, visibility, evidence lookup, polarity, ranking, option composition and rejection/atomicity under stated inputs. **They do not establish that an operational history can produce their authored snapshots, Domain changes or committed depths.** No component pass is counted as recovered end-to-end coverage.

`IsolatesClosureOutput` isolates only prospective Closure in the listed component families. It supplies an explicitly labelled neutral numerical result, with no historical population or persisted expressive Closure state. Constitutional initialization and its frozen result remain real. It issues no per-head read queries. Both this double and a nested legacy numerical double reject canonical preservation. New boundary tests verify the double cannot create a successful authorised Commit.

`ParticipantContextFixture::projectedCommittal` explicitly constructs consumer input without calling preservation. It requires the labelled component boundary. This is deliberately distinct from invoking Commit, and supplies no frozen authorised Closure history. The underlying snapshot/edge rows are authored test inputs, not claimed production provenance. Coordinate-only snapshots retain authored creating-Domain memberships for lineage-query inputs, but contain no fabricated direct Commit edge. They are incomplete historical inputs, explicitly unsuitable for empirical Correspondence geometry.

The 34 isolated families are enumerated in `evidence/prospective-closure-component-families.json`. Existing assertions remain except for the explicit consolidations below. HomologousOrder now authors Extent/Rhythm snapshot directions instead of obsolete depth/cadence directions; its zero-retention and coherence assertions remain. Snapshot assembly has explicit isolated interval/reference metadata; those inputs are not claimed observations. Database constraints, ledger/C1 observation code, causal selectors, Chirality, Receipt authority and operation handlers remain production implementations. Passing a component test does not validate Closure-dependent action eligibility or a later Domain lifecycle.

## Consolidations and removals

| Prior contract | Modern coverage | Deliberate limit |
| --- | --- | --- |
| ClosureBirth's 14 Cardinal/lateral/birth-only cases | Two real expressive-birth tests; existing ProspectiveClosureCalculatorTest and ProspectiveClosureStateTest | Old Cardinal centroids and birth-only immutability no longer define production Closure. Historical migration coverage is not represented as prospective lifecycle coverage. |
| CommittalClosure's eight direct-model cases | Four real bootstrap/read-only/missing-state/immutability cases; calculator repeated-Domain cases; batch-history test; genuine Commit and descendant Integration | No manufactured twelve-Commit history or forced Domain alternation. The old archived cadence-backfill scenario is removed, not reported as passing. |
| HomologousPosition direct Committal edge-count assertion | Equal-depth/shared-Origin/coordinate component assertions retained | The shared coordinate builder, later-Horizon projection and Mediate coordinate builder no longer fabricate direct Committal-to-Committal edges. Five such edges are no longer asserted as a lawful history. Genuine Integration separately proves the first actual committed ancestor. |
| CommitParticipationState transitive Committal-to-Committal assertion | Effective evidence and reset component assertions retained; actual Commit inheritance and descendant reset in Integration | No second authorised Commit is claimed. |
| HomologousOrder local Commit success under a numerical double | Local Commit option composition against an opposite population | No canonical success is inferred from controlled eligibility. |
| Three direct-model canonical Chirality/Correspondence/attenuation candidates | Exact source scalar/component/direction preservation in CommitSourceHistoryLifecycleTest and ProspectiveClosureStateTest; descendant immutability in CommitDescendantHistoryLifecycleTest | These are consolidations, not recovered original identities. |
| Nine invalid Tragedy action datasets | Five already-lawful action-exhaustion datasets retained, plus independent existing exhaustion/authority tests | Late-Phase ownership/Revise/Mediate/Order combinations are not newly established end-to-end. Their tier mutation and late-added provenance builders are removed. |
| HomologousMediation's single Integration-labelled authored graph scenario | Consolidated into ordinary Reconcile result comparison and the independent Bias coherence matrix | The intermediate component execution exposes a coherent Bias, contrary to the old assumed outcome. No Mediate-to-inadmissible-Bias end-to-end claim remains; no population is manipulated to force it. |

The old source and results remain in git history and the previous per-test inventories. Exact added, removed, reclassified and retained identities will accompany complete validation. A removed invalid scenario is never counted as a passing assertion.

Additional contract corrections preserve behaviour rather than historical scalar accidents:

- Reinforcement captures authorised Closure **after** each genuine Cycle entry and **before** Receipt loading, then compares the entire state across the load. The former comparison crossed two distinct event boundaries.
- Raw attenuation records the resolved Chirality before disposable projection writes and compares it afterwards, alongside unchanged raw local values. It no longer substitutes the historical constant `-0.5` for the no-reuse invariant (the intermediate run observed `-0.1`).
- Bias reclassification compares the resulting scalar, Orientation and Cadence with ordinary independent calculation; it no longer requires that a particular authored graph produce a Negative direction. The intermediate result was Positive, and no numeric value was changed to force a direction.

The API graph serializer now receives an explicit Closure-state boundary value (0.375), alongside its controlled Correspondence/Chirality. This verifies authorised-state serialization rather than the retired fallback from an ungoverned expressive to Origin Closure. The concurrent-Horizon test now verifies an open Cycle retains its owner across a supplied boundary projection and an actual subsequent expressive contribution; its two forced Commit successes are removed. Simultaneous late Commit across real Domain transitions remains outside demonstrated end-to-end coverage.

## Genuine lifecycle coverage retained

The unmocked 89-action Commit source scenario and its separately named descendant/search scenario retain their operation policy and transcript. The source scenario now also checks every actually executed Reconcile against ordinary calculated Correspondence and both components, checks its actual parents, and requires at least one such operation. They verify actual offered operations, evidence authority, canonical source preservation, fresh descendant state, selected search distances and exclusions. The approved C1 and prospective Closure suites retain their own real observation/service contracts; the calculator's repeated-Domain populations are component arithmetic, not fabricated end-to-end histories.

The four documented Integration directional-disagreement Commit blocks and the historical ParticipationLocalEpoch transcript are unchanged. No additional multi-Commit diagnostic or semantic redesign is undertaken. Complete validation must report their actual results.

## Review limits

The modern suite deliberately stops claiming lifecycle coverage for arbitrary multi-Commit branches, forced later-Horizon transitions and the removed Tragedy combinations. These are coverage limits, not unresolved mathematical decisions. New assertion failures must be reported individually; a labelled component fixture is not permission to change expected values to obtain green results.

Receipt-neutral Chirality and grounding checks now use the real governed-expression fixture instead of developed coordinate-only histories. Two Homeostatic hydration tests explicitly isolate numerical outputs; they measure population/query composition, not full numerical geometry cost. HomologousBias, HomologousMediation, HomologousOrder, AttestationPolarity, ReceiptContext and the open-Cycle boundary-projection case explicitly isolate numerical Correspondence as well as Closure. Their selectors, Chirality, ownership, evidence and atomicity remain exercised. The standalone foreign Cardinal-placement case and the two cross-region geometry assertion blocks are removed: source-less coordinate snapshots cannot prove that geometry. C1 has independent real observation/geometry contracts, but this exact multi-Horizon case remains a coverage gap. The intermediate attempt to omit memberships was rejected by lineage provenance checks and reverted; no production fallback was added.

## Focused validation

Tested code: `661d52ab7d8061595107d3ca0eaa6e6df85e8d12`. [PostgreSQL/SQLite matrix](https://github.com/miranovadev/commons/actions/runs/38312799509).

| Selection | PostgreSQL | SQLite |
| --- | --- | --- |
| C1 Phase Profile | 48 pass / 300 assertions / 47.95s | 48 pass / 300 assertions / 17.00s |
| Prospective Closure | 14 pass / 85 assertions / 10.36s | 14 pass / 85 assertions / 3.52s |
| Observable/ledger and component boundaries | 131 pass / 1,961 assertions / 121.74s | 131 pass / 1,961 assertions / 35.36s |
| Representative fixtures | 172 pass / 3 fail / 2,045 assertions / 335.49s | 170 pass / 3 fail / two existing PostgreSQL-only skips / 2,039 assertions / 36.23s |
| Genuine Commit, including actual Reconcile checks | 1 pass / 112 assertions / 115.92s | 1 pass / 112 assertions / 25.54s |
| Genuine descendant/search | 1 pass / 129 assertions / 119.93s | 1 pass / 129 assertions / 31.37s |

The same three ClosureHysteresis assertions remain in both fixture selections: read-only historical scalar expectation, negative-alignment Domain boundary expectation, and a source-polarity candidate correctly blocked by disagreeing Extent/Rhythm. These selections overlap complete-suite identities. Scoped Pint (32 files) passes on both databases. No skip was introduced; the two SQLite skips already require PostgreSQL-specific behaviour.

## Complete validation and coverage accounting

Both complete suites finished at the tested code head above. No production files, migrations or substrate records changed in this modernisation. The reporting commit changes documentation/evidence only.

| Complete suite | Latest repository checkpoint (`a6a809f`) | Modernised suite | Assertions | Workflow wall time |
| --- | --- | --- | ---: | --- |
| [Core](https://github.com/miranovadev/commons/actions/runs/38312799520) | 902 pass / 241 fail / 1,143 cases | **1,109 pass / 7 fail / 1,116 cases** | 13,151 | **9m13s** |
| [Integration](https://github.com/miranovadev/commons/actions/runs/38312806450) | 21 pass / 12 fail / 33 cases | **21 pass / 11 fail / 32 cases** | 17,533 | **8m22s** |

Discovered, selected and executed identity multisets match exactly for both suites. **Zero complete-suite skips, zero missing-history errors, no timeout.** Core's seven failures consist of six assertion failures and one correctly rejected Commit. Core's separate SQLite migration selection passes all 14 cases / 46 assertions. Frontend build/contracts and [Inspector browser review](https://github.com/miranovadev/commons/actions/runs/38312806442) pass.

The task began from the repository's later 902/241 checkpoint, after the previously reviewed 895/248 checkpoint. Counts changed deliberately; they are not a compatibility claim.

- **891 retained previously passing Core identities still pass.** The other 11 old passes were deliberately consolidated: seven retired ClosureBirth contracts, two old CommittalClosure contracts, and two controlled Commit-success datasets. None is represented as an unchanged passing lifecycle test.
- **203 retained previously failing Core cases now pass:** 201 formerly missing-history cases now execute as explicitly labelled component contracts; both Reinforcement poles now compare Closure across the Receipt event alone, after the genuine Cycle entry.
- **42 Core identities were removed or consolidated; 15 new/renamed contracts pass.** These comprise the documented birth/persistence replacements, component guards and narrowed component contracts. The identity lists retain the old outcomes and Stage 2 comparison.
- The checkpoint's **234 missing-history cases** are now **201 passing component contracts / two exposed baseline assertions / 31 removed or consolidated**. None remains missing-history blocked. Those 201 passes do **not** prove newly constructed committed lifecycles.
- The newly labelled component group contains **339 cases: 336 pass / three fail, across 35 classes** (34 affected families plus the guard class). Other Core cases include pre-existing unit/component tests as well as real service tests; they must not all be relabelled end-to-end.
- All **21 prior Integration passes remain passing**, and the same 11 substantive failures remain. The one invalid authored HomologousMediation Integration scenario is consolidated, not skipped or counted as passing. Its exact Mediate-to-inadmissible-Bias lifecycle remains unproven.

[Durable per-test inventories and coverage lists](evidence/prospective-closure-test-modernisation/) include every current outcome, exact added/removed/recovered identities, checkpoint-masked accounting, focused results and the actual lawful Commit transcript. `summary.json` records the strict coverage check and each shard's measured runtime. The prior checkpoint inventories remain unchanged in `evidence/prospective-closure-history-continuation/`.

## Remaining failures

A means substantively present in the Stage 2 baseline; it does not imply every old expectation is normative. B identifies an approved Stage 3 trajectory/event consequence. No newly exposed implementation defect is confirmed; the remaining failures are not concealed or marked expected failures.

| Category | Core | Integration |
| --- | ---: | ---: |
| A: Stage 2 baseline assertion/trajectory | 5 | 7 |
| B: approved Stage 3 trajectory | 2 | 4 |
| C: confirmed newly exposed production defect | 0 confirmed | 0 confirmed |
| D: remaining fixture/history or execution failure | 0 | 0 |

Exact Core failures:

1. **A — ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary:** expects Settlement, observes Emergence; same baseline mismatch.
2. **A — ChiralityPopulationHistoryTest::test_attestation_and_replacement_are_observed_through_real_dispatch:** expects -0.5, observes -0.1; unchanged.
3. **A — ClosureHysteresisTest::test_read_only_bootstrap_preview_matches_actual_commit_while_field_remains_in_settlement:** expects Correspondence -0.0964505, observes 0.1265986; unchanged.
4. **A — ObservableServiceTest::test_matching_lineages_in_complementary_horizons_align:** previously masked; now reaches the same Stage 2 Correspondence assertion, expected 1, actual 0.5.
5. **A — HomologousBiasTest::test_zero_retains_population_history_and_does_not_mutate_measurements_or_state:** previously masked; same retained Negative/actual Positive population direction mismatch as Stage 2.
6. **B — ClosureHysteresisTest::test_negative_closure_alignment_can_commit_while_corrected_field_remains_in_settlement:** actual Commit crosses a Closure boundary; the old test expects no boundary. Stage 2 already failed later numeric assertions in this case.
7. **B — ClosureHysteresisTest::test_commit_freezes_source_attestation_direction_as_the_next_region_frame:** candidate Extent 0.0714286 Positive / Rhythm -0.25 Negative is correctly inadmissible. Its later source-polarity assertions remain unexecuted.

Exact Integration identities are listed in `integration-remaining-failures.csv`. Category A contains NaturalChargeBoundaryLifecycle, both ParticipationLifecycleRegression cases, ParticipationLocalEpoch, ParticipationRecovery's successful-revisit case, ParticipationSourceEquivalence and ParticipationTragedyLifecycle. Category B contains both FreshBootstrap polarities, OperationalClosurePath and ParticipationHttpHarness. The latter four still encounter **Extent 0 Negative / Rhythm 0.1875 Positive** and **do not complete Commit**. No authored populations, admissibility rules or ParticipationLocalEpoch transcript were changed.

Stage 2 had 1,117 passes / eight failures from 1,125 Core cases. Five of its failures remain substantively identical; its negative-alignment case now meets the approved Stage 3 boundary earlier; raw attenuation and ordinary-result Chirality contracts were modernised to compare their intended invariant rather than an incidental scalar/direction. The latter is explicitly named `test_reclassification_result_uses_real_chirality_independently_of_operand_coherence`; Correspondence remains isolated there. The per-test evidence distinguishes these changes from unchanged passes.

## Genuine lifecycle evidence and performance

The final unmocked source transcript still contains **89 authorised actions and one Reconcile**, now with five additional assertions. The source has Extent **0 with retained Negative**, Rhythm **-0.1875 Negative**, Closure **-0.09375**. All 112 source/Commit assertions and all 129 descendant/search assertions pass on both databases. This establishes the demonstrated first Commit and descendant, not arbitrary further Commits, Domain alternation or the four blocked HTTP/lifecycle scenarios.

The prior complete workflow times were Core **9m11s**, Integration **7m18s**; final times are **9m13s / 8m22s**. Integration remains below the preferred 15 minutes and maximum 20. Core remains below its 10-minute ceiling, above its preferred five-minute target. Its slowest final job used approximately 7m45s of the eight-minute job budget, so runner variation remains a practical concern.

An intermediate Core run (`38309437394`) exceeded a shard's eight-minute limit: PHP took 494.59s. Newly executing assertions had invalidated the old timing estimates. The existing five-shard class weights were updated from measured runs; no timeout, isolation, discovery, accounting or shard-count rule was weakened. The final PHP shard runtimes are 239.45 / 306.34 / 279.13 / 222.49 / 408.51 seconds. Integration retains four isolated PostgreSQL shards: 406.76 / 338.28 / 319.81 / 443.31 seconds. This is not a production speedup claim: fixture scope, executing assertions and runner performance differ.

## Review disposition

The consolidated fixture modernisation is complete and every remaining failure is visible. Production Correspondence, Chirality, prospective Closure, causal selection, admissibility, Receipt authority, movement/recovery, canonical preservation and other approved semantics are unchanged by this continuation. No production data was modified, no compatibility repair was added, and no test was skipped to hide a failure.

Claire should review the explicit coverage reductions above and the remaining baseline/trajectory failures. Arbitrary multi-Commit topologies, simultaneous late Commit across real Domain transitions, the removed foreign-geometry and Tragedy combinations, and ParticipationLocalEpoch's separate lawful third-Variant scenario are not demonstrated by this work. They are coverage gaps, not invitations to invent semantic rules. There are no new semantic decisions needed for the completed component changes.

**Complete execution is verified; the suite is not green and full regression sign-off is not claimed. PR #255 remains unmerged.**
