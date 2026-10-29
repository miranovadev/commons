# Participatory Exploration Completion — Stage 3

This implements Claire's Stage 3 handoff after Personal Gate Acquisition. Exploration is personal completion of an authorised route, not shared spentness or arrival alone. No live population migration, event rewrite, automatic judgment, acquisition reset or new movement authority is introduced.

## Completion and current status

An Exploration Event retains its existing late-Phase terminal-head, recorded arrival, evidence and no-forward-generation prerequisites. Its witness now selects the acting Participant's effective Attestation on the exact head. Both Support and Refute qualify. Contract 2 records the Participant and successful personal-route evaluation alongside the existing head, arrival, Attestation and Receipt IDs. Publication rechecks the witness under the existing Substrate lock and field-version guard. The Phase uniqueness constraint and append-only history remain authoritative.

The route must have no outstanding lawful first personal judgment at an active Write and no outstanding personal acquisition at an available Gate in Distinguish, Persist or Disperse. Acquisition uses Stage 2's recorded-observation predicate and Receipt requirement, with the exact Participant, Gate and creating Phase role. Pending and abandoned records do not count; recorded failed observations do. Consumption does not remove completion.

Current explored status requires a qualifying personal head encounter, complete personal participation on the selected route, and no newly lawful non-Variant generation at each active head. An old event cannot waive an outstanding lawful first judgment. Historical witnesses are not rewritten, including the older witnesses which selected another Participant's Attestation.

## Route selection and availability

The existing bounded active-head selector supplies forward routes. Evaluating a particular ancestor checks all authorised paths from that ancestor to each head, visiting shared nodes once. An independent branch is not imported; two branches that genuinely converge remain separately accountable from their shared ancestor. Recorded consecutive Phases from the encounter disambiguate shared arrivals. A single causal parent remains the actual parent, including Reclassification and Revision; multiple parents use the selected arrival Gate or creating Gate. The Region, Horizon, terminal and canonical Committal boundaries remain in force. A foreign Reconciliation operand supplies evidence, not a personal movement obligation. Two authorised local routes to one head are evaluated through the requested branch; completed shared participation is reused.

Hard-superseded nodes remain historical path provenance but impose no independent current obligations. Evidence availability and mutability use the same base predicate as Attestation options. Unavailable judgments do not become impossible quotas. The head still needs a genuine personal judgment to substantiate an encounter. Revision preserves first judgment at the same position through its Revision predecessors; it does not grant Attestation or revision authority on those predecessors, nor fabricate an Attestation on the new Proposition. Publishing a new event still requires the actual head's own personal Attestation.

Existing first-Attestation options remain reachable through ordinary forward participation and nearest-first backward recovery. Rest without completion remains possible when the action schema permits Rest; it records no Exploration Event. Traversal still requires its existing personal Attestation path and never gains authority from this evaluator.

## Reopening and separation

New active structure and newly lawful Reconcile, Reclassify or Commit can reopen a route. Fresh Variant does not satisfy its own prerequisite. Traverse, Revise and Attestation reversal alone do not reopen it. Reconsideration may indirectly make forward generation lawful. Completion records, prior acquisition, immutable events and first judgments are retained.

The evaluator never constructs an InputPlan or grants an operation. Consumption remains Constrain/Bias only. Measurement thresholds, Closure, canonical Commit, Stage 1 cognition and human/autonomous backend parity remain unchanged.

## Boundary decisions

| Boundary | Decision and authority | Verification |
| --- | --- | --- |
| Foreign witness | Select exact effective personal head Attestation (handoff §4) | Support/Refute and older foreign vote tests |
| Active route gap | Missing lawful first judgment blocks completion, even with a historical event (§5) | Each producing position and recovery tests |
| Multiple paths | Requested forward branch plus recorded arrival; no evidence-ancestry union (§§2,5) | Reconciliation and sibling boundary tests |
| Revision | Same-position reconsideration retains completed first judgment; new publication needs actual head judgment (§§4,7) | Existing Revision/reversal reopening tests |
| Acquisition | Reuse Stage 2 completion; no exploration allowance (§9) | All three missing-role cases, returning-Gate and natural HTTP tests |
| Actionless Rest | Stopping is not personal completion (§11) | Incomplete Rest publishes no event |
| Concurrency | Stable field plus under-lock witness revalidation (§10) | Stale, duplicate, rollback and forged identity tests |

## Historical comparison

`scripts/diagnostics/participatory-exploration.py` reads the October 26 plain PostgreSQL dump without connecting to or modifying a database. On `personal-exploration-offsets-attempt-2`, it reproduces 86 events and 34 foreign witness references. All 86 events had effective personal head judgments at event time. Following recorded encounter routes and excluding nodes already hard-superseded at that time found zero missing active-route judgment candidates. Representative foreign-witness events include `01m5smvys6x0pfjx6vv5vzwndq` and `01m5sn0c7tspjvfy3ypq962fjx`; both had a personal head judgment and the same four-node selected route.

This is a provenance comparison, not exact action-packet replay. It does not establish historical action lawfulness, nor retroactively apply Stage 2 acquisition to the old cook. Missing judgments in the final snapshot alone are not evidence of premature exploration.

## Validation and performance

Final validation is recorded in the PR against merged main `5a24e05dca6f3f14c56101e26a5f00bb2890ae16`. Exploration reuses the hydrated graph and effective Attestations. Acquisition completion selects distinct Gate/role keys in one batched query; encounter arrivals are batched across relevant events. Context-local memoization avoids repeated history queries. The focused frontier fixture currently holds at 27 queries from two to eight personally judged heads; final PostgreSQL measurements and baseline failures are reported in the PR.

The fixed natural Commit journey now judges the previously missed active position on its second authorised Reconciliation route. Those real judgments change later Chirality admissibility: the journey Rests, returns for lawful reconsideration, and then completes Bias and Commit. It retains the same structural population and Receipt consumption authorities, with one additional Cycle. The Tragedy fixture consequently expects a cadence interval of 16, Chirality of -0.1788649 and crossover margin of -0.1527312; exact tolerances and its independent observable projection remain unchanged. Isolated historical-event fixtures no longer assert personal completion without personal judgment. None of these corrections fabricate natural acquisition or Attestation records.

An evidence shortage can still leave a Participant actionless with an incomplete encounter: personal completion does not create a consumable Receipt or waive existing Attestation evidence rules. Rest remains available in that case without an Exploration Event. A historical dump cannot establish that such a shortage was avoidable at a particular event time.
