# Stage 3 prospective Closure validation

**Pre-repair checkpoint.** See the [fixture repair follow-up](prospective-closure-fixture-repair.md)
and the [continued fixture repairs](prospective-closure-fixture-followup.md)
for current results and the before/after comparison. The results below remain
attributed to their original tested head.

This is an implementation checkpoint for PR #255, not a full regression sign-off.
Claire performs final review. The PR remains a draft and must not be merged.

## Specification and implementation

The [target](prospective-closure-target.md) and
[source-backed ambiguity register](prospective-closure-implementation-review.md#ambiguity-register)
resolve the original Origin and cumulative-history questions. Every available
Phase interval has equal weight, including each of Origin's `[0,0,0,0,0,0,1]`.
Origin's constitutional Closure, Extent and Rhythm remain -1 independently.
No new weighting, maturity rule, empirical Origin Orientation, fabricated birth,
or historical backfill was introduced.

Production persistence, complete-context birth initialization, governing Cycle
updates, independent Extent/Rhythm hysteresis, exact canonical preservation and
stale-state rejection are implemented. Frozen committed rows have database
guards on PostgreSQL and SQLite. Attestation changes local Chirality without an
independent Closure update. Structural progression does not remeasure older
Closure. The implementation mapping and atomic boundaries are in the review.

Correspondence C1, Shape Topology, local Chirality, temporal predecessor
selection, Receipt authority, movement/recovery, Tragedy/Exhaustion, attenuation
A and Tension/Charge equations were not redesigned. New Closure legitimately
changes some trajectories. No production substrate data was deleted or changed.

## Complete execution checkpoint

Validated implementation/test head: `0e072626e6c5fd9f494a1edd1686f9caaf0d774b`.
This includes restoration of the existing Order instructions about independent
Write visibility and the persisted consistency guard. The subsequent reporting
commit changes documentation, result inventories and an EOF blank line only;
these are results for the named code head, not a claim that later CI has finished.

| Run | Result | Assertions | Runtime |
| --- | --- | ---: | --- |
| [Phase Profile contracts](https://github.com/miranovadev/commons/actions/runs/38177228853), PostgreSQL | 48 Phase Profile/history + 14 prospective Closure cases pass; broader observable/ledger group 81 pass / 59 fail | 300 + 85 + 599 | 85.25s + 18.90s + 135.62s |
| Same run, SQLite | Same pass/fail counts | 300 + 85 + 599 | 20.25s + 4.16s + 26.17s |
| [Core](https://github.com/miranovadev/commons/actions/runs/38177228829) | 1,140 executed: 583 pass, 3 assertion failures, 554 errors, no skips | 6,317 | Workflow 6m16s; slowest PHP shard 278.15s |
| [Integration](https://github.com/miranovadev/commons/actions/runs/38177234159) | 31 executed: 19 pass, 11 assertion failures, 1 error, no skips | 17,282 | Workflow 6m44s; slowest PHP shard 349.68s |
| Frontend and [Inspector browser](https://github.com/miranovadev/commons/actions/runs/38177234070) | Pass | — | Browser workflow 1m13s |

Workflow duration is `updated_at - run_started_at` for the completed Actions
run. PHP duration comes from each shard's runtime artifact. Discovered, selected
and JUnit-executed test multisets match exactly for Core and Integration.
The [durable inventory](evidence/prospective-closure-stage3/summary.json) and
per-shard CSVs record every result, including failure category and first test
stack frame. An error prevents the underlying assertion from being assessed.

Focused coverage includes constitutional values/immutability, six genuine zero
intervals, first Settlement and mature comparative Cadence, cumulative repeated
Domains, selected ancestry, Revision/Reconcile/foreign-source Bias, unavailable
versus zero, effective Attestation replacement, independent directions,
Cycle updates of active and superseded expressives, canonical source preservation,
stale rejection and bounded batch queries. The grounded negative/negative Commit
test dispatches a real receipt-backed Commit. CanonicalCommitMeasurementTest
passes in the broader focused group. This does not replace a passing complete
HTTP lifecycle or establish that all observable/ledger regressions pass.

## Integration failure inventory

Categories are relative to the final Stage 2 implementation: A unchanged
baseline, B expected new trajectory, C genuine regression, D test setup or
infrastructure boundary. A Stage 2 C1 trajectory failure is therefore A here.

| Test (method suffix where needed) | Category | Evidence |
| --- | --- | --- |
| FreshBootstrapTest, Origin polarity case #0 | B | Last Order has Extent Negative and Rhythm Positive; other Commit requirements pass |
| FreshBootstrapTest, Origin polarity case #1 | B | Same component values and sole coherence rejection |
| OperationalClosurePathTest | B | Same component values and sole coherence rejection |
| ParticipationHttpHarnessTest | B | Same rejection before unchanged 101-action budget expires |
| NaturalChargeBoundaryLifecycleTest | A | Existing expected Settlement / actual Emergence at NaturalBoundaryHistory |
| ParticipationLifecycleRegressionTest, successful_route_revisit | A | Same authored script mismatch at step 32 |
| ParticipationLifecycleRegressionTest, distinguish_under_another_committal | A | Same authored script mismatch at step 26 |
| ParticipationLocalEpochTest | A | Same unavailable Reconcile at step 32 |
| ParticipationRecoveryTest, successful_revisit | A | Same authored script mismatch at step 32 |
| ParticipationSourceEquivalenceTest | A | Same authored script mismatch at step 31 |
| ParticipationTragedyLifecycleTest | A | Same authored script mismatch at step 32 |
| HomologousMediationTest, admissible_mediate_can_reach_an_inadmissible_bias | D | Fixture creates ordinary Committals directly from Committals without an expressive source and frozen prospective history; earlier Stage 2 assertion is masked |

All four B cases record the same last authorised Closure:

- Depth 0 (unavailable), Historical Phase Profile 0, Extent 0 retaining Negative.
- Cadence 0 (unavailable), Historical Orientation 0.375 from four observations,
  Rhythm 0.1875 Positive; scalar Closure 0.09375.
- Emergence means `[0,0,0,0,0,0,1]`; Settlement means
  `[null,14,3,0,0,3,1]`; seven comparison contributions
  `[0,-1,-1,+1,+1,-1,+1]` sum to zero. Phase 1 remains unavailable.
- Attestation, Receipt, Phase position, causal Write, active candidate and Write
  opportunity all pass. The sole reason is `extent_rhythm_directions_diverge`.

No admissibility rule, action budget, authored population or numerical expectation
was changed to make these trajectories Commit. Both FreshBootstrap polarities,
OperationalClosurePath and the HTTP Commit lifecycle **do not complete** in this
run. HorizonProofCycle, FullEvidenceFlow, the nine action-contract cases, both
Receipt/Phase-boundary cases and the staggered local-epoch case pass. Full names
for all 31 cases are retained in the inventory.

No remaining Integration failure is currently demonstrated to be C. That is
not proof that the implementation has no regressions: incomplete Core fixture
conversion still masks many established assertions.

## Core and observable/ledger limitations

The complete Stage 2 Core baseline
[38124941998](https://github.com/miranovadev/commons/actions/runs/38124941998)
executed 1,125 cases: 1,117 pass and eight fail. Stage 3 adds 15 focused cases to
the Core inventory. It does not omit failing classes or weaken coverage selection.

The 554 `ClosureHistoryUnavailable` errors are classified **provisionally D**:
their immediate boundary is absent authorised birth state or frozen selected
committed history in older direct-model fixtures. The strict production read
does not reconstruct missing state from current populations. This diagnosis
does not establish that each underlying assertion would pass after lawful
fixture conversion. The per-test inventory records the exact failing boundary.
Fixtures must initialize after their real ledger/edges or use a real existing
expressive source at Commit; a fabricated source/history is not an acceptable fix.

Other remaining Core failures:

- A: ChargeBoundaryTragedyTest still expects Settlement and observes Emergence.
- A: ChiralityPopulationHistoryTest still expects -0.5 and observes -0.1.
- D: ParticipationApiTest's atomic advancement fixture creates its producing
  Cycle without `entry_committal_id`; the new ordinary birth cannot obtain a
  governing frozen history. The HTTP 422 masks the atomicity assertion.

A genuine prompt regression was corrected before this tested head: the Order
rewrite omitted the existing independent-Write-visibility and consistency-guard
instructions. Both sentences were restored, and the complete prompt contract
class now passes, without changing action authority.

The other six Stage 2 Core failures are masked by earlier missing-history errors:
two HomologousBias cases, LineageAttenuation's raw-calculation case,
ObservableService's matching-lineages case and two ClosureHysteresis cases.
They must not be reported as resolved or substantively unchanged at their old
assertion. The broader focused observable/ledger group's 59 failures likewise
prevent a full regression claim.

Core's additional SQLite preflight executes 18 cases: 12 pass and six
CommittalClosure cases error on missing authorised state (45 assertions).
The subsequent PostgreSQL shards still execute. These overlapping preflight
cases are not added to the 1,140 unique Core cases. The scoped Pint check passes
its 11 files; the final reporting commit removes a trailing blank line at EOF
in HorizonCycleFixture reported by `git diff --check`.

## ParticipationLocalEpoch

The test's intended invariant is slot-local reopening: a third Distinguish
Variant increments only its own exploration epoch, reopens genuinely spent
options, preserves failure history and leaves Disperse epochs unchanged.
Its 84-step authored transcript additionally assumes a particular Reconcile
is available at step 32. Stage 2 established Shape -0.4701871/Negative versus
Phase Profile +0.1428571/Positive, so that operand is incoherent under unchanged
Mediate admissibility. That incidental trajectory cannot establish the invariant.

Keep the historical transcript unchanged. Recommended replacement: a separate
HTTP scenario starting from a naturally exhausted local slot, then a lawful
Distinguish Variant, asserting only that slot's epoch/options and unchanged
unrelated failure/Disperse history. The existing passing
ParticipationStaggeredEpochTest covers naturally spent positions reopening only
through their own Variants, but does not claim the third-Variant case. Extending
that lawful setup needs a reviewed scenario, not synthetic eligibility or causal
edges. No Mediate authority was weakened.

## Runtime, isolation and remaining review concerns

Stage 2's complete Integration baseline
[38123013953](https://github.com/miranovadev/commons/actions/runs/38123013953)
was 23 pass / eight fail, 17,394 assertions, workflow 6m13s and PHP shards
288.17 / 326.02 / 308.95 / 319.42 seconds. Stage 3's checkpoint is 6m44s with
PHP shards 280.27 / 349.68 / 320.93 / 170.29 seconds. Both are under the preferred
15-minute target and unchanged 20-minute maximum. Different failures can shorten
execution, so these times are not a production speedup claim.

Stage 2's four-shard PostgreSQL isolation, schema reuse, physical-state cleanup
and exact coverage accounting remain intact. Earlier unisolated execution hit
20 minutes without a summary; the prior four-case fresh-state experiment took
about 530 seconds. Those are prior evidence of both legitimate longer C1
trajectories and retained PostgreSQL physical-state cost, not new measurements
made by this Stage 3 change. No timeout increase conceals the issue.

During Stage 3, repeated schema initialization exposed a PostgreSQL trigger
function surviving table teardown; `CREATE OR REPLACE FUNCTION` corrected it.
A failing Exhaustion fixture could leave its manually opened transaction active
and block following schema teardown; test-owned transaction cleanup now runs in
`finally`. Core runs its selected PostgreSQL suite even if SQLite preflight fails,
so preflight cannot hide the complete inventory. These corrections do not delete
production data or add a production fallback.

The batch test compares individual and batch results and bounds history loading
to at most 14 queries, without per-head queries. Cumulative JSON state and large
governing-Cycle fan-out have not been benchmarked on a mature experimental
substrate; CI runtimes do not establish that scale limit.

The unchanged Rest crossover diagnostic retains the old Cardinal/Cadence
conditional bound. It does not calculate production Closure, but participates
in existing recovery routing. Its interpretation as a bound on new four-primitive
Closure is unverified. The strict movement/recovery boundary prevents inventing
a replacement formula here; Claire and Chat Nova should review that dependency.

The original numerical ambiguity is resolved. Outstanding work concerns lawful
fixture conversion, replacement lifecycle scenarios and the separately scoped
Rest-bound interpretation. The implementation-readiness protocol's stop condition
applies wherever completing a fixture would require invented provenance or
measurement history. It does not justify claiming the remaining regressions pass.
