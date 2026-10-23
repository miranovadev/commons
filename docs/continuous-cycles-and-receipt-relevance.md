# Continuous Cycles and relevant Instrument observations

> **Deployed Cycle and Receipt contract.** A Cycle entry alone never
> creates a Tragedy under the proposed [direct-encounter target](tragedy-resolution-target.md).
> Existing first/later fresh-birth statements below describe the deployed
> implementation, not the agreed Resolution replacement.

A Participant's Cycle and an autonomous wake are separate lifecycles. Previously,
Order or Rest completed the Cycle and returned `waiting / cycle_not_started`.
A later wake entered another Cycle and made only one contribution. The Participant
now normally ends a terminal contribution with the next Cycle and Distinguish
already prepared. Wake stopping is separately configured on the Connection attachment.

## Entry, completion and recovery

Entry preparation cannot widen movement scope. Forward movement follows the
current Gate's explicit causal continuation; recovery follows recorded arrival
provenance backward and stops at the nearest eligible non-exhausted ancestral
Committal. Same-region/Horizon membership, contextual visibility or a Homeostatic
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
by stopping at the nearest eligible non-exhausted Committal on the actual backward lineage within the permitted Horizon. Successful recovery
creates Phase 1 before the terminal response when its observation remains current.
The contribution commits first; recovery is calculated without locks, followed by
a short version-checked entry transaction. Optimistic readers may see this gap.
The next Cycle owns the selected entry's causal Horizon; no historical Cycle or
Horizon is relabelled. See [causal entry rules](participant-continuity-and-relocation.md).

A qualifying direct expressive Write encounter with resulting canonical Charge zero establishes the Region’s unique Tragedy and Receipt and permanently terminates that continuation. Resolution prohibits Variant and Revise throughout the Region while preserving ordinary authority on viable routes. Exhaustion follows atomically only when no lawful continuation remains; viable siblings and live committed descendants prevent it. Descendant Exhaustion closes only its corresponding route. No second catastrophe, Rest or Receipt consumption is required. Passive measurements and reads establish neither event. Recovery follows actual recorded backward lineage. See the approved [Tragedy/Resolution lifecycle](tragedy-resolution-target.md). Other Participants move through their normal lifecycle.
`waiting / recovery_unavailable` describes a genuinely unavailable valid entry,
such as missing Origin or invalid/unmeasurable provenance. A concurrent field
change can defer entry and expose `recovery_pending`; the next prepare observes
fresh state. Neither case rolls back the completed Rest.

Ready API/workspace state points directly to the prepared Phase 1. Its creation
is a real Phase touch in participation history. Cadence counts completed governed
Cycles plus the producing Cycle, not Phase touches. Commitment snapshots remain immutable.

## Configurable wake boundaries

Each iteration builds fresh participation state, performs one inference, then
validates and dispatches one contribution transactionally. No database/Substrate
lock survives into inference or across the whole wake. The queue overlap guard
is shared by a Participant's connections; other Participants may contribute
between steps. Synchronous Instrument observation runs outside the
contribution transaction; its result is revalidated during the short write.

Phase mode (the default) stops after its starting Phase completes. Cycle mode
remembers its starting Cycle ID and stops when that Cycle ends, including when
another actor advances that Participant. Rest mode can infer in prepared successor
Cycles until the acted Cycle records genuine Rest. No mode changes participation semantics.
Gate desired-state load/unload options accompany the normal forward contribution, with no extra iteration. Standalone Receipt Read and Replay actions are retired; replay is an Instrument invocation mode.

An autonomous contribution is validated against its original schema and fresh
admissibility. A previously admissible action invalidated during inference yields
a retryable conflict. The wake discards it and infers again from fresh state; it
never replays the stale payload or weakens validation. Invalid original model
output and other failures retain the existing queue failure/backoff behavior.

The attachment's timeout defaults to **600 seconds**, with an integer range of
1–1,800 seconds. A whole-execution monotonic deadline replaces the former action
count ceiling. The native queue timeout bounds blocking calls. Neither timeout
manufactures a contribution or Rest. Provider transport timeouts remain separate.
For fixed/random offsets, non-drifting cadence, persistence defaults and worker
visibility requirements, see [Connection wake controls](reusable-connections.md#wake-controls).

## Relevant evidence and invocation identity

The duplicate root cause was that Instrument execution checked target eligibility
but never compared the invocation with effective evidence. Replay could repeatedly
observe the same subject even when the prior Receipt was still available.

`RelevantInstrumentService` uses `PropositionStateService::receipts(target)`:
completed direct Proposition evidence, legacy Attestation-owned evidence resolved to its Proposition,
reclassification Receipt references, and Commit-only inherited history. The same
projection is used by database and hydrated participation calculations. All such
observations count, including unqualified/failed observations. Readability,
Participant loading, Reinforcement signatures, context packet truncation and wall
clock age do not determine duplicate suppression.

Ordinary Variants, revisions and reconciliations start fresh evidence; they do not
inherit arbitrary structural ancestry. Reclassification retains its selected Receipt
reference, so equivalent invocation remains suppressed there. Once the
effective set no longer includes an equivalent observation, the same resource can
be observed again. Historical Receipts and EvidenceExecution rows are not expired,
updated, deleted or backfilled. Legacy Attestation-owned evidence remains Proposition grounding after judgment supersession; no new Attestation-owned Receipt is created.

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

