# Evidence & Participatory Exploration: natural lifecycle validation

Stage 4 validates the merged Stage 1–3 contract against main `dbf5bbfdafd19e8f09e7f80f8912e01127233da6`. Its tree is `15cdd8bc2f03639940370179c355f9cbd2023b41`, exactly the validated Stage 3 head `6d79f568cad5d10eceb00295fb51fdd306f6b0e1`. The implementation PR records final CI results, baseline comparisons and measured PostgreSQL timings.

## Complete lifecycle

| Phase | Acquisition and participation | Independent authority |
| --- | --- | --- |
| Distinguish | Personal Instrument at the actual governing Committal before fresh generation | Shared Receipts do not complete another Participant's discovery; existing movement remains lawful |
| Persist | Personal Preparation when a readable Artifact exists; Instrument otherwise | Deferred examination resolves acquisition without generating or advancing; subsequent Variant is a separate action |
| Constrain | Effective judgment and ordinary evidence permit lawful Reclassification | Consumption attaches the original Receipt to the actual consumer |
| Disperse | One personal Instrument or Preparation at the exact Gate | Either completes the same durable role; contributed Preparation may atomically perform its separately authorised Variant/Revise |
| Mediate | Admissible Reconciliation brings qualifying operand evidence into cognition | Secondary evidence ancestry does not create movement authority |
| Bias | Effective Attestation, coherent Chirality components and lawful Receipt authority | Consumption remains an independent Reclassification relationship |
| Order | Closure and structural requirements govern Commit or existing Traverse | Canonical history and the next Cycle's Committal entry remain authoritative |

A visible Receipt always has a bounded summary. Its immutable observation may be explicitly loaded or included through a selected Read's **direct consumption**. Contextual inclusion does not persist a personal load. Artifacts remain separate preserved source material. Acquisition is durable per Participant × Gate × role. Exploration records personal route completion; actionless Rest alone does not. New active continuation or newly lawful forward generation may reopen a route without resetting earlier acquisition or judgments.

## Executable journey register

All `Api` journeys below inherit `ParticipationHttpTestCase`: database writes outside actual application requests or the real Wake job are rejected. External DNS/HTTP are deterministic, stray HTTP is prohibited, and Artifact storage uses the local test disk. No inference API, live website or shared object storage is used. Existing controlled fixtures are explicitly distinguished below.

| Journey | Executable evidence | What it establishes |
| --- | --- | --- |
| A: shared Origin | `EvidenceExplorationLifecycleTest::test_three_personal_origin_observations_include_a_resolved_failure_and_survive_return` | Three own discoveries; exact Gate/owner; visible distinct summaries; failed provider produces an unverified observation; shared evidence does not waive discovery; return does not reset it |
| B: Persist | Same test's citation fallback; `test_real_persist_read_failure_completes_only_after_attempted_delivery`; fixed Commit transcript | No-Artifact fallback, bounded reading, pending does not count, real storage failure counts, no Phase advance/consumption from acquisition, deferred examination and subsequent lawful generation |
| C: consumption/Read | `test_shared_disperse_choice_and_slow_read_context_preserve_personal_memory`; audited `ParticipationHttpHarnessTest::test_real_http_actions_reach_an_operational_commit` | Actual consumption in Phases 3/6; identity and provenance remain; slower reader gets `read_context` content with no load row; losing Read removes content; every displayed reference resolves once |
| D: personal choice | `test_shared_disperse_choice_and_slow_read_context_preserve_personal_memory`; shared-Origin test's Constrain-to-Disperse continuation | Two Participants use different mechanisms at the same Gate; later personal acquisition enriches cognition; completing one suppresses both; returned traversal does not reacquire; no-Artifact Disperse offers Instrument and then generation without a Preparation loop |
| E: Reconciliation | Audited fixed Commit transcript; `test_late_operand_ancestor_acquisition_refreshes_a_recorded_reconciliation` | Actual admissible Reconciliation; both operand histories and shared ancestry deduplicate; unrelated unattached sibling evidence excluded; late lawful acquisition at an operand ancestor appears without changing edges or consumption |
| F: slower Participants | Shared-Disperse test; `ParticipantExplorationHttpTest` | Personal effective witness, independent encounters, missing acquisition/judgment does not complete another's route; incomplete actionless Rest publishes no event |
| G: reopening/recovery | Shared-Disperse test; `ParticipationRecoveryTest`; `ParticipationLifecycleRegressionTest` | After both encounters, slower Participant lawfully reconsiders earlier judgments: this alone leaves the original Participant's route explored. New Disperse continuation then reopens it; the original Participant returns through existing structure and judges it without reacquiring; historical events remain unchanged; backward recovery and committed-route traversal retain their boundaries |
| H: Bias/Commit | Audited fixed transcript; `ParticipationTragedyLifecycleTest`; `ParticipationSourceEquivalenceTest` | Real seven-Phase execution, Bias consumption, canonical Commit, exact numerical assertions, next-Cycle entry and human/provider action parity |

