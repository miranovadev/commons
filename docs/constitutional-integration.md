# Stage 6: constitutional participation coverage

The `constitutional` group identifies a small set of participant-visible laws.
It is a selection label, not an exemption from Core. `composer test:constitutional`
selects its Core cases; `composer test:integration-full -- --group=constitutional`
also includes any broader constitutional cases. Normal Core
and Full Integration continue discovering these tests through their existing
commands. No pre-existing test changes suite membership.

## Acceptance map

| Epic scenario | Production-path evidence | Additional primitive coverage |
| --- | --- | --- |
| A: repeated Variant reopening | `ParticipationLocalEpochTest` creates a third Distinguish Variant through natural failures and Rest, checking epoch-local eligibility and unchanged failure rows. | `ExplorationEpochTest`, `ExplorationPositionTest`, migration/provenance tests. |
| B: staggered causal positions | `ParticipationStaggeredEpochTest` causes one real failure to spend Distinguish A and Disperse B, advances A while B remains spent, then advances B. Parked HTTP observers see each independent reopening. | Exact structural identity tests retain revision and multi-input cases. |
| C: ancestral recovery and attenuation | `ParticipationRecoveryTest` checks actual traversed edges and executed actionless Rest; the successful route's untraversed Commit is excluded. | `CausalRecoveryTest` explicitly proves near/far winners, true edge distance, and exclusion of attractive siblings, descendants and unrelated Committals. These controlled numeric fixtures are recovery-primitive evidence, not API-created topology. |
| D: current-Horizon confinement | Every live step of the natural Tragedy consumer stays in the current Horizon until actual Exhaustion. | Controlled positive-capacity/multiple-ancestor recovery cases, plus authoritative numerical-capacity tests. |
| E: backward boundary crossing | The natural Tragedy journey crosses its API-created Horizon boundary backward and inspects actual distance. | The controlled cross-Horizon fixture distinguishes an intermediate ancestral Committal from the previous Horizon's provenance node. |
| F/G: full Tragedy and shared evidence | `ParticipationTragedyLifecycleTest` creates Tragedy naturally, admits another participant into its Horizon, claims the shared final attempt, reads and consumes the receipt, exhausts the failed continuation, recovers to Origin and takes live exploration. Foreign-Horizon access and duplicate claims are rejected. | Atomic claim/rollback, unrelated-lineage failure exclusion, immutable history and migration tests. |
| H: one participant's Domain round trip | **Still under construction.** The HTTP journey must prove Settlement → Emergence → Settlement through actual boundary Commits and all seven phases. An investigative solo sequence has completed that route; fixed-script replay is still required before acceptance. | Existing service-level cycle tests remain; a single Commit is not described as a Domain round trip. |
| I: collective Domain round trip | **Still under construction.** The interleaved HTTP journey must check position persistence, cross-owner attestations and shared receipt consumption along a real Horizon chain. | Existing `FullEvidenceFlowTest` is retained as separate service coverage. |
| J: structural Rest | The two constitutional Rest methods in `ParticipationHttpHarnessTest` cover both productive-action rejection and an unread informational receipt that leaves Rest available. | `AuthoritativeRestReasonTest` and action-contract tests. |
| K: positive and negative visibility | `ParticipationVisibilityLifecycleTest`, the Bias revisit and the Tragedy journey pair expected context with forbidden targets/evidence. | Existing packet, exhausted-history, projection and query-budget cases remain. |
| L: participant-source equivalence | `ParticipationSourceEquivalenceTest` compares HTTP state with a real worker's input and checks the complete seven-phase contribution contract with connection enablement toggled. | Worker locking/retry and concurrency coverage remain in their existing suites. |

## Fixture discipline

All constitutional HTTP cases use `ParticipationHttpTestCase`. Even legitimate
initial setup is performed through HTTP. The existing guard rejects structural
database writes outside application requests. Observers arrive through actual
contributions and remain parked; the tests do not move them directly.

