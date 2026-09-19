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
| H: one participant's Domain round trip | **Still under construction.** The HTTP journey must prove Settlement → Emergence → Settlement through actual boundary Commits and all seven phases. The current probes have not established that complete sequence. | Existing service-level cycle tests remain; a single Commit is not described as a Domain round trip. |
| I: collective Domain round trip | **Still under construction.** The interleaved HTTP journey must check position persistence, cross-owner attestations and shared receipt consumption along a real Horizon chain. | Existing `FullEvidenceFlowTest` is retained as separate service coverage. |
| J: structural Rest | The two constitutional Rest methods in `ParticipationHttpHarnessTest` cover both productive-action rejection and an unread informational receipt that leaves Rest available. | `AuthoritativeRestReasonTest` and action-contract tests. |
| K: positive and negative visibility | `ParticipationVisibilityLifecycleTest`, the Bias revisit and the Tragedy journey pair expected context with forbidden targets/evidence. | Existing packet, exhausted-history, projection and query-budget cases remain. |
| L: participant-source equivalence | `ParticipationSourceEquivalenceTest` compares HTTP state with a real worker's input and checks the complete seven-phase contribution contract with connection enablement toggled. | Worker locking/retry and concurrency coverage remain in their existing suites. |

## Fixture discipline

All constitutional HTTP cases use `ParticipationHttpTestCase`. Even legitimate
initial setup is performed through HTTP. The existing guard rejects structural
database writes outside application requests. Observers arrive through actual
contributions and remain parked; the tests do not move them directly.

The in-progress Domain driver selects only published actions, targets and directions. Real
measurements, Closure, receipt consumption, failure publication, recovery and
Horizon projection run throughout. Bounded action budgets fail with the complete
contribution trace. They are not substitutes for assertions about actual Domain
transitions and causal Horizon parents.

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

The final Epic checkpoint must validate one exact candidate across all suites
with an appropriate timeout and retained results. It must account for every
selected test, retain Query and Load, run the frontend build and action contracts,
and distinguish completed passes from cancellations. Full Integration includes
Core; overlap must not inflate the count of unique tests. No assertion, test,
or suite membership may be weakened merely to fit a runtime target. Targeted
Stage 5/6 results do not replace that final aggregate signal.

Stage 7 implementation cleanup remains a separate review stage under these
behavioural protections.