The fixed transcript remains authored, not a runtime policy that searches for whatever action happens to pass. Its population, measurement values and thresholds are unchanged by Stage 4. The audit checks summaries, immutable execution Gates, actual consumers, deterministic deduplication, ID references and projection read-only behavior after every operation.

A Receipt acquired on an otherwise unrelated branch can qualify through **recorded consumption by an operand ancestor**. In the fixed journey, `r4` qualifies through `p13`; the unattached `rp8` does not. Excluding both merely because of acquisition origin would violate the approved association contract.

### Focused boundaries and limits of natural evidence

The following remain executable service-level/controlled-topology proofs, not claims that every permutation was recreated through a separate all-HTTP cook:

- `ParticipantExplorationTest` and `ParticipatoryCompletionTest`: new Reconcile/Reclassify/Commit opportunity, reversal/Revision/Traverse non-reopening, hard Supersession, convergence, foreign operand movement, stale/duplicate/rolled-back event publication.
- `GateCausalReceiptCognitionTest`: explicit load versus contextual inclusion, canonical inherited references, secondary-operand sibling exclusion and Horizon/Region boundaries.
- `PersonalGateAcquisitionTest`: abandoned/expired/invalid reservations versus genuine failures, role and Gate identity, failure completion and bounded history lookup.
- PostgreSQL `InstrumentAllowanceConcurrencyTest`, `PreparationConcurrencyTest`, `HorizonProofConcurrencyTest`, `BiasReceiptConcurrencyTest`: independent connections, competing acquisition mechanisms, duplicate resolutions, failure races, Receipt consumption and structural generation races.
- `PreparationJourneyTest`: real Wake delivery/presentation/deferment/contribution; `ParticipationSourceEquivalenceTest`: every post-Instrument inference sees an already-persisted completed Receipt and fresh acquisition state in the same Phase.
- Existing UI tests cover nonblocking action selection and stale request rejection. Stage 4 adds no UI rule.

Persist intentionally offers deferment as acquisition resolution, followed by an ordinary structural action. A contributed Preparation remains the existing Disperse atomic workflow; no artificial Phase 2 Disperse contribution is introduced. Evidence shortage may lawfully yield Rest without an Exploration Event. Successful Commit is demonstrated by the fixed journey, not promised for an arbitrary inference policy.

## Demonstrated integration correction

**Invariant:** after a resolved evidence observation, a subsequent human request or Wake must receive fresh authoritative participation state; a failed read must not fabricate verified evidence or advance the Phase.

On exact merged main, the new real Persist storage-failure test produced a failure Receipt, then failed its next GET assertion: expected `ready`, received `waiting` with `potential_projection_pending`. The external reading endpoint and Wake failure paths do not return through the ordinary successful contribution refresh. The Receipt advanced the field version while the projection retained its prior version.

The only production correction is in `PreparationService::fail`: after a genuine failure Receipt is recorded, schedule the existing projection refresh **after transaction commit**, outside evidence write locks. Abandoned reservations still create no Receipt and gain no completion. Duplicate failure resolution returns before scheduling another refresh. No measurement formula, field-version guard, acquisition rule, movement permission or error classification changes.