Constitutional lifecycle acceptance is being converted to predefined action
scripts with deterministic expected state after every step. All selected HTTP lifecycle cases now use that runner; the synchronous worker
case has an explicit three-action sequence and expected intermediate states. Inputs never come from an autonomous
probe or a policy that keeps choosing actions until an outcome appears. The
scripts preserve real measurements, Closure, receipt consumption, failure
publication, recovery and Horizon projection. See the
[fixture contract](../tests/Fixtures/participation/README.md).

Investigative probes may establish candidate sequences, and separate asynchronous
liveness coverage remains useful. Their successful return is not constitutional
acceptance. A candidate sequence must be frozen, reviewed for the intended laws,
and replayed with exact expected state. Existing semantic assertions and Core/Full membership are preserved. Complete
Stage 6 validation is still in progress.

Service fixtures may still construct narrow numeric/structural conditions to
test a primitive. The acceptance map makes those limits explicit. They do not
stand in for the API lifecycle journeys.

## Validation checkpoints

Stage 5's independent PostgreSQL Tragedy result is accepted semantic evidence;
its cancelled Core/Full jobs are not complete suite passes. See
[the recorded acceptance](stage-5-lifecycle-correctness.md#accepted-validation-evidence).
Stage 6 results and timings are recorded on its review PR.

The initial local constitutional selection passed 13 tests / 3,149 assertions
in 119.26 seconds on SQLite. This includes the new staggered-epoch journey and
the strengthened Tragedy confinement assertions. It does not prove H/I or
constitute complete Stage 6 or aggregate validation.

The changed cases also passed independently on PostgreSQL 18 / PHP 8.5 at
`f6fd87481cd6e17aa2460d564427086f866ec0b0`:

| Case | Assertions | PHP time | Complete job time |
| --- | ---: | ---: | ---: |
| Staggered epochs | 198 | 28.27 s | 1m42s |
| Tragedy with per-step Horizon confinement | 612 | 75.51 s | 2m18s |

[Validation run](https://github.com/miranovadev/commons/actions/runs/35426948576).
The workflow-only commit `a62d33f93cbc5d4033d9ce56815cf18a4e916723`
explicitly pins the application/test checkout above; it does not change regular
suite selection. These are targeted passes, not complete Core/Full passes.

The final Epic checkpoint must validate one exact candidate across all suites
with an appropriate timeout and retained results. It must account for every
selected test, retain Query and Load, run the frontend build and action contracts,
and distinguish completed passes from cancellations. Full Integration includes
Core; overlap must not inflate the count of unique tests. No assertion, test,
or suite membership may be weakened merely to fit a runtime target. Targeted
Stage 5/6 results do not replace that final aggregate signal.

Stage 7 implementation cleanup remains a separate review stage under these
behavioural protections.

## Fixed-script conversion (in progress)

Local SQLite replay passed the 30-step staggered journey (1,980 assertions,
5.05 seconds) and the 107-step Tragedy journey (8,684 assertions, 26.52 seconds).
These results precede PostgreSQL validation of the converted tests; they do not
replace the earlier pinned PostgreSQL results or constitute a Stage 6 checkpoint.
No production semantics or existing Core/Full membership changed in this conversion.

The complete converted selection (excluding the Domain journeys) passed locally:
13 tests / 36,958 assertions in 100.38 seconds. The only remaining use of the
exploratory helper in these API files is the separate, non-constitutional harness
smoke test. Expected-state fixture deltas were checked against all original full
expected states; the two Rest scripts also passed through the compact loader
(525 assertions, 1.57 seconds). PostgreSQL replay remains pending.

The frozen solo script also passed locally: 600 contributions plus entry,
127,327 assertions, 728.51 seconds. Its first PostgreSQL replay at `73e2642`
stopped at step 36 because SQLite and PostgreSQL returned the same two
reconciliation parents in different order. `Proposition::parents()` has no ordering
clause, and `InputPlanService` exposes those references without sorting. The
script comparator now treats only `parent_ids` as an exact set, preserving all
members and the ordered context populations. That failed run is not a pass;
PostgreSQL replay of the corrected harness is required.
