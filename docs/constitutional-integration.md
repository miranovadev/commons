# Focused constitutional participation coverage

> The exploration portions below are historical. Current personal events, active heads and their replacement proofs are documented in [Participant-relative exploration](participant-relative-exploration.md).

> Historical checkpoint. Rest-born Tragedy, final-attempt permission, consumption-based exhaustion and recovery-following warrant scope described here are retired. Current authority is [first/later fresh Charge-boundary events](branch-tragedy.md); this document retains historical validation details.

Constitutional acceptance is a collection of focused production-path invariant
proofs. It no longer requires a deterministic full Domain round trip in Core or
Full Integration. Minimizing the natural traces is explicitly stopped. Fresh
substrates running naturally validate emergent behavior separately from these
fixed proofs. The later Homeostatic epic's Stage 6 adds fresh schema-driven HTTP
probes; see [its validation report](homeostatic-stage-6-validation.md).

`composer test:constitutional` selects 13 cases, all included in ordinary Core
and Full Integration. They use predefined real API actions and deterministic
expected intermediate state. No constitutional test depends on an autonomous
probe eventually returning. Numerical Potential, structural actionability and
current spentness remain separate concepts.

## Focused acceptance map

| Invariant | Production-path proof | Boundary retained |
| --- | --- | --- |
| Repeated Variant after genuine exhaustion | `ParticipationLocalEpochTest`: first, second and third Distinguish Variants through real failures and Rest | Before reopening all current options are spent; only the local epoch advances; old failures stay immutable |
| Independent structural epochs | Two epoch regressions in `ParticipationLifecycleRegressionTest`, plus `ParticipationStaggeredEpochTest` | A later Committal cannot reopen Origin; different Disperse Gates advance independently; parked participants observe A reopening while B stays spent, then B reopening |
| Successful-route forward navigation / Bias revisit | `ParticipationLifecycleRegressionTest::test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit` | From the exact original causal Gate, traverse its frozen Bias classification and that classification's direct Commit; reject source mutation and inappropriate Rest; preserve content, attestations, commitment snapshot and failure history |
| Rest availability | Two constitutional cases in `ParticipationHttpHarnessTest` | Productive actions exclude Rest; an unread informational receipt does not; reading it leaves the Phase unchanged |
| Backward recovery and actual distance | Two cases in `ParticipationRecoveryTest` | API-produced phase Gates establish the traversed path; untraversed successful Commit is excluded; actual actionless Rest recovers two edges backward |
| Current-Horizon confinement and boundary recovery | `ParticipationTragedyLifecycleTest` | Every live final-attempt step stays in its Horizon until Exhaustion; recovery then crosses the actual API-created boundary backward, counting distance across both Horizons; exploration resumes |
| Tragedy lifecycle | The same fixed 107-step Tragedy journey | Natural creation, receipt availability, another participant's legitimate final Variant, Read, consumption, later Exhaustion; duplicate claims and foreign-Horizon receipt access rejected; original evidence unchanged |
| Visibility, positive and negative | `ParticipationVisibilityLifecycleTest`, Bias revisit and Tragedy assertions | Expected propositions, attestation summaries, receipt evidence and parent references are visible; foreign/distant structure and forbidden action targets are absent/rejected; exhausted successful history stays visible without becoming failure |
| Multi-participant evidence and action semantics | Tragedy's producer/consumer/outside observer; independent-epoch parked observers; visibility's separate author/observer; two `ParticipationSourceEquivalenceTest` cases | Shared receipt consumption is observed by another participant; outsiders cannot use it; one participant's local advance does not move or reopen another position; manual/connected state and worker contribution contract agree |

