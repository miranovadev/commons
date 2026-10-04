# Chirality Stage 1: proposition-local Attestation events

Review branch: `feat/chirality-stage-1-proposition-local`.
Base: `ea51bb8a4b0321050fdea82a620785605436f23c`.
This stage is implemented for review, with the unresolved boundaries below. It is not a historical-data deployment migration. No Stage 2–5 work is included.

## Diagnostic provenance and formulas

Inspected the preserved diagnostic implementations at `diagnostic/chirality-v3-canonical-state`, commit `6daca15acc19982e4a324f45403e521286c8a652`:

- `diagnostics/chirality-v3-natural-rhythm/CandidateLocalMeasurements.php`: signed displacement, the calibrated neutral Origin reference, population SD/CV, developmental Cadence and the undefined zero-mean exception.
- `diagnostics/chirality-v3-local-attestation/PropositionLocalMeasurements.php`: current evidence restricted to the proposition and own-Attestation-triggered observation.
- The subsequent propagating-cursor implementation was inspected to identify the Stage 2 features that must remain excluded.

Expressive Orientation is `(current effective own lean - previous complementary lineage lean) / 2`, rounded to seven decimals. The previous Origin is a neutral reference (`0`), as in the calibrated diagnostic; it is not an ordinary empirical lean sample. No previous comparison yields Orientation `0`. The existing evidence service still supplies effective votes in the established polarity frame. This is displacement against the diagnostic complementary reference, not subtraction from the proposition's immediately preceding vote.

Expressive Cadence uses only this proposition's completed intervals. Population SD is `sqrt(sum((x - mean)^2) / count)`. CV is `SD / mean`; mature, defined Cadence is `(1 - CV) / (1 + CV)`, bounded and rounded as in the diagnostic. The user's revised Stage 1 instruction selects this developmental formula for expressive propositions generally; the earlier diagnostic's later-Domain comparative-CV branch is not promoted.

- Zero or one interval contributes numeric `0` (immature), including singleton `[0]`.
- Genuine zero intervals are retained; `[0,4]` has CV `1` and Cadence `0`.
- `[0,0,6]` has CV `sqrt(2)` and negative Cadence.
- Mature all-zero intervals throw `DeferredChiralityCadenceBoundary`, carrying the proposition ID and attempted intervals. There is no epsilon, numeric replacement, direction reset, or Stage 2 `+1` rule.

## Production lifecycle

`local_chirality_states` is durable expressive measurement state, distinct from disposable/lineage-resolved `proposition_measurements` and existing population direction observations. It records local components and scalar, retained component directions, own intervals, last own beat position and Attestation ID, and historical act count.

`LedgerService` initializes fresh expressive state after proposition inclusion. After a new own Attestation enters the ledger, it updates that proposition's state under the existing substrate lock. Production Attestation changes are replacement acts (`supersedes_attestation_id`), handled by the same path. Re-recording an existing ledger contribution is not another beat. Direct SQL edits to historical evidence are not a new supported mutation API.

At each genuine Attestation event, the service snapshots the count of represented Cycle entries for this proposition's entry Committal and current Horizon. Subtracting consecutive own event positions produces exactly the number of represented Cycle entries between those events. No wall-clock ordering or historical timestamp reconstruction is used. Unrelated entry basins do not enter the count. There is no inherited beat position and no cross-proposition first interval.

A fresh expressive proposition has numeric Orientation/Cadence/local Chirality `0/0/0`, zero own acts, `[]` intervals and no last own beat. Existing entry-frame component hysteresis is preserved, including the diagnostic's neutral values retaining the entry snapshot directions. Parent local values and histories are never copied.

`LocalMeasurementService` reads this durable state for expressive propositions. It does not invoke the live v2 calculator for them. Component directions travel with the returned values so projection publication cannot re-resolve them from unrelated projection history. Contextual reads batch the local-state table in one query.

The inverse-square lineage walker, weights and denominator remain unchanged. Population aggregates and resolved descendant values can still change when their legitimate historical inputs change; those reads do not remeasure stored expressive local components.

Origin and ordinary Committal calculation retain their existing path in this stage. No canonical hard-edge preservation is implemented. Correspondence, Closure, Receipt/grounding and Bias admissibility formulas are unchanged.

## Prospective migration boundary (review clarification)

The migration deliberately does not infer past beat positions from today's Cycle population. Existing unattested propositions can start at their first genuine new event. An already-attested historical proposition without event-born local state fails explicitly; it is not silently reset or recomputed on read. Review clarification: historical migration is not required. Historical substrates may remain stored beside new substrates. Live vNext requests for already-attested historical expressive propositions without event-born local state fail explicitly. No backfill or reconstruction from current population is planned.

## Validation

Local PHP 8.5.8, SQLite in-memory database, isolated test-class processes, 512 MiB memory limit. Core selection used the repository's exact repeated exclusions: `--exclude-group=integration --exclude-group=query --exclude-group=load`. Frontend assets were built before the final Core run. No existing tests or natural-history fixtures were edited.

