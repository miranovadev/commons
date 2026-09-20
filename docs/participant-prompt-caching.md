# Participant prompt caching

This document records the **#187 baseline**. The current Core + Phase composition,
v2 family strategy and new measurements are in [Participant Phase instructions](participant-phase-instructions.md).
Reproduce the historical benchmark at `5fc2fb9`; the current comparison has its own script.

This change rearranges the already-allowlisted inference packet. It does not change
the plan, action schema, Perspective, receipt visibility, or substrate mechanics.
Existing participants use it on their next inference; no migration, reset, data
rewrite or new substrate is needed. **Improved structural reuse is measured;
actual provider cache-hit rates and dollar savings remain unmeasured.**

## Baseline before restructuring

Measured against main `3f55314eb3e9b374c8fd9038eba601a276a4925b` before changing
serialization. The existing solo and collective natural captures supply **1,230
ready inference states** and **1,226 adjacent same-participant comparisons**.
These are historical fixtures passed through today's serializer, not new natural
runs and not production API usage. One wake can contain several inference calls;
these counts are calls, not scheduler wakes.

The production path is `WakeParticipant` → `ParticipationService::prepare/stateFor`
→ `InputPlanService` → `ParticipantContextSerializer::plan/inference` →
`ParticipantInferenceManager` → `ParticipantPromptFactory` → `OpenAIResponsesClient`.
The baseline client discarded provider usage after extracting the contribution.
No historical cached/input/output token measurements are available in these captures.

Baseline request order:

1. `model`.
2. `input[0]`, system: 14,680 bytes of Commons instructions, then connection instructions.
3. `input[1]`, user: `status`, then `plan` with Phase first, provenance,
   exhausted alternatives, Gate ID, lineage, Read, Gate, Write, optional commitment
   details, receipts, Rest fields, and the full action schema.
4. `text.format`: the strict, action-bound provider schema.

There is no separate participant identity or substrate/Origin section in the
allowlisted model context. Connection instructions are the existing personalization;
Origin/provenance statements appear only in their existing population. Adding
new summaries or restoring all historically loaded receipts would change cognition.
Neither is done here.

## New layout and invariants

`ParticipantPromptLayout` operates on `ParticipantContextSerializer::inference`'s
result without querying anything. The request has three messages:

| Layer | Contents | Ordering |
| --- | --- | --- |
| System | Constitution aligned with #185's lineage/Homeostatic rules, one receipt-layout sentence, existing connection instructions | Stable monolithic instructions first |
| Loaded receipts | Already-visible loaded receipt identity, provenance, capabilities, observation and evidence | Load timestamp, then receipt ID |
| Current state | Historical lineage first; remaining Read/Gate/Write and receipt state; current Phase; full action schema last | Fixed field order; original population order |

Loaded bodies and `plan.receipts` join on `id`; evidence is not duplicated. Dynamic
availability, consumption, attachment, replay eligibility, loaded status and load
timestamp stay in the final message. Newly loaded older receipts follow earlier
loads. Equal load timestamps use ID as a deterministic tie-breaker; this is not
a promise of append order within timestamp ties. Clear removes the loaded body on
the next request; any evidence still visible as an ordinary receipt stays visible,
exactly as before. Re-load uses its new load timestamp. Leaving the visible scope
removes the body even if a load remains stored. There is no application cache that
can resurrect an old body or obscure clearing.

Object keys are recursively canonicalized in context. Existing nulls, empty
collections, observation timestamps and stored provider request IDs are retained.
No new clock, random ID, token counter or presentation field enters the prompt.
Receipt identities sort deterministically. Lineage, parent IDs, ranked search
results, instrument lists and other semantic lists keep their supplied order.
Attestation summaries are object maps; their keys are canonicalized. Both action
schemas, including property/enum/union ordering, remain exactly unchanged.

Propositions already have disjoint owning populations; no additional proposition
deduplication is introduced. Cite statements and target-specific instrument
exclusions remain where their distinct meanings require them. The provider schema
and in-packet schema are both retained; removing either could change cognition or
the enforced output contract. Extra receipt ID references explain the small size
increase below.

## Provider configuration

