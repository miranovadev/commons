# Stage 2: HTTP participation harness and red regressions

This checkpoint adds seven Core tests: four harness controls and three explicit
regressions for the production failures prioritized after Stage 1. It changes no
application code, migrations, Composer selection, or Actions workflows. The three
regressions assert the intended behavior and are expected to fail on the current
implementation. They are not skipped, marked incomplete, inverted to expect the
bug, or excluded from Core. A red Core result is therefore expected at this
checkpoint; it is not approval to merge or advance to semantic corrections.

## Boundary and legitimate setup

`tests/Support/ParticipationHttpTestCase.php` uses Laravel's HTTP testing kernel,
with the production routes, middleware, authentication, request validation,
participation dispatcher, services, and database transactions. It does not run a
separate socket server or simulate the browser or LLM transport.

Even initial setup uses HTTP:

1. `POST /api/bootstrap` creates and authenticates the operator.
2. `POST /api/substrates` creates a private substrate.
3. `POST /api/substrates/{slug}/participants` joins each participant.
4. `POST /api/substrates/{slug}/participants/{id}/participate/enter` establishes
   Origin and the initial Horizon with a required explicit Attestation and no Receipt,
   or enters an additional participant into the existing substrate.

After that, helpers read `GET .../participate` and send
`POST .../participate` with the current `phase_id` and a contribution. Positive
contributions must be advertised by that participant's current schema and return
HTTP 200. Negative controls deliberately send invalid or stale requests to the
same endpoint and assert 422 or 409 without structural advancement.

Only external evidence transport is deterministic: DNS for
`evidence.example.test` resolves to a public test address, and its HTTPS responses
are fixed per URL. Stray HTTP requests are prohibited. The real hyperlink
verifier, fingerprints, measurements, Closure calculation, eligibility, epoch,
failure, receipt, recovery, and projection services remain active. There are no
fake Closure values, synthetic snapshots, direct action-service calls, or mocked
participation semantics.

The harness extends the framework test base directly, avoiding the existing
project test base's structural fixture helpers. After migrations, a guard on the
default database connection rejects non-SELECT SQL outside an application HTTP
request, before execution. A passing control attempts a direct title update,
checks the guard's exception, and confirms the title remains unchanged. This is
a fixture-discipline guard, not a security boundary for arbitrary SQL or additional
connections. Test assertions use read-only database queries to inspect actual
commit snapshots, supersession edges, and unchanged failure history.

Forbidden intermediate setup includes direct proposition/edge insertion, failure
insertion or rewriting, epoch mutation, phase/cycle/recovery-pointer changes,
manufactured receipts, forced eligibility, manual projection repair, and fabricated
Commit or Closure state. Observers reach their positions through ordinary Enter
and contribution requests, then simply remain idle while the explorer acts.

## Deterministic action driving

`readParticipation`, `contribute`, `postContribution`, `assertActionAvailable`,
`assertActionUnavailable`, and `visibleProposition` support explicit scenarios.
`driveUntil` and `reachFirstCommit` provide bounded journeys using published
actions and target enums only. The default policy prefers Commit, Reclassify,
Variant, Reconcile, Traverse, Attestation, Instrument, Rest, then receipt Read.
It selects the first legal target, favors support when advertised, and otherwise
uses the legal direction returned by the schema. Explicit scenario actions can
override that policy. It never changes stored state to escape a dead end.

A failed assertion includes the ordered contribution trace and participation
response: participant, cycle, phase and phase ID, Horizon, gate, advertised action
names, and submitted payload. Collisions also show before/after option data.
Budgets fail with this evidence rather than looping indefinitely. IDs and times
are real generated values; determinism concerns the action policy and evidence,
not hard-coded identifiers or required cycle counts.

## First production regressions

| Priority | HTTP reproduction | Required behavior / current failure |
| --- | --- | --- |
| 1. Successful-route revisit / Bias | Reach a real operational Commit, continue exploring and resting, and naturally return to the Bias gate on that committed route. Read-only supersession queries identify the existing classification and Commit; they do not position the participant. | Bias must offer traversal to the existing classification; Order must then allow traversal to its existing Commit. Currently Bias exposes receipt Read and Rest but no forward traversal. The first red assertion is the missing `proposition.traverse` action. |
| 2. Distinguish across Committals | Leave an observer at Origin; let the explorer reach another Commit in the same Horizon and create its first Distinguish Variant there. Re-read the observer's original slot. | Origin's spent option and local epoch must remain unchanged. Currently its displayed epoch advances from 2 to 3 and `spent` becomes false, even though the observer's phase and gate and the stored failure rows are unchanged. |
| 3. Independent Disperse slots | Reach Constrain gate A's Disperse slot through HTTP; an observer reaches the same slot. Create and naturally spend its option, recover and explore to a different Constrain gate B, then create B's first Disperse Variant. Both slots share a Horizon and the participant's Committal context. | A's spent option must stay spent at its existing epoch. Currently it reopens, with displayed epoch 1 becoming 2, while the observer's phase and gate and the stored failure rows remain unchanged. Keying only by Horizon, Committal, and phase kind would still conflate these distinct parent gates. |

These scenarios are reduced, deterministic reproductions of the Stage 0 findings,
not imports of production database state. Assertions use equality across the
unrelated action rather than baking the observed epoch numbers into the contract.
No regression asserts that the current defect is desirable. The Bias test's
downstream traversal assertions are intentionally unreachable until the missing
action is corrected in a later authorized stage.

The four green controls establish that the harness can reach a real operational
Commit with a calculator-produced snapshot and natural failures/receipt
consumptions; that invalid and stale contributions are rejected; that optional
receipt Read keeps the current phase and leaves Rest available; and that direct
fixture writes are blocked. Reaching this Commit does **not** claim a complete
Domain cycle, Tragedy, or exhaustive lifecycle acceptance coverage.

## Running and reviewing

Use the configured PostgreSQL test database and the normal setup in
[testing.md](testing.md):

```bash
composer test:core -- --group=http-participation
composer test:core -- --filter=ParticipationHttpHarnessTest
composer test:core -- --filter=ParticipationLifecycleRegressionTest
```

The first command should currently report four passed and three failed. The
second is the green harness control set; the third contains the intended red
regressions. The `http-participation` group is a filter convenience, not a suite
exclusion: all seven run in ordinary Core and Full Integration. Stage 1's 579
Core cases remain, giving 586 Core cases, 588 Full Integration cases, 16 Query
cases, 22 Load cases, and 626 unique PHP cases. Existing test bodies are unchanged.

Local SQLite execution is a fast harness check. The review PR's normal Core
workflow supplies PostgreSQL 18 validation plus the frontend build and UI tests;
its JUnit artifact retains the failure traces. Check that only the three named
regressions fail, rather than treating any red run as sufficient evidence.

This stop records the historical Stage 2 checkpoint. Subsequent work needed authorization for local
structural epochs and scoped spentness, followed by backward-lineage recovery.
That recovery correction must include Tragedy's direct `recovery_proposition_id`
jump to the Horizon opener. Remaining epic acceptance scenarios, including a
natural third Variant and the complete Domain cycle, are not claimed here.

Stages 3–6 subsequently changed the recorded red boundaries and recovery
contracts; see [the checkpoints](substrate-motor-checkpoints.md). The current
movement authority is [Invariant 10](core-invariants.md#10-movement-follows-authorised-structure),
not an action merely advertised by a historical harness run. Traverse requires
the current Gate's existing causal continuation; recovery walks recorded
ancestry backward. Context, region membership and Homeostatic calculations
cannot create an edge. No edge means no movement.