The successful-route case records a positive causal journey. It does not prove
rejection of an unrelated frozen classification in the same Region/Horizon.
[Invariant 10](core-invariants.md#10-movement-follows-authorised-structure)
requires that negative boundary independently: calculation populations and
visibility alone cannot become Traverse targets. The completed Homeostatic model
has one explicit additional rule for Order Committals, independently validated
by `HomologousOrderTest`; it does not broaden ordinary Traverse in other Phases.

Recovery's controlled near/far fixtures remain in `CausalRecoveryTest`: they
prove inverse-square attenuation can change the winning ancestral Committal,
exclude attractive siblings/descendants/unrelated nodes, and cross a Horizon
boundary through an intermediate ancestor rather than its provenance opener.
These are explicitly recovery-primitive fixtures. They complement the real HTTP
recovery journeys; they are not described as API-created numeric conditions.
Revision and multi-input structural identity tests also retain their existing
Core placement.

## Fixture discipline

`ParticipationHttpTestCase` creates substrates, participants, topology, receipts,
attestations and cycles through actual HTTP requests. Its guard rejects fixture
writes outside application requests. Source equivalence additionally executes
the real worker with three predefined inference responses. Only external
inference and external evidence transport are deterministic substitutes.

`ScriptedParticipationTestCase` expands fixed expected-state deltas before the
first request, then checks before and after every scripted contribution. Identity
bindings come from declared response paths, not an eligibility picker. Parent
references are compared as an exact unordered collection because their model
relation is unordered; ordered context populations remain strict. Expectations
are never regenerated to make a failing test pass. See the
[fixture contract](../tests/Fixtures/participation/README.md).

## Manual natural replay

Both original natural captures remain byte-identical in `tests/Replay/Golden/`,
with compressed and uncompressed hashes. Both complete fixed scripts were moved
byte-for-byte into `tests/Replay/Fixtures/`. Their test bodies and lifecycle
assertions remain in `tests/Replay/ParticipationDomainCycleTest.php`; only the
namespace, fixture paths and selection labels changed.

Use `composer test:replay-domain` to run the optional 600-contribution solo and
630-contribution collective replays. The actual operational round trip is
Settlement → Emergence → Settlement. Origin bootstrap is not substituted for a
working Horizon. These artifacts are available for reference, manual replay and
performance investigation; mandatory PHPUnit discovery covers `tests/Unit` and
`tests/Feature`, not `tests/Replay`.

Only the two Stage 6 long replay cases leave mandatory discovery under the revised
acceptance decision. No Stage 1–5 correctness test, assertion or suite assignment
is removed or weakened. Full Integration retains the broader existing
`HorizonProofCycleTest` and `FullEvidenceFlowTest` service journeys. There is no
replacement minimal-Domain test and no further minimization work.

## Validation evidence and limits

- The Bias revisit is **resolved**, not an expected red regression. Its Stage 5
  semantic fix remains unchanged. Fresh isolated SQLite replay passed 4,318
  assertions in 10.58 seconds (10.65 seconds command duration).
- The exact 13 focused cases previously passed on PostgreSQL 18 / PHP 8.5 at
  `48a2f2f4ad379a26e53d1f1e5489cf834fbee9c0`: 36,958 assertions, 857.33 seconds
  PHP, 15m31s complete job. [Run 35428949764, job 105859856314](https://github.com/miranovadev/commons/actions/runs/35428949764/job/105859856314),
  artifact `10580925373`. The Tragedy case took 216.69 seconds; Bias revisit took
  47.51 seconds. All selected test bodies, support code and JSON fixtures are
  unchanged from that completed PostgreSQL pass. This is retained targeted
  evidence, not a claim of a new PostgreSQL or aggregate run.
- Fresh SQLite validation after the manual-replay split passed **13 tests / 36,958
  assertions in 107.46 seconds**. Bias revisit took 11.18 seconds within the
  selection; the complete Tragedy journey took 27.52 seconds. There were no
  failures, skips or expected-red exceptions. Pint passed. This is focused
  evidence, not a full Core/Full/Query/Load run.
- Membership collection: **Core 617; Full Integration 619; Query 16; Load 22;
  657 unique mandatory cases**. All 13 constitutional cases belong to Core.
  The two manual replays are absent from every mandatory selection. Compared with
  Stage 5, the only added mandatory case is the staggered epoch proof.
- Both fixed replay fixture hashes match their pre-move originals. Both compressed
  natural captures and their decompressed contents match the preservation manifest.
- Stage 5's cancelled Core/Full runs still **do not count as complete suite
  passes**. Its unchanged isolated PostgreSQL Tragedy pass remains accepted
  evidence for the full Tragedy flow and backward Horizon-boundary recovery.
  See [Stage 5's record](stage-5-lifecycle-correctness.md#accepted-validation-evidence).

There are no remaining expected-red focused lifecycle regressions. Focused passes
are not a completed all-suite signal. The previously requested deliberate final
Epic aggregate validation remains outstanding, now using mandatory suites without
the manual Domain replays. Final aggregate validation is now authorized. Core and
Full Integration have 120-minute job ceilings, and Query/Load retain 20 minutes. These are
ceilings, not extra work: suite membership, assertions, statement timeout,
frontend build and action contracts remain unchanged. The final checkpoint will
record complete results for one candidate; targeted evidence is not substituted
for those runs.

## Deferred work

The [runtime investigation](constitutional-runtime-investigation.md) and
[measurements](evidence/constitutional-runtime.json) remain preserved. Repeated
lineage/observable recalculation is the fastest-growing measured cost in the
SQLite journey sample; PostgreSQL attribution is unmeasured. That optimization
work is explicitly deferred to a separate performance effort. No production
performance or lifecycle semantics change in this acceptance/suite update.

Stage 7 cleanup, final aggregate validation and fresh natural substrates are
separate subsequent work. Nothing has been merged or deployed here.