- New focused tests: **26 tests, 75 assertions, passed**.
- Batched-query and evidence-fixture regression verification alongside focused coverage: **46 tests, 570 assertions, passed** before the final additional Reclassify test; all are also covered in final Core.
- Final Core coverage: **1,053 tests, 12,704 assertions: 1,033 passed, 9 assertion failures, 7 errors, 4 skipped**.
- A baseline comparison on unchanged `main` covered **179 tests, 2,058 assertions** in affected classes. It reproduced only the two SQLite incompatibilities below.
- `npm run build` (including TypeScript checks): passed.
- Pint and `git diff --check`: passed.

The early broad discovery mistakenly treated a comma-separated exclusion as one group in this PHPUnit version and included integration/query cases. Those results are not represented as Core results. The final Core selection uses the exact Composer exclusions. No experimental history was altered to address those exploratory failures.

### Core failures by cause

| Classification | Cases | Tests |
| --- | ---: | --- |
| Deferred mature all-zero Cadence | 6 errors | `BiasLineageTest::test_each_head_uses_its_own_ancestry_and_excludes_itself_and_unrelated_majorities`; four datasets of `AttestationPolarityTest::test_bias_aggregate_retains_its_own_direction_at_zero_and_replays_hard_removal`; `PropositionReceiptWeightTest::test_proposition_loads_are_neutral_and_survive_attestation_supersession` |
| Old numeric expectations | 5 failures | `LineageAttenuationTest::test_raw_calculation_never_reads_or_reuses_accumulated_projections`; `ChiralityPopulationHistoryTest::test_attestation_and_replacement_are_observed_through_real_dispatch`; `ObservableServiceTest::test_matching_lineages_in_complementary_horizons_align`; two `ClosureHysteresisTest` cases listed below |
| Old field/direction trajectories | 3 failures | `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary`; `HomologousBiasTest::test_zero_retains_population_history_and_does_not_mutate_measurements_or_state`; `HomologousBiasTest::test_reclassification_result_uses_ordinary_measurement_without_operand_coherence_constraint` |
| Environment, reproduced on main | 1 failure, 1 error | `CausalHorizonLineageTest::test_cycle_ownership_is_immutable_in_bulk_sql` (PostgreSQL trigger absent on SQLite); `Api/ParticipationHttpReadGuardTest::test_diagnostics_accept_read_queries_but_reject_data_modifying_ctes` (PostgreSQL query syntax unsupported on SQLite) |

The two Closure tests stop on obsolete Chirality numeric assertions, not on a changed Closure formula:

- `test_read_only_bootstrap_preview_matches_actual_commit_while_field_remains_in_settlement`: expected `-0.5`, observed `-0.3622449`.
- `test_negative_closure_alignment_can_commit_while_corrected_field_remains_in_settlement`: expected `-0.3813817`, observed `-0.312583`.

Other concrete changes: a fresh unattested expressive node no longer contributes local `-0.5`; an attenuated example now resolves to `-0.1`. The natural Charge fixture observes `0.2666666` instead of zero. The Bias zero-history fixture's claimed earlier Negative observation is no longer established by its existing authored history. The Reclassify result begins locally fresh rather than taking the v2 result direction.

The four skipped cases are PostgreSQL-dependent tests in `TransactionLockOrderTest`, `LocalParticipantNavigationTest`, `ParticipationSnapshotTest` and `PropositionSearchTest`.

### Review disposition

The zero-mean cases are concrete Stage 1 boundaries; their Attestation cannot proceed under the temporary semantics. The focused test verifies that the real transaction shape rolls back the attempted Attestation, ledger entry and local state together. Stop here for review; do not coerce a value or silently advance Stage 2.

For obsolete scalar assertions, propose updating expectations to the new local-plus-attenuated measurements after review. For trajectory-dependent tests, review the intended fixture contract first; do not add actors, acts or timing changes to recover old outcomes. The historical-data boundary is prospective by explicit review approval; no historical reconstruction is required. No PR or merge is included in this stage's review branch.

## Exact changed files

- `app/Domain/Observables/ChiralityValues.php`
- `app/Exceptions/DeferredChiralityCadenceBoundary.php`
- `app/Models/Cycle.php` (comment only)
- `app/Services/LedgerService.php`
- `app/Services/Observables/LineageMeasurementService.php` (projection version only)
- `app/Services/Observables/LocalChiralityCalculator.php`
- `app/Services/Observables/LocalChiralityStateService.php`
- `app/Services/Observables/LocalMeasurementService.php`
- `database/migrations/2026_10_04_000000_create_local_chirality_states.php`
- `tests/Feature/PropositionLocalChiralityTest.php`
- `tests/Unit/LocalChiralityCalculatorTest.php`
- `docs/chirality-stage-1.md`
