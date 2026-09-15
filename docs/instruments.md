# Instruments and immutable observations

A **Receipt is an immutable observation/result produced by an Instrument execution**.
Capabilities are independent, not mutually exclusive Receipt types:

| Capability | Meaning |
| --- | --- |
| Readable | The existing observation can be loaded into persistent Participant context, subject to existing qualification and encounter rules. |
| Replayable | The stored originating invocation can be executed again, producing a new Receipt. |

`ReceiptCapability` names these domain capabilities. New Receipts persist their
capabilities as a JSON list; an empty list supports archival observations. A null
legacy value resolves deliberately from the known Instrument kind. Cite is
readable-only. Hyperlink and Web Search support both. A historical execution
without structured invocation input cannot be replayed; no invocation is inferred
from prose or evidence content. Unsupported `artefact` Receipts grant neither.
Capability support does not bypass operational eligibility or evidence qualification.

## Read and Replay

`receipt.read` takes only `action` and `receipt_id`. It loads an existing qualifying,
encountered observation through the established `participant_receipt_contexts`
mechanism without fetching anything or advancing the Phase. Existing Cite context
exclusions, persistent loads, clears and Participant/Proposition uniqueness remain
in effect. See [Reinforcement](reinforcement.md).

`receipt.replay` also takes only `action` and `receipt_id`. It is an Instrument
execution: the Receipt must be encountered and its original Proposition must be
an eligible Instrument Write in the current Phase. Replay uses the same Phase
progression as `instrument.execute`, advancing to the next Phase. It is
available in phases 1–6, including Distinguish and Mediate, when these target
rules permit it. Order admits neither execution, Read nor Replay. Visibility of a historical Receipt alone grants no execution authority.

Replay additionally requires no equivalent Receipt in the target's effective
evidence. Capability and current admissibility are distinct: an effective Receipt
suppresses its own same-subject Replay, including failed observations. Loading or
clearing does not change suppression. Read remains independently available. The
UI follows the server's authorized action IDs. See
[Receipt relevance and invocation exclusions](continuous-cycles-and-receipt-relevance.md).

Replay stores a new `instrument_executions` row, copies the normalized structured
invocation from the source execution's `input`, and sets
`replayed_from_receipt_id` on the **new execution**. Its new Receipt has the normal
unique execution link and its own ledger entry. Replaying R2 links R3 to R2, so
history is reconstructable through Receipt → execution → previous Receipt.
The exact original Proposition or Attestation subject is preserved. No existing
Receipt, execution result, invocation or provenance is rewritten. Domain model
updates reject changes to saved Receipts and originating invocation fields.

Execution and Replay never load a Receipt or create active Reinforcement.
Explicit Read is required. Even if multiple equivalent or changed observations
are loaded, each Participant still contributes at most one counted Reinforcement
per active classification under the evidence inheritance rules. Reclassification
preserves qualifying proposition evidence; committed results remain frozen.

## Web Search: the first discovery Instrument

```json
{"kind":"web_search","query":"evidence to investigate"}
```

The query is required, non-whitespace, and at most 1,000 characters. Whitespace
is collapsed before execution and persistence. The strict query form is separate
from Cite/Hyperlink's `{kind, reference}` form, including in the inference transport.
Web Search is offered wherever Instrument execution is otherwise admissible;
it can also provide the Origin's initial observation.

`WebSearchProvider::search(query): SearchObservation` is the small provider boundary.
The initial `TavilyWebSearchProvider` adapter translates the provider response into
query, execution time, provider/request provenance, and up to five resources:
title, normalized HTTP(S) URL, excerpt, source hostname, rank, optional relevance
and publication metadata. URLs preserve meaningful query parameters and discard
fragments. These are normalized discovered URLs, not a claim that the source has
published an authoritative canonical URL. Discovered pages are not fetched.

Titles are bounded to 300 characters, excerpts to 1,200 with a truncation flag,
and URLs to 2,048. The persisted normalized result records the observation at
execution time; neither Read nor rendering silently refreshes it. Empty successful
results are a valid observation that no resources were returned for that query.

Search evidence identity hashes the normalized query and result set. Execution
time and provider request IDs distinguish events without manufacturing new evidence
signatures. Changed results create a distinct observation; no semantic comparison,
stability clock or replay-based Closure effect is introduced for Search.
A successful search verifies that the provider returned this result set, not that
its claims are true. Its observation qualifies for explicit loading. The provider's
generated answer, raw pages and unrelated response fields are never persisted or
passed to Participants.

Hyperlink Replay continues to fetch the canonical public URL through the existing
SSRF-resistant verifier. It retains the global seven-day server-observed version
stability rule. A newly observed changed page gets a new immutable Receipt even
while unqualified; prior Receipts are never retroactively promoted. Fresh observations remain possible once equivalent evidence is no longer effective;
the version clock does not override duplicate suppression.

## Configuration and operation

```dotenv
COMMONS_WEB_SEARCH_PROVIDER=tavily
TAVILY_API_KEY=your-server-side-key
```

These live in `config/services.php`. The key is never included in Participant
packets, schemas, Receipt metadata or frontend configuration. The adapter uses the
[Tavily Search endpoint](https://docs.tavily.com/documentation/api-reference/endpoint/search)
with basic search, five results, generated answers/raw content/images disabled,
and automatic parameter selection disabled. Tavily is an implementation provider,
not a permanent Commons semantic dependency.

Requests have a three-second connection limit and ten-second total limit, TLS
verification, no redirects, no proxy and a 256 KiB response limit. There are no
implicit retries or provider fallbacks. Invalid responses, network errors, missing
keys and unsupported provider configuration produce a completed attempt with a
safe, unqualified failure Receipt; they never disclose provider error bodies or
credentials. A later explicit execution can retry once equivalent evidence is no longer effective. The Instrument retains
the existing synchronous transaction and Phase progression, including failed
observations; a failure grants no Reinforcement.

All external text stays in the packet's evidence/data fields and is rendered as
escaped text. It never becomes system instructions or action-schema definitions.
The Participant prompt explicitly treats external instructions as untrusted data.
Automated tests mock all search calls.

Run the additive migration before serving this version. It widens the Instrument
and Receipt kind constraints and adds nullable capability/provenance columns,
without rewriting observations or backfilling any loads. Rollback refuses to
remove recorded Search or replay history.

