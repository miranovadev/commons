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
- `tragedy-lifecycle.json`: 135 steps. Natural Tragedy exists after 102; another
  participant reaches its actual Horizon at 110, claims the final attempt at 111,
  and consumes its receipt at 116. Commit Region exhaustion and backward
  recovery occur at 133; step 134 proves exploration remains actionable afterward.
  The test also retains explicit rejection of foreign-Horizon receipt access and
  a competing duplicate final attempt.

The committed-lineage Closure replacement changes the natural C1 crossover in
the revisit/epoch/source-equivalence scripts. Their expected topology follows that
result. In the Tragedy journey, the former supporting judgments leave measured
Potential in the new basin. The authored evidence path now refutes the four tested
expressive positions before Rest. Twelve explicit instrument/Rest/judgment Cycles
exercise a slower basin rhythm: the final prospective interval is 20, against
C1's frozen 10. Depth is positive while zero Cadence retains its negative direction,
so ordinary alignment blocks Commitment. The faster eight-Cycle version permits
Commitment and cannot lawfully demand Rest. The final-attempt participant follows the new
attempt directly after recovery, refutes its tested generative evidence, and
instruments the final Mediate result before judging its classification.
These are fixed HTTP contributions, not numerical overrides or runtime choices.
The tests retain the Tragedy, receipt visibility/consumption, unique final attempt,
no-premature-exhaustion, immutable history and backward-recovery assertions.

An unexpected state fails at that step. Do not regenerate expected output to
make a failing test pass: determine whether it exposes a regression or an explicitly
authorised change to the lifecycle contract first.

The complete solo and collective Domain scripts live unchanged in
`tests/Replay/Fixtures/`. They are manual reference/performance replays, invoked
with `composer test:replay-domain`, and are outside mandatory PHPUnit discovery.
A deterministic full Domain round trip is not a Core acceptance requirement.
