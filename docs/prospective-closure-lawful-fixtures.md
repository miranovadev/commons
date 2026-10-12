# Stage 3: lawful fixture continuation

This continuation starts at reviewed report `8b86d55`: Core 887 passed / 253 failed, including 246 missing-history errors; Integration 19 passed / 12 failed. The existing setup-path inventory and [fixture follow-up](prospective-closure-fixture-followup.md) remain the baseline. No measurement semantics were reopened.

## Corrections and acceptance criteria

### Governed expressive birth and projection isolation

`GovernedExpressionFixture` uses the real `CycleEntryService` constitutional bootstrap, its actual live Distinguish Phase, and `ContributionDispatcher` to create the first expressive. It supplies no measurement double, copied Committal, invented parent, manually authored snapshot, or forced Domain transition. The dispatcher records the birth, selected temporal source, causal edge and prospective measurement in its normal transaction.

`CommitParticipationStateTest::test_new_phase_touches_dirty_the_region_but_completion_is_not_another_touch` now uses that birth. Its original dirty-region, projection-version and touch-count assertions are unchanged. The old ungoverned Order construction is initialized only by the historical graph tests that need it. An intermediate run exposed an unrelated empty substrate left by unconditional setup; removing that unnecessary setup fixed the test without changing its assertion or production invalidation.

`PropositionMeasurementServiceTest::test_live_measurements_are_replaceable_but_closure_comes_from_birth` is now explicitly an isolated projection test. Its -1 is a controlled authorised service output, not an inferred constitutional value for an ungoverned ordinary expression. The original numeric, replacement and no-Proposition-write assertions are unchanged. This one recovery is a clarified projection contract, not a reconstructed lifecycle.

The separately named `test_real_origin_and_developmental_projections_preserve_their_own_authorised_state` checks the real Origin and dispatcher-born expression. It verifies constitutional -1, distinct ordinary state, genuine governance/parent/birth observation, and preservation of each stored Closure despite contradictory caller-supplied projection values. It uses no measurement control.

### Presentation birth ordering

`PropositionPresentationTest::node` records the already-authored selected source, gives its edges the result's producing Phase, and initializes expressive measurements after the complete parent set. The Reconcile display test now supplies both of its existing parents before initialization. Its hydration-order reversal and primary-source assertions remain unchanged; it does not add a late causal parent to an already measured birth.

The original assertions now execute in:

- `test_display_counts_keep_inherited_evidence_in_its_original_frame_and_refresh_after_replacement`;
- `test_zero_and_tied_counts_expose_the_existing_retained_direction_without_context_queries`;
- `test_reconciliation_display_source_is_stable_when_hydration_order_changes`.

These are presentation-consumer fixtures. Their existing authored display snapshots are not evidence that a complete ordinary Commit lifecycle succeeded. Real Commit preservation is covered separately below.

### Separately named lawful operation scenarios

`PropositionHistoryLifecycleTest::test_authorised_operation_resets_judgments_and_touches_without_copying_source_history` has two cases: **Persist Variant** and **Constrain Reclassify**. They start from `GovernedExpressionFixture`, use actual Instrument and Attestation actions in the required evidence-first order, and use the real dispatcher and Phase progression.

Their equivalent fresh-history acceptance criteria are: correct expressive tier/register/statement and causal source/edge strength; fresh judgment and touch identity; no copied Attestations or Instrument executions; the explicitly attached Receipt for Reclassify only; unchanged source history and authorised Closure; and a new touch only when progressing from the new result. Both cases execute real measurement initialization.

The six historical `PropositionHistorySemanticsTest` cases remain unchanged. The two new cases cover Variant/Reclassify freshness; they do **not** replace its ordinary-Committal, Revision, Mediate or Bias construction requirements. A broader Origin substitution was not retained: complete storage alone would not make all of that old graph's producing operations lawful.

## A genuine ordinary Commit

`CommitSourceHistoryLifecycleTest::test_commit_preserves_a_genuine_expressive_source_and_its_evidence_references` is a separately authored Integration scenario: constitutional **Support**, with **Refute preferred for ordinary expressive Attestations**. The existing driver selects only actions and operands offered by the actual participation schema and contributes through `ParticipationService`. Its 101-action budget matches the historical full-cycle cases. It uses real Correspondence, Chirality and prospective Closure; only the existing external Hyperlink verifier is deterministic test infrastructure.

The scenario reaches a real Commit and verifies the genuine expressive source, canonical source identity, statement, exact authorised source Closure, frozen snapshot observation, inherited evidence references, and absence of copied Attestations/Instrument executions on the Committal. No eligibility, measurements, population records or causal links are manufactured.

The new `expressiveDirection` option defaults to the old Origin-aligned choice. Existing driver callers and all original Integration transcripts retain their policy. A read-only `recordAction` callback captures the successful actual action inputs, creating Cycle/Phase positions and result identities. The retained `lawful-commit-history.json` contains that transcript and the resulting real Closure observation/snapshot. It is evidence from execution, not a synthetic history seed or a claim that raw database insertion can replace replay.

