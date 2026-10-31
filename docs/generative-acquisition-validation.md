# Generative acquisition validation

This report accompanies [the acquisition contract](generative-acquisition.md) and PR #291. Validation uses isolated Laravel databases, deterministic Instrument/reader adapters and GitHub's PostgreSQL 18 runners. No production/shared database or autonomous cook was used.

## Revisions and comparison

The exact merged-main baseline is `103e766339f590347c08639d3649ea049f7274da`. Its source tree equals PR #290's final head `d52fcc43bb1e4c034db625ae86f7f76cabb4ebc9`; existing Integration results at that head therefore cover the exact same baseline source. Query and Load were also rerun on a validation ref at the exact merge SHA.

Production and journey validation ran at `c40364b10482fa0521e45c73898c91c95907ee0e`. `ebec57970904ea7911dc5f79d64a5075e3aa5bec` adds a historical migration round-trip regression, corrects the concurrent Wake fixture to acquire in a new Cycle, and updates documentation; production is identical. The final report separates baseline failures from changed fixture expectations. No threshold, numerical tolerance, test timeout or evidence-authority assertion was relaxed.

## Natural journeys

| Journey | Executable evidence | Result |
| --- | --- | --- |
| Progressive reading | `GenerativeAcquisitionJourneyTest::test_progressive_preparation_deferments_preserve_observations_and_later_generate` | Real Preparation/read/acknowledge/Defer twice, then a third Preparation/Variant; distinct Receipts retain the Gate, no consumption or Exploration Event |
| Independent admitted opportunities | `GenerativeAcquisitionJourneyTest`, `AcquisitionAttemptTest`, `TransactionLockOrderTest` | Two admitted actors can resolve competing Variants; tested for Instruments, Preparations and external I/O while another writer contributes |
| Slower participation | `GenerativeAcquisitionJourneyTest::test_slower_participant_loads_shared_deferred_observations_then_uses_its_own_attempt` | Shared summaries and explicit loads are visible; own acquisition is still required for fresh Variant. Separate journey verifies first judgment on existing structure without acquisition |
| Repeated same-Gate attempts | `GenerativeAcquisitionJourneyTest::test_instrument_defer_repeats_at_the_same_gate_and_then_generates` | Defer closes each Cycle, preserves observations, and permits a later lawful attempt |
| Reconciliation, Bias, Commit | `PersistCommitHttpFixture`, fixed `persist-skeptical-commit.json`, `ParticipationHttpHarnessTest`, `FullEvidenceFlowTest` | Revised natural transcript reaches actual Reconciliation, Reclassification and Commit using real measurements and ordinary consumption |
| Return, recovery and canonical history | `ParticipationVisibilityLifecycleTest`, `CommitDescendantHistoryLifecycleTest`, `ParticipationTragedyLifecycleTest` | Existing traversal/recovery and canonical source assertions pass with the new birth chronology |
| Causal Artifact accessibility | `PreparationExecutionTest` and `EvidenceExplorationLifecycleTest` | Consumed/secondary-operand sources qualify; unrelated sibling, foreign Receipt and guessed Artifact selections remain excluded |

The HTTP journeys retain the write guard that rejects fabricated acquisition, Attestation, consumption, exploration and Commit state. Isolated authority/concurrency fixtures are explicitly distinct from these natural journeys.

## Validation campaign

