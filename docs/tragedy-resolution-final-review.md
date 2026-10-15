# Stage 4 final implementation and validation review

Implementation tested: `7b8267b583315912a837e9547f02d0223ecee01f` on PR #257, `feat/tragedy-resolution-stage-4`. Main/base: `f89c2775bc000f1034bcc1aefbb6cdac3b467412`. Reporting-only commits do not change that tested implementation. The approved [target](tragedy-resolution-target.md) and all ten [Core Invariants](core-invariants.md) remain authoritative; earlier birth-only/second-catastrophe rules are replaced.

## Checkpoint recovery and completed work

The previous workspace was recoverable. Its committed implementation matched pushed `29c41afb984db720a76f1443a7d2cd88b151115f`, except for one unrelated archived Stage 3 evidence file, which was not published. Twelve uncommitted fixture edits were reviewed and incorporated, with further corrections where a surviving route had to prevent Exhaustion. Existing PR and branch history were preserved.

The final implementation retains the checkpoint's immutable direct terminal encounters, one regional Tragedy/Receipt, region-wide Resolution restrictions, atomic recursive Exhaustion, substrate-complete continuation authority, and recorded backward recovery. This completion pass additionally fixes two option-consumer gaps: revision candidates now obey the same regional generation restriction as dispatch, and Bias's special committed-source Traverse path cannot reintroduce a terminal expressive source. Regressions verify database and hydrated-context options, stale dispatch rejection, independent sibling actions, and unchanged frozen Commit history. These gaps were identified by source inspection; the new tests were not run against a reverted production implementation.

Active participant instructions, Horizon UI copy and read-only CLI diagnostics now describe the approved lifecycle. Every Phase receives the lifecycle rule, including when Rest is unavailable. Rest/Potential inspection no longer predicts a Tragedy from numerical or exploration failure diagnostics. The existing instruction-content fingerprint changes the prompt cache family automatically. Relevant live documentation is aligned; historical migration evidence remains historical.

No observable equations, attenuation A, canonical Commit preservation, Receipt evidence authority or movement permissions were relaxed. No production history or experimental database was converted.

## Acceptance cases

The 32-case Stage 4 selection runs on PostgreSQL and SQLite. The feature cases deliberately use controlled numerical fields and persisted component history; they are **not proof of a naturally reached end-to-end catastrophe lifecycle**.

| Approved case | Verified contract |
| --- | --- |
| One terminal route, no alternative | Authorized first expressive birth immediately records terminal encounter, unique Tragedy/Receipt and Exhaustion. |
| Terminal route with viable sibling | Region stays in Resolution; Variant/Revise are unavailable while an ordinary sibling Instrument remains authorized. |
| Later terminal encounter with another survivor | A third branch remains live; no second Tragedy/Receipt and no premature Exhaustion. |
| Live committed descendant | Its persisted Commit edge keeps the parent Region open even if the old expressive source itself becomes terminal. |
| Exhausted descendant | Closes only that route. Parent closes recursively only after its last independent route terminates. Same-Horizon and boundary recovery tests preserve the distinction. |
| Multi-parent join | Backward closure requires all of each parent's children to be terminal. An independent alternative survives; foreign operands do not invent local movement. |
| Region-wide restrictions and unrelated authority | Previously authorized Revise is removed from raw options and dispatch; a live descendant Region can still generate. A terminal committed source cannot reappear through Bias's special Traverse path. |
| Direct encounter scope | Authorized birth, Instrument, Attestation and expressive Traverse; already-zero targets; resulting Charge rather than previous Charge; actual encounter actor rather than birth actor. |
| Historical and recovery authority | Positive later observations and epoch changes do not revive terminal history; exhausted plans offer Rest only; reads/passive updates/Rest publish no terminal event; actual backward arrival excludes siblings and foreign-parent shortcuts. |

`DirectTragedyResolutionTest`, `TerminalContinuationTest`, `CausalRecoveryTest`, `HomologousMediationTest`, `ChargeBoundaryTragedyTest` and the existing recovery suites provide the detailed assertions. The two PostgreSQL publication races verify unique first publication and repeated observation. Rollback checks cover the initial event, later terminal encounter, Receipt/warrant state and derived regional membership. PostgreSQL/SQLite triggers reject mutation of encounter, Tragedy and Exhaustion evidence.

