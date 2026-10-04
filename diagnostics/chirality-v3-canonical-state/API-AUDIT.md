# Canonical component API audit

Source revision: `1274cbf934f74061f578a540740ff34513846451`. No production implementation was changed. “Compatible” means the reader can preserve supplied frozen values; it does not mean the candidate has been integrated into the live storage pipeline.

| Service/API | Existing behavior | Candidate treatment / evidence |
| --- | --- | --- |
| `ChiralityValues::average()` | Means its two **local** components; has no independent resolved aggregate | Not a canonical aggregate calculator. `(0.5 + 0)/2 = 0.25`, not `0.3156786`. Keep aggregate separate; canonical branch required wherever this mean would supply C1's field contribution |
| `ChiralityValues::resolveDirections()` | Nonzero sign, then caller-supplied prior/default at zero | Do not call to recreate C1 component directions; copy B's resolved strings |
| `LocalMeasurements` | Carries aggregate scalars separately from optional head-component objects | Can represent distinct scalar/component scopes, but component type/documentation says local. Canonical scope/provenance must be explicit |
| `LocalMeasurementService::calculate/chiralityComponents` and `ChiralityCalculator::components/leanFor` | Raw expressive/current-history evaluation, including source-history evidence | Canonical-boundary routing required. Do not use these to derive C1's preserved components |
| `LineageMeasurementService::calculate/prospectiveCommit` | Rewalks incoming ancestry for a Committal head; aggregates raw values; returns head-local components | Preserve source scalars and resolved metadata at Commit, separately obtain Closure. Existing descendant walk can keep its normal distances and stop at canonical C1 |
| `LineageMeasurementService::chirality` | Calls each member's component `average()` to obtain its scalar | Explicit canonical scalar branch required. Returning canonical metadata through a plain component object alone would silently use `0.25` instead of `0.3156786` |
| `ObservableService::components/calculate` | Delegates to lineage calculation | Inherits the preceding canonical-routing requirement; no independent snapshot guard |
| `PropositionMeasurementService::recalculate` | Existing Committal snapshot takes precedence over raw calculation | Compatible once canonical snapshot is correctly authored. Pre-snapshot birth path still needs canonical observation input |
| `PropositionMeasurementService::project` | With snapshot, copies frozen component values/directions. Without snapshot, invokes `componentAttributes` for supplied local components | Existing-snapshot path compatible. First canonical publication must preserve explicit source directions rather than resolve them for C1 |
| `PropositionMeasurementService::observeMissing` | Snapshot branch copies frozen scalar/component diagnostics; otherwise calculates raw | Compatible snapshot branch; no silent reconstruction of missing historical component metadata |
| `PropositionMeasurementService::observeChirality` | Returns a Committal snapshot scalar immediately; otherwise calculates components and observes their directions | Compatible after canonical snapshot exists; before snapshot needs canonical branch |
| `PropositionMeasurementService::componentAttributes` | Resolves directions using own observations, entry snapshot, then defaults | Keep for expressive observations. Bypass at canonical preservation; do not rerun zero hysteresis for C1 |
| `PropositionMeasurementService::chiralityAttributes(..., components: null)` | Reads values/directions from the supplied measurement view | Direct runtime probe: full canonical metadata retained unchanged; no SQL write |
| `ChiralityGateService::evaluate` | Reads projected component directions and compares them independently of scalar Chirality | Direct runtime probe: scalar-only unavailable; full canonical Positive/Negative disagreement. It does not infer component signs from positive scalar |
| `CommitmentSnapshotService::record` | Freezes scalar and component fields from the measurement after independent grounding/Closure checks | Existing storage shape accepts the fields. Candidate must supply a correctly preserved measurement before record; no need to copy Attestations |
| `CommitmentSnapshot` / `PropositionMeasurement` | Store scalar and component fields independently, with nullable component diagnostics | Sufficient numeric fields; distinguish canonical preserved metadata from expressive local state by scope/provenance |
| `PotentialProjectionService` snapshot rebuild path | Copies snapshot scalar and component metadata; expressive path calls `chiralityAttributes` | Compatible frozen reader; depends on correctly authored canonical snapshot and expressive routing |
| `GraphProjectionService` | Prefers immutable Committal snapshot for scalar field publication | Compatible; does not rebuild component means |
| `PropositionResource::toArray` | Publishes loaded scalar observables; does not expose numeric O/AC fields in its ordinary observables object | Direct runtime probe confirms preserved scalar publication. A component display would need an explicit canonical label; this resource currently is not a numeric component API |
| `HomeostaticDirectionService` | Aggregates scalar measurements; Committal snapshot can supply scalar | No component inheritance/recalculation required |
| `ChiralityPopulationHistoryService` | Uses `observeChirality` and separate population scalar history | Snapshot shortcut is compatible. Population direction is not a substitute for preserved component directions |
| `InputPlanService` | Adds ChiralityGateService diagnostics for Bias | Direction consumer via the Gate API; no new equation or threshold |
| `ParticipantContextSerializer`, `ParticipantPromptLayout` | Forward the plan's Chirality admissibility result | Compatible forwarding; no local recalculation |
| `resources/js/types.ts`, `ParticipationNotices.tsx` | Consume nullable component directions and backend admissibility reason | Compatible with preserved directions; no inference from scalar required |

## Evidence establishing the scopes

`ChiralityValues` explicitly documents “Local components only: their mean, never their separate values, is attenuated.” `LineageMeasurementService::calculate()` retains the distance-zero component object while independently accumulating Chirality across all positions. `docs/observables.md` likewise distinguishes inspectable local components from the attenuated combined scalar and states that frozen snapshots preserve component values/directions.

`LineageAttenuationTest::test_preview_and_actual_committal_use_identical_distances` and `test_raw_calculation_never_reads_or_reuses_accumulated_projections` remain the existing hard-edge contracts that the earlier preservation candidate intentionally differs from. This task adds no new attenuation change.

`tests/Unit/ChiralityValuesTest.php` and `tests/Feature/ChiralityV2Test.php` establish zero-direction retention and immutable snapshot diagnostics. Full canonical preservation must copy the resolved source direction, not recreate it from a zero Cadence value.

The consumer probes are read-only, in-memory input views—not replacement database measurements, fake grounding, or new lifecycle actions. Other entries above are source audits, not claims of end-to-end candidate integration.
