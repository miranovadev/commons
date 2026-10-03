# Chirality direction autopsy (diagnostic only)

Pinned parent: `a8a39fdee06431c14d7882f29c93ff01473542d2`, the preserved lawful grounding continuation. This experiment does not alter production code, authored placements, participants, candidate formula, coefficients, hysteresis, admissibility or existing fixtures.

`DirectionAutopsyTest.php` copies the existing scheduler and replays its first 33 actions, comparing them against the natural-rhythm archive. `DirectionContinuation.php` copies the existing continuation policy unchanged, stopping after its first 34 contributions. The test compares actor, Cycle, Phase, input, bijective target IDs, intervals, measured field values and directions against the grounding archive. It stops before the archived continuation step 34 (fully grounded Bias), without performing another authored action.

`DirectionRecorder.php` listens to ordinary measurement/history SQL writes and reads post-contribution table snapshots. Its inspection calls the unchanged candidate's diagnostic calculation, with an assertion that capture adds no measurement/history writes. It never persists calculated values. SQL records preserve intermediate observations as well as post-action direction changes.

The existing opt-in `CandidateLocalMeasurements.php` remains the diagnostic candidate from the earlier experiment. Production lifecycle, projection, attenuation, grounding and direction services are used. The conventional test Hyperlink verifier remains the pre-existing external-evidence fixture; this run introduces no measurement or grounding mocks. Three genuine actors remain fixed.

## Reproduce

With the repository's PHP/Composer and PostgreSQL test environment:

```sh
tar -xzf diagnostics/chirality-v3-natural-rhythm/runtime-results.tar.gz -C diagnostics/chirality-v3-natural-rhythm
tar -xzf diagnostics/chirality-v3-grounding-continuation/runtime-results.tar.gz -C diagnostics/chirality-v3-grounding-continuation
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-direction-autopsy/bootstrap.php diagnostics/chirality-v3-direction-autopsy/DirectionAutopsyTest.php
python3 diagnostics/chirality-v3-direction-autopsy/report.py
```

The dedicated Actions workflow also runs the canonical coherence, zero-retention, and read-only component tests under the ordinary bootstrap in separate processes. The repository's normal Core workflow runs automatically on branch pushes.

## Artifacts

- `REPORT.md`: interpretation, exact boundary identity, semantic provenance and classification.
- `BOUNDARIES.md`: all selected boundaries through the fully grounded Bias refusal.
- `DIRECTION-LINEAGE.md`: all changed persistence snapshots, with previous/current directions, intervals and maturity.
- `runtime-results.tar.gz`: lossless `result-3-sparse_5.json`, `direction-records.json` and continuation checkpoint. Extract here to inspect raw IDs, SQL observations and every lifecycle predicate.
- `run-manifest.json`: pinned commits, workflow and validation result.
- `export.py`: lossless compressed log transport; `report.py`: rendering only, no field reimplementation.

Indices are zero-based. Different replays assign fresh database IDs; the action history and numeric states are compared to the archive. No PR was opened.
