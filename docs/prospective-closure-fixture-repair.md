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

Final result inventory and run links are recorded below after complete execution.
The earlier [Stage 3 report](prospective-closure-validation.md) is the pre-repair
checkpoint, not the outcome of this follow-up. Stage 2 remains 1,125 Core cases:
1,117 passed and eight failed. Stage 3 retains those cases and adds 15.
