# Chirality Stage 2: event-born beat continuity

> Historical stage report. The approved [active causal Cadence correction](active-causal-attestation-cadence.md)
> supersedes local-only interval input and immediate-parent beat selection. Other
> formulas, event ownership, direction rules and canonical preservation remain intact.

Branch: `feat/chirality-stage-2-beat-continuity`.
Parent: approved Stage 1 `1aa462ef0ed57a9dbca979d08ac93dceeca079ad`.
No Stage 3 canonical measurement preservation, historical backfill, trajectory-fixture rewrites, or merge is included.

## Representation and event order

The cursor is separate from expressive local measurement state. `proposition_attestation_cursors` records the most recent genuine Attestation's substrate-ledger sequence, its original Attestation ID, and the selected birth Supersession when inherited. An inherited reference is not an effective Attestation and never enters evidence/grounding queries.

New Cycles record `chirality_entry_after_sequence`: the substrate's latest ledger sequence at their actual creation, under the same substrate lock used by Attestation recording. This column is immutable through the Cycle model. Existing historical rows remain NULL; no timestamps or present-day population counts reconstruct their birth positions.

For a previous beat at ledger sequence `b` and a current Attestation at `a`, the local interval counts represented Cycle entries with:

```
b <= chirality_entry_after_sequence < a
```

Representation is still the ending proposition's entry Committal plus owning Horizon, as in the diagnostic. Every qualifying Cycle counts, including several Cycles at the same ledger boundary. The ledger sequence supplies ordering, not the numeric interval itself. It therefore does not introduce extra beats for structural or unrelated ledger writes.

This portable cursor also handles unary foreign-source continuation: it counts the child's represented entries since the source beat, without subtracting incompatible population counts from two entry basins. The Stage 1 `last_position` field remains an own-event scoped population diagnostic; it is not the propagated cursor or the interval calculator.

## Propagation and ownership

The substrate-locked `LedgerService` copies the cursor when recording the producing Supersession. It does not copy local samples, numeric components, scalar Chirality or Attestation rows. A later extra causal edge cannot retroactively replace an already-selected birth cursor. There is no dynamic ancestry search, merge, averaging, or latest-parent selection.

- Unary writes select their causal source.
- Unattested propositions pass the same genuine beat through unchanged.
- The first child Attestation closes the interval from that beat and appends it to the child only.
- The child's own Attestation then advances its cursor; existing parents, siblings and already-born descendants are not rewritten.
- Reconcile selects the source matching the producing Phase's acting Gate. The other parent is ignored for rhythm even if its beat is newer or its edge is recorded first. A null local cursor stays null. Both causal edges and their ordinary attenuation contributions remain intact.
- Cursor bookkeeping can pass through a Committal without creating local expressive measurement state or copying Attestations. Commit's Correspondence/Chirality measurement behavior remains unchanged until Stage 3.

This follows the birth selection and interval semantics in `diagnostics/chirality-v3-local-reconcile/LocalReconcileMeasurements.php`, preserved in the diagnostic sequence. Only events already recorded into the production ledger are genuine beats; isolated fixture/import rows without ledger events do not manufacture rhythm.

## All-zero regularity

For at least two intervals with mean and population SD both zero:

```
raw CV = unavailable
 effective CV = 0
Cadence = +1
Cadence direction = Positive
```

The calculator exposes `raw_cv`, effective `cv`, maturity and `zero_mean_regular`. Singleton `[0]` remains immature with numeric zero. Mixed zero/nonzero histories keep ordinary population CV. Individual zero intervals are not deduplicated. The temporary Stage 1 exception is removed.

All Stage 1 Orientation, component hysteresis, own-Attestation recalculation, fresh expressive numeric reset, attenuation, Receipt/grounding and admissibility semantics remain in place. The only Stage 1 tests updated are assertions explicitly superseded by Stage 2: the deferred all-zero boundary and previously absent cross-boundary first intervals. Their atomic-rollback, reset and invariance checks remain.

## Prospective migration boundary

Historical migration is not required. Historical substrates may stay stored beside new substrates. There is no backfill of either local measurements or beat chronology. A live vNext request for an already-attested historical expressive proposition without event-born local state still fails explicitly. Missing recorded historical beat state is likewise not reconstructed from current population or arbitrary ancestors. Previously unattested propositions can begin with their first genuine prospective event.

`docs/chirality-stage-1.md` and the migration comment now document this approved prospective boundary, rather than requiring a historical reconstruction policy.

## Validation

Runtime: PHP 8.5.8, SQLite in-memory, 512 MiB limit; repository Core selection with separate `--exclude-group=integration`, `--exclude-group=query`, and `--exclude-group=load` arguments. Existing frontend build from unchanged frontend source was present. Core ran in isolated test-class processes; affected classes were rerun after integration fixes.

