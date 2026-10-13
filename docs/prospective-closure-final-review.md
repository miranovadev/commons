# Stage 3 final conformance and review-readiness pass

This pass starts at `f5a8b8ddf4260e40abd1b0d5af41f9dce869b228` (tested code `661d52ab7d8061595107d3ca0eaa6e6df85e8d12`): Core **1,109 pass / seven fail / 1,116 cases**, Integration **21 pass / eleven fail / 32 cases**. The approved specification and ten Core Invariants are unchanged. This is not another historical-fixture reconstruction.

Final tested code: `9bc9e8576dc9c28d0f52d766193304a22f614d31`. Validation results below are populated only from completed runs at that SHA. Reporting-only commits are not new tested production implementations.

## Production conformance

The complete production diff against PR base `e7c357551f6e9916a8e84c8c61c309069b280ca6` was inspected: 19 PHP application files, one additive migration and four frontend files. Their blob identities match the reviewed implementation. **This final pass changes no application, migration or frontend production code. No new production defect was identified.**

| Approved contract / invariants | Implementation inspected | Verification and scope |
| --- | --- | --- |
| Constitutional Origin, genuine zero versus unavailable; 2, 3, 9 | `ProspectiveClosureCalculator::origin`, `ProspectiveClosureService::initialize` | Frozen Closure/Extent/Rhythm/Depth/Cadence −1; seven available intervals [0,0,0,0,0,0,1]; no empirical Orientation count or synthetic birth. Ordinary first interval remains null. State/Phase Profile tests cover representation and immutability. |
| Equal-weight cumulative selected Domain history; 5, 7, 9 | `ProspectiveClosureCalculator::profile`, `ProspectiveClosureHistory::phases`, `ProspectiveClosureService::measure` | One sum/count contribution per available interval, including each Origin interval. Frozen selected entry history plus selected temporal observations, keyed by observation identity, grouped by creating Domain. No Horizon-mean averaging or sibling population scan. Repeated-Domain arithmetic and structural/persistence tests are not proof of naturally authorised repeated transitions. |
| Historical Orientation; 1, 6, 7 | `ProspectiveClosureHistory::orientations`, `ProspectiveClosureCalculator::calculate`, unchanged `LocalChiralityCalculator` / `LocalChiralityStateService` | Select active causal expressives, exclude selected hard-superseded nodes; use already-resolved local Orientation, not final Chirality. Genuine zeros count. Frozen historical inputs win over later mutable inputs. Attestation replacement and arithmetic mean tests execute; no product or second displacement. |
| Complete-context birth and genuine Cycle entry; 1, 4, 5, 6 | `ContributionDispatcher::dispatch`, `LedgerService::record`, `Cycle::save`, `ProspectiveClosureService::entered` | Initialise after ledger, temporal facts, causal edges and contribution context. New Cycle save is under the Substrate transaction lock; update all governed expressives including superseded, with each head's separate traversal. Completion/Rest/read/Attestation are not additional Closure triggers. Batch versus single selection is tested. |
| Independent Extent/Rhythm hysteresis; 4, 7, 8 | `ProspectiveClosureCalculator`, `ClosureGateService`, `CommitmentAdmissibilityService`, `HomologousOrderService` | Fixed four-primitive weights, immature Cadence stays unavailable at numeric zero, independent retained directions. Both agreeing signs allowed only with other authority. Opposed directions fail closed. Old Depth/Cadence signs do not stand in for outer-component signs. |
| Canonical preservation and stale state; 4, 6, 8, 9 | `CanonicalMeasurementService::preserve`, `ProspectiveClosureService::preserve`, `CommitmentSnapshotService::record`, existing schema observation guard | Root lock; exact authorised observation copied, including trigger, historical inputs and directions; complete equality checked against validated state. Intervening Cycle invalidates old preview; rollback tests reject stale state. Frozen row UPDATE guards on both databases. No backfill/read-time repair. |
| Attenuation A and independent lifecycles; 1, 7 | `LineageMeasurementService`, `PropositionMeasurementService`, unchanged C1/Chirality services | C and Ch retain inverse-square, minimum-distance, canonical-boundary traversal. Closure stays local. Prospective Commit resolves the existing source rather than adding a head. Tension/Charge equations unchanged. C1 event independence, Chirality event tests, source preservation and descendants remain covered. |
| Authority, evidence and movement; 5, 8, 9, 10 | Commit grounding/structural checks and unchanged Receipt, Tragedy, Exhaustion and recovery services | No new action, eligibility waiver, Receipt authority, sibling movement or recovery route. Component rejection/selection tests and existing passing lifecycle cases remain distinct. |
| Representation; 2, 3 | Snapshot accessors, inspection, phase instructions and four frontend files | Extent/Rhythm names and directions reflect new components; UI does not compute Closure or infer eligibility from agreement alone. Frontend and Inspector checks validate rendering/contracts. |

