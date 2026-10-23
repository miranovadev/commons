# Stage 2D validation

## Scope and baseline

Base: merged Stage 2C, `7d0eaa37157a4bf29fb463586ffbaedd8563b04c`.
No production/shared database was accessed. Reading/inference tests use fresh isolated
schemas so their operational I/O runs outside database write transactions.

The recorded Stage 2C baseline is:

| Suite | Baseline result | Actual failure cause |
| --- | --- | --- |
| [Core](https://github.com/miranovadev/commons/actions/runs/39925035873) | 1,305 passed, 1 failed; 14,042 assertions | `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary`: `NaturalBoundaryHistory.php:63` expects Settlement but the constructed history enters Emergence. |
| [Integration](https://github.com/miranovadev/commons/actions/runs/39925040000) | 31 passed, 3 failed; 9,073 assertions | Two `FreshBootstrapTest` aligned-policy cases exhaust their 101-action budget at Cycle 23 / Mediate Rest, with no admissible Commit. `NaturalChargeBoundaryLifecycleTest` uses the same Settlement/Emergence fixture assumption as Core. |
| [Phase Profile](https://github.com/miranovadev/commons/actions/runs/39925036491) | All 12 jobs passed | None. |
| [C3](https://github.com/miranovadev/commons/actions/runs/39925039964) | 107 passed; 2,355 assertions | None. |

## Consequential fixture corrections

- Disperse structural tests now provide shared ordinary evidence when isolating
  Variant/Revise, retaining ownership, lineage, spentness and terminal assertions.
- Preparation source selection accepts consumed Receipts within the same causal
  catalogue; loading remains independent. Shared consumption still removes a
  Receipt from generation and Reclassification availability.
- The fixed skeptical HTTP transcript now reads preserved resources and completes
  five Preparations through Variant. It retains its 21 generated Proposition
  identities, actual continuation failures, Cycle positions and canonical geometry.
- Operational HTTP/reading journeys use fresh-schema isolation instead of a single
  outer test transaction. Production still rejects Artifact reads in write
  transactions; the tests do not disable this safeguard.
- PostgreSQL JSONB key order and unordered SELECT row order are not domain history.
  Assertions compare selection maps and deterministically ordered structural rows,
  preserving every field and value.

## Focused checks

Native reader, execution and interaction tests cover byte-preserving raw text,
HTML sanitisation, Unicode boundaries, explicit budgets, capability/source failures,
consumed-source accessibility, provenance associations, presented acknowledgements,
atomic Variant/Revise, deferment, stale ownership, failure and retry boundaries.

The PostgreSQL race tests use independent processes and assert actual Substrate
row-lock waits for competing starts and contributed-versus-deferred completion.
The complete suite includes existing Instrument allowance, replay, provenance,
Reclassification, mathematical and cognitive-context invariants.

Frontend: 132 tests pass; TypeScript and production build pass. The actual React
presentation escapes source text, acknowledges after rendering and clears the
transient response on unmount. The workspace blocks resolution until presentation.

[Persisted examples and captured inference requests](examples.json) demonstrate
contributed/deferred ordinary Receipts, a failed execution without a Receipt, and a
later ordinary inference request without the previously prepared Artifact body.

The Core job budget is 12 minutes (previously 8). A measured run completed its
entire fourth shard successfully but crossed the old budget during job cleanup.
Fresh-schema reading tests retain real transaction boundaries; no test or
PostgreSQL statement timeout was removed.

## Complete validation

Final CI results and cause-level comparison are recorded here after all shards finish.
