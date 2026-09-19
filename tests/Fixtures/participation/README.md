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
- `tragedy-lifecycle.json`: 107 steps. Natural Tragedy exists after 71; another
  participant reaches its actual Horizon at 79, claims the final attempt at 80,
  reads its receipt at 81 and consumes it at 88. Horizon Exhaustion and backward
  recovery occur at 105; step 106 proves exploration remains actionable afterward.
  The test also retains explicit rejection of foreign-Horizon receipt access and
  a competing duplicate final attempt.

An unexpected state fails at that step. Do not regenerate expected output to
make a failing test pass: determine whether it exposes a regression or an explicitly
authorised change to the lifecycle contract first.

The complete solo and collective Domain scripts live unchanged in
`tests/Replay/Fixtures/`. They are manual reference/performance replays, invoked
with `composer test:replay-domain`, and are outside mandatory PHPUnit discovery.
A deterministic full Domain round trip is not a Core acceptance requirement.