The regression verifies the 422 reading failure, completed personal acquisition, unchanged Phase, unverified failure metadata, absence of fabricated deliveries, rejected repeated delivery, one Receipt and subsequent lawful Persist generation. The existing rollback and concurrency tests remain in the campaign. Two controlled failure fixtures first publish their earlier dirty structural impacts before taking their unchanged-structure snapshot; the snapshot assertion itself is retained.

Reproduce the focused natural campaign with:

```sh
php artisan test tests/Feature/Api/EvidenceExplorationLifecycleTest.php
php artisan test tests/Feature/Api/ParticipationHttpHarnessTest.php tests/Feature/Api/ParticipationSourceEquivalenceTest.php
php artisan test tests/Feature/PreparationFailureReceiptTest.php tests/Feature/PreparationJourneyTest.php
```

## Performance campaign

- Natural Reconciliation fixture: measure actual acquisition/exploration evaluation cold and repeated in one `ParticipationContext`, then fresh causal cognition query count and catalogue/full-packet bytes. Local SQLite observation: 24 cold queries, **0 repeated queries**, 34 cognition queries, 14,618 packet bytes and 11,207 catalogue bytes for nine Receipts. PostgreSQL output in CI is authoritative for that engine.
- Existing query suite: acquisition history at 1/12 abandoned reservations; active frontier at 2/8 heads; overlapping operand histories at 1/21 Receipts. Deduplication and payload bounds stay asserted.
- The C3 workflow compares the exact PR base and proposed head, including existing failures. No query/payload threshold, numerical tolerance or timeout is relaxed.
- The full Core and Integration suites include migrations on isolated PostgreSQL databases; Core additionally runs its SQLite migration preflight. Frontend build/typecheck, UI tests, C3 and Phase Profile remain part of validation.

The first PostgreSQL comparison of this correction (`d2bffb3`) against exact base `dbf5bbf` measured unchanged acquisition queries (2 → 2 as abandoned attempts grew from 1 → 12, then 0 memoized), frontier queries (27 → 27 for 2 → 8 heads), and overlapping Receipt queries (26 → 26 for 1 → 21 Receipts). The overlapping catalogue grew from 608 to 13,174 bytes identically on both versions. Frontier wall time was 41.99/69.71 ms on main and 36.58/58.22 ms on the proposal; these single CI samples are observations, not a speedup claim. Both query runs reported the same 15 passes and nine baseline failures.

Known baseline failures from the identical merged-main tree are one Core boundary assertion, three Integration cases (two bootstrap-driver stalls and the same boundary assertion), and nine query cases (one oversized packet and eight unavailable Closure-history fixtures). They are not treated as passing journeys. Broader performance claims remain limited by those existing fixtures.

## Historical reference

The read-only diagnostic `scripts/diagnostics/participatory-exploration.py` reproduces the October 26 export: 29 executions, 21 Preparations, two preserved Artifacts, 86 Exploration Events and 34 foreign witnesses. Five Participant/Gate/Phase groups have repeated executions under the old lifecycle. All 86 events had effective personal head judgments at event time; selected-route reconstruction finds no missing active-route judgment candidates. This is not exact action-packet replay, proof that every historical opportunity was reachable, or retroactive Stage 2 completion. The export is never modified.

## Boundary register

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| Failed observation | Can the next request remain stale? | Refresh existing projection after committed failure Receipt | Stage 4 §§5,12,16 | Documented; integration defect corrected | Real HTTP failure regression |
| Sibling acquisition | Is origin alone sufficient to exclude a Receipt? | Honour actual qualifying consumption associations | Stage 1 and Stage 4 §§6,8 | Documented | Fixed Reconciliation audit |
| Reopening | Does return renew acquisition? | Preserve Participant × Gate × role completion | Stage 2; Stage 4 §10 | Documented | Natural slower-Participant continuation |
| Persist contribution | Must Preparation generate structure? | Defer resolves reading; generation remains separate | Stage 2 §5 | Documented | Fixed transcript and failure continuation |
| Inadmissible measurement | Should validation force Commit? | Preserve lawful non-progression and all thresholds | Stage 4 §§1,11,16 | Documented | Bootstrap baseline and fixed successful journey |

No new semantic policy is needed for the demonstrated correction. No production/shared database records are modified, no historical event is rewritten, and no live autonomous cook is initiated.
