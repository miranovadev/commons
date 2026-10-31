# Generative acquisition validation

This report accompanies [the acquisition contract](generative-acquisition.md) and PR #291. Validation uses isolated Laravel databases, deterministic Instrument/reader adapters and GitHub's PostgreSQL 18 runners. No production/shared database or live autonomous cook was used.

## Corrected boundary

An acquisition belongs to one **exact Phase/generative opportunity**, not a whole Cycle. Distinguish, Persist and Disperse can each acquire and resolve independently within the same Cycle. The execution lookup, memoization and partial unique index use `phase_id`. Preparation admission and resolution also bind to the exact Phase. A completed earlier execution cannot authorize or suppress a later Phase's attempt.

One attempt still admits only one acquisition and one Variant/Defer outcome. Independently admitted Participants retain their opportunities after a competing Variant, subject to the existing genuinely invalidating structural boundaries. Defer still completes the Cycle without consuming its Receipt, creating a Proposition or publishing an Exploration Event. Exploration-first selection, causal Artifact accessibility and explored-Write suppression are unchanged by this correction.

The original unmerged migration is corrected to create `evidence_attempt_phase_unique`. Historical executions remain unadmitted and their Receipts remain intact; the migration regression preserves them across a round trip. A rollback with admitted attempt history is still refused. A database at merged main picks up the new index when it migrates. An isolated test database that already applied the superseded PR draft must be rebuilt to test the edited migration. No shared database was altered.

## Revisions and comparison

The exact merged-main baseline is `103e766339f590347c08639d3649ea049f7274da`. Its source tree equals PR #290's final head `d52fcc43bb1e4c034db625ae86f7f76cabb4ebc9`; Integration results at that head cover identical baseline source. Query and Load were also rerun at the exact merge SHA.

The mistaken Cycle-scoped checkpoint was `eb80e64e527887b524222f4f44c7836ec7e741aa`. Its campaign does **not** prove the corrected contract. Phase-scoped production, migration and natural journeys were revalidated at `e6386911136ddd3c103eae29d8a47f3247d13907`. `7197ee8464ef16f1a2d0154d1fe3c6d0123f6b88` corrects the remaining two Core fixtures and documentation; production is identical. Final documentation changes do not alter tested code. No measurement formula, numerical tolerance, test timeout, movement invariant or evidence-authority assertion was relaxed.

## Failing before, passing after

`GenerativeAcquisitionJourneyTest::test_progressive_preparation_deferments_preserve_observations_and_later_generate` failed against the Cycle-scoped implementation because Persist lacked `preparation.start` after a successful Distinguish Variant. It now passes with independent Instrument and Preparation executions sharing a Cycle but having different Phase IDs. It asserts that the first execution resolved through Variant, the second starts unresolved, and submitting the earlier execution to Persist returns a conflict. Variant remains unavailable until the current Phase acquires.

The natural Commit transcript retains its lawful deliberate birth staggering through actual Preparation/Defer choices instead of quota-induced Rest. It still reaches Reconciliation, Bias Reclassification and Commit through real measurements and ordinary Receipt authority. Separate direct journeys exercise immediate Distinguish and Persist acquisition/Variant in one Cycle. Wake now observes the persisted result before each following inference, can acquire in successive Phases of one Cycle, and stops at a genuine later Rest. Its existing deadline assertions remain unchanged.

The bounded solo diagnostic was corrected rather than carrying forward chronology from the mistaken quota. Immediate Persist gives birth intervals `0, 1, 0` and Phase Profile `+1/7`; a genuine Disperse Defer gives `0, 1, 1` and `-1/7`. The latter has coherent first-Mediate Correspondence in the tested history. These cases establish neither universal solo impossibility nor a complete solo Commit. See [the updated design review](solo-commit-design-review.md). The positive solo regression retains its original assertions and accepted narrow skip; no broad solo search was conducted.

## Natural journeys

