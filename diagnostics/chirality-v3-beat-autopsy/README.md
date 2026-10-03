# Attestation beat primitive autopsy

Diagnostic only, pinned to preserved direction autopsy commit `b9f6faacf007917f5135d123ec874100aedf389d`. No new history is authored. The same 33-action prefix and 34-action continuation are replayed, with archived actor/action/target/measurement assertions. The fixed population remains three genuine participants.

Interpretation A is the existing opt-in candidate, unchanged. Interpretation B is a separately instantiated `OccupiedLocalMeasurements`, never container-bound or persisted. For each local lineage it counts represented entries before each act, deduplicates that cumulative coordinate, differences consecutive coordinates, and applies the same developmental formula and maturity threshold. Origin remains constitutional; later comparative semantics are untouched. It does not group by owning Cycle ID, participant, timestamp, phase or target.

The real `LineageMeasurementService` is instantiated with B to apply ordinary attenuation to every relevant local component mean. The real `ProjectionService` gives field values. Orientation, Correspondence and Closure equality are checked at every paired observation. Direction histories use the real `ChiralityValues::resolveDirections` with unchanged entry/default fallbacks and per-proposition in-memory B history after each complete contribution.

The real `ChiralityGateService` and `PhaseGenerationService` evaluate an unsaved clone carrying the B measurement. Existing causal-source regional eligibility is checked by the real `BiasGateService::forPhase`, which depends on evidence, not the candidate interval stream. These are local-source predicate calculations on the same state, not execution, a rewritten global action plan, or a prediction of a future Commit. No hypothetical action is fed to the scheduler. Database writes during the dual observer are prohibited by a query listener.

## Reproduce

Use the repository's PHP 8.5/Composer and PostgreSQL testing environment:

```sh
tar -xzf diagnostics/chirality-v3-natural-rhythm/runtime-results.tar.gz -C diagnostics/chirality-v3-natural-rhythm
tar -xzf diagnostics/chirality-v3-grounding-continuation/runtime-results.tar.gz -C diagnostics/chirality-v3-grounding-continuation
tar -xzf diagnostics/chirality-v3-direction-autopsy/runtime-results.tar.gz -C diagnostics/chirality-v3-direction-autopsy
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-beat-autopsy/bootstrap.php diagnostics/chirality-v3-beat-autopsy/BeatAutopsyTest.php
python3 diagnostics/chirality-v3-beat-autopsy/report.py
```

The workflow also runs unchanged canonical act-count/population and direction-coherence contracts under the normal bootstrap in separate processes. The pre-existing deterministic external Hyperlink verifier is retained; no new measurement or grounding mocks are introduced.

## Deliverables

- `REPORT.md`: findings and semantic provenance.
- `ZEROS.md` and `zero-map.json`: every zero pair, with exact previous-specimen act/target IDs, actors, own Cycles and cumulative coordinates.
- `TRACE-A.md`, `TRACE-B.md`: full paired traces including unchanged selected boundaries and changed proposition observations.
- `control.json`: existing distinct-position matrix control, including archive hash.
- `runtime-results.tar.gz`: lossless `beat-trace.json` and `result-3-sparse_5.json` from the verified replay.
- `run-manifest.json`: code/run identities and validation counts.

New code: `BeatAutopsyTest.php`, `BeatContinuation.php`, `BeatRecorder.php`, `OccupiedLocalMeasurements.php`, `bootstrap.php`, `export.py`, `report.py`, and `.github/workflows/beat-autopsy.yml`. Existing production files, fixtures and diagnostic histories are unchanged. No PR or merge.
