# Active causal Attestation Cadence validation

**Ready for review.** Natural Commit and recovery pass with the corrected active-lineage rhythm. Core and Integration return to their established baseline failures; all 12 Phase Profile jobs, C3 and frontend pass. Query/Load retain the same established failure sets. No merge has been performed.

## Revisions and scope

Baseline: merged main `231dbd8498d75cd8a280f17bd5edb0a4a39a5629`, including PR #280. The existing PR #281 continues on `fix/active-causal-attestation-cadence`. The merged baseline differs from previously validated `0172cf92abfe9b66534345c0bb40abbafb1e5661` only in six report/evidence files; application code, schema, configuration, workflows and tests are identical. Main also has an independent Core run `40184124563` and passing Phase Profile run `40184124533`.

Initial implementation checkpoint: `30341ac384775429bb419481199577cbf92c0add`. The final tested implementation is `aecd7b1acccf081111f1547403700c9ff2f6884c`; it adds the non-destructive schema upgrade to the repaired natural-journey checkpoint `a8dfc9c9f1d4fc524a8837b19437e7f207124243`. A later report/evidence commit adds the opt-in cost diagnostic but does not change production code or the ordinary suites.

The discrepancy was an empty Cadence reference at every new expression, despite a transported preceding beat. The correction selects the latest two own intervals and preceding qualifying beat on the recorded active local lineage. Hard-superseded sources are traversed only for provenance; Reconcile's foreign operand never contributes rhythm. A compact source/index/value reference is frozen at the first own Attestation and combined with local intervals on subsequent own events. See the [implementation contract](active-causal-attestation-cadence.md).

Changed services: `ActiveCadenceLineageService`, `AttestationBeatCursorService`, and `LocalChiralityStateService`. Persistence: an additive migration introduces nullable `local_chirality_states.cadence_reference` without historical backfill; existing cursor `source_supersession_id` now remains after own Attestations. The mathematical calculator and every exploration, evidence, Bias, Closure and Commit eligibility authority are unchanged.

## Normative and invariant coverage

| Contract | Regression evidence |
| --- | --- |
| A/B/C exact inputs | `AttestationBeatContinuityTest::test_normative_active_ancestry_keeps_event_ownership_and_historical_measurements`, Revision and Reclassification data sets |
| B first | A reference `[3,2]` + B own `[3]` = `[3,2,3]`; Cadence `0.6995578` |
| C first | B excluded; A reference `[3,2]` + C own `[4]` = `[3,2,4]`; Cadence `0.5721225` |
| C second | Same A reference + C own `[4,3]` = `[3,2,4,3]`; Cadence `0.6185129` |
| Multi-Proposition continuity | `test_reference_crosses_short_active_positions_without_copying_their_references` |
| Off-route hard replacement / siblings | `test_replacement_off_the_selected_route_is_excluded_without_following_its_new_branch` |
| Reconcile selected Gate, order independence | `test_reconciliation_reference_follows_only_active_ancestry_of_the_acting_gate`, both insertion orders; existing foreign-beat/no-fallback tests |
| Event-time selection and retained context | `test_first_event_selects_current_active_context_then_retains_that_reference` |
| Existing database upgrade | `test_additive_reference_migration_preserves_historical_measurements_without_backfill`; old scalars/events remain unchanged, new expressions work, missing old references cannot be fabricated |
| Bootstrap, missing history, real zeros, CV | Local calculator tests; first-child, single-participant zero, mixed-zero, all-zero historical-reference tests |
| Orientation and hysteresis | `PropositionLocalChiralityTest`; all-zero historical-reference test independently asserts local Refute Orientation and ordinary Bias disagreement |
| Immutability and atomicity | Ancestor state equality in normative/structural tests; unrelated events/Cycles, rollback and duplicate-ledger contracts |
| Canonical Commit boundary | `CanonicalCommitMeasurementTest`; `test_canonical_boundary_transports_only_the_existing_beat_not_pre_commit_intervals`; no copied Committal local state/votes |
| Publication and attenuation | Local measurement/real dispatcher tests and ordinary published Bias components; no special eligibility override |
| Human/autonomous and lifecycle | Existing HTTP, source-equivalence, real Commit and recovery tests remain authoritative |
| Query bound | One query at 2 and 22 sparse hops; nearest two-sample predecessor limits SQL output to two positions |

## First changed natural decision

The original 77-step HTTP transcript passes unchanged on main. On the initial correction it executes 53 steps and rejects zero-based step 53: Cycle 12, Bias, reclassification of `p15`. Only Rest is offered at that point. The [recorded comparison](evidence/active-causal-cadence/original-transcript-comparison.json) preserves exact inputs and measured differences.

