# Current-source grounding versus historical Cadence

Diagnostic-only continuation of the preserved three-author `sparse_5` history.
Base: `5eb40cd8569edeb0ea8b41fd8f79c8318c067460`.

The first 33 actions (archived steps 0–32) are replayed unchanged and asserted
against the earlier archive, including participant/Cycle/Phase, spacing, occurrence
order and a bijective target-ID mapping. This stops immediately before the first
mature `[5,5]` Constrain observation. All three ready participant plans are retained.

A separate continuation then selects A1's existing `attestation.create` on the
Persist Write that is blocking A2 at Constrain. Every contribution uses
`ParticipationService::contribute` with an action and target from the real current
schema. No Phase, Cycle, Receipt, Attestation, measurement or result proposition is
inserted directly during the continuation. The initial three-participant/Origin
setup is exactly the previous diagnostic's setup.

The fixed continuation policy first executes any available Commit and stops.
Otherwise it finds a lawful peer action addressing an exact ungrounded source at
Constrain, Bias or Order, then uses the fixed priority Reclassify, Reconcile,
Traverse, Support Attestation, Instrument, Variant, Rest. Offers are sorted by
Phase descending then actor index. It never consults field values, Cadence or CV.
It does not flip a prior Support to Refute, repeat an Instrument in one Phase,
or create a Rest-only padding Cycle. At most 80 additional contributions are
allowed. A bound or exhausted policy is not a universal reachability conclusion.

New genuine Attestations are included normally in historical Cadence. Nothing
freezes the earlier +1 value or the positive field. The existing diagnostic
`CandidateLocalMeasurements` remains unchanged; all other operations, grounding,
Correspondence, Closure, attenuation and projection are production code. The
repository TestCase's deterministic hyperlink verifier is retained, as in the
original specimen; no field or grounding value is mocked by this diagnostic.

Files:

- `GroundingContinuationTest.php`: exact prefix replay and initial grounding assertions.
- `GroundingContinuation.php`: field-blind schema-selected continuation and before/after evidence.
- `bootstrap.php`: reuses the boundary inspector and existing diagnostic candidate.
- `export.py`: lossless gzip/base64 result transport.
- `report.py`: runtime evidence rendering, without independent calculator formulas.
- `SCOPES.md`, `REPORT.md`, `TRACE.md`, `run-manifest.json`, `runtime-results.tar.gz`: report and evidence.
- Workflow `.github/workflows/grounding-continuation.yml`: one continuation and narrowly selected canonical contract tests.

The canonical tests run in separate PHPUnit processes under their normal bootstrap.
Some canonical tests deliberately use constructed fixtures/controlled Closure to
isolate contracts; their passing is not presented as a naturally authored field
witness. Those helpers are not used in this continuation.

Run from the repository root with test dependencies and PostgreSQL configured:

```sh
tar -xzf diagnostics/chirality-v3-natural-rhythm/runtime-results.tar.gz -C diagnostics/chirality-v3-natural-rhythm
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-grounding-continuation/bootstrap.php diagnostics/chirality-v3-grounding-continuation/GroundingContinuationTest.php
python3 diagnostics/chirality-v3-grounding-continuation/report.py
```

No application code, existing diagnostic candidate, fixture or admissibility rule
is changed. No PR or merge is requested or opened.