The additive migration is now explicitly round-trip tested against existing Origin snapshot, immutable Phase birth and ledger records: it creates no new history, changes none of that evidence, and an uninitialised state still fails closed. This test runs on both databases. No production records were touched.

The already-recorded Rest crossover limitation remains: `CrossoverMarginService` uses the separately specified old conditional Cardinal/Cadence bound, consumed by the existing recorded-path recovery rule. It is **not a validated bound on prospective Closure**. Neither that experiment nor movement/recovery was changed or re-approved here; see [the implementation review](prospective-closure-implementation-review.md) and [its existing scope](rest-crossover-experiment.md). This pass provides no new multi-Commit routing evidence.

## Disposition of the seven checkpoint Core failures

These classify meaning as well as historical timing. A baseline failure is not automatically a valid assertion.

| Checkpoint test | Classification and final contract |
| --- | --- |
| `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` | Unchanged historical failure / obsolete authored trajectory. `NaturalBoundaryHistory` expects Settlement where the field opens Emergence, before its catastrophe assertions. Retained failing; no population changes or claim of unmocked catastrophe coverage. |
| `ChiralityPopulationHistoryTest::test_attestation_and_replacement_are_observed_through_real_dispatch` | Obsolete pre-vNext scalar expectations, already failing in Stage 2. Now checks event-born Orientation 0 → +0.5 → −0.5, effective replacement, both acts, Receipt neutrality, and recorded population mean against the independent source/Origin inverse-square expression. Real Attestation dispatch; Closure remains an explicitly isolated component. |
| `ClosureHysteresisTest::test_read_only_bootstrap_preview_matches_actual_commit_while_field_remains_in_settlement` | Obsolete C1 scalar/trajectory assumptions. Replaced by `test_read_only_preview_preserves_source_measurements_and_the_authorised_closure_state`: read-only preview, exact C/Ch/Closure source preservation, full Closure observation, canonical field projection (including its existing two-stage rounding) and defined Domain transition. Service fixture, not complete lifecycle evidence. |
| `ClosureHysteresisTest::test_negative_closure_alignment_can_commit_while_corrected_field_remains_in_settlement` | Lawful prospective Closure consequence; obsolete name/Domain assertion. Negative Depth/Cadence did not imply negative Extent/Rhythm. Replaced by `test_coherent_closure_preserves_source_without_prescribing_the_next_domain`, checking outer agreement, exact source state and Tension-driven Domain. Genuine negative outer coherence is separately exercised by the existing 89-action scenario. |
| `ClosureHysteresisTest::test_commit_freezes_source_attestation_direction_as_the_next_region_frame` | Lawful prospective Closure rejection: Extent +0.0714286 Positive / Rhythm −0.25 Negative, with grounding satisfied. Replaced by `test_source_attestation_cannot_bypass_opposed_extent_and_rhythm`, checking direct rejection/rollback and unchanged Origin frame. The intended successful polarity preservation is added to the existing genuine Commit scenario, without altering its actions. |
| `HomologousBiasTest::test_zero_retains_population_history_and_does_not_mutate_measurements_or_state` | Obsolete assumption that fixture events established Negative population history. Now compares zero with actual prior population direction and verifies read/selection non-mutation. Removed its incidental all-three-options assertion; member eligibility remains in the separate matching/dominance tests. Both retained poles remain explicitly covered in `ChiralityPopulationHistoryTest::test_identical_zero_population_retains_its_own_actual_prior_direction`. |
| `ObservableServiceTest::test_matching_lineages_in_complementary_horizons_align` | Obsolete assumption that matching shape forces Correspondence and Chirality to +1. Replaced by `test_component_ancestry_attenuates_independent_current_measurements_until_the_committal_boundary`. Preserves raw-component means, inverse-square accumulation and stop-at-boundary exclusion. Authored boundary projection is explicitly not genuine Commit evidence. |

No expected number was simply replaced with observed output. No test was skipped or removed in this final pass; four names express the corrected contracts, and one migration contract was added.

## The eleven checkpoint Integration failures

All original scripts, action budgets, populations and assertions remain unchanged. Exact identities and messages appear in the final inventory.