| Journey | Executable evidence | Result |
| --- | --- | --- |
| Multiple opportunities in one Cycle | `GenerativeAcquisitionJourneyTest`, `ParticipationSourceEquivalenceTest`, `GovernedExpressionFixture` | Distinguish and Persist acquire independently; old execution reuse conflicts; human/autonomous sequence agrees |
| Progressive reading | `GenerativeAcquisitionJourneyTest::test_progressive_preparation_deferments_preserve_observations_and_later_generate` | Real Preparation/read/acknowledge/Defer twice, then Preparation/Variant; Receipts retain the Gate, no consumption or Exploration Event |
| Independent admitted opportunities | `GenerativeAcquisitionJourneyTest`, `AcquisitionAttemptTest`, `TransactionLockOrderTest` | Two admitted actors resolve competing Variants; Instruments, Preparations and external I/O preserve the admitted opportunity |
| Slower participation | `GenerativeAcquisitionJourneyTest` | Shared summaries and explicit loads are visible; own acquisition is required for fresh Variant, while first judgment on existing structure needs no acquisition |
| Repeated same-Gate attempts | `GenerativeAcquisitionJourneyTest::test_instrument_defer_repeats_at_the_same_gate_and_then_generates` | Defer closes each Cycle, preserves observations, and permits another lawful opportunity |
| Reconciliation, Bias, Commit | `PersistCommitHttpFixture`, fixed `persist-skeptical-commit.json`, `ParticipationHttpHarnessTest`, `FullEvidenceFlowTest` | Actual Reconciliation, Reclassification and Commit with unchanged measurement gates |
| Return, recovery and canonical history | `ParticipationVisibilityLifecycleTest`, `CommitDescendantHistoryLifecycleTest`, `ParticipationTragedyLifecycleTest` | Existing authority and canonical source assertions pass with real current-Phase acquisition |
| Causal Artifact accessibility | `PreparationExecutionTest`, `EvidenceExplorationLifecycleTest` | Consumed/secondary-operand sources qualify; unrelated sibling, foreign Receipt and guessed Artifact selections remain excluded |

Natural HTTP journeys retain the write guard against fabricated acquisition, Attestation, consumption, exploration and Commit state. Isolated authority/concurrency fixtures are distinct from those journeys.

## Validation campaign

The full campaign found no new failures beyond those reproduced on merged main. The remaining baseline failures and accepted skip are identified below; the aggregate workflows are not all green.

