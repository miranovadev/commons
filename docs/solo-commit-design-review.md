# Solo first-Commit design review

The explored-Write restriction blocks first Commit from a fresh Origin with one Participant and the current seven-Phase lifecycle. The blocker occurs before Bias. A healthy route can complete personal exploration, but the Participant cannot develop the pair of coherent Signals needed for its first Reconciliation. This finding concerns a fresh substrate with one Participant throughout. It does not establish a restriction on one Participant continuing a previously developed population history.

## Smallest healthy reproduction

Use the first 16 author actions of `tests/Fixtures/participation/persist-skeptical-commit.json`. The HTTP harness creates Origin and exercises each acquisition, Attestation, consumption and Rest through application endpoints.

| Live Cycle | Actions | Result |
| --- | --- | --- |
| 2 | Acquire at Origin, create Distinction A, acquire at A, create Persist B, Rest | B needs its first judgment |
| 3 | Attest A and B, Reclassify B into C, acquire at C, create Signal D, Rest | D has no personal Attestation yet |
| 4 | Revisit A and B, Attest C and D, Rest | Personal Exploration Event at D |
| 5 | Inspect Origin's action plan | Fresh Distinction is available. Existing Writes on the completed route have no actions |

B is born in A's Cycle, C in the following Cycle, and D in C's Cycle. The Persist, Constrain and Disperse intervals are therefore `0, 1, 0`. Distinguish has no preceding expressive observation. Against Origin's constitutional profile, the seven contributions are `[0, +1, -1, +1, 0, 0, 0]`. Phase Profile is `+1/7`. Shape Topology is negative, so the existing component-coherence gate rejects D for Reconciliation.

The diagnostic repeats the branch through a fresh Distinction and obtains the same intervals and inadmissibility. It also asserts that early Rest requests fail while fresh Persist generation or Constrain Reclassification is available.

## Why other healthy sequencing cannot change that result

At a fresh Persist Gate, acquisition completes in the current Phase and Cycle. The first Variant is then available and Rest is withheld. A contributed Disperse Preparation creates its Variant in the same Cycle. Deferring it or choosing Instrument leaves the Participant in that Cycle with the first Variant available.

Before the first Mediate Write exists, Constrain's initial visit requires Rest because the newly generated Persist Proposition has no Attestation. On the return, its first judgment makes Reclassification available. It consumes one ordinary Receipt, and Disperse acquisition supplies another observation. The tested paths retain completed evidence for the eventual Exploration witness.

Revision requires an owned leaf with an effective personal Refute from an earlier Cycle. Persist and Constrain nodes already have children by that time. A personally judged Signal reaches Mediate and its required Rest records completion before the Participant can return to revise it. The explored-Write filter then closes that action. Traverse and permitted earlier reversals change the route's judgments without changing its immutable birth intervals.

Only one unfinished branch can develop at a time. `PhaseGenerationService` withholds another Variant at Origin while an existing active head remains personally unexplored. Once the branch completes, its ancestors are suppressed as Writes, so a fresh Distinction starts another branch with the same timing. By induction, every healthy Signal before the first Reconciliation has the conflicting component directions. Mediate cannot produce its first Write, so Bias and Order remain unreachable.

The previous positive transcript escaped this pattern at step 16 by reversing its Attestation on explored Distinction `p1`. Step 17 then created another Persist Variant at that older Gate. That longer Persist interval was part of its subsequent admissibility. The approved restriction removes access to that sequence.

## Recorded failures provide a narrower exception

A second HTTP diagnostic acquires one preserved source, experiences actual reading failures at Persist and Disperse, and consumes the source's completed Instrument Receipt at Constrain. The remaining available observations are failed Preparations. Acquisition is complete, but `ExplorationEventService::completionWitness()` cannot find a completed execution for its evidence witness. Rest publishes no Event.

This route remains open for Revision. Two successive lawful Signal Revisions produce negative Phase Profile and coherent Correspondence. Neither obtains a second eligible operand. Earlier completed branches would still have their original positive Phase Profile, and the unfinished revised route prevents a fresh Origin Variant. Further Revision replaces the leaf of this same route. This exception changes the measurements, but cannot bootstrap the required pair.

## Executable investigation and design blocker

Run `vendor/bin/phpunit tests/Diagnostics/SoloCommitFrontierTest.php tests/Diagnostics/SoloFailedPreparationFrontierTest.php`. Five cases cover mixed judgments, all-Support, Refute with permitted reversals, Traverse, contributed and deferred Preparation, Instrument, a recorded unsuccessful Instrument, and the failed-Preparation Revision exception. The existing SQLite/PostgreSQL final-review jobs also run them.

The positive `HorizonProofCycleTest` remains enabled and unmodified. It passed on exact merged main `cafa8ab` and fails on the explored-Write branch. The population Commit journey passes with real staggered Participant entries, which introduce another birth interval. The remaining design conflict is whether fresh-Origin solo first Commit must remain reachable when completed ancestors are closed and fresh generation requires all existing heads to be explored. Resolving it requires an explicit design decision about those interacting rules. This investigation changes diagnostic tests, CI coverage and documentation.
