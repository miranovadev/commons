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

Validated implementation: `faea4aab07330e27edc37fb627c07f083383d750`.
The subsequent commit only records these results and clarifies the historical
Stage 2C selection documentation; application and test code are unchanged.

| Validation | Result |
| --- | --- |
| [Core / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/39950238557) | All 1,345 cases executed: **1,344 passed, 1 baseline failure**, 14,300 assertions. All five shards completed. |
| [Integration / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/39950243880) | All 34 discovered cases executed: **31 passed, 3 baseline failing cases**, 9,337 assertions. The summary confirms complete coverage with no omitted cases. |
| [Phase Profile / SQLite and PostgreSQL](https://github.com/miranovadev/commons/actions/runs/39950238435) | **All 12 jobs passed**, including lifecycle, committed history and final-review contracts. |
| [C3 context](https://github.com/miranovadev/commons/actions/runs/39950243789) | **107 passed**, 2,358 assertions, including the causal-context comparison. |
| [Frontend](https://github.com/miranovadev/commons/actions/runs/39950238557/job/120402631655) | **132 passed**; TypeScript and production build passed. |
| [PostgreSQL Preparation races](https://github.com/miranovadev/commons/actions/runs/39950238557/job/120403080786) | Both competing-start and competing-resolution tests passed with observed lock contention. Included in Core above. |
| SQLite portability checks | **14 passed**, 46 assertions, in the first Core job, separately from the PostgreSQL totals. |
| Local focused Stage 2A/2B/2D checks | **95 passed**, 620 assertions: readers, Preparation execution/interaction, Instrument Phase neutrality and Receipt-governed generation. |
| Formatting | Pint and `git diff --check` passed. |

### Remaining failures, compared by cause

1. **Constructed natural-boundary fixture: unchanged baseline cause.**
   Core's `ChargeBoundaryTragedyTest` and Integration's
   `NaturalChargeBoundaryLifecycleTest` both fail at
   `tests/Fixtures/NaturalBoundaryHistory.php:63`: the fixture requires its first
   constructed Commit to enter Settlement, while its actual committed direction is
   Emergence. This is the same assertion and actual value as merged Stage 2C.
   The mathematical assertion remains intact; Stage 2D does not alter the
   measurements or force the desired direction.
2. **Two aligned-policy bootstrap journeys: established failing policies, changed
   lawful chronology.** Both `FreshBootstrapTest` cases still exhaust 101 driver
   iterations without an admissible Commit. Stage 2C's trace had 101 actions and
   ended at Cycle 23 / Mediate Rest. The Stage 2D trace has 107 actions because six
   iterations now perform Prepare plus its prepared Variant; it ends at Cycle 25 /
   Constrain Reclassify. Their last Bias eligibility reports
   `component_directions_disagree`: orientation positive, Attestation cadence
   negative, Chirality positive. Preparation is being selected, delivered and
   resolved; this is not missing evidence or an unfinished execution blocking the
   journey. These traces are explicitly not claimed to be identical to the
   baseline. No cadence, admissibility or Commit assertion was weakened to force
   these policies to succeed.

No additional failing case remains in the requested suites. The reauthored
skeptical canonical-Commit/source-equivalence journey, Exploration Epoch renewal,
ordinary Receipt consumption, recovery and C3 checks pass. Intermediate failures
from obsolete Disperse fixtures, strict transport selection parameters and
unordered PostgreSQL snapshots were corrected without removing their independent
structural assertions.

Initial local full Integration ran all 34 cases and exposed the source-equivalence
transport fixture correction as well as the three baseline cases. Its corrected
test subsequently passed locally with 1,859 assertions, and the complete final
PostgreSQL run above confirms the resulting 31/3 suite result. Local SQLite alone
is not used as evidence of PostgreSQL concurrency correctness.
