# Stage 3A validation

Base: `d22aa6b0f592d5741f9a573150b3feafe956a954`, merged Stage 2D.
The merge tree is identical to Stage 2D's final documentation commit; no production
or shared database records are used in validation.

## Established baseline

| Suite | Result and cause |
| --- | --- |
| [Merged-main Core](https://github.com/miranovadev/commons/actions/runs/39957303899) | 1,344 passed, 1 failed; 14,300 assertions. `ChargeBoundaryTragedyTest` fails at `NaturalBoundaryHistory.php:63`: expected Settlement, actual Emergence. |
| [Stage 2D Integration](https://github.com/miranovadev/commons/actions/runs/39950243880) | 31 passed, 3 failed; 9,337 assertions. One identical natural-boundary cause; two aligned-policy `FreshBootstrapTest` cases exhaust their driver budget without an admissible Commit. Last Bias eligibility is `component_directions_disagree` (orientation positive, cadence negative, Chirality positive). |
| [Merged-main Phase Profile](https://github.com/miranovadev/commons/actions/runs/39957303795) | All 12 jobs pass. |
| [Stage 2D C3](https://github.com/miranovadev/commons/actions/runs/39950243789) | 107 tests pass; 2,358 assertions. |

The Stage 2D Integration/C3 runs validated the same application/test tree as this
base; only their final report documentation was added subsequently.

## Verification scope

Focused extraction tests cover HTTP(S), relative and redirected bases, query strings,
Unicode, entities, empty/nested labels, malformed HTML, unsupported/invalid schemes,
deduplication, count/text/URL/JSON limits and explicit supplementary failure.
Real verification/persistence checks retain exact Artifact bytes, fingerprints,
signatures, seven-day stability, duplicate suppression and original invocation
provenance. No extra HTTP request is made for extracted destinations.

Loaded/unloaded C2/C3 tests retain causal scope and snapshot structural history.
Multiple loaded collections remain bounded and appear only once in the prompt.
Existing Instrument allowance, Phase neutrality, Reclassification consumption,
Preparation and terminal/measurement contracts are included in validation.

The obsolete assertion expecting anchor text to remain absent from loaded evidence
now requires that bounded text exactly once, while still excluding Artifact bodies,
scripts and private storage paths. Independent assertions remain intact.

## Results

Local focused validation: **226 tests, 1,491 assertions pass**, including retained
Stage 2A/2B/2D contracts. Frontend: **134 tests pass**; TypeScript and production
build pass. Pint and whitespace checks pass.

A manual bounded-resource check used 2,097,137 HTML bytes containing 62,006 unique
anchors. The extractor reported all 62,006, retained 25 in 1,391 JSON bytes and
marked truncation. On the local PHP 8.5 runtime it took 2.92 seconds, with a
25,694,208-byte PHP peak and 95,552 KiB process peak RSS. This is a measured extreme
input check, not a timing assertion or a guarantee for every deployment.

Validated implementation: `3e1fbfd45d5752e6b205a730611daf39782fcd10`.
The final report commit changes this document only; application and test code are
unchanged from the completed runs below.

| Check | Result |
| --- | --- |
| [Core / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/39960930501) | All 1,385 cases executed: **1,384 passed, 1 baseline failure**, 14,556 assertions. All five shards completed. |
| [Integration / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/39960980614) | **31 passed, 3 baseline failures**, 9,337 assertions. Summary confirms all 34 discovered cases executed with no skips. |
| [Phase Profile / SQLite and PostgreSQL](https://github.com/miranovadev/commons/actions/runs/39960930439) | **All 12 jobs passed**, including canonical committed-history and lifecycle checks. |
| [C3 context](https://github.com/miranovadev/commons/actions/runs/39960980414) | **108 passed**, 2,414 assertions, including the causal-context comparison. |
| [Frontend](https://github.com/miranovadev/commons/actions/runs/39960930501/job/120438476836) | **134 passed**; TypeScript and production build passed. |
| SQLite portability | **14 passed**, 46 assertions, separately from PostgreSQL Core totals. |
| Focused local contracts | **226 passed**, 1,491 assertions. |

### Failure comparison by actual cause

- **Natural-boundary fixture:** Core `ChargeBoundaryTragedyTest` and Integration
  `NaturalChargeBoundaryLifecycleTest` still fail at
  `tests/Fixtures/NaturalBoundaryHistory.php:63`. The assertion requires Settlement
  while the constructed Commit actually enters Emergence. Both the assertion and
  observed value match the baseline. No mathematical condition was changed.
- **Two aligned-policy bootstrap journeys:** `FreshBootstrapTest` still exhausts
  101 driver iterations without an admissible Commit. Both cases contain the same
  107-action trace as Stage 2D, including six Prepare/contributed-Variant pairs,
  ending at Cycle 25 / Constrain Reclassify. The complete action arrays and final
  Bias diagnostics were compared directly, excluding only the generated Gate ID;
  they are identical. Bias still reports `component_directions_disagree`, with
  positive orientation, negative Attestation cadence and positive Chirality.

No new failing case or changed failure cause remains in the requested suites.
Stage 3A did not require lifecycle fixture reconstruction or relax structural,
causal, mathematical, consumption or action-eligibility assertions.

Unsupported HTML reference mechanisms and the explicit extraction limits are
documented in the Stage 3A contract. No automatic crawling, new Instrument,
Preparation transition, global prompt-limit increase or database migration was
introduced. No production/shared database records were modified.
