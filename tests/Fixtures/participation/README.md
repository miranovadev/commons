# Fixed participation journeys

These files define inputs and expected participant-visible state. They are test
contracts, not policies for choosing an available action. No fixture is generated
or updated during a test, and no test waits for an autonomous participant to return.

Each JSON line is one named participant's `enter` or `contribute` step. An `enter`
creates that participant through the API first. A contribution includes its exact
payload, the expected state before the POST, and the expected returned state.
`before.previous_after` refers to an earlier **expected** response in this file;
it never derives an expectation from the running application. `after_changes`
contains fixed path/value replacements against that expected before-state. The
loader expands these static deltas before issuing any request, and the runner
still compares the complete projected state. `parent_ids` is compared as an exact
set (the model has no ordering clause); the four context populations retain their
ordered comparisons. No parent is removed or added during normalization. This avoids repeating unchanged
visibility and history in hundreds of steps.

`$ref` identifies an entity already returned by an earlier API request. `bind`
(and occasionally `bind_before`) declares the response path at which a new identity
must appear. The runner checks that it is a valid, fresh ULID and checks every
subsequent reference exactly. These bindings accommodate generated IDs without
choosing targets from runtime eligibility.

`ParticipationScript::state()` deliberately omits clocks and raw numerical
measurements. It checks position, Horizon, cycle, phase, gate, available action
names, Rest, visible proposition spentness/epochs and attestations, receipt
availability and attachment, and commitment direction. The lifecycle tests add
specific database assertions for immutable failure history, causal Horizon parents,
Tragedy consumption and backward recovery. This does not redefine Potential as
frontier rank or action availability.

- `bias-revisit.json`, `committal-epochs.json`, `disperse-epochs.json`: the three
  original production regressions, retaining their history/epoch assertions.
- `local-epochs.json`: 84 steps, including the first, second and third
  Distinguish Variants at steps 2, 20 and 83.
- `recovery-revisit.json` and `recovery-distance.json`: the actual ancestral
  path, exclusion of an untraversed Commit, and real actionless Rest.
- `rest-after-receipt.json`, `rest-unread-receipt.json`, `visibility.json` and
  `source-equivalence.json`: fixed counterparts of the existing API contracts.
  The separate synchronous worker case uses exactly two Variants followed by
  Rest, with expected phase/position/lineage checks at each inference call.
- `staggered-epochs.json`: 30 steps. The same failed continuation spends A and B
  at step 27; A alone reopens at 28; B alone reopens at 29. Semantic assertions
  inspect parked observers between those final steps.
- `tragedy-lifecycle.json` retains the historical transcript. The current
  `ParticipationTragedyLifecycleTest` uses the skeptical Persist Commit journey.
  Its Settlement Committal has a measured margin of -0.1950613. Distinguish
  has no existing Write and cannot acquire. Rest at that entry moves seven
  recorded edges back to Origin without changing the Horizon or any proof.

Coverage is deliberately separated:

- `ParticipationTragedyLifecycleTest`: natural closed-crossover Rest, exact backward
  ancestry, and unchanged Tragedy/exhaustion/Receipt/Horizon history after recovery.
- `NaturalChargeBoundaryLifecycleTest`: ordinary unmocked contributions by two
  participants create first Tragedy, load non-evidential warrant memory, reproduce
  the Charge boundary, exhaust the Region and recover while preserving history.
- `ChargeBoundaryTragedyTest` and `AuthoritativeRestReasonTest`: zero Potential and
  a no-Potential Rest cannot create Tragedy/exhaustion; fresh resolved zero Charge
  remains the catastrophe authority. Bias/warrant suites independently cover
  consumption authority and exact-once use.

Crossover governs fresh Variants. Rest and backward recovery preserve the independent catastrophe and exhaustion proofs.

An unexpected state fails at that step. Do not regenerate expected output to
make a failing test pass: determine whether it exposes a regression or an explicitly
authorised change to the lifecycle contract first.

The complete solo and collective Domain scripts live unchanged in
`tests/Replay/Fixtures/`. They are manual reference/performance replays, invoked
with `composer test:replay-domain`, and are outside mandatory PHPUnit discovery.
A deterministic full Domain round trip is not a Core acceptance requirement.

Chirality v2 recalibrates the recorded intermediate Rest reasons where live Potential is now zero: `no_remaining_potential` replaces `no_available_move` only at the observed steps. Authored actions, topology and recovery assertions are unchanged. The final Crossover remains closed with Correspondence -0.5276331, Chirality 0.7666717 and Tension bound 0.2463462. The diagnostic independently checks Orientation, live Attestation rates, their mean and unchanged attenuation; live reconstruction is not compared to an earlier frozen snapshot.

Origin now requires an explicit Attestation and creates no bootstrap Receipt.
The archived expected packets and declared bindings omit that former observation;
authored actions and ordinary evidence observations remain unchanged.

## Explored Writes and the natural Commit transcript

The first branch in `persist-skeptical-commit.json` closes at Mediate. On its return to Origin the author creates a fresh Distinction. Two further branches receive a Participant entry between their Distinction and Persist births. The resulting Persist, Constrain and Disperse Cycle intervals are 1, 1 and 0. Both Signals have negative Shape Topology and Phase Profile directions. Mediate offers both directed Reconciliations across successive Cycles. Bias reclassifies the mediated synthesis using the other branch’s Preparation Receipt. Order offers Commit after the author judges that classification. All 70 author actions and both staggered entries go through HTTP.

The single-Participant bootstrap has a separate limitation. Use `vendor/bin/phpunit tests/Diagnostics/SoloCommitFrontierTest.php` to replay two completed branches in isolation. Both have intervals of 0, 1 and 0. Persist and Disperse generate within their source Gate’s Cycle. Constrain follows a Rest and first judgment in the next Cycle. Both Signals have negative Shape Topology and positive Phase Profile, so neither qualifies for Reconciliation. The author can create a fresh Distinction at Origin. Each prior route remains visible and personally explored. The original positive solo Commit test remains enabled. This reproduction covers healthy, sequential first-Commit participation.

The focused follow-up is documented in [Solo first-Commit design review](../../../docs/solo-commit-design-review.md). It checks alternative judgment and acquisition choices, and a failed-Preparation route whose later Revisions acquire coherent Correspondence but still lack another Reconciliation operand.
