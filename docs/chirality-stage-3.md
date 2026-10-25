# Chirality Stage 3: canonical Commit hard edge

> Historical stage report. The approved [active causal Cadence correction](active-causal-attestation-cadence.md)
> supersedes local-only interval input and immediate-parent beat selection. Other
> formulas, event ownership, direction rules and canonical preservation remain intact.

Branch: `feat/chirality-stage-3-canonical-commit`.
Base: approved, unmerged Stage 2 `68a2fdd6fb44ed755b93f538cc4be4887ec86fb4` (`feat/chirality-stage-2-beat-continuity`).
Stage 1 and Stage 2 remain intact. No merge, Stage 4 work, historical backfill, or trajectory-fixture changes are included.

## Production behavior

`canonical_measurements` is an explicit durable representation separate from `local_chirality_states` and disposable `proposition_measurements`. Each record identifies the selected expressive source and independently stores resolved Correspondence, resolved Chirality, Orientation, Attestation Cadence, and their directions. It also preserves the source's published Correspondence component metadata, so downstream readers do not calculate new Committal-local diagnostics. The model rejects updates; preservation inserts once under the existing contribution transaction and substrate lock.

Commit resolves the selected source **before** creating the Committal or its causal edge. Resolution reads the Stage 1 event-born Chirality components; it does not recalculate them. The source's resolved scalar need not equal the mean of its published Orientation and Cadence. Those values are stored and read independently.

The new Committal receives no Attestation rows and no local Chirality state or interval history. Existing Commit evidence/source references and the Stage 2 beat cursor remain intact. Evidence references still satisfy ordinary grounding, but cannot cause a second expressive measurement at the canonical head.

Closure continues through the unchanged `ClosureBirthService` path at proposition creation. Commit snapshot validation still checks the actual frozen Closure against the preview. Snapshot C/χ and component metadata come from the canonical baseline; Closure comes from the new birth. Tension, Charge and Domain continue through ordinary projection. A rejected Commit rolls the canonical record and the other writes back together.

`LocalMeasurementService` explicitly recognizes canonical state. `LineageMeasurementService` treats its head as distance zero without a new attenuation pass, and stops at canonical boundaries even at the head. Its Chirality-only path uses the stored scalar, never the component mean. The first expressive child still begins with fresh local numeric state and empty intervals. Its historical contribution is the canonical baseline weighted with the unchanged `1/(1+d)^2`; pre-Commit propositions are not separate attenuation terms.

Commit previews combine the selected source's resolved C/χ with prospective Commit Closure. They no longer measure a hypothetical extra expressive head.

## Consumer integration within Stage 3

- Full/local/Chirality-only observable paths read canonical state explicitly, including batched `ParticipationContext` reads.
- `PropositionMeasurementService` protects canonical scalars from generic projection arguments and preserves component and zero-value directions. Correspondence direction ancestry stops at the canonical direction as well.
- `CommitmentSnapshotService` consumes these preserved values through its existing measurement input; its Closure, evidence, and projection validation remain unchanged.
- `ChiralityGateService` and phase direction consumers receive preserved metadata through the normal measurement/snapshot pathways.
- `PotentialProjectionService` already uses the central observable path and snapshot metadata; no separate formula was introduced.
- Graph/resource serialization retains its existing public shape and reads the preserved projections/snapshots. Focused coverage verifies repaired projection/resource values and gate directions after disposable measurement loss.

The component DTO comments now distinguish published metadata from local means. Correspondence component directions can be explicitly retained just as Chirality directions already could. Neither formula changes in this stage.

Diagnostic evidence: the scalar/component distinction and canonical baseline match the established `chirality-v3-canonical-state/CanonicalStateAudit.php` and preceding hard-edge diagnostic. Diagnostic branches remain untouched.

## Prospective boundary

No historical reconstruction occurs. Existing snapshots and legacy/bootstrap Committals without a canonical record retain their existing paths. Origin remains the special parentless bootstrap, not an expressive source Commit. New ordinary Commits receive canonical records through the production dispatcher.

Historically attested expressive propositions missing event-born local state still fail explicitly on live vNext measurement, as approved in Stages 1–2. The new table is not populated from old projections, current populations, or copied Attestation state. Manually inserted Committals are not silently upgraded to canonical boundaries.

## Validation

PHP 8.5.8; SQLite in-memory; 512 MiB per test process. Core is the repository's Unit + Feature selection excluding `integration`, `query`, and `load` with separate flags. Core ran in isolated class processes; the final focused class was rerun after completing its additional Closure test. This is local SQLite coverage, not a PostgreSQL CI result.

| Coverage | Result |
| --- | --- |
| Focused Stage 1–3 | **49 tests, 275 assertions passed** |
| New Stage 3 tests | **10 tests, 115 assertions passed** |
| Core | **1,076 tests, 12,836 assertions: 1,056 passed, 11 failures, 5 errors, 4 skipped** |
| Pint; `git diff --check` | Passed |

