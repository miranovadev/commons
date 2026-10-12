# Stage 3 fixture repair and regression validation

This follow-up changes test construction only. Production prospective Closure is
approved; its mathematics, event triggers, admissibility and strict missing-state
boundary are unchanged. No production file or migration is changed by this repair.
PR #255 remains unmerged for Claire's review.

## Construction findings

The original 554 `ClosureHistoryUnavailable` errors are grouped into 118 actual
construction paths in [the setup inventory](evidence/prospective-closure-fixture-repair/original-554-setup-groups.csv).
The accompanying six JSON files retain every test identity in each group. Grouping
uses the original exception stacks and the method containing each test-side stack
frame, rather than treating every occurrence of `stored()` as one root cause.
Direct setup inside a test remains identified separately.

The largest original paths include:

| Cases | Construction path | Finding |
| ---: | --- | --- |
| 38 | `receiptFixture → HomologousBiasTest::source → setUp` | Receipt observation reaches a direct expressive before authorised Closure exists; its committed fixture ancestry also requires repair |
| 32 | `HomologousMediationTest::phase → expression → pair → setUp` | A new Cycle observes an ordinary Committal constructed from another Committal, with no expressive source state |
| 29 + 16 | controlled Closure double → `ExhaustionFixture::phase`, including `commitFrom/exhaustedBranch` | Existing numerical isolation incorrectly demanded real historical state before returning its authored output |
| 23 | `InputPlanVisibilityTest::proposition` | Projection/Receipt happens before caller-supplied parents; some nodes have no governing entry |
| 16 | `governedPhaseFixture → AttestationPolarityTest::node → regionSeed` | Frozen state missing on a directly assembled non-Origin Committal |
| 14 | `ReinforcementTest::phase → setUp` | Existing expressive has an unledgered edge and no Closure initialization before a later governing Cycle |
| 14 | `receiptFixture → TragedyTest::proposition → fixture` | Receipt is created before parents and, in some scenarios, before later conversion of the node to a Committal |
| 13 | `loadedReceiptFixture → ClosureHysteresisTest::candidate` | Completed expressive births were not initialized before Receipt observation |
| 12 | `PhasePositionInvariantTest::phase → fixture` | The existing explicitly controlled field double was installed after the construction events it was meant to isolate |

These counts describe original paths, not mutually independent application defects.
The complete grouped inventory includes the smaller paths and all 31 direct-test
setup cases. No masked assertion is counted as passing.

## Corrections

- `PropositionBirth::complete` is an explicit construction hook after the real
  ledger birth and all supplied edges. It requires an existing immutable Phase
  Profile observation and initializes within a transaction. It neither creates
  ancestry nor repairs reads.
- Direct builders pass their known selected source to `LedgerService::record`.
  This records the immutable C1 observation at birth, not retrospectively from a
  later graph. Reinforcement records its already-existing causal edge in the
  ledger before initializing.
- PersistInstrument, Reinforcement, WriteOnce, AttestationPolarity and
  ClosureHysteresis helpers complete known expressive births before subsequent
  reads or governing Cycle entries. The AttestationDirectionChange and
  ParticipantContinuity builders follow the same ordering.
- `boundaryTragedyFixture` and `legacyTragedyClassificationFixture` complete their
  newly constructed expressive only after the known parent edge exists.
- `ExhaustionFixture` retains its pre-existing controlled numerical model. It
  completes births and copies the selected expressive output at its existing
  canonical fixture boundary. This does not demonstrate real Closure eligibility;
  these tests deliberately isolate exhaustion/authority behaviour.
- `controlledBirthClosureFixture` now isolates the whole-field persistence/event
  boundary as well as the numeric output. It writes no synthetic historical rows.
  Proposition-specific overrides continue to delegate actual birth/Cycle work.
  Frozen controlled canonical outputs are shared across captured mock instances,
  and preservation checks the current authored source output, including a later
  proposition-specific override. This fixes the Commit/wake case and stacked
  Exhaustion overrides without changing production preservation.
- PhasePositionInvariant installs its existing numerical double before building
  the fixture. WriteOnce's intentionally ungoverned rejection target explicitly
  skips measurement initialization; no governor or parent is invented for it.

Assertions, authored Integration transcripts, populations and numeric expectations
are preserved. No test is skipped or removed. Focused CI adds representative
fixture cases on PostgreSQL and SQLite. Contracts and fixtures run as separate
matrix jobs under the unchanged eight-minute limit: the combined PostgreSQL job
previously spent 83.41s + 18.42s + 167.66s + 162.97s in its four test commands,
leaving little room for setup or additional assertions. No timeout is increased.
The four Integration shards, isolation,
coverage accounting and 20-minute maximum remain unchanged.

## Boundaries retained for review

1. `CreatesHomologousPositions::commit`, `laterHorizonFixture` and the homologous
   Mediate fixture create non-Origin Committals directly from Committals. There
   is no expressive source to preserve. A faithful replacement needs a lawful
   authored lifecycle with its own grounded, coherent source. Copying the old
   Committal or inserting a synthetic expressive would conceal the missing history.