| Campaign | Result | Evidence |
| --- | --- | --- |
| Focused acquisition, migration and HTTP journeys | 24 passed / 426 assertions | Local SQLite |
| Wake, history and lock-order fixture checks | 20 passed, 1 PostgreSQL-only skip / 247 assertions | Local SQLite; original deadline assertions retained |
| Remaining Core fixture corrections | 25 passed / 246 assertions | Local SQLite; both affected PostgreSQL Core shards also pass in the final run |
| Phase Profile, Correspondence, natural Commit, canonical history and Stage 4 | All 12 jobs passed at both corrected revisions; five bounded solo cases passed / 1,001 assertions | [Final PostgreSQL/SQLite run](https://github.com/miranovadev/commons/actions/runs/41168077959) |
| C3 cognition | 132 passed / 1,279 assertions; recorded comparison 1 passed / 1,480 assertions | [Corrected run](https://github.com/miranovadev/commons/actions/runs/41167865060) |
| Full Core | 1,576 passed, 1 baseline failure / 16,500 assertions; migration preflight 14 passed / 46 assertions | [Final run](https://github.com/miranovadev/commons/actions/runs/41168078069), [exact-main baseline](https://github.com/miranovadev/commons/actions/runs/41159090951) |
| Integration | 37 passed, same 3 baseline failures, 1 accepted solo skip; the existing inventory-verifier failure remains | [Corrected run](https://github.com/miranovadev/commons/actions/runs/41167865108), [identical-source baseline](https://github.com/miranovadev/commons/actions/runs/41158123448) |
| Query | 15 passed, same 9 baseline failures / 1,038 assertions | [Final run](https://github.com/miranovadev/commons/actions/runs/41168077971), [exact-main rerun](https://github.com/miranovadev/commons/actions/runs/41164201245) |
| PostgreSQL Load / concurrency | 17 passed / 198 assertions, same 9 baseline failures; exact main: 15 passed / 180 assertions, 11 failures | [Final run](https://github.com/miranovadev/commons/actions/runs/41168077987), [exact-main rerun](https://github.com/miranovadev/commons/actions/runs/41164201252) |
| Frontend | 150 component tests passed; TypeScript and production build passed | Core frontend job and local typecheck |

The acquisition-specific race tests cover duplicate Instrument reservations, Instrument/Preparation races, contributed/deferred outcome contention and duplicate failure recording. Load passes concurrent Wake inference, waiting Reclassification and both external-observation cases. The external-observation cases preserve an admitted opportunity instead of expecting the old rejection; their safety assertions still require one outcome per execution and persistence before subsequent inference.

### Baseline failures retained

* `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` and Integration's `NaturalChargeBoundaryLifecycleTest` expect Settlement where their natural history produces Emergence. This domain mismatch is present on merged main.
* Both `FreshBootstrapTest` directions fail their bounded solo motor on baseline. They do not establish a universal impossibility or prove complete solo Commit.
* `HorizonProofCycleTest` retains its accepted narrow skip and original positive assertions. Integration's inventory verifier counts this skip inconsistently; the same verifier failure exists at the identical baseline source.
* Query's dense cognitive case is 54,511 bytes against its unchanged 45,000-byte budget. Eight `ParticipationPerformanceTest` cases fail with `ClosureHistoryUnavailable` before their performance assertions. All nine reproduce on exact main.
* Load retains nine exact-main failures: `BiasReceiptConcurrencyTest`'s foreign tragedy-Receipt fixture; three `HorizonProofConcurrencyTest` cases (Variant attempted without acquisition, Receipt-context count, stale Commit before lock); and five `TransactionLockOrderTest` cases (graph direction expectation, waiting Reconcile, waiting Commit, concurrent Rests and fresh-sibling exhaustion). No timeout or authority requirement was relaxed.
* SQLite's bulk-update immutability assertion in `CausalHorizonLineageTest` fails on exact main; PostgreSQL enforces it. No SQLite authority assertion was removed.

### Fixture corrections

Retired Cycle-wide expectations were corrected, not reclassified as accepted failures. These included an earlier Phase incorrectly blocking Disperse Instrumentation, forced Rest before Persist acquisition, Wake prematurely expecting a Cycle to end after the first Variant, and a history assertion counting the judgment introduced solely by that forced return. Wake fixtures that exercise later evidence authority now use real Origin entry. Natural Commit delays are explicit Preparation/Defer decisions. Receipt consumption, history preservation, measurement, deadline and movement assertions remain meaningful and enforced.

## Query and payload observations

Measurements below come from the PostgreSQL query jobs with unchanged representative populations. Timings are individual shared-runner observations, not a claimed speedup.

| Measurement | Merged main | Corrected Phase-scoped attempt |
| --- | --- | --- |
| Acquisition state lookup | 2 queries, 0 on warm repeat | 1 query, 0 on warm repeat in the same `ParticipationContext` |
| Active frontier, 2 → 8 heads | 27 → 27 queries; 32.28 / 57.56 ms | 26 → 26 queries; 31.48 / 57.43 ms |
| Overlapping Receipt ancestry, 1 → 21 Receipts | 26 → 26 queries; 608 → 13,174 bytes | Identical; no duplicate catalogue entries |
| Constrain / Bias context | 33,762 / 34,132 bytes; 29 objects | Identical |
| Recovery, 0 → 1,000 unrelated historical nodes | 42 → 42 queries; 54.43 / 55.09 ms | 42 → 42 queries; 53.57 / 64.83 ms |

The corrected natural Reconciliation fixture records 23 cold acquisition/exploration queries, zero on a warm repeat, 34 cognition queries and a 17,951-byte packet (14,524-byte catalogue, eight distinct Receipts). The two extra Receipts are actual deferred Preparation observations introduced by the explicit lawful transcript. They are retained and deduplicated rather than hidden to preserve an old payload size. This fixture differs in chronology from merged main, so its runtime is not a direct speed comparison. Existing bounded summaries and explicit-load limits remain intact.

## Remaining limits

Defer uses existing recovery and does not guarantee return to a Gate that has become unavailable. Prepared Revision is retired because it conflicts with the Variant-or-Defer attempt; ordinary lawful Revision remains independent. The accepted positive solo Commit limitation remains unproven by the bounded diagnostics, with its chronology conclusions updated as described above. No recovery redesign or mathematical change is included.
