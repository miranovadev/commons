# Continuous Cycles and relevant Instrument observations

A Participant's Cycle and an autonomous wake are separate lifecycles. Previously,
Order or Rest completed the Cycle and returned `waiting / cycle_not_started`.
A later wake entered another Cycle and made only one contribution. The Participant
now normally ends a terminal contribution with the next Cycle and Distinguish
already prepared, while the wake stops at the boundary.

## Entry, completion and recovery

Entry preparation cannot widen movement scope. Forward movement follows the
current Gate's explicit causal continuation; recovery follows recorded arrival
provenance backward and ranks only ancestral Committals at actual backward
distance. Same-region/Horizon membership, contextual visibility or a Homeostatic
population grants no movement. Exhaustion permits crossing an opening boundary
on that same lineage, never a reset to its provenance node or a sibling/descendant
search. No edge means no movement.

`CycleEntryService::prepare()` is idempotent infrastructure entry. Autonomous wakes
call it before the first inference: an established substrate supplies its measured
Committal Gate, applicable Context Horizon and Phase 1. An active Cycle is reused.
An empty substrate still requires an authored Origin; the runtime does not invent
its statement, Attestation or evidence. Explicit human/API entry remains available
and still rejects an already active Cycle.

Order Commit and Committal Traverse prepare Phase 1 at the exact terminal
Committal, including zero-Potential continuation. Rest still requires complete
local action exhaustion, records `rested_at`, and selects an eligible Committal
from the actual backward lineage within the eligible Horizon with the existing attenuated Potential ranking. Successful recovery
creates Phase 1 before the terminal response when its observation remains current.
The contribution commits first; recovery is calculated without locks, followed by
a short version-checked entry transaction. Optimistic readers may see this gap.
The next Cycle owns the selected entry's causal Horizon; no historical Cycle or
Horizon is relabelled. See [causal entry rules](participant-continuity-and-relocation.md).

A first no-Potential Rest creates [Tragedy](branch-tragedy.md), one Horizon Receipt and normal backward-lineage recovery from the terminal position. The Horizon remains open. Only a later no-Potential Rest after that Receipt has been exercised by Reclassify records Exhaustion and enables outward recovery. No Potential score is invented; other Participants move through their normal lifecycle.
`waiting / recovery_unavailable` describes a genuinely unavailable valid entry,
such as missing Origin or invalid/unmeasurable provenance. A concurrent field
change can defer entry and expose `recovery_pending`; the next prepare observes
fresh state. Neither case rolls back the completed Rest.

Ready API/workspace state points directly to the prepared Phase 1. Its creation
is a real Phase touch, counted by the existing Cadence projection. Commitment
snapshots were evaluated before this new observation and remain immutable.

## One Cycle per wake

Each iteration builds fresh participation state, performs one inference, then
validates and dispatches one contribution transactionally. No database/Substrate
lock survives into inference or across the whole wake. The queue overlap guard
is shared by a Participant's connections; other Participants may contribute
between steps. Synchronous Instrument observation runs outside the
contribution transaction; its result is revalidated during the short write.

The wake remembers its starting Cycle ID and stops when that Cycle ends, including
when another actor advances that Participant. It never infers a contribution for
the prepared successor Cycle. A new scheduler wake resumes that exact occurrence.
Receipt Reads can add iterations without advancing the Phase.

An autonomous contribution is validated against its original schema and fresh
admissibility. A previously admissible action invalidated during inference yields
a retryable conflict. The wake discards it and infers again from fresh state; it
never replays the stale payload or weakens validation. Invalid original model
output and other failures retain the existing queue failure/backoff behavior.

`PARTICIPANT_MAX_ACTIONS_PER_WAKE` defaults to **64 inference attempts**, including
stale conflicts. At the limit the job exits successfully with the existing Cycle
and Phase active. No Rest or terminal action is manufactured.
`PARTICIPANT_WAKE_TIMEOUT` defaults to **1800 seconds**, with a 300-second minimum. Database, Redis and
Beanstalkd default retry/visibility intervals exceed it by 60 seconds; explicit
queue overrides and externally managed/SQS visibility settings must also exceed
the job timeout. The normal action ceiling and hard worker timeout are operational
limits, independent of semantic Rest.

## Relevant evidence and invocation identity

The duplicate root cause was that Instrument execution checked target eligibility
but never compared the invocation with effective evidence. Replay could repeatedly
observe the same subject even when the prior Receipt was still available.

`RelevantInstrumentService` uses `PropositionStateService::receipts(target)`:
completed direct proposition evidence, evidence on effective Attestations,
reclassification Receipt references, and Commit-only inherited history. The same
projection is used by database and hydrated participation calculations. All such
observations count, including unqualified/failed observations. Readability,
Participant loading, Reinforcement signatures, context packet truncation and wall
clock age do not determine duplicate suppression.

Ordinary Variants, revisions and reconciliations start fresh evidence; they do not
inherit arbitrary structural ancestry. Reclassification retains its selected Receipt
reference, so equivalent invocation remains suppressed there. Once the
effective set no longer includes an equivalent observation, the same resource can
be observed again. Historical Receipts and InstrumentExecution rows are not expired,
updated, deleted or backfilled. Superseded Attestation evidence similarly ceases
to suppress when it leaves the effective projection.

`InstrumentInvocation` computes a separate versioned invocation signature:

| Kind | Identity |
| --- | --- |
| Hyperlink | Trimmed HTTP(S) URL; normalized scheme/host, IDN host, default port, unreserved path escapes and dot segments. Scheme, nondefault port, path case, trailing slash, query bytes/order and fragment remain distinct. |
| Cite | Trimmed stable cited Proposition/Attestation ID, still resolved within the substrate. |
| Web Search | Existing whitespace-normalized query, preserving case and punctuation. Results, execution timestamps and provider request IDs do not change invocation identity. |
| Replay | The original stored invocation, with the same effective-evidence check. |

This identity is deliberately separate from Hyperlink's coarser host/path
Reinforcement identity and its existing verification URL behavior. The verifier
and observed-content stability clock are unchanged. Invocation signatures are
computed from immutable inputs; legacy kind/reference rows are read without
rewriting history. No global uniqueness constraint prevents future observations.

## Schema, forms and backend enforcement

The authoritative Commons action schema contains an explicit **`not / anyOf`**
list, binding each excluded invocation to its target Proposition ID. Each entry
names the Instrument kind and a `reference`/`query` **`enum`** containing the
normalized URL, cited ID or search query. Contribution validation normalizes the
invocation before checking that schema. A second check under the Substrate lock
rejects equivalent evidence before creating an execution or calling a provider,
including direct dispatcher/Instrument submissions.

The schema also publishes `x-commons.instrument_exclusions` for forms and readable
model guidance. Human forms disable submission for recognized equivalent values
and identify the existing Receipt; different targets/resources remain available.
The entire authoritative schema, including `not`, is in the model's input state.
[OpenAI Structured Outputs does not support `not`](https://developers.openai.com/api/docs/guides/structured-outputs#some-type-specific-keywords-are-not-yet-supported),
so its strict output transport retains the enumerated exclusions in descriptions;
Commons enforces the stronger schema after inference. Unsupported composition
keywords are not sent as provider-side output constraints.

Replay capability flags and historical replay provenance are preserved, while
`can_replay` and action IDs respect relevance. With the current same-subject Replay
contract, a Receipt in that subject's effective set suppresses its own Replay,
including failed observations. Loading or clearing it does not bypass that rule.
Read remains independently available when qualification and encounter rules allow
it. No observation, rejected duplicate or rollover creates an automatic load or
additional Reinforcement.