2. Several direct model fixtures create ungoverned ordinary expressives, attach
   parents only after measurement/Receipt creation, or change tier after birth.
   Examples are InputPlanVisibility, Tragedy, CommitParticipationState,
   PropositionMeasurementService and the search-history helper. CommittalClosure
   directly makes an ordinary Committal and reads Closure before any selected
   expressive source exists. LocalParticipantNavigation authors disconnected
   Committal snapshots and per-node field values; converting these into mature
   canonical history would require more than filling in an omitted birth call. The helper cannot
   infer a missing governor, selected source or completed multi-parent birth.
   Malformed governance tests must retain their deliberate malformed condition.
3. ClosureHysteresis's old controlled preview helper changes
   `ClosureBirthService::prospective` and Cardinal/Cadence values. That no longer
   changes the authorised Extent/Rhythm state. Porting its old direction and
   eligibility scenarios requires an explicit test-contract revision; no new
   values or eligibility were supplied here. Its legacy `history_source` field
   expectation likewise is not permission to add historical compatibility.
4. Four Integration Commit scenarios still stop at the documented Extent/Rhythm
   disagreement. The original ParticipationLocalEpoch transcript is unchanged.
   A separate lawful third-Variant scenario remains a review item.

These are fixture/scenario boundaries, not reopened measurement-design questions.
Other missing-state cases remain individually visible in the result inventory;
an exception alone does not prove their underlying application assertions correct.

## Validation record

Tested code head: `117e657c456d3f7856903458075507b6bd5cd141`.
The subsequent reporting commit changes only documentation and inventories.

