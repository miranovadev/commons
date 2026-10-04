# Chirality Stage 4: integration and regression review

Branch: `feat/chirality-stage-4-integration`.
Base: corrected Stage 3 `8d0dc987e01f25e931dbcbc150fcfd01c959b891`.
All staged branches remain unmerged. Stage 5 has not begun.

## Stage 3 migration correction

A separate commit on Stage 3 changes both canonical proposition foreign keys from `foreignUuid` to `foreignUlid`. The target key retains cascade deletion; the source key retains the existing default restrictive/no-action policy, preserving its evidence reference. No other Stage 3 file changed in that correction.

Focused Stage 1–3 verification after the correction: **49 tests, 275 assertions passed** on SQLite. The installed Laravel PostgreSQL schema grammar emits `char(26)` for both columns and the expected constraints. No PostgreSQL server, client, or Docker runtime was available locally, so this is grammar verification, **not** a live PostgreSQL migration result. PostgreSQL execution remains a CI validation limitation.

## Integration fixes

1. Background Potential projection publication previously omitted Correspondence component values/directions from regenerated measurement rows. It now publishes those fields alongside the preserved Chirality metadata. A shared `correspondenceAttributes` reader applies the existing direction resolution. Canonical snapshots supply their already-preserved fields. The Potential projection version advances to `potential-regions-v4-canonical-metadata`, so old publications are recognized as stale and can be explicitly rebuilt through existing maintenance. No historical event state is reconstructed.
2. `observeChirality` now returns the explicit canonical scalar before the snapshot lookup. In the pre-snapshot portion of Commit, it must not attempt to record canonical metadata in the expressive `chirality_observations` table. This also avoids its incompatible Correspondence-column insert. A focused test covers canonical observation before snapshot creation and confirms no expressive direction-history write.

Neither change modifies an observable formula, attenuation weight, beat cursor, evidence/grounding rule, admissibility rule, or natural trajectory.

## Accepted fixture-contract repairs

The six Stage 3 conflicts are repaired without weakening production:

- The controlled scalar mocks in `CausalHorizonLineageTest` and `ParticipantContinuityTest` now provide explicit Chirality and Correspondence component values. Existing direction resolution supplies their published metadata; production still rejects incomplete canonical preservation. The controlled scalar values and populations are unchanged.
- `LineageAttenuationTest` now compares the source-preserving preview with an explicitly established canonical target and asserts that the target is a one-position measurement boundary.
- `ChiralityV2Test` establishes canonical state for its manually constructed genuine Commit edge, compares resolved observables and preserved component metadata, and checks boundary termination. It no longer expects another expressive head at Commit.

No natural-history fixture, action sequence, timing, population, or Receipt was modified.

## Consumer audit

| Area | Finding / disposition |
| --- | --- |
| `LineageMeasurementService` | Canonical heads stop the attenuation walk. Chirality-only and complete reads use the stored scalar independently of component means. No change needed. |
| `LocalMeasurementService` | Explicit canonical branch bypasses expressive calculators; ordinary expressive nodes use event-born local state. No change needed. |
| `PropositionMeasurementService` | Snapshot/projection repair and component hysteresis already preserve canonical data. Fixed canonical observation before snapshot and exposed the existing Correspondence metadata resolution for background publication. |
| `ObservableService` | Delegates to the semantic lineage reader. No additional calculator path. |
| `ChiralityGateService` | Reads retained component directions through measurement observation, not a scalar-derived direction pair. Existing zero-direction tests remain. |
| `PhaseGenerationService` | Bias uses `ChiralityGateService`; structural opportunity rules remain separate. No change needed. |
| `CommitmentAdmissibilityService` | Grounding and Commit-born Closure remain separate from expressive measurement. No change needed. |
| `CommitmentSnapshotService` | Publishes preserved projected C/χ and metadata plus independently frozen Closure. Grounding and preview/actual checks unchanged. |
| `PotentialProjectionService` | Fixed omitted Correspondence metadata and invalidated old projection publications by version. Added rebuild coverage for every canonical metadata field. |
| `GraphProjectionService`, `PropositionResource` | Read maintained measurements and immutable commitment snapshots. Graph canonical field values use snapshot scalars/Tension. No recomputation from component mean. |
| `PropositionInspectionService`, committed history | Read frozen Closure births/snapshots in entry order; do not reinterpret canonical Chirality. |
| Plan/prompt direction consumers | `ParticipantPromptLayout` carries the existing admissibility fields; Bias and Mediate plans use gate services and published directions. No new defaults introduced. |
| Bias evidence consumers | `BiasGateService` and `MediatedIncomingBiasService` still use `ChiralityCalculator` only for evidence lean/direction helpers, not its legacy component formula. Evidence semantics remain unchanged. |
| Caching/invalidation | `ParticipationContext` caches observation-local canonical rows and event-born local state separately. Contribution dispatch does not mutate through a reused context. `FieldMutationService` continues to invalidate disposable projections; it does not recalculate local Chirality. |

## Validation

PHP 8.5.8, SQLite in-memory, 512 MiB per class. Core excludes `integration`, `query`, and `load` using separate flags. The complete Integration group ran separately. Test classes ran in isolated processes. No test timed out. PostgreSQL-specific limitations below remain explicit.