The first interval difference is step 5: `p2` now records a genuine same-Cycle zero from the active predecessor's latest beat, rather than closing against the older birth-time cursor (`[2]`). The first changed Cadence/direction is step 12: `p3` uses historical `[2,1]` plus own `[0]`, measuring `+0.1010205` instead of immature zero with a retained negative direction.

At the consequential step 52, Reconcile result `p15` uses `p1` reference `[1,1]` plus own `[0]`, measuring `+0.1715729`. Its negative Orientation disagrees with positive Cadence. Main instead measures immature zero with negative retained Cadence. The old next-step reclassification therefore relied on the superseded local-only Cadence assumption. Bias correctly rejects it under the unchanged component agreement rule.

No Exploration Event, measurement, Receipt or authority is fabricated to preserve that transcript. The original comparison remains diagnostic evidence. The replacement fixed HTTP transcript records 73 actual authorised operations: skeptical development, supportive Mediate judgment, then an offered reversal revisiting the existing classification. The historical aligned driver is unchanged; the new direction preference is explicit on the affected natural fixtures. An existing Reconcile on another branch is not treated as the committed source merely because it was the last Reconcile in execution order.

The [captured natural Commit](evidence/active-causal-cadence/natural-commit.json) also records the fixture hash, seven real Receipts, two verified Reconciles and canonical source values.

The replacement's Cycle 14 Bias reclassification lawfully selects the older `p15` synthesis while the Gate is the newer `p19`; Cycle 15 returns via the offered `p15` Refute and attests the resulting classification `p20` before Commit. No action is added to an application schema. The final source remains a real Phase-6 expressive Proposition with ordinary Receipt consumption and effective evidence.

To reproduce the original comparison, extract the fixture from checkpoint `30341ac` with `git show 30341ac:tests/Fixtures/participation/persist-skeptical-commit.json`, then run the opt-in `ActiveCadenceTranscriptTest` with `COMMONS_CADENCE_TRANSCRIPT` pointing at that file on each revision. `COMMONS_CADENCE_TRACE_FILE` controls the JSON trace destination.

## Initial broad checkpoint

The [CI inventory](evidence/active-causal-cadence/initial-ci-summary.json) records initial results and exact job IDs, with independent current-main Core results. Core: 1,513 passed and the same one baseline Charge-boundary failure. C3: 107 focused tests / 1,027 assertions and recorded comparison 1 / 1,390. Frontend: 148 tests plus TypeScript/Vite passed. Query: 13 passed / 9 established failures / 953 assertions. Load: 18 passed / 8 established failures / 178 assertions. Initial Integration and four Phase Profile lifecycle jobs expose the obsolete natural transcript and driver decisions described above; these are not declared acceptable final results.

C3's ten operational plans remain 71–77 queries, identical to the merged checkpoint case-by-case. Query and Load failure identities and assertion counts are unchanged. Query's dense payload and legacy prospective-Closure fixture failures remain baseline limitations. Workflow budgets and mathematical assertions are retained.

## Performance and remaining boundaries

The [identical event-cost probe](evidence/active-causal-cadence/event-cost-comparison.json) ran five sibling births and their first/second own Attestations on main and the final implementation under PHP 8.5.8 / SQLite. Closure output is explicitly isolated for this component probe; natural lifecycle evidence is separate.

| Operation | Main queries | Corrected queries | Main median elapsed | Corrected median elapsed |
| --- | ---: | ---: | ---: | ---: |
| Expressive birth | 85 | 85 | 43.846 ms | 40.861 ms |
| First own Attestation | 76 | 76 | 32.232 ms | 44.745 ms |
| Second own Attestation | 78 | 77 | 42.200 ms | 42.620 ms |

The first-event probe measured an approximately 12.5 ms increase; subsequent events avoid a duplicate cursor lookup. These five-sample shared-container timings are diagnostics, not production throughput estimates. Query counts are exact. The required ancestry query replaces the previous cursor read and stops as soon as two qualifying samples are found. Sparse ancestry still requires walking backward to enough samples or a canonical boundary; there is no fixed hop cap that would truncate legitimate history. C3 read-plan query counts remain unchanged. The final PostgreSQL selector probe measured 2.14 ms at 2 sparse hops, 2.68 ms at 22 sparse hops, and 2.21 ms when the nearest predecessor supplied both samples; each used one SQL call.

The additive migration does not backfill old event-time references or reconstruct historical selected cursors. Historical values remain readable and unchanged. A previously attested local state missing its reference cannot silently resume live remeasurement; fresh prospective expressions establish their own reference. No live cook was performed, as requested.

Established baseline Core/Integration and Query/Load failures remain independent limitations. No thresholds, mathematical assertions, query ceilings or workflow timeouts were weakened.

## Final validation