| Test | Classification / observed boundary |
| --- | --- |
| `NaturalChargeBoundaryLifecycleTest::test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor` | Unchanged Stage 2 historical failure; same obsolete Settlement assumption in `NaturalBoundaryHistory`. Catastrophe/recovery tail remains unexecuted. |
| `ParticipationLifecycleRegressionTest::test_distinguish_under_another_committal_does_not_advance_origins_epoch` | Unchanged historical C1 trajectory: step 26 reaches Mediate with no admissible non-Rest move. Later cross-Committal epoch assertion is not demonstrated by this script. |
| `ParticipationLifecycleRegressionTest::test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit` | Unchanged historical C1 trajectory, step 32 Mediate mismatch. Successful committed-route revisit tail remains unexecuted. |
| `ParticipationLocalEpochTest::test_a_third_distinguish_variant_reopens_only_its_own_slot_through_http` | Unchanged historical C1 trajectory, step 32. Recorded operand Shape −0.4701871 Negative / Profile +0.1428571 Positive is inadmissible; original third-Variant transcript preserved. |
| `ParticipationRecoveryTest::test_successful_revisit_keeps_its_untraversed_commit_out_of_backward_recovery` | Unchanged historical C1 trajectory, step 32. The specific successful-revisit recovery tail remains unexecuted. |
| `ParticipationSourceEquivalenceTest::test_connection_driven_and_manual_participation_share_the_complete_action_contract` | Unchanged historical C1 trajectory, step 31. Other real-worker/source tests remain separate coverage, not proof this complete script passes. |
| `ParticipationTragedyLifecycleTest::test_natural_bootstrap_crossover_remains_open_and_rest_keeps_the_region_without_catastrophe` | Unchanged historical C1 trajectory, step 32. Its later Rest/catastrophe assertions are not reached. |
| `FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_a_lawful_commit`, both polarity datasets | Two lawful Stage 3 consequences: Extent 0 Negative / Rhythm +0.1875 Positive. Correct coherence rejection; neither completes Commit. |
| `OperationalClosurePathTest::test_receipt_backed_full_cycle_commits_with_real_hysteretic_closure_calculations` | Same lawful Stage 3 coherence block; no completed Commit claim. |
| `Api/ParticipationHttpHarnessTest::test_real_http_actions_reach_an_operational_commit` | Same lawful Stage 3 coherence block within unchanged HTTP action budget; genuine service lifecycle success does not imply HTTP Commit success. |

The historical comparison is backed by [Stage 2/initial Stage 3 validation](prospective-closure-validation.md) and [the checkpoint inventory](evidence/prospective-closure-test-modernisation). The six scripted Mediate failures are old authored-trajectory incompatibilities, not newly identified Stage 3 defects. Their intended invariants cannot be recovered by merely editing the intermediate expected action list.

## Coverage retained and limitations

The [modernisation report](prospective-closure-test-modernisation.md) and [contract mapping](evidence/prospective-closure-consolidated-contracts.json) remain the detailed record: 42 Core identities consolidated/removed, 15 new/renamed contracts at that checkpoint, 201 formerly masked cases passing as components, and one unsupported Integration scenario consolidated. Those are coverage changes, not recovered end-to-end assertions.

This pass reviews that mapping and retains:

- Commit authority/evidence: grounding and stale rejection matrices; selected-source canonical preservation; real Receipt and Attestation references; hard-edge consumption and fresh descendant reset. Numerical Closure doubles still throw on canonical preservation, including nested doubles.
- Homology/causal selection: shared Origin/depth/Phase/register membership, malformed/cyclic/disconnected histories fail closed, source visibility versus movement, independent operand direction gates, Revision/Reconcile/foreign-source temporal selection. Multi-region coordinate cases are component/read-model tests.
- Tragedy/Exhaustion/recovery: boundary predicates and proof/authority checks, read-only diagnostics, actual-arrival backward selection and sibling exclusion remain covered. Controlled field values prove boundary handling, not natural catastrophe generation.
- Historical preservation: immutable birth facts, frozen Closure and snapshots, no read repair, structural/Attestation event independence, stale preview rejection, batch/individual equivalence and selected post-Commit search distances.

Genuine lifecycle demonstrated: the unchanged 89-action skeptical-development history completes a lawful Commit with an actual Reconcile, source Extent 0 retained Negative / Rhythm −0.1875 Negative / Closure −0.09375, exact canonical preservation, source evidence by reference and now source-polarity preservation. The separate genuine descendant/search case verifies fresh ownership/evidence, selected distances, memory-only search Receipt without grounding authority, and unchanged frozen history. HorizonProofCycle and other existing passing Integration cases remain identified individually in the inventory; some passing Integration selections themselves explicitly control Closure and must not be relabelled unmocked Stage 3 Commit coverage.

Remaining lifecycle limits: arbitrary multi-Commit / repeated natural Domain transitions; simultaneous late Commit across a real Domain transition; the retired positive-input foreign-Horizon Reconcile geometry combination; nine retired late-Phase Tragedy combinations; natural catastrophe/recovery in the retained failing history; the LocalEpoch third-Variant HTTP scenario; the four blocked aligned-evidence Commit journeys; and the tails of the six historical Mediate scripts. Component arithmetic/selection tests do not close these gaps. No new populations or causal histories were manufactured to do so.

## Completed validation and readiness