| Coverage | Result |
| --- | --- |
| Focused Stage 1 + Stage 2 | **39 tests, 160 assertions passed** |
| Final Core | **1,066 tests, 12,824 assertions: 1,052 passed, 9 assertion failures, 1 error, 4 skipped** |
| Migration/cursor integration regression check | **44 tests, 384 assertions passed**; also included in final Core |
| Pint and `git diff --check` | Passed |

Focused coverage includes: first cross-boundary interval with frozen timestamps; interval ownership and no vote/sample inheritance; one-hop and multi-hop pass-through; single-participant zero-spacing maturation; singleton zero; ordinary mixed-zero CV; both Reconcile edge orders with a newer other-parent beat; both edge orders with absent local cursor/no fallback; other-parent attenuation; unary cross-basin spacing; structural progression without interval growth; frozen already-born cursors; Committal cursor-only transport; Cycle completion immutability; and Stage 1 mutation/read invariance, real dispatcher/Reclassify and rollback coverage.

All six Stage 1 deferred-zero errors now pass: the four `AttestationPolarityTest` datasets, `BiasLineageTest` and `PropositionReceiptWeightTest`. No new unresolved implementation regression was observed in final Core.

### Remaining Core failures

The same eight old numeric/trajectory expectations remain unchanged:

| Test | Classification / observed change |
| --- | --- |
| `LineageAttenuationTest::test_raw_calculation_never_reads_or_reuses_accumulated_projections` | Old scalar: `-0.5` expected, `-0.1` observed |
| `ChiralityPopulationHistoryTest::test_attestation_and_replacement_are_observed_through_real_dispatch` | Old fresh-node scalar: `-0.5` expected, `-0.1` observed |
| `ObservableServiceTest::test_matching_lineages_in_complementary_horizons_align` | Old alignment scalar: `+1` expected, `0` observed |
| `ClosureHysteresisTest::test_read_only_bootstrap_preview_matches_actual_commit_while_field_remains_in_settlement` | Old Chirality assertion: `-0.5` expected, `-0.3622449` observed; Closure formula unchanged |
| `ClosureHysteresisTest::test_negative_closure_alignment_can_commit_while_corrected_field_remains_in_settlement` | Old Chirality assertion: `-0.3813817` expected, `-0.312583` observed; Closure formula unchanged |
| `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` | Old catastrophe trajectory: Charge `0` expected, `0.2666666` observed |
| `HomologousBiasTest::test_zero_retains_population_history_and_does_not_mutate_measurements_or_state` | Fixture's assumed earlier Negative population direction is not established under vNext |
| `HomologousBiasTest::test_reclassification_result_uses_ordinary_measurement_without_operand_coherence_constraint` | Old v2 result direction: Negative expected, Positive observed |

Two unrelated SQLite cases previously reproduced on unchanged main remain:

- `CausalHorizonLineageTest::test_cycle_ownership_is_immutable_in_bulk_sql`: PostgreSQL immutability trigger is absent in SQLite (assertion failure).
- `Api/ParticipationHttpReadGuardTest::test_diagnostics_accept_read_queries_but_reject_data_modifying_ctes`: PostgreSQL query syntax unsupported by SQLite (error).

The four skipped PostgreSQL-dependent cases are unchanged from Stage 1. No trajectory fixtures were modified or populations/timing tuned to make Core green. The Core results are local SQLite coverage, not a claim of a green PostgreSQL CI run.

## Exact Stage 2 changes

- Deleted `app/Exceptions/DeferredChiralityCadenceBoundary.php`.
- Updated `app/Models/Cycle.php`.
- Updated `app/Services/LedgerService.php`.
- Added `app/Services/Observables/AttestationBeatCursorService.php`.
- Updated `app/Services/Observables/LineageMeasurementService.php` (projection version).
- Updated `app/Services/Observables/LocalChiralityCalculator.php`.
- Updated `app/Services/Observables/LocalChiralityStateService.php`.
- Updated `database/migrations/2026_10_04_000000_create_local_chirality_states.php` (comment only).
- Added `database/migrations/2026_10_04_010000_record_attestation_beat_cursors.php`.
- Updated `docs/chirality-stage-1.md` (prospective-boundary clarification).
- Added `docs/chirality-stage-2.md`.
- Added `tests/Feature/AttestationBeatContinuityTest.php`.
- Updated `tests/Feature/PropositionLocalChiralityTest.php`.
- Updated `tests/Unit/LocalChiralityCalculatorTest.php`.

Stop for Stage 2 review. Stage 3 has not begun.