## Fixture corrections

- Recovery fixtures now terminate the continuation they actually exercise. A live committed sibling prevents parent or boundary Exhaustion until that sibling's own route terminates. An already-exhausted region cannot accept additional manufactured contribution evidence.
- Warrant, memory and incoming Bias fixtures create an independent terminal component while preserving the tested viable route. Exhausted memory remains readable without warrant authority; a foreign-source result does not create a missing edge from the acting Gate.
- Homologous mediation now checks complete authority beyond the hydrated option set. Without an alternative, the foreign gate closes; a sibling created before the event preserves it. Epoch-relative Rest failures do not stand in for permanent terminal authority.
- Duplicate manually inserted exhaustion proofs were removed where the real observer already publishes the proof. Immutability tests now expect database rejection. Migration rollback tests protect actual Stage 4 terminal evidence, rather than requiring an obsolete Receipt-consumption history.
- Formatting failures in the checkpoint were corrected: import order in `DirectTragedyResolutionTest` and an unused regional-membership import in `HomologousBiasTest`. Formatting and whitespace gates run in the final validation. Prompt contract assertions now verify the approved lifecycle both with and without Rest authority; CLI checks parse the single JSON document and verify that inspection publishes no writes.

These are current-contract replacements, not numerical adjustments to force historical trajectories. Existing controlled-field fixtures remain explicitly labelled. No new causal arrival history was invented, and no admissibility prerequisite or action budget was weakened.

## Complete suite results

