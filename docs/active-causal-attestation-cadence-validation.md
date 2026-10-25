# Active causal Attestation Cadence validation

## Revisions and scope

Baseline: merged main `231dbd8498d75cd8a280f17bd5edb0a4a39a5629`, including PR #280. The existing draft PR #281 continues on `fix/active-causal-attestation-cadence`. Initial implementation checkpoint: `30341ac384775429bb419481199577cbf92c0add`. Final results follow below when the remaining natural transcript validation completes.

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

The replacement's Cycle 14 Bias reclassification lawfully selects the older `p15` synthesis while the Gate is the newer `p19`; Cycle 15 returns via the offered `p15` Refute and attests the resulting classification `p20` before Commit. No action is added to an application schema. The final source remains a real Phase-6 expressive Proposition with ordinary Receipt consumption and effective evidence.

To reproduce the original comparison, extract the fixture from checkpoint `30341ac` with `git show 30341ac:tests/Fixtures/participation/persist-skeptical-commit.json`, then run the opt-in `ActiveCadenceTranscriptTest` with `COMMONS_CADENCE_TRANSCRIPT` pointing at that file on each revision. `COMMONS_CADENCE_TRACE_FILE` controls the JSON trace destination.

## Initial broad checkpoint

The [CI inventory](evidence/active-causal-cadence/initial-ci-summary.json) records initial results and exact job IDs, with independent current-main Core results. Core: 1,513 passed and the same one baseline Charge-boundary failure. C3: 107 focused tests / 1,027 assertions and recorded comparison 1 / 1,390. Frontend: 148 tests plus TypeScript/Vite passed. Query: 13 passed / 9 established failures / 953 assertions. Load: 18 passed / 8 established failures / 178 assertions. Initial Integration and four Phase Profile lifecycle jobs expose the obsolete natural transcript and driver decisions described above; these are not declared acceptable final results.

C3's ten operational plans remain 71–77 queries, identical to the merged checkpoint case-by-case. Query and Load failure identities and assertion counts are unchanged. Query's dense payload and legacy prospective-Closure fixture failures remain baseline limitations. Workflow budgets and mathematical assertions are retained.

## Final validation

Pending final lifecycle replay and CI at the completed implementation revision.
