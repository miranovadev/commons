# Stage B: reconsideration correction checkpoint

PR [#280](https://github.com/miranovadev/commons/pull/280). Base/current main: `eeee6a6d81c4a32f605624f4804775f46af17bf4`. Final functional/test revision: `b6e701d2c70be8adfeeed3685f3df216435091e7`. A later documentation-only commit records the results. Fresh database only; no production cook, deployment or merge.

This report supersedes the [initial Stage B checkpoint](../participant-exploration-forward/initial-stage-b-validation.md). In particular, that checkpoint's interpretation of Revise as an advancing opportunity was incorrect under Claire's approved continuation handoff.

## Correction and preserved authority

- Forward development is Variant, Reconcile, Reclassify and Commit. Fresh Variant stays outside its own prerequisite frontier. Traverse accesses existing authorized forward structure.
- Attestation and Revise remain ordinary lawful actions. Neither availability nor execution alone reopens exploration. Applicability follows an exact same-position revision chain; the resulting active head is re-evaluated for forward opportunities.
- All relevant reachable active heads must be explored. Hard-superseded inputs do not become independent heads; local/foreign Reconcile, Horizon, Region and arrival restrictions remain authoritative.
- Rest can coexist with reconsideration. Its shared non-recursive completion witness distinguishes a tested encounter from ordinary actionless Rest. Pending evidence executions block Rest, with an authoritative locked recheck. Acquisition and missing evidence remain completion safeguards, not automatic reopening events.
- No observable calculator, Cadence, Receipt authority or allowance, Commit threshold, canonical snapshot rule, terminal lifecycle authority, or movement permission changed.

## Natural lifecycle correction

The original natural driver was rerun after correcting production behavior. It no longer stalled on Revise but still depended on an unrelated Variant resetting old exploration. The first changed target is action 24, Cycle 7 Distinguish: the old transcript traverses its first route, while the personal model correctly retains that encounter and selects the newer route. At action 25 the resulting Persist decision differs.

The corrected driver permits ordinary entry Attestation reconsideration when its preferred judgment and fresh Variant are unavailable. No action is forced through validation. It reaches a real Commit in 77 recorded operations with ordinary Preparation, Reconcile, both Receipt consumption boundaries, real calculators and preserved source/snapshot assertions. The [recorded journey and fixture notes](../participant-exploration-forward/README.md) explain the fixed HTTP replay, observer path and consequential Tragedy golden values. No synthetic Exploration Events are used in these natural journeys.

The three formerly failing HTTP lifecycle/recovery/source-equivalence journeys and Commit-descendant history passed locally together: **6 tests, 3,617 assertions**. The dependent Tragedy HTTP journey passed **898 assertions** after updating its recorded node alias, Cycle population and exact mathematical expectations for the new chronology. Its independent margin equation, attenuation/canonical checks, closed-boundary behavior and absence of Tragedy/exhaustion remain asserted.

## Checkpoint CI results

**All newly introduced lifecycle failures are resolved. The PR is ready for Claire's review, with established baseline failures still visible. It is not merged.**

| Check | Final result | Evidence |
| --- | --- | --- |
| Core / PostgreSQL | **1,502 passed; 1 baseline failure**, 15,491 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40176879959) |
| SQLite migrations | 14 passed, 46 assertions | Core shard 1 |
| Integration | **31 passed; 3 baseline failures**, all 34 discovered cases executed, no skips, 7,194 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40176884036), [complete inventory](../participant-exploration-forward/integration-summary.json) |
| Phase Profile | **All 12 jobs passed**, including both real Commit-history journeys on SQLite and PostgreSQL | [Run](https://github.com/miranovadev/commons/actions/runs/40176879982) |
| C3 | 107 focused tests / 1,027 assertions and recorded comparison / 1,390 assertions passed | [Run](https://github.com/miranovadev/commons/actions/runs/40176883961) |
| Frontend | TypeScript/Vite build and all 148 UI tests passed | Core frontend job |
| Inspector | Browser review passed | [Run](https://github.com/miranovadev/commons/actions/runs/40176884065) |
| Query | **13 passed; 9 baseline failures**, 953 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40176049575) |
| Load / PostgreSQL concurrency | **18 passed; 8 baseline failures**, 178 assertions | [Run](https://github.com/miranovadev/commons/actions/runs/40176049508) |

All nine specifically requested natural Commit/recovery test classes pass in Integration: CommitSourceHistoryLifecycle, CommitDescendantHistoryLifecycle, ParticipationLifecycleRegression, ParticipationRecovery, ParticipationSourceEquivalence, HorizonProofCycle, OperationalClosurePath, ParticipationHttpHarness and FullEvidenceFlow. The repaired ParticipationTragedyLifecycle test also passes.

Query and Load ran at `978606750fbe621c153187d9c239310c18f350b6`. All application code is identical at the final revision; subsequent changes add regression proofs, repair authored fixture expectations and document baseline evidence.

## Baseline failures

The unchanged base was checked independently; its files and fixtures were not modified:

- Core: `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` expects Settlement but reaches Emergence at `NaturalBoundaryHistory.php:63`. [Base Core run](https://github.com/miranovadev/commons/actions/runs/40166689571).
- Integration: both data sets of `FreshBootstrapTest::test_explicit_origin_and_aligned_evidence_reach_a_lawful_commit` exhaust the existing driver budget without Commit. `NaturalChargeBoundaryLifecycleTest::test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor` fails the same Settlement/Emergence fixture assertion. A fresh base run executed 10 selected cases: **7 passed, 3 failed, 2,218 assertions**; [case inventory](../participant-exploration-forward/baseline-comparison.json).
- Query: the dense Order payload is 54,511 bytes versus the unchanged 45,000-byte ceiling (base 55,062); eight performance fixtures lack authorized prospective Closure. [Base Query run](https://github.com/miranovadev/commons/actions/runs/40166689511).
- Load: the same eight previously verified concurrency failures remain: missing Closure fixtures, duplicate identical-transition receipt state, a worker that does not reach its intended lock, and a graph/detail direction expectation. [Base Load run](https://github.com/miranovadev/commons/actions/runs/40166689535).

Mathematical assertions and workflow/query ceilings were not weakened to hide these failures.

## Regression coverage and fixture boundaries

| Continuation handoff cases | Coverage |
| --- | --- |
| 1, 3, 11: Revise availability, selection and Variant | Prior-Cycle self-Refute test asserts explored status, independently selectable Revise/Attestation, lawful fresh Variant, actual Revision, and immutable history |
| 2, 4, 5, 10: reversal and unrelated updates | Existing personal reversal/independent-update test; no reset from Cycle or field version |
| 6: newly lawful Reclassify | Actual Attestation dispatch changes Bias eligibility; cold and hydrated exploration both reopen the relevant ancestry |
| 7: newly lawful Reconcile | Controlled Correspondence change exposes an ordinary counterpart through the personal recorded Mediate arrival |
| 8: newly lawful Commit | Isolated historical input plus actual Attestation completes Commit grounding; ordinary Closure selection and ancestor reopening are asserted |
| 9, 14, 15: new heads, independent actors, hard revision and full ancestry | Multi-head/display-limit tests, real personal HTTP journeys, same-position revision applicability and new forward child, local/foreign Reconcile |
| 12–13: Rest with reconsideration, no false history | Schema retains Attestation alongside lawful Rest for explored Writes; Rest cannot invent another event at a non-head; pending execution, untested arrival, evidence shortage, rollback, stale, frozen arrival, idempotence and append-only proofs |
| 16–18: shared plans and independent authority | Full fixed HTTP/provider action-schema equivalence, Prepared contributions, natural Commit/Receipt histories, Phase Profile, C3, terminal contracts and cold/warm query checks |

`ExplorationHistory` remains explicitly synthetic input for isolated selection tests; it is not proof of event publication. The real service/HTTP encounters establish their history through completed Rest. Controlled observable fixtures isolate reopening selection; the natural Commit journeys use the real calculators. Four old cognition assertions that acquisition alone reopens exploration were corrected; all twelve Phase/Domain cases still assert visibility, exact producing position, unavailable actions and compact-context representation.

Additional local selections passed: 127 exploration/Rest/Preparation cases with 1,274 assertions (three PostgreSQL-only concurrency cases were skipped on SQLite); 20 personal exploration cases with 154 assertions; the additional Rest/reconsideration proof with 6 assertions; 17 cognition cases with 191 assertions. Selections overlap and are not summed as unique coverage. Changed PHP passed Pint; whitespace checks passed.

## Performance implications and limits

With an already hydrated `ParticipationContext`, initial personal frontier evaluation remains **21 queries at both 2 and 8 heads**, with **zero** additional queries on repeat evaluation in the same context. This count excludes hydration. Same-position revision history traversal uses batched in-memory edges; no persisted exploration cache or epoch was introduced.

C3's ten recorded operational plans use **71–76 queries** (the prior checkpoint used 70–76). The pending-execution safeguard adds a bounded check where Rest is offered. Those recorded cases intentionally have no reconstructed personal history. They are cognitive projections, not lifecycle replays.

These measurements do not establish a worst-case bound for large personal histories across distinct foreign Bias/Order scopes. The existing broader Query failures and dense payload overrun remain limitations. No limit was raised or comparison population truncated.

The slowest final Core PHP shard took **568.06 seconds (9m28s)**, above the five-minute target. The longest Integration PHP shard took **430.46 seconds**, and Query took **538.38 seconds**. These are observed suite execution times, not isolated performance benchmarks or whole-workflow timings. CI configuration, targets and ceilings were not increased. Core runtime and the broader baseline performance failures remain follow-up limitations; the corrected functional lifecycle has no remaining new failures.
