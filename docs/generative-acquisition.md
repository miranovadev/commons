# Exploration-first generative acquisition

Acquisition prepares one current generative attempt. It is not a permanent Participant × Gate × role allowance. Existing personally unexplored structure takes precedence: lawful Attestation, Traverse and ordinary Revision do not require acquisition. Explored Writes remain visible and non-actionable under the existing personal exploration evaluator.

## Admission and resolution

The prospective fresh Variant probe excludes acquisition. It checks the current
[Crossover Margin](rest-crossover-experiment.md) of the Cycle's governing
Committal in its Domain. A closed margin suppresses Instrumentation, Preparation
and the Variant. Resolution rechecks the same boundary before admitting a
contribution. An ordinary competing Variant alone cannot revoke admission. Only if that probe is lawful may a Participant reserve an execution. Actual Variant submission requires that admitted current execution and all independent structural/evidence conditions.

| Phase | Acquisition before fresh Variant |
| --- | --- |
| Distinguish | Instrument at the governing Committal |
| Persist | Preparation when causally accessible readable Artifacts exist; otherwise Instrument |
| Disperse | Instrument or available Preparation |
| Constrain, Mediate, Bias, Order | No new acquisition prerequisite; existing structural authority applies |

One execution per **exact Phase/generative opportunity** is marked with `attempt_admitted_at`. A partial unique index on `phase_id` protects this reservation. Distinguish, Persist and Disperse may each acquire in the same Cycle; an earlier Phase's attempt neither satisfies nor blocks a later Phase. No permanent Gate quota remains. Returning to existing structure requires no acquisition, while a newly lawful Variant attempt at the same Gate requires a new observation.

`attempt_resolution` stores the single Variant or Defer decision separately from immutable execution status and Receipt metadata. Variant generates through the ordinary dispatcher and Phase progression. Preparation retains its atomic contributed Receipt/Variant transaction. Neither mechanism consumes a Receipt. Consumption remains Reclassification in Constrain and Bias.

Defer requires a reason, closes the Phase and Cycle with `deferred_at`, preserves the observation at its exact Gate, and creates no Proposition or Exploration Event. It is not recorded as Rest. Subsequent entry uses existing backward recovery; it does not promise return to a Gate that has become unavailable. Repeated lawful acquisition/Defer Cycles have no lifetime limit.

Prepared Revision is retired: an acquisition admits a fresh Variant opportunity, not reconsideration. Ordinary lawful Revision remains independent, with its existing evidence and explored-Write restrictions.

## Failure, concurrency and storage

Admission runs under the Substrate lock. Instrument reserves before external I/O, observes outside the transaction, then persists the result under the existing authority checks. Preparation reserves before reading. The same Participant cannot reserve an Instrument and Preparation independently in one Phase. Resolution locks the root, Participant and execution and permits only one outcome. Exact supported retries reuse the recorded result; conflicting or stale requests are rejected. A successful Variant cannot subsequently Defer or generate again.

Two independently admitted Participants retain their opportunities if one creates an ordinary Variant first, including while the other's external observation is in flight. This exception bypasses only the newly competing continuation. It does not bypass hard Supersession, terminal Regions, canonical Commit, Context Horizon, ordinary Receipt prerequisites or measurement authority. Safe Defer remains available when a structural transition makes Variant unsafe.

Recorded external failures preserve unverified ordinary observations. Pending, invalid, abandoned and expired-before-observation reservations do not fabricate a Receipt or authorize Variant. An expired Instrument reservation can resolve through Defer; infrastructure failures leave its reservation for that bounded recovery. Preparation's existing actual-read/delivery failure classification remains authoritative. An unavailable observation never gains positive verification.

Historical executions retain null admission/resolution fields. Migration does not reinterpret them as current attempts or delete any evidence. Downgrade refuses to discard admitted attempt history. PostgreSQL JSON object-key ordering is normalized when matching a reserved invocation; immutable input is not rewritten.

## Causal Artifacts and cognitive state

Preparation reuses the Gate-causal cognitive horizon and the catalogue's recorded Receipt associations. A visible consumed Receipt or authorised secondary Reconciliation operand can supply a readable preserved Artifact. The selected Receipt must belong to the Substrate and actually reference the requested Artifact. Supported reader, storage and boundary checks still apply. Hashes alone grant no access, and evidence ancestry never grants movement.

Summaries, personal loading, Read-context inclusion, original Gate provenance and immutable consumption relationships are unchanged. Deferred reading summaries preserve identity, purpose, requested/delivered boundaries and outcome. Full reading content remains bounded and subject to the ordinary loading contract.

The shared action plan distinguishes `not_required`, `required`, `pending`, `awaiting_resolution` and `resolved`. After Instrument persistence, the next inference or human action sees Variant/Defer. Human Defer uses the existing contribution form, retaining its execution and reason across refreshes. Preparation resolution schemas accompany delivered reading; execution still requires actual presentation acknowledgement. Human and autonomous clients cannot bypass the backend by submitting an older plan.

Exploration completion no longer looks up historical acquisition. Effective personal judgment, selected active routes, independent evidence witness and genuine forward-generation reopening remain unchanged. Acquisition and Defer do not publish completion events.

## Executable evidence and limitations

`GenerativeAcquisitionJourneyTest` uses application HTTP operations, deterministic evidence and a database-write guard. It covers progressive Preparation/Defer/Variant, repeated Instrument/Defer, two admitted competing Variants, and slower participation that reads accumulated shared observations and acquires independently. The revised fixed transcript reaches Reconciliation, Bias and Commit with real measurements. Recovery, canonical history and post-Commit source-search tests retain their independent authority assertions.

`AcquisitionAttemptTest`, `PreparationExecutionTest`, `PreparationConcurrencyTest`, `InstrumentAllowanceConcurrencyTest` and `TransactionLockOrderTest` cover reservation, resolution, expiry, historical migration, causal Artifact safety and concurrent writers. The frontend regression catches the Instrument Defer action and preserves nonblocking refresh. The [validation report](generative-acquisition-validation.md) records exact baseline comparisons and remaining failures.

The Phase boundary permits immediate Distinguish and Persist births in one Cycle. The bounded solo diagnostic distinguishes immediate acquisition from voluntary Defer; neither chronology is imposed by a Cycle quota. The retained positive solo Commit regression remains unproven by these bounded diagnostics. Its original assertion and documented skip remain; see [the updated design review](solo-commit-design-review.md). No measurement or recovery policy is changed to force Commit, and this PR does not investigate alternative solo histories.