Focused tests cover exact scalar/component preservation; independent scalar vs component mean; no copied votes/local state; preserved evidence references; source-state invariance; canonical reads without consulting Attestations or poisoned pre-Commit local state; normal and context reads; fresh/frozen Commit Closure distinct from controlled source Closure; preview/snapshot agreement; normal projection; fresh child and canonical-only attenuation; retained zero directions after projection loss; generic projection protection; record immutability; rejection rollback; and resource serialization.

### Remaining failures: existing Stage 1–2 expectations

These eight tests already failed on Stage 2 and were not edited:

| Test | Classification |
| --- | --- |
| `LineageAttenuationTest::test_raw_calculation_never_reads_or_reuses_accumulated_projections` | Old local/lineage Chirality expectation: -0.5 vs -0.1 |
| `ChiralityPopulationHistoryTest::test_attestation_and_replacement_are_observed_through_real_dispatch` | Old fresh-head Chirality: -0.5 vs -0.1 |
| `ObservableServiceTest::test_matching_lineages_in_complementary_horizons_align` | Old alignment scalar: +1 vs 0 |
| `ClosureHysteresisTest::test_read_only_bootstrap_preview_matches_actual_commit_while_field_remains_in_settlement` | Now first fails on obsolete extra-head Correspondence: -0.0964505 vs preserved 0.1265986 |
| `ClosureHysteresisTest::test_negative_closure_alignment_can_commit_while_corrected_field_remains_in_settlement` | Now first fails on obsolete extra-head Correspondence: -0.3999863 vs preserved -0.3872817 |
| `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` | Old natural trajectory expects Charge 0; actual 0.2666666 |
| `HomologousBiasTest::test_zero_retains_population_history_and_does_not_mutate_measurements_or_state` | Earlier Negative population history no longer established |
| `HomologousBiasTest::test_reclassification_result_uses_ordinary_measurement_without_operand_coherence_constraint` | Old result direction: Negative vs Positive |

### Six additional Stage 3 fixture conflicts

| Test | Classification and proposed future update |
| --- | --- |
| `LineageAttenuationTest::test_preview_and_actual_committal_use_identical_distances` | Assertion assumes Commit is an extra measurement/attenuation head and manually creates a Committal without canonical state. Replace with canonical preservation plus downstream-distance assertions. |
| `ChiralityV2Test::test_preview_and_persisted_candidate_share_component_and_attenuation_semantics` | Manually inserted Committal follows legacy measurement; new preview preserves the expressive source. Exercise the production Commit path or explicitly establish canonical state in the fixture. |
| `CausalHorizonLineageTest::test_an_existing_settlement_cycle_contributes_after_another_participant_opens_emergence` | Scalar-only `LocalMeasurements` mock omits resolved component metadata; canonical preservation correctly rejects incomplete state. Supply explicit complete metadata in this routing-isolation fixture. |
| `ParticipantContinuityTest::test_real_commit_retains_local_continuity_with_or_without_a_domain_boundary` (both datasets) | Same scalar-only mock incompatibility; preserve explicit component values/directions in the controlled fixture. |
| `ParticipantContinuityTest::test_rest_skips_closed_crossover_without_reopening_or_exhausting_tragedy_history` | Same incomplete mocked measurement; update the fixture contract without changing production semantics. |

The four mock cases raise `Canonical preservation requires complete resolved source metadata.` No default components or reconstructed scalar were invented to make them pass. These are fixture contract conflicts, not evidence of an ordinary production Commit failure. The new focused tests exercise complete production source measurements and actual dispatch successfully. Proposed fixture updates above remain unimplemented pending review.

### Unrelated SQLite limitations

- `CausalHorizonLineageTest::test_cycle_ownership_is_immutable_in_bulk_sql`: missing PostgreSQL-only trigger, assertion failure.
- `Api/ParticipationHttpReadGuardTest::test_diagnostics_accept_read_queries_but_reject_data_modifying_ctes`: PostgreSQL query syntax rejected by SQLite, error.

Both were previously reproduced on unchanged main. Four existing PostgreSQL-dependent skips remain unchanged. No additional unresolved production regression was observed in this coverage.

## Exact Stage 3 files

- `app/Domain/Observables/ChiralityValues.php`
- `app/Domain/Observables/CorrespondenceValues.php`
- `app/Models/CanonicalMeasurement.php`
- `app/Services/ContributionDispatcher.php`
- `app/Services/Observables/CanonicalMeasurementService.php`
- `app/Services/Observables/LineageMeasurementService.php`
- `app/Services/Observables/LocalMeasurementService.php`
- `app/Services/PropositionMeasurementService.php`
- `database/migrations/2026_10_04_020000_create_canonical_measurements.php`
- `tests/Feature/CanonicalCommitMeasurementTest.php`
- `docs/chirality-stage-3.md`

Stop for Stage 3 review. Stage 4 has not begun; no stages are merged.