| Suite | Recovered Stage 4 checkpoint | Final at tested SHA | Assertions | CI run |
| --- | --- | --- | ---: | --- |
| Core | 1,117 pass / 19 fail / 1,136 cases | **1,143 pass / 1 fail / 1,144 cases** | 13,449 | [38721238481](https://github.com/miranovadev/commons/actions/runs/38721238481) |
| Integration | 21 pass / 11 fail / 32 cases | **21 pass / 11 fail / 32 cases** | 17,534 | [38721245839](https://github.com/miranovadev/commons/actions/runs/38721245839) |

All discovered/selected/executed case identities match exactly. Zero complete-suite skips or missing cases; no newly regressed retained test. These are completed test inventories with failing assertions, not green workflows. Core shard 4 passed all 192 cases and uploaded its complete evidence, but its first job conclusion was `cancelled` at 8m05s; see the targeted retry and timing disclosure below.

The durable [test inventory and baseline comparison](evidence/tragedy-resolution-final-review) records every final identity, outcome, assertion count and runtime, plus changed/retired identities. Complete suite discovery, shard selection and execution are compared as multisets. Focused selections overlap complete suites and are not added to their totals.

### Remaining failures and baseline comparison

The Stage 3 reference is tested code `9bc9e8576dc9c28d0f52d766193304a22f614d31`, merged into the current main base. Its [final report](prospective-closure-final-review.md) documents 1,116 pass / 1 fail in Core and 21 pass / 11 fail in Integration. The recovered Stage 4 checkpoint was separately verified from its completed CI logs: 1,117 pass / 19 fail in Core and 21 pass / 11 fail in Integration. Handover claims were not treated as new test execution.

All 1,093 retained Stage 3 Core passing identities still pass; its one retained failure is unchanged. Twenty-three old passing identities are absent: seventeen obsolete lifecycle contracts are replaced by current-contract cases, and six historical regional-proof conversion cases are retired under the explicitly approved fresh-database scope. Fifty new/renamed case identities pass, for a net increase of 27 cases over Stage 3. The eight-case increase over the recovered Stage 4 checkpoint consists of seven added direct-lifecycle/option cases and one additional foreign-sibling dataset. No skipped test hides a replacement.

| Earlier passing contract family | Current coverage / deliberate scope change |
| --- | --- |
| Two CausalRecovery exhaustion trajectories | Committed sibling prevents parent closure; live committed descendant prevents boundary crossing; actual route termination permits the original backward recovery. |
| Three ChargeBoundary first/second-birth identities | Later direct encounter retains first identity, later-encounter rollback restores Resolution/warrant, and exhausted-descendant recursion closes a resolving parent. |
| ExhaustedContinuation epoch-driven closure | Temporary epoch failures do not permanently terminate an independent route. |
| ExhaustionRealtime first/second event pair | Terminal encounters and final-route exhaustion publish minimal events only after commit; existing rollback broadcast suppression remains. |
| Four Exhaustion first/final-failure contracts | Immediate final-route exhaustion, recorded parent recovery, no movement of other participants, and proof authority independent of Rest/consumption. |
| HomologousBias positive escape / later catastrophe | Positive escape preserves Resolution without reopening Variant; surviving acting Gate prevents invented regional closure. |
| HomologousMediation hydration/epoch assumption | Parameterized complete-authority test with and without a pre-existing surviving sibling. |
| HorizonFailureInvariant and HorizonProofCompatibility | Audit actual terminal events, reject database proof corruption, and refuse rollback that would discard Stage 4 terminal evidence. |
| Two RegionalBoundary first/second lifecycle contracts | Surviving positive continuation keeps Resolution open; terminating the last existing continuation exhausts once without Receipt use. |
| Six RegionalExhaustionMigration historical conversion cases | Replaced in scope by empty full-chain installation and empty Stage 4 round trip on both databases. Historical experimental proof conversion is intentionally unsupported; new evidence immutability is covered independently. |

Integration has exactly the same 32 identities, 21 passing outcomes and 11 failing outcomes as Stage 3, with the same 17,534 assertions. No Integration script or budget was modified.

| Remaining category | Boundary actually reached |
| --- | --- |
| Core: `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` | `NaturalBoundaryHistory` expects Settlement but reaches Emergence before its catastrophe assertions. Same historical authored-trajectory failure as Stage 3. |
| Integration: `NaturalChargeBoundaryLifecycleTest` | The same obsolete pre-catastrophe Domain assumption; its natural catastrophe/recovery tail is not demonstrated. |
| Six Integration Mediate scripts | Unchanged histories reach no admissible non-Rest action at steps 26, 31 or 32. These are the two `ParticipationLifecycleRegressionTest` scenarios and the LocalEpoch, Recovery, SourceEquivalence and TragedyLifecycle scenarios listed in the Stage 3 report. Their later invariants are not established by these scripts. |
| Four Integration Commit journeys | Both `FreshBootstrapTest` datasets, `OperationalClosurePathTest` and `ParticipationHttpHarnessTest` reach Extent 0 retained Negative / Rhythm +0.1875 Positive. Prospective Closure correctly blocks Commit. Original populations, scripts and action budgets are unchanged. |

These failures remain visible. They are neither green tests nor newly demonstrated Stage 4 production regressions. The exact failed identities/messages are retained in `summary.json`; the earlier report remains the detailed baseline classification.

## Database, presentation and CI evidence

The [focused matrix](https://github.com/miranovadev/commons/actions/runs/38721238457) completed all twelve jobs successfully. Exact results and durations are in `focused-results.json`.

| Selection | PostgreSQL | SQLite |
| --- | --- | --- |
| Stage 4 direct/recursive contracts and fresh migrations | 32 pass / 132 assertions; 37.47s | 32 pass / 132 assertions; 5.67s |
| Concurrent terminal publication / repeated observation | 2 pass / 19 assertions; 4.53s | PostgreSQL-specific selection |
| Phase Profile C1 | 48 pass / 300 assertions | 48 pass / 300 assertions |
| Prospective Closure | 15 pass / 90 assertions | 15 pass / 90 assertions |
| Existing observables / ledger / boundaries | 131 pass / 1,961 assertions | 131 pass / 1,961 assertions |
| Representative fixtures | 175 pass / 2,090 assertions | 173 pass / 2,084 assertions; two existing PostgreSQL-only skips |
| Population / attenuation review | 50 pass / 523 assertions | 50 pass / 523 assertions |
| Genuine Commit history | 1 pass / 113 assertions | 1 pass / 113 assertions |
| Genuine descendant / search history | 1 pass / 129 assertions | 1 pass / 129 assertions |

Scoped Pint passes on both databases (37 existing files plus 12 Stage 4 files), as does full-branch whitespace checking. The Core SQLite migration preflight passes 14 cases / 46 assertions. Frontend typecheck/build and **130 UI tests** pass. [Inspector browser review](https://github.com/miranovadev/commons/actions/runs/38721245667) passes its desktop/mobile synthetic graph checks. The two pre-existing SQLite skips both execute in PostgreSQL; no new skip was added.

Fresh installations execute the full existing migration chain on both databases through `DatabaseMigrations`; `RegionalExhaustionMigrationTest` also reverses and reapplies the empty Stage 4 migration. Historical event-bearing rollback is refused. No legacy database upgrade, proof backfill or conversion path is required or claimed. Pre-existing historical migration tests remain tests of their original contracts.

Initial full Core elapsed **10m05s**, five seconds above the nominal ten-minute total target and above the preferred five minutes. It included 34s before the first job started; execution from first job start to workflow completion was 9m31s. The slowest PHP job reached 8m05s and was marked cancelled despite all test, upload and cleanup steps reporting success. Its 192 tests / 3,165 assertions and exact inventory completed before cancellation. Only that job was retried at the same commit, without changing limits: **7m27s job time, 391.77s PHP time, 192 pass / 3,165 assertions**, successful. Retry identities, outcomes and assertions exactly match the original artifact; the retry is not counted as extra suite coverage. Initial and retry evidence are both retained. The initial elapsed overrun remains disclosed, not erased by the retry.

Integration elapsed **8m16s**, below its 15-minute preference and 20-minute ceiling. The focused matrix took 6m01s; Inspector 1m12s; frontend 1m22s within its two-minute cap. Test execution was not cut off. Runtime variation and the narrow Core margin remain CI observations, not evidence of a production performance improvement.

The five Core / four Integration PostgreSQL shards, fail-fast settings, complete accounting, per-test isolation, Integration storage maintenance and existing time limits are preserved. Stage 4 adds a focused matrix selection and formatting scope; it does not hide failed tests or increase timeouts. Superseded candidate Core runs are not used as complete final evidence. The `2c893442` run exposed obsolete prompt wording assertions and a console-output matcher issue; both were corrected in the tested commit without changing production behavior.

`TerminalContinuationService` deliberately reads the complete persisted substrate graph rather than inferring terminal authority from a hydrated option subset. Query-growth regressions remain covered, but substrate-size CPU/memory scaling and lock duration under large production graphs have not been benchmarked here. The stored recursive proof includes its evaluated graph. Passing CI runtimes are not a production scalability claim.

## Ambiguity register and review limits

**No unresolved Stage 4 semantic ambiguity was found.** The [implementation register](tragedy-resolution-implementation.md) records resolved decisions with their authority. Coverage gaps below are not a request to reopen approved mathematics.

- A natural, unmocked bootstrap-to-Tragedy-to-recursive-recovery journey remains unverified. Controlled component events do not fill that gap.
- The existing genuine 89-action Commit and descendant/search histories remain distinct evidence of lawful Commit and preserved Stage 1–3 history, not proof of arbitrary repeated Domain transitions, multi-Commit recovery or the failing historical script tails.
- The pre-existing Rest crossover calculation retains its separately specified Cardinal/Cadence bound. It is not validated as a bound on prospective Closure; this pass does not change or re-approve that experiment.
- Inspector checks use desktop/mobile synthetic graph fixtures. They validate presentation, not a live substrate's complete lifecycle. Full Core/Integration were run on PostgreSQL; SQLite received the focused matrix and migration checks.

**Ready for Claire’s final review; not merged.** No unresolved Stage 4 implementation ambiguity or newly regressed retained test remains. The twelve baseline failures, natural-lifecycle gaps, unbenchmarked large-substrate cost and initial Core time-budget overrun are explicit review considerations. Full green-suite, exhaustive lifecycle and production-scale sign-off are not claimed. Claire retains the final merge decision.