| Complete suite | Checkpoint | Final | Assertions | Workflow elapsed / execution window |
| --- | --- | --- | ---: | --- |
| [Core](https://github.com/miranovadev/commons/actions/runs/38389248183) | 1,109 pass / 7 fail / 1,116 | **1,116 pass / 1 fail / 1,117** | 13,209 | **10m12s / 8m49s** |
| [Integration](https://github.com/miranovadev/commons/actions/runs/38389254326) | 21 pass / 11 fail / 32 | **21 pass / 11 fail / 32** | 17,534 | **10m05s / 8m36s** |

All discovered/selected/executed identity multisets match exactly. **Zero complete-suite skips, missing-history errors or timeouts.** All **1,109 previously passing Core identities** and **21 passing Integration identities** remain passing. Two retained failures now pass; four renamed current-contract replacements pass; the new migration case passes. The remaining failure categories are **one Core historical authored trajectory; seven unchanged historical Integration trajectories; four lawful prospective Closure Integration blocks; zero newly identified production regressions**. These failed workflows completed their suites; they are not green runs.

The exact [inventory and checkpoint comparison](evidence/prospective-closure-final-review) includes each test identity, outcome, assertion count and runtime. No new skip/exclusion or discovery change was made. The existing five Core / four Integration PostgreSQL shards, transaction isolation plus dead-storage maintenance, and job timeouts are unchanged.

Performance: checkpoint workflow elapsed was Core 9m13s / Integration 8m22s. Final Core elapsed **10m12s exceeds the nominal ten-minute total target by twelve seconds**, including **1m23s before the first job started** while the superseded run released concurrency. From first job start to final run completion it took 8m49s; the slowest PHP job took 7m29s within its unchanged eight-minute limit, and frontend took 1m16s within two minutes. Core remains above the preferred five-minute target; this pass does not claim the elapsed target was met. Integration elapsed 10m05s includes 1m29s initial queueing and remains below both the 15-minute preference and 20-minute ceiling. No timeout was increased. Runtime variation is not presented as a production performance improvement.

Core's separate SQLite migration selection also completed successfully (14 cases / 46 assertions); the newly added prospective Closure round-trip case is in complete Core and the dual-database focused matrix.

[Focused matrix](https://github.com/miranovadev/commons/actions/runs/38389248081), all ten jobs successful (5m47s workflow):

| Selection | PostgreSQL | SQLite | PHP seconds PG / SQLite |
| --- | --- | --- | --- |
| Phase Profile C1 | 48 pass / 300 assertions | 48 pass / 300 assertions | 82.61 / 20.37 |
| Prospective Closure, including migration | 15 pass / 90 assertions | 15 pass / 90 assertions | 19.04 / 4.29 |
| Existing observable/ledger and boundaries | 131 pass / 1,961 assertions | 131 pass / 1,961 assertions | 201.73 / 42.66 |
| Representative fixtures | 175 pass / 2,077 assertions | 173 pass / 2,071 assertions; two existing PG-only skips | 171.30 / 72.47 |
| Final population/attenuation review | 50 pass / 522 assertions | 50 pass / 522 assertions | 182.51 / 22.97 |
| Genuine Commit | 1 pass / 113 assertions | 1 pass / 113 assertions | 105.35 / 16.00 |
| Genuine descendant/search | 1 pass / 129 assertions | 1 pass / 129 assertions | 122.28 / 22.02 |

Focused selections overlap complete-suite identities. The two pre-existing SQLite skips concern PostgreSQL-specific behaviour and both execute in PostgreSQL; none was introduced. Formatting (37 relevant files) and whitespace pass on both databases. Frontend build and **130 UI tests** pass; [Inspector browser review](https://github.com/miranovadev/commons/actions/runs/38389254502) passes (1m29s, desktop/mobile synthetic graph checks). These browser checks are presentation coverage, not a live substrate lifecycle.

**Ready for Claire’s final review, not merged.** No newly identified production defect or material new regression was found in the reviewed scope. The twelve remaining historical/trajectory failures and the explicit lifecycle limits above are not waived or described as passing. They do not evidence a newly introduced Stage 3 implementation defect; final merge acceptance remains Claire’s decision. The nominal Core elapsed-budget overrun and the pre-existing Rest-bound scope limitation are disclosed for review. Full green-suite or exhaustive lifecycle sign-off is not claimed.

The first review code `e476fa29bdcc0f43a264c5e0b3a53f6a46db22d7` is superseded. Its focused selections identified two formatting issues and the lingering outer-direction assumption, corrected before final validation. Its incomplete/superseded suite runs are not final evidence. The second candidate `d056cb2fd7600f0efc6f1f6668dfd1bf408d0e55` exposed a test-only one-unit-in-the-last-decimal mismatch: directly rounding the algebraic mean is not identical to the existing separately rounded Prominence minus Turbulence projection. The assertion now uses that canonical projection. The tested SHA above includes the migration contract and all corrections.
