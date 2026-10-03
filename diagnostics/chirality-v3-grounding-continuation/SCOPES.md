# Implementation and canonical contract evidence

Source pin: `5eb40cd8569edeb0ea8b41fd8f79c8318c067460`, the preserved autopsy branch.
These findings describe that implementation; they do not promote diagnostic CV Cadence.

## Exact objects and opportunities in the archived autopsy

| Boundary | Missing-vote object | How it was created | Existing ordinary opportunity | Why absent |
|---|---|---|---|---|
| First mature Constrain, step 33 | `01m40f324pp4sxbeb55gqf3awx`, expressive Persist / stance Write, statement `Author 0 development 3` | A0, Cycle 2, Persist, step 3 | A1 is now at Persist and can `attestation.create` on this exact Write | Earlier supporters selected Instrument; historical acts at steps 2/15/32 target its Distinguish parent, not this Write |
| First Bias, step 72 | `01m40f656b7yzvx9frzzfgae5s`, expressive Mediate / stance result, statement `Author 1 development 66` | A1, Cycle 4, Reconcile at step 69, from the Disperse results of steps 52 and 66 | A2 at Mediate is offered `attestation.create` on this exact result at step 70 | Scheduler selects Instrument at 70 and advances A2 to Bias; neither parent votes nor the Instrument create a vote on the new result |

The IDs above are the preserved autopsy IDs, not fresh-run ULIDs. In the continuation,
identity is tracked through the replay's bijective input-ID map and explicit target
IDs in the runtime trace. No original database is edited.

There is no rule that the source author or next classifier must supply its vote.
`PropositionStateService::attestations` aggregates effective votes on the permitted
source IDs without restricting them to the acting participant. A contributing
attestor must own their current Phase, belong to the substrate, and have the exact
target in that Phase's ordinary Attestation options. A1 and A2 above are concrete
eligible existing actors, not special roles or new population.

At Constrain/Bias, the current Gate is the previous Phase's Write. Ordinary
Attestation acts target **the acting participant's eligible current Writes**, not
any proposition they choose. Thus the blocked participant cannot simply vote on
the current Gate from Constrain/Bias. A peer still at its producing Phase can
support it. At Order, Attestation and Instrument are excluded entirely; another
participant at Bias can ground the selected Bias Write while Order waits.

## Scope comparison

| Aspect | Existing developmental-Cadence diagnostic | Effective source grounding |
|---|---|---|
| Object being observed | The selected head's causal lineage in its current Context Horizon, with immediate-parent Horizon comparison | The selected source proposition; additional source IDs only through explicit Commit history |
| Attestation population | Every historical act whose `proposition_id` is in that lineage; all participants; includes superseded acts | Only currently effective acts: no `supersededBy` relation |
| Cycle population | Cycles in the lineage's Horizon entering represented Committals, plus Origin's special producing Cycle | No interval or Cycle-population criterion for vote existence |
| Interval unit | Represented Cycle entries strictly between consecutive selected acts, using the preserved diagnostic occurrence order | Not a timing statistic |
| First-Settlement maturity | Fewer than two intervals: numeric zero with immature state; otherwise nonzero-mean CV and `(1-CV)/(1+CV)` | Requires an actual effective vote on the permissible source IDs; maturity never substitutes for it |
| Inheritance | Recomputed from ancestral historical acts; no copied vote or automatically inherited +1 value | Expressive Variant/Revise/Reclassify/Reconcile reset contribution history; Commit references source history transitively |
| Direction | Diagnostic Cadence has its own resolved component direction | Existence check itself does not require Support; other operational direction checks remain separate |

“Domain rhythm” is therefore shorthand. This observable is **not** an indiscriminate
Horizon-wide Attestation stream. The head determines which ancestral propositions
and acts are represented. Its represented entry-Committals determine a broader
Cycle-entry population within the same Horizon. The candidate uses all such entries,
including unfinished/rested/otherwise contribution-free entries, but this authored
history creates no artificial participants or idle padding to exploit that scope.

The diagnostic `CandidateLocalMeasurements::intervals` queries historical acts
without `effective()` and filters their target propositions to the selected lineage.
`ContextLineageComparisonService::endingAt` builds that lineage via causal parents
within the exact Horizon. `LineageMeasurementService` separately attenuates local
measurements along ancestry; it does not grant source Attestations.

The production `ChiralityCalculator` at this pin still computes its existing
act/entry population similarity, **not** the provisional first-Domain CV rule. Its
historical-act versus effective-evidence distinction is nevertheless explicit and
tested. This investigation reused the existing diagnostic candidate unchanged.

## Grounding and receipts are not one interchangeable scope

`Attestation::scopeEffective` excludes superseded votes. `ContributionDispatcher::attest`
creates a vote for the specified target through the actor's Phase, superseding that
actor's previous effective vote on that same target if applicable. It records the
act in the ledger; the returned proposition participates in normal Phase advancement.

`PropositionStateService::sourceIds` stops at an expressive proposition. For a
Committal it follows explicit `commitSources`, including prospective Commit sources,
and never treats generic structural parents as contribution-history inheritance.