| Campaign | Result | Evidence |
| --- | --- | --- |
| Focused local acquisition, Preparation, Receipt cognition and personal exploration | 167 passed / 1,691 assertions | SQLite; includes four new natural journeys |
| Final acquisition/migration suite | 15 passed / 97 assertions | SQLite, after the migration regression was added |
| Phase Profile, fixtures, final review, natural Commit, canonical history and Stage 4 | All 12 jobs passed | [PostgreSQL and SQLite run](https://github.com/miranovadev/commons/actions/runs/41164503512) |
| C3 focused contracts | 132 passed / 1,279 assertions; recorded reprojection 1 passed / 1,480 assertions | [C3 run](https://github.com/miranovadev/commons/actions/runs/41164508254) |
| Full Core | 1,575 passed, 1 baseline failure / 16,492 assertions; migration preflight 14 passed | [Current run](https://github.com/miranovadev/commons/actions/runs/41164503532), [exact-main baseline](https://github.com/miranovadev/commons/actions/runs/41159090951) |
| Integration | 37 passed, 3 baseline failures, 1 accepted solo skip; baseline inventory-verifier failure also remains | [Current run](https://github.com/miranovadev/commons/actions/runs/41164508249), [identical-source baseline](https://github.com/miranovadev/commons/actions/runs/41158123448) |
| Query | Both revisions: 15 passed, same 9 failures | [Paired run](https://github.com/miranovadev/commons/actions/runs/41164508254), [exact-main rerun](https://github.com/miranovadev/commons/actions/runs/41164201245) |
| PostgreSQL Load / concurrency | Proposed: 17 passed / 198 assertions, 9 baseline failures. Exact main: 15 passed / 180 assertions, 11 failures | [Final fixture run](https://github.com/miranovadev/commons/actions/runs/41165155178), [exact-main rerun](https://github.com/miranovadev/commons/actions/runs/41164201252) |
| Frontend | 150 component tests passed; TypeScript and production build passed | Core frontend job plus local typecheck |

The acquisition-specific PostgreSQL race tests also pass in Core: duplicate Instrument reservations, Instrument/Preparation races, contributed/deferred outcome contention and duplicate failure recording. The final Load rerun passes concurrent Wake inference and both external-observation cases. Those two cases now preserve a pre-admitted opportunity instead of expecting the old post-observation rejection. Their unchanged safety assertions still require one outcome per execution and real persisted observation before subsequent inference.

The final historical-migration regression also passed on PostgreSQL at `ebec579`: Core shard 1 ran 317 tests / 3,427 assertions, plus the 14-test migration preflight. It preserves multiple historical executions and their Receipts exactly across migration, leaves them unadmitted, and permits one real new attempt. [Final migration and fixture rerun](https://github.com/miranovadev/commons/actions/runs/41165155177). All 12 [Phase Profile jobs](https://github.com/miranovadev/commons/actions/runs/41165155321) passed again on this revision. The final documentation commits do not change tested production or test code.

### Baseline failures retained

* `ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary` and Integration's `NaturalChargeBoundaryLifecycleTest` expect Settlement where their constructed natural history produces Emergence. This domain mismatch is present on merged main.
* Both `FreshBootstrapTest` directions fail their bounded solo motor on baseline and proposed. The new trace reaches further conditions but does not prove a complete solo Commit. It is not classified as a new universal impossibility.
* `HorizonProofCycleTest` retains its previously accepted narrow skip and original positive assertions. Integration's inventory verifier still counts this skip inconsistently; the same verifier failure exists at the identical baseline source.
* Query's dense cognitive case remains 54,511 bytes against its unchanged 45,000-byte budget. Eight `ParticipationPerformanceTest` cases still fail with `ClosureHistoryUnavailable` before their performance assertions. All nine failures reproduce on exact main.
* Load retains nine exact-main failures: `BiasReceiptConcurrencyTest`'s foreign tragedy-Receipt fixture; three `HorizonProofConcurrencyTest` cases (Variant attempted without acquisition, Receipt-context count, stale Commit before lock); and five `TransactionLockOrderTest` cases (graph direction expectation, waiting Reconcile, waiting Commit, concurrent Rests and fresh-sibling exhaustion). The Closure-history and lock-entry fixture failures remain explicit; no timeout or authority requirement was relaxed. The new acquisition-specific concurrency paths pass independently.
* SQLite's bulk-update immutability assertion in `CausalHorizonLineageTest` also fails on exact main; PostgreSQL enforces that assertion. No SQLite authority assertion was removed.

### Corrections demonstrated during implementation

Focused regressions cover the retired permanent allowance, Variant before acquisition, receipt-less abandoned reservations, duplicate admission/resolution, cross-Participant reuse and causal Artifact selection. The hard-Supersession regression first exposed an overbroad admitted-opportunity bypass; the corrected check blocks Variant while preserving safe Defer and the original observation. The human Instrument resolution regression first failed because the schema advertised Defer without a control; the existing Gate card now offers it. PostgreSQL exposed JSON object-key ordering in reserved Instrument identity; matching now normalizes keys without rewriting stored input.

Fixture corrections use actual extra Cycles, first judgments and evidence operations. Preparation no longer authorizes a Revision, a deferred attempt no longer continues generating in the same Cycle, and a spent early-Phase attempt no longer authorizes another acquisition later in that Cycle. These deliberate contract changes are distinguished from runtime regressions.

## Query and payload observations

Measurements below come from the paired PostgreSQL query jobs, using unchanged representative populations. Timings are individual shared-runner observations, not a claimed speedup.

| Measurement | Merged main | Proposed |
| --- | --- | --- |
| Acquisition state lookup | 2 queries, 0 on warm memoized repeat | 1 Cycle-scoped query, 0 on warm repeat |
| Active frontier, 2 → 8 heads | 27 → 27 queries; 25.02 / 42.34 ms | 26 → 26 queries; 29.93 / 54.97 ms |
| Overlapping causal Receipt ancestry, 1 → 21 Receipts | 26 → 26 queries; 608 → 13,174 bytes | Identical; no duplicate catalogue entries |
| Constrain / Bias context | 33,762 / 34,132 bytes; 29 objects | Identical |
| Recovery with 0 → 1,000 unrelated historical nodes | 42 → 42 queries | 42 → 42 queries |

The revised natural Reconciliation fixture records 23 cold acquisition/exploration queries, 0 on repeat within one `ParticipationContext`, 34 cognition queries and a 13,675-byte packet (10,248-byte catalogue, six distinct Receipts). Its chronology differs from the baseline fixture, so those numbers are a bounded observation rather than a direct latency comparison. Existing explicit-load limits and bounded summaries are retained; this PR does not claim to fix the existing oversized dense-packet case.

## Remaining limits

One attempt is allowed per whole Cycle, as specified in the handoff. An early Variant therefore spends that Cycle's opportunity; later Phase generation may require return in another Cycle. Defer uses existing recovery and does not guarantee re-entry to a Gate that has become unavailable. Prepared Revision is retired because it conflicts with a Variant-or-Defer attempt; ordinary lawful Revision remains available independently.

The acquisition chronology removes the earlier solo diagnostic's first-Mediate component contradiction: its unchanged Phase Profile is now `-1/7`, or `-3/7` after a Defer Cycle, coherent with negative Shape Topology. A full solo Commit remains unproven by the retained bounded driver. [The design review](solo-commit-design-review.md) distinguishes the old historical proof from this result. No separate solo search, recovery redesign or mathematical change is included.