[OpenAI's caching documentation](https://developers.openai.com/api/docs/guides/prompt-caching)
distinguishes explicit caching on GPT-5.6+ from implicit caching on earlier models.
The implementation recognizes GPT-5.6 and GPT-6 names, uses explicit-only mode,
and places two boundaries: after system instructions and after loaded evidence.
The first survives evidence changes; the second protects repeated loaded cognition.
Dynamic state is outside both. Other model names retain implicit caching. Model
selection and retention settings are unchanged.

The bounded cache key hashes model, layout version, complete system text and
substrate. Participants with identical instructions can share the same family;
different instructions or substrates separate it. Direct calls without worker
metadata fall back to connection identity. Phase, evidence, participant ID and
schema never churn the key. System-text changes automatically change the hash;
material layout semantics require incrementing `ParticipantPromptLayout::VERSION`.
The #185 wording correction therefore gets a new cache family automatically;
`commons-prefix-v1` remains appropriate because the layout and boundaries are unchanged.

**The strict output schema is an independent cache limitation.** The documented
rendered context includes output-format instructions; moving `text.format` later
in request JSON does not move those instructions later in model context. Exact
matching and cache availability still govern reuse. We do not broaden schemas to
make them stable. Cache keys and boundary settings cannot override a mismatch.

## Before/after measurements

The figures below were captured at `405897e`, before the constitution correction.
Re-running the script uses the current constitution for both layout comparisons.

The reproducible offline comparison reconstructs every old packet from the new
parts and verifies strict recursive value/type equality, all list order, and the
original action schema. **All 1,230 pass.** It also reproduces the exact baseline
request construction, using identical connection instructions and model names.
The complete aggregate and representative section sizes are in
[prompt-cache-measurements.json](prompt-cache-measurements.json).

| Mean per inference | Before | After |
| --- | ---: | ---: |
| Complete JSON request bytes, default HTTP escaping | 44,150 | 45,448 |
| Serialized input-array bytes, unescaped slashes | 42,377 | 43,595 |
| Visible text bytes | 40,016 | 41,119 |
| Rough input tokens: visible text + response format, bytes / 4 | 10,377 | 10,653 |
| Shared visible prefix with immediately previous call | 14,940 | 30,722 |
| Same prefix, receipt-heavy calls with 10+ loads (932 samples) | 14,926 | 34,453 |
| Prefix with previous identical-schema request, no TTL model, averaged over all calls | 7,220 | 15,703 |
| Actual provider cached/uncached/output tokens and cache-hit ratio | Unavailable | Unavailable |

The receipt-heavy representative has **39 loads**: 35,980 baseline receipt bytes,
28,524 bytes in the new stable body block, and a visible prefix of
17,079 → 42,859 bytes. Its complete request increases 67,683 → 69,722 bytes.

Only **19/1,226 adjacent pairs** have the same output schema. However **601/1,230
calls** reuse a schema previously seen by that participant. The last table row
concerning prefix reuse compares those earlier same-schema calls, assigning zero
to first occurrences. It assumes neither expiry nor eviction and ignores actual
model tokenization and cache boundaries. It is a structural scenario, not a
measured cache-hit percentage or a prediction of production billing.

An illustrative input-only cost model shows why production measurement matters.
Using bytes/4 and **hypothetical** prices of $1 per million ordinary input tokens
and $0.10 per million cached input tokens, with no separate cache-write charge:

| Scenario, 1,000 representative inferences | Before | After |
| --- | ---: | ---: |
| No cache reuse | $10.38 | $10.65 |
| Every previous same-schema prefix remains reusable | $8.75 | $7.12 |

These are sensitivity examples, not current model prices or actual savings.
The second assumes roughly 18.7% lower input cost; the first costs roughly 2.7%
more. Output is excluded because no output usage exists in the replay corpus.
Models charging for cache writes require their actual write counts and rates too.
Do not treat the doubled visible prefix as a doubled provider hit rate.

Reproduce without APIs, databases, credentials, or a lifecycle replay:

```bash
xz -dc tests/Replay/Golden/solo-domain-natural.jsonl.xz \
  tests/Replay/Golden/collective-domain-natural.jsonl.xz \
  | php scripts/measure-prompt-cache.php > prompt-cache-report.json
```

An optional model-name argument changes only request shape (default `gpt-5.4`);
it never calls that model. These captures are already versioned; the benchmark
does not expand mandatory CI or introduce another lifecycle fixture.

## Usage and rollout

Every successful HTTP response logs one `commons.inference.usage` event, before
contribution decoding, so refusal/malformed structured output still retains usage.
It includes requested/reported model, provider response ID, participant/substrate,
Phase/Domain, layout version, cache key, request bytes, loaded count, total input,
cached input, uncached input (`input - cached`), cache writes, ordinary input
(`input - cached - writes`), output and hit ratio. Missing measurements are null;
zero-input ratios and inconsistent counts are not fabricated. Logger exceptions
cannot fail a contribution. No credentials, prompts or evidence bodies are logged.
Transport errors without a usable response have no token measurements.

Worker metadata travels separately from the unchanged participant state and never
enters the model's allowlist. There are no new tables, queries, locks or network
calls. Configure existing application log retention to retain these ordinary info
events. Aggregate `sum(cached_input_tokens) / sum(input_tokens)` by participant,
substrate, model and version; do not average individual ratios. Count these as
inference calls, not completed wakes. For billing, deduplicate provider response
IDs and price ordinary, cached, written and output tokens at that model's rates.

New prefixes warm naturally. Deploy/restart workers by the existing procedure;
no graph or participant migration is needed. Compare warmed production usage and
cost against retained pre-deploy provider usage where available. This repository
has no such baseline. Rollback is an ordinary code rollback and leaves no provider
files or database state to clean up. Data equivalence is tested; stochastic model
choices can still respond to a formatting/order change, so observe existing natural
substrates after deployment. Domain lens and Perspective remain deferred.

## File inputs: defer

[Responses file inputs](https://developers.openai.com/api/docs/guides/file-inputs)
can reference uploaded file IDs. Text documents are extracted into model context;
PDFs can also contribute page images. Upload-once is therefore a transport/lifecycle
choice, not proof of discounted inference. The current receipts already carry
bounded excerpts or ranked search observations. Uploading full source documents
would change their visible meaning/scope; uploading these small JSON bodies adds
provider file ownership, expiration, deletion and rehydration work without measured
benefit. No files or retrieval tools are introduced. Revisit only for genuinely
large immutable bodies with demonstrated repeated use and a measured benefit.

## Regression coverage

Core adds focused cases for seven-phase information/schema equivalence, corrected
constitution semantics and hash, deterministic keys and receipt order, evidence append/clear/reload,
ranked evidence/parent order, provider compatibility, cache-family scoping and
usage/refusal/logging failures. A small persisted fixture verifies real load,
clear/reload behavior and zero layout queries. Existing request assertions now
read the third message and compare context object keys independently of order;
ordered IDs and strict schemas remain checked. Integration also checks that worker
telemetry identity is correct while the worker state still exactly matches HTTP.

Core and Integration are the required gates. Query/Load are unnecessary because
this patch changes neither query structure nor concurrency semantics. Final suite
results and runtimes are recorded in the PR.