**Ready for review: no new implementation failures remain.** The [final CI inventory](evidence/active-causal-cadence/final-ci-summary.json) records the exact jobs and summaries at `aecd7b1`. Nothing is merged.

| Validation | Final result | Evidence |
| --- | --- | --- |
| Focused Chirality / beat / canonical / upgrade | **62 passed, 386 assertions** | Local PHP 8.5.8 / SQLite; the same feature contracts also execute in PostgreSQL Core/Profile |
| Corrected descendant and crossover journeys | **2 passed, 976 assertions** | Local fixed HTTP and natural lifecycle selection; full Integration below |
| Core | **1,514 passed; 1 established baseline failure; 15,589 assertions** | [Run 40189512172](https://github.com/miranovadev/commons/actions/runs/40189512172); all five PostgreSQL shards complete |
| Integration | **31 passed; 3 established baseline failures; 34/34 executed, zero skips; 6,960 assertions** | [Run 40189513019](https://github.com/miranovadev/commons/actions/runs/40189513019), [exact case inventory](evidence/active-causal-cadence/integration-summary.json) |
| Phase Profile / observable / canonical contracts | **12/12 jobs passed** | [Run 40189512189](https://github.com/miranovadev/commons/actions/runs/40189512189); both SQLite/PostgreSQL natural Commit and descendant tests pass; existing two SQLite backend skips are executed in PostgreSQL |
| C3 Context | **107 passed / 1,027 assertions; recorded comparison 1 passed / 1,390 assertions** | [Run 40189512998](https://github.com/miranovadev/commons/actions/runs/40189512998); all ten plans preserve their 71–77 query counts |
| Frontend | **148 passed; TypeScript/Vite build passed** | [Job 121157364123](https://github.com/miranovadev/commons/actions/runs/40189510128/job/121157364123) |
| Query | **13 passed; 9 established baseline failures; 953 assertions** | [Run 40189512177](https://github.com/miranovadev/commons/actions/runs/40189512177) |
| Load / PostgreSQL concurrency | **18 passed; 8 established baseline failures; 178 assertions** | [Run 40189512160](https://github.com/miranovadev/commons/actions/runs/40189512160); additional terminal concurrency checks pass in Phase Profile |

Both final Core runs have identical test counts and the same sole baseline failure. The slowest validation-branch shard took 488.98 seconds; the primary feature-branch run's slowest shard took 579.84 seconds, versus 503.86 seconds on current main. Both remain within the unchanged workflow ceiling; the slower primary result is recorded alongside the faster duplicate rather than treated as a controlled performance comparison. Query took 533.54 seconds; Load 59.99 seconds. The final natural source Commit took 96.24 seconds in PostgreSQL; descendant history 79.75 seconds in the same Profile validation. These runner durations are not controlled benchmarks.

### Failure classification

- **Established Core baseline:** `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` expects Settlement but observes Emergence. Its heading matches the independent merged-main run `40184124563`.
- **Established Integration baseline:** the two aligned `FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_a_lawful_commit` data sets exhaust their unchanged driver budget; `NaturalChargeBoundaryLifecycleTest::test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor` retains the Settlement/Emergence failure. These are the exact three merged-baseline identities. [Prior independently verified baseline and unchanged-code comparison](participant-relative-exploration-validation.md#established-main-failures).
- **Established Query baseline:** the same dense payload is **54,511 bytes** against the unchanged **45,000-byte** ceiling; eight legacy performance fixtures lack authorised prospective Closure. All nine failure headings match the initial checkpoint and the merged baseline evidence.
- **Established Load baseline:** the same eight Closure-fixture, duplicate-transition Receipt, lock-wait and graph-direction failures remain. Failure headings and assertion counts match the checkpoint/baseline evidence.
- **Obsolete expectations repaired:** cross-boundary first intervals now use the latest qualifying active beat; hard-replaced sources no longer supply their own beat; mature active reference context replaces fresh-local-only Cadence. No mathematical formula or tolerance changed.
- **Legitimate trajectory changes repaired:** the fixed 77-step path becomes a recorded 73-step path. The dependent search test identifies the unselected operand of the actual committed synthesis, rather than the last Reconcile anywhere in execution order. The crossover golden Chirality changes to **0.077151** and its bound to **−0.0673926**; independent projection equations, closed-bound, no-false-Tragedy and no-false-Exhaustion assertions remain intact.
- **New regressions or unresolved semantic blockers:** none identified by the completed suites.

Integration retains every test case and lifecycle assertion. Its assertion total changes from the baseline's 7,194 to 6,960 because the authored path is shorter, not because cases or mathematical checks were removed. The plain aligned fixture and already-passing multi-Participant driver retain their original policies. Formatting and whitespace checks pass. No live population cook was required or run.