The final PostgreSQL transcript has **89 successful actions**. Its source retains Extent **0 / Negative**, Rhythm **-0.1875 / Negative**, and Closure **-0.09375**. Historical Orientation is -0.375 over four observations (including a genuine zero); the seven Historical Phase contributions are `[0,-1,-1,1,1,-1,1]`. Depth/Cadence are explicitly unavailable. The snapshot preserves the same authorised observation and remains in Settlement. These are observed results, not numerical inputs supplied by the fixture.

This establishes **one** ordinary source-preserving Commit. It does not establish repeated alternating Horizons, two separate homologous regions at equal depth, arbitrary requested Domain flips, or a transitive Committal-to-Committal Commit. The historical ParticipationLocalEpoch transcript remains untouched and still lacks a separately validated third-Variant replacement.

## Validation and accounting

**Tested code head: `111583715db7b16a3ada52baf0f698e40823bf3e`.** The subsequent reporting commit changes only documentation/evidence; the results below refer to this code head.

| Complete suite | Before | Final | Assertions | Workflow wall time |
| --- | --- | --- | ---: | --- |
| [Core](https://github.com/miranovadev/commons/actions/runs/38295670731) | 887 pass / 253 fail | **895 pass / 248 fail** | 10,772 | **9m19s** |
| [Integration](https://github.com/miranovadev/commons/actions/runs/38295678417) | 19 pass / 12 fail | **20 pass / 12 fail** | 17,399 | **9m42s** |

Both completed with exact discovered/selected/executed identity multisets and zero skips. All **887 previously passing Core identities** remain passing. Five previously masked original cases now pass, and three new Core cases pass. All 31 original Integration outcomes are unchanged; the separately named genuine Commit passes. No newly exposed failure or newly failing checkpoint pass occurred in this continuation.

The original 554 missing-history cases now comprise **308 passing / five exposed failures / 241 still masked**, compared with 303 / five / 246 at the checkpoint. Stage 2 remains 1,117 pass / eight fail from 1,125 cases; changes in test population and masked baseline failures prevent a simple pass-count comparison from establishing compatibility.

[Focused PostgreSQL/SQLite workflow](https://github.com/miranovadev/commons/actions/runs/38295670764):

| Selection | PostgreSQL | SQLite | Assertions PG / SQLite | PHP seconds PG / SQLite |
| --- | --- | --- | ---: | ---: |
| Phase Profile C1 | 48 pass | 48 pass | 300 / 300 | 69.92 / 20.72 |
| Prospective Closure | 14 pass | 14 pass | 85 / 85 | 15.24 / 4.18 |
| Observable/ledger | 98 pass / 42 fail | 98 pass / 42 fail | 1,359 / 1,359 | 138.70 / 34.67 |
| Expanded fixture representatives | 148 pass / 15 fail | 147 pass / 15 fail / one existing skip | 1,749 / 1,746 | 232.31 / 61.20 |
| New genuine Commit | 1 pass | 1 pass | 107 / 107 | 119.30 / 25.54 |

Scoped Pint (25 files), whitespace, frontend/UI and [Inspector browser](https://github.com/miranovadev/commons/actions/runs/38295678395) pass. The separate SQLite migration selection in Core still reports **12 pass / six CommittalClosure missing-history errors**, 45 assertions, 3.18s; this check is not green.

Checkpoint workflow times were Core **9m36s**, Integration **7m24s**. Final Core is 17s shorter; Integration is 2m18s longer, including the new real Commit. Both remain below 15 minutes. Runner variance and changed executed work preclude claiming a production performance improvement/regression. An intermediate complete run at `fa7a908` independently produced the same 895/248 and 20/12 counts in 9m45s / 8m46s; final results supersede it. Cancelled/superseded partial runs are not counted as complete validation.

[Exact inventories and comparison](evidence/prospective-closure-lawful-fixtures/README.md) retain every identity, failure classification, assertion count, runtime and original setup-path disposition.

The original 1,140 Core identities are retained, with three new Core cases. The original 31 Integration identities are retained, with one new Integration case. No test was skipped to obtain the improvement; SQLite retains its existing PostgreSQL-only migration skip. Focused selections overlap Core/Integration and are not additional suite cases.

The focused matrix adds a separate PostgreSQL/SQLite lifecycle job for the new genuine Commit, preserving each job's eight-minute budget. Integration retains four PostgreSQL shards, fixture isolation, exact discovered/selected/executed accounting and the 20-minute maximum. No timeout was raised.

## Remaining failures and construction boundaries

| Classification | Core | Integration |
| --- | ---: | ---: |
| A: unchanged Stage 2 failure | 3 | 7 |
| B: approved Stage 3 trajectory/event consequence | 4 | 4 |
| C: confirmed new production defect | 0 confirmed | 0 confirmed |
| D: incomplete/unsupported fixture history | 241 | 1 |
| Total failures | **248** | **12** |

Core A: ChargeBoundaryTragedy's ordinary unmocked boundary, ChiralityPopulationHistory's real-dispatch Attestation replacement, and ClosureHysteresis's historical read-only bootstrap preview. Core B: both Reinforcement pole cases create genuine governing Cycles while asserting Receipt neutrality; negative-alignment ClosureHysteresis reaches a Closure boundary; source-polarity ClosureHysteresis remains blocked by Extent **0.0714286 Positive** / Rhythm **-0.25 Negative**, with all other Commit requirements true. Its later polarity assertions remain unexecuted.

Four other Stage 2 failures remain masked: ObservableService matching lineages, HomologousBias zero retention and ordinary reclassification, and LineageAttenuation raw calculation. The baseline negative-alignment case now fails at an earlier Stage 3 boundary.

Integration A: NaturalChargeBoundaryLifecycle, both ParticipationLifecycleRegression cases, ParticipationLocalEpoch, ParticipationRecovery successful revisit, ParticipationSourceEquivalence and ParticipationTragedyLifecycle. A here means unchanged from Stage 2, including the previously established C1 transcript incompatibility; it does not make that transcript normative.

Integration B: both FreshBootstrap polarities, OperationalClosurePath and ParticipationHttpHarness remain blocked by Extent **0 Negative** / Rhythm **0.1875 Positive**. **They do not complete Commit.** Their populations and assertions are unchanged. Integration D: HomologousMediation's admissible-Mediate/inadmissible-Bias history has no genuine expressive source for its ordinary committed ancestry. HorizonProofCycle and the other 18 original passing cases still pass, alongside the new Commit scenario.

The five original recovered identities and the four separately named new cases are listed in the accompanying evidence. There are no newly failing previously passing cases in the completed comparison. The remaining setup failures still mask substantive assertions; this is not full Stage 3 regression sign-off.

| Remaining family | Intended invariant and precise unresolved fixture boundary |
| --- | --- |
| `CreatesHomologousPositions`, `HomologousMediation` and consumers | Preserve committed depth, shared-ancestor coordinates, producing Phase/register, membership and operand visibility/admissibility. Their builders still request ordinary Committals directly from Committals and, in places, prescribe Domain snapshots. HomologousPosition's original depth test explicitly asserts those raw direct edges and Gates. Replacing them with expressive sources changes that historical scenario. The new lawful single Commit is a foundation, not proof of the required multi-branch/multi-depth histories. No token source was inserted. |
| `laterHorizonFixture` | Preserve causal Horizon selection and foreign-scope boundaries. The helper promises a requested number of opposite-Domain transitions from arbitrary existing state, supplies no expressive source, and writes the requested direction into snapshots. A lawful replacement must replay an explicitly authored operation history and verify the resulting transitions; it cannot preserve that unconditional construction promise by forcing Closure. |
| `TragedyTest` | Preserve classification and action/exhaustion authority. Several old setups instrument expressions before mutating their tier into a Committal, omit governing entries, or attach parents afterwards. The real first-birth helper does not justify choosing missing parents or transplanting those action graphs into a different Domain. These cases remain unchanged rather than being supplied fabricated governance. |
| Remaining `CommitParticipationStateTest` cases | Preserve effective judgments, evidence and touch inheritance/reset. The transitive scenario explicitly Commits a Committal directly. The new Integration case proves genuine expressive-source inheritance, but not that impossible raw chain; the other historical graph cases remain visible. Only the independent dirty-region/touch test was reconstructed here. |
| `PropositionSearchTest` | Preserve true search distances, same-Domain selected ancestry and lack of movement/Receipt authority. The old fixture uses a null root-Horizon opener as governor, a manufactured foreign Horizon and a late-added ancestor while asserting exact distances. Neither inserting an Origin parent nor substituting the new single Commit preserves those distances and cross-Domain history. No such substitution was made. |
| `CommittalClosureTest` | Distinguish constitutional Origin, authorised ordinary state and frozen Commit history. Its older builder supplies source-less ordinary Committals, authored intervals/Domain outcomes and sometimes only a hydrated tier change. The new real Origin/ordinary projection test and Commit preservation scenario supply separate current-contract coverage, not a mechanical restoration of the retired preview arithmetic. The original cases are retained. |
| Other direct-model/rejection fixtures | Existing malformed governance cases stay malformed. In particular, real Instrument completion on the deliberately ungoverned Receipt subject still cannot invent a Closure observation. No production read-time fallback, historical backfill or Receipt-authority exception was added. |

These are outstanding reconstruction tasks, not renewed mathematical ambiguity. The validated action transcript should be reused as evidence when authoring further lawful builders. It must not be shortened by inserting a token expression, selecting arbitrary governors or manufacturing an observed population. The listed historical scenarios remain available for comparison.

## Scope and review

Only tests, focused CI commands and documentation changed. Production application files and migrations are unchanged; no production substrate records were accessed or modified. Correspondence C1, Chirality vNext, prospective Closure, causal selection, admissibility, Receipt authority, movement/recovery, canonical preservation and the ten Core Invariants remain unchanged.

Claire's review is still needed for the new scenario's limited equivalence and the outstanding historically authored fixture contracts. No new semantic decision is requested, and no broad replacement of the remaining multi-Commit histories is claimed. PR #255 remains unmerged.