| Validation | Complete result | Assertions | Runtime |
| --- | --- | ---: | --- |
| [Core](https://github.com/miranovadev/commons/actions/runs/38226078326) | **1,140 cases: 832 pass / 308 fail** (13 failures, 295 errors), no skips | 10,118 | **7m52s**, slowest PHP shard 355.18s |
| [Integration](https://github.com/miranovadev/commons/actions/runs/38226084432) | **31 cases: 19 pass / 12 fail** (11 failures, one error), no skips | 17,292 | **6m18s**, slowest PHP shard 322.94s |
| [Focused PostgreSQL and SQLite](https://github.com/miranovadev/commons/actions/runs/38226078278) | On each: 48 C1/history + 14 prospective Closure pass | 300 + 85 | PG 62.62s + 13.68s; SQLite 11.45s + 2.35s |
| Same workflow, observable/ledger group | On each: 97 pass / 43 fail | 1,353 | PG 127.65s; SQLite 19.71s |
| Same workflow, representative fixture group | On each: 70 pass / 13 fail | 969 | PG 103.26s; SQLite 43.61s |
| Frontend/UI and [Inspector browser](https://github.com/miranovadev/commons/actions/runs/38226084158) | Pass | — | Browser 1m28s |

Scoped Pint and whitespace checks pass. Core's overlapping SQLite preflight
remains 12 pass / six missing-history errors in CommittalClosureTest (45
assertions); it does not prevent complete PostgreSQL execution. These focused
commands overlap Core/Integration and must not be added to the suite totals.

The [summary](evidence/prospective-closure-fixture-repair/summary.json), per-test
Core/Integration CSVs, [before/after comparison](evidence/prospective-closure-fixture-repair/comparison-summary.json),
and [setup-path outcomes](evidence/prospective-closure-fixture-repair/setup-path-outcomes.csv)
retain exact accounting. Discovered, selected and JUnit-executed multisets match
for both complete suites. Workflow times use completed-run start/end metadata;
PHP times come from shard artifacts. CI remains red for the recorded failures.

### Before and after

| Checkpoint | Core passed / failed | Missing Closure history |
| --- | ---: | ---: |
| Stage 2 baseline (1,125 cases) | 1,117 / 8 | — |
| Stage 3 before repair (1,140 cases, `0e07262`) | 583 / 557 | 554 |
| First shared-double repair (`5114b03`) | 789 / 351 | 349 |
| Repaired preservation and Phase-position setup (`15d4c66`) | 827 / 313 | 298 |
| Final fixture repair (`117e657`) | **832 / 308** | **293** |

Of the original 554 masked cases, **248 now pass, 13 expose failures, and 293
remain masked**. The API atomic-advance case also now passes. All 583 previously
passing Stage 3 cases remain passing. No new test cases, skips or removed assertions
account for that improvement. The 15 Stage 3 additions to the Stage 2 Core
inventory are unchanged.

An intermediate run (`f1c57ac`) exposed two defects in the test double itself:
stacked overrides compared against an older captured value, and a previously
captured service could not see a controlled canonical copy. The shared frozen
output map and active-source comparison corrected both. The final runs restore
the affected Exhaustion and Commit/wake assertions; these were test-infrastructure
regressions, not production changes.

### Final failure classification

| Category | Core | Integration | Evidence |
| --- | ---: | ---: | --- |
| A — unchanged Stage 2 baseline assertion | 3 | 7 | Individual cases below and in CSVs |
| B — lawful Stage 3 trajectory or obsolete result wording | 5 | 4 | Two Cycle/Receipt cases, two Closure trajectories, one old error-text assertion; four documented Integration coherence blocks |
| C — confirmed genuine implementation regression | 0 confirmed | 0 confirmed | Masked assertions prevent ruling out hidden defects |
| D — missing birth/frozen fixture history | 293 | 1 | Exact failing setup frames retained; application assertions still masked |
| D — exposed legacy preview fixture contract | 7 | 0 | Old hook/fields no longer represent authorised Extent/Rhythm state |
| **Total failing** | **308** | **12** | No unclassified case is silently counted as passing |

The 13 newly exposed failures comprise one A, five B and seven legacy-contract D
cases. D is a construction-boundary classification, not a claim that every
underlying invariant would pass once its scenario is lawfully reconstructed.

Integration retains the same seven baseline cases: NaturalChargeBoundaryLifecycle;
two ParticipationLifecycleRegression transcripts; ParticipationLocalEpoch;
ParticipationRecovery successful revisit; ParticipationSourceEquivalence; and
ParticipationTragedyLifecycle. The four B cases are both FreshBootstrap polarities,
OperationalClosurePath and ParticipationHttpHarness. Each retains Extent 0 Negative
and Rhythm 0.1875 Positive as its sole Commit block. HomologousMediation's
admissible-Mediate/inadmissible-Bias scenario remains D, masking its prior baseline
assertion. All 31 full names are in the inventory. HorizonProofCycle and the other
18 passing cases complete, but the four blocked lifecycles **do not**.

Before repair, Stage 3 Core took 6m16s and Integration 6m44s; final runs take
7m52s and 6m18s respectively. More Core assertions now execute, so duration alone
is not a like-for-like performance benchmark. Integration stays below the
preferred 15 minutes and unchanged 20-minute maximum. No timeout, shard count,
isolation boundary or authored action budget was relaxed.

No production implementation defect has been demonstrated by this follow-up.
Full regression sign-off remains incomplete: the specific construction and
legacy-contract boundaries above require reviewable lawful scenarios, not
invented provenance, measurement fallback, or revised historical expectations.


## How to read the remaining failures

A successful formerly masked test means its original assertions executed to the
end. A missing-history exception still masks the intended invariant. A newly
exposed assertion failure is neither a pass nor automatically a production defect.

The eight Stage 2 Core failures must be tracked individually:

| Stage 2 failing case | Follow-up disposition |
| --- | --- |
| ChargeBoundaryTragedy, ordinary unmocked boundary | Same Settlement/Emergence assertion (A) |
| ChiralityPopulationHistory, dispatched Attestation replacement | Same expected -0.5 / actual -0.1 (A) |
| ClosureHysteresis, read-only bootstrap preview | Same expected -0.0964505 / actual 0.1265986 (A); now unmasked |
| ClosureHysteresis, negative alignment | Commit executes, but now hits the earlier `closure_boundary` assertion (B); the old numerical assertion has not executed |
| ObservableService, matching complementary lineages | Still masked by missing authorised state (D) |
| HomologousBias, zero retains population history | Still masked (D) |
| HomologousBias, ordinary reclassification result | Still masked (D) |
| LineageAttenuation, raw calculation | Still masked (D) |

The newly exposed legacy-contract cases are not repaired by changing expected
numbers. `zero_depth`, `unrelated_newer_snapshot`, the three failing
`order_native_preview` data rows, and `admissible_preview_cannot_authorize_commit`
all depend on the obsolete preview double or its Cardinal/Cadence gate.
`legacy_missing_components` expects the old `history_source` preview field.
A new authorised Extent/Rhythm scenario is required before these can establish
their intended invariants. In particular, the stale-state test changes the old
calculator hook, not stored prospective state; production stale-state regression
coverage remains the separately passing focused tests.

`actual_candidate_rejection` reaches the intended rejection but fails on obsolete
error wording before its subsequent rollback assertions. Those later assertions
are not counted as validated. The two Reinforcement pole cases create genuine
new governing Cycles while loading evidence: their historical Closure diagnostics
change at that authorised trigger. A Receipt-only test must separate the Cycle
entry from its before/after measurement, without suppressing the event.

The source-polarity Commit fixture is a further **B** case, not an unexplained
schema error. Failure-only diagnostics expose Historical Phase Profile 0.1428571,
Historical Orientation -0.5, Extent 0.0714286 Positive and Rhythm -0.25 Negative.
The [recorded eligibility output](evidence/prospective-closure-fixture-repair/polarity-commit-eligibility.json)
agrees on PostgreSQL and SQLite. Depth and Cadence are unavailable zero. The only rejected requirement is Closure
(`extent_rhythm_directions_diverge`); every other requirement passes. Its later
polarity-preservation assertions do not execute. Supplying different measurements
or extra history merely to force this authored candidate to Commit is excluded.