- Focused Stage 1–4: **51 tests, 297 assertions passed**.
- Final Core: **1,078 tests, 12,966 assertions: 1,064 passed, 9 failures, 1 error, 4 skipped**.
- Full Integration: **31 tests, 22,919 assertions: 22 passed, 8 failures, 1 error, no skips**.
- The entire Integration selection was also run on an isolated checkout of the corrected Stage 3 head: **identical totals and identical failing cases**. There are no new Integration failures relative to that base.
- Pint and `git diff --check`: passed.

Core includes canonical Reclassify, Reconcile, Bias, Closure/Commit, measurement, projection, and resource coverage. The six accepted Stage 3 conflicts now pass. The two additional focused tests verify background canonical metadata publication and pre-snapshot canonical observation.

### Remaining Core failures

The eight semantic/trajectory expectations inherited from Stages 1–3 remain unedited:

| Test | Classification / proposed review |
| --- | --- |
| `LineageAttenuationTest::test_raw_calculation_never_reads_or_reuses_accumulated_projections` | Old Chirality expectation -0.5 vs -0.1. Preserve the projection-independence invariant; review the expected scalar against local-zero plus attenuated ancestor. |
| `ChiralityPopulationHistoryTest::test_attestation_and_replacement_are_observed_through_real_dispatch` | Same old fresh-head scalar. Review the event-born measurement expectations. |
| `ObservableServiceTest::test_matching_lineages_in_complementary_horizons_align` | Old +1 alignment vs 0. Review against developmental local Cadence. |
| `ClosureHysteresisTest::test_read_only_bootstrap_preview_matches_actual_commit_while_field_remains_in_settlement` | Old extra-head Correspondence -0.0964505 vs preserved 0.1265986. Replace numeric Commit expectations with source preservation plus actual Closure, after review. |
| `ClosureHysteresisTest::test_negative_closure_alignment_can_commit_while_corrected_field_remains_in_settlement` | Old Correspondence -0.3999863 vs preserved -0.3872817. Same proposed preservation assertion. |
| `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` | Old natural trajectory expects Charge 0; current Charge 0.2666666. Do not tune populations to restore catastrophe. |
| `HomologousBiasTest::test_zero_retains_population_history_and_does_not_mutate_measurements_or_state` | Old assumed Negative history is no longer established. Review an explicit lawful history for the hysteresis invariant. |
| `HomologousBiasTest::test_reclassification_result_uses_ordinary_measurement_without_operand_coherence_constraint` | Old Negative result now Positive. Keep ordinary result measurement; review the expected direction. |

Two unrelated SQLite cases previously reproduced on main remain:

- `CausalHorizonLineageTest::test_cycle_ownership_is_immutable_in_bulk_sql`: PostgreSQL-only immutability trigger absent in SQLite (failure).
- `Api/ParticipationHttpReadGuardTest::test_diagnostics_accept_read_queries_but_reject_data_modifying_ctes`: PostgreSQL union query syntax rejected by SQLite (error).

The four PostgreSQL-dependent Core skips remain unchanged. These are not a claim of PostgreSQL CI success.

### Remaining Integration failures

All nine cases reproduce on corrected Stage 3. Proposed updates below require trajectory review and were **not** applied.

| Test | Evidence / classification |
| --- | --- |
| `Api/ParticipationRecoveryTest::test_successful_revisit_keeps_its_untraversed_commit_out_of_backward_recovery` | Script step 39 now reports `no_available_move` instead of the fixture's assumed continuing route. |
| `Api/ParticipationTragedyLifecycleTest::test_natural_bootstrap_crossover_remains_open_and_rest_keeps_the_region_without_catastrophe` | Same obsolete actionability expectation at step 39. |
| `Api/ParticipationLifecycleRegressionTest::test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit` | Same route assumption at step 39. |
| `Api/ParticipationLifecycleRegressionTest::test_distinguish_under_another_committal_does_not_advance_origins_epoch` | Earlier route assumption fails at step 30 before the intended epoch assertion. |
| `Api/ParticipationSourceEquivalenceTest::test_connection_driven_and_manual_participation_share_the_complete_action_contract` | Shared script assumes an action remains available at step 38; current plan permits Rest. |
| `FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_emergence` (both datasets) | Expected Correspondence -0.5276331 vs preserved -0.5348354. Old numeric trajectory expectation. |
| `HomologousMediationTest::test_admissible_mediate_can_reach_an_inadmissible_bias_without_outcome_coercion` | Fixture expects opposed Bias directions; current local Orientation 0.5/Positive and immature Cadence 0/retained Positive align. |
| `NaturalChargeBoundaryLifecycleTest::test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor` | Error looking up a `Tragedy` row that the changed natural field never creates. Do not manufacture the missing event. |

For route/actionability fixtures, review the lawful observed path and decouple routing invariants from assumed numeric outcomes. For natural histories, preserve the changed outcome as evidence; any targeted catastrophe fixture should be separately and explicitly constructed, not a retuning of this history. No semantic rollback is proposed.

## Exact Stage 4 files

- `app/Services/PotentialProjectionService.php`
- `app/Services/PropositionMeasurementService.php`
- `tests/Feature/CanonicalCommitMeasurementTest.php`
- `tests/Feature/CausalHorizonLineageTest.php`
- `tests/Feature/ChiralityV2Test.php`
- `tests/Feature/LineageAttenuationTest.php`
- `tests/Feature/ParticipantContinuityTest.php`
- `docs/chirality-stage-4.md`

Stop for Stage 4 review. No merge and no fresh cook.
