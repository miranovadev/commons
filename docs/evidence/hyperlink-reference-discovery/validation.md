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

Full CI results and cause-level baseline comparison will be recorded after the
implementation run finishes.