For Constrain/Bias Reclassify, `PhaseGenerationService::available` checks effective
source Attestation, an unused source/register classification and an authorized
ordinary/Bias Receipt catalogue, alongside structural/Gate conditions. The Receipt
must not be confused with a requirement for a direct source Instrument at every
Reclassify. For Commit, `CommitmentAdmissibilityService::grounding` explicitly
requires both source Attestation and completed evidence Receipt, plus its separate
Closure/position/causal/Region/opportunity checks.

`PropositionOptionService::attestationOptionsFor` permits non-expressive-phase Write
Attestation with either a shared ordinary available Receipt **or** existing target
evidence. Distinguish/Disperse add their instrumented-target requirement. Thus the
new reconciled result really can be offered for Attestation at archived step 70,
even before the chosen Instrument gives it direct evidence. The schema, not a
hypothetical repaired fixture, establishes that opportunity.

## Expected cooperative lifecycle

| Acting Phase | Object/action | Required evidence and consequence |
|---|---|---|
| Persist | Peer attests existing Persist Write P | Target must be a legal current Write with Receipt eligibility; effective vote on P is authored |
| Constrain | Classifier Reclassifies P | P's effective vote + authorized Receipt + ordinary predicates; creates result Q with no copied vote |
| Disperse | Authors produce ordinary expressive alternatives | Evidence/support and recovery follow existing rules; no fabricated partner |
| Mediate | Reconcile two eligible distinct Disperse Gates | Creates result R; parent votes and parent receipts are not automatically inherited |
| Mediate | Peer attests R (or instruments it where evidence is needed) | R is a current Write for that peer; action advances that peer to Bias |
| Bias | Reclassify R | Requires R's own effective vote, authorized Receipt, Chirality/structural conditions; creates Bias result B |
| Bias | Another participant attests B | Classification supplies its selected evidence Receipt reference; new effective vote on B is still required |
| Order | Commit B | Rechecks effective source vote, completed evidence, Closure and structural predicates; then production field determines Domain |

This is an available contract, not a claim that every schedule can maintain its
field or that every waiting participant should Rest immediately. A pending actor
can remain at its Phase while another participant contributes. `stateFor` rechecks
current evidence; it does not freeze the absence of a vote forever.

## Canonical tests establishing intentional separation

- [ReconciliationTest::test_reconcile_is_structural_selection_with_exact_gate_content](https://github.com/miranovadev/commons/blob/5eb40cd8569edeb0ea8b41fd8f79c8318c067460/tests/Feature/ReconciliationTest.php): after genuine reconciliation operation, result has zero Attestations and receipts, source IDs only itself; the two parent votes still exist on their parents.
- [ReclassificationEvidenceTest::test_reclassification_preserves_evidence_and_content_without_copying_judgments_or_uptake](https://github.com/miranovadev/commons/blob/5eb40cd8569edeb0ea8b41fd8f79c8318c067460/tests/Feature/ReclassificationEvidenceTest.php): evidence references/content are preserved while result Attestation count stays zero.
- [CommitParticipationStateTest::test_commit_cannot_recover_history_from_before_a_non_commit_reset](https://github.com/miranovadev/commons/blob/5eb40cd8569edeb0ea8b41fd8f79c8318c067460/tests/Feature/CommitParticipationStateTest.php): Variant, Revise, Reclassify and Reconcile do not grant prior contribution history; a later Commit inherits only newly earned permitted history.
- [OperationalCommitmentTest::test_bias_grounding_waits_for_another_participants_attestation_before_commit](https://github.com/miranovadev/commons/blob/5eb40cd8569edeb0ea8b41fd8f79c8318c067460/tests/Feature/OperationalCommitmentTest.php): Order waits with no source vote; another participant at Bias attests its Write; Commit becomes admissible.
- [ChiralityV2Test::test_replacement_changes_orientation_but_both_acts_remain_in_cadence](https://github.com/miranovadev/commons/blob/5eb40cd8569edeb0ea8b41fd8f79c8318c067460/tests/Feature/ChiralityV2Test.php): historical Cadence counts both original and replacement acts, while effective Orientation changes.
- `ChiralityV2Test::test_non_origin_grounding_still_requires_both_attestation_and_receipt`: non-Origin vote and Receipt requirements remain independent.
- [EvidenceFirstInteractionTest::test_receipt_then_attestation_unlocks_personal_traversal_without_loading](https://github.com/miranovadev/commons/blob/5eb40cd8569edeb0ea8b41fd8f79c8318c067460/tests/Feature/EvidenceFirstInteractionTest.php): shared same-Horizon evidence can expose ordinary Write Attestation.

Some canonical contract tests construct Phase/field fixtures to isolate a rule.
They establish those rules' deliberate design, not a lawful first-Emergence witness.
The continuation uses none of their controlled-field or fabricated-evidence helpers.

## Earlier would-be crossover

At archived step 31 the missing source is exactly the same Persist Write as at 33.
The counterfactual +1 head Cadence gave Tension +0.0802688 but did not add a vote.
Immediately before step 31, A0 and A2 are at Constrain; A1 remains at Distinguish.
The third historical act at step 32 is A1's vote on the Distinguish parent. That
act both completes `[5,5]` and advances A1 to Persist, exposing the next, different
vote target: the ungrounded Persist Write. Maturity and source voting are distinct
events, but this particular cooperative opportunity becomes available at their
adjacent Phase boundary. There is no rule permitting a single act to count as both
votes, and no evidence of a permanent unavailable-action contradiction.
