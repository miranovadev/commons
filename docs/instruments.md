# Instruments and immutable observations

## Persist-only, phase-neutral Instrumentation (Stage 2A)

One completed Instrument execution per Participant Cycle is permitted in Persist,
against the exact current Gate. It leaves Phase, Cycle and position unchanged and
returns fresh cognition with the ordinary Receipt automatically loaded for its
creator. Completed unsuccessful observations spend the allowance; infrastructure
failures without an execution/Receipt do not. Receipt consumption or unloading
does not restore it. See [the Stage 2A contract](epistemic-evidence-stage-2a.md).

Stage 2B adds the shared catalogue prerequisite: Persist offers Instrument only
when no eligible unattached ordinary Receipt is available. If one exists, lawful
Variant/Revise replace acquisition. Neither loading nor authorship controls this
choice, and generation does not consume the Receipt. An exhausted allowance does
not suppress independently lawful Attestation or Traverse. Disperse keeps its
existing generation rules until Preparation and reading can be activated.
See [the Stage 2B contract](epistemic-evidence-stage-2b.md).

## Hyperlink Artifact preservation (Stage 1)

Successful Hyperlink observations now preserve the exact received resource as a
private, immutable, content-addressed **Artifact**. Receipt metadata adds an
`artifacts` array, independently selected title/description and extraction-source
labels. Evidence fingerprints and the seven-day version rule remain unchanged.
See [the Stage 1 contract](hyperlink-artifacts-stage-1.md) for metadata examples,
atomic storage, failure semantics and required shared-storage configuration.
Artifacts do not become readable documents in Participant cognition. C2/C3
continue presenting one bounded description for eligible personally loaded
observations; internal storage keys and full bytes are omitted.


> **C2/C3 update:** [Participant Context C3](participant-context-c3.md)
> extends loaded ordinary evidence presentation to all causally selected
> associations, including Write and extended history.
> **C2 update:** [Participant Context C2](participant-context-c2-implementation.md)
> supersedes the inference Receipt layout and loading timing described below.
> New Instrument Receipts auto-load for their performer; eligible local Write
> loads and personal same-substrate unloads are supported. Loaded Read/Gate
> content appears once in the top-level catalogue; Write alone exposes metadata.
> Operational evidence, causal authority and measurement formulas are unchanged.

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

## Instrument replay and Participant memory

`instrument.execute` accepts a fresh Citation, Hyperlink or Web Search invocation,
or `instrument: {"replay_receipt_id": "R"}` with the normal `proposition_id`.
The forms are mutually exclusive. The schema enumerates replay IDs for each exact
eligible Instrument target. Catalogue visibility or replay capability alone grants
no execution authority. The server restores the immutable originating invocation;
the Participant does not restate its URL or query. Replay follows ordinary Instrument
Persist Gate eligibility and the once-per-Cycle allowance, without Phase progression.

Loading is separate: optional Gate `receipt_options.load/unload` accompanies a
successful forward contribution atomically. There is no standalone Receipt Read
or Replay action. Rest cannot change Receipt memory. Persistent load state survives
Cycles and Horizons; full bodies activate only for loaded evidence grounding the
current participant-facing Read. All other references remain lightweight metadata.
See [Reinforcement](reinforcement.md).

Replay additionally requires no equivalent Receipt in the target's effective
evidence. Capability and current admissibility are distinct: an effective Receipt
suppresses its own same-subject Replay, including failed observations. Loading or
clearing does not change suppression. Gate memory selection remains independent. The
UI follows the server's target-bound Instrument schema. See
[Receipt relevance and invocation exclusions](continuous-cycles-and-receipt-relevance.md).

Replay stores a new `instrument_executions` row, copies the normalized structured
invocation from the source execution's `input`, and sets
`replayed_from_receipt_id` on the **new execution**. Its new Receipt has the normal
unique execution link and its own ledger entry. Replaying R2 links R3 to R2, so
history is reconstructable through Receipt → execution → previous Receipt.
The original observed Proposition is preserved; new Receipts ground it. Legacy Attestation-owned Receipts resolve through their Proposition without rewriting historical rows. No existing
Receipt, execution result, invocation or provenance is rewritten. Domain model
updates reject changes to saved Receipts and originating invocation fields.

Execution and Replay automatically load the new Receipt for its performer under C2.
Qualifying loaded uptake uses the existing Reinforcement rules. Even if multiple equivalent or changed observations
are loaded, each Participant still contributes at most one counted Reinforcement
per active classification under the evidence inheritance rules. Reclassification
attaches its selected Commit Region Receipt; committed results remain frozen.
Receipt metadata is shared. Gate options establish optional personal loading
for Reinforcement, independently of one-use consumption by Reclassify.

## Proposition Search: bounded historical memory

```json
{"kind":"proposition_search","query":"optional text","limit":5}
```

Proposition Search is an internal-memory Instrument, not an evidence-discovery
Instrument. It reads backward from the exact Proposition on which the Instrument
is executed by reusing `BackwardRecoveryLineage`: raw recorded causal hops,
durable participant arrival provenance at multi-input Writes, revisions and
historical intermediates all retain their real distance. It never starts from a
Substrate-wide Proposition query, display ancestry, timestamps or creation order.

The base candidate set is structural before filtering. The Instrument removes its
head, restricts candidates to the head's creating Domain, and subtracts the
Propositions already exposed by the current participant-facing plan. That visible
set comes from `ParticipantContextSerializer::visiblePropositionIds()`, not the
broader calculation-local `ParticipationContext` hydration. A Proposition may
therefore be resident for measurement calculations and still remain legitimate
historical memory when it is not actually in cognition. Siblings, unrelated
branches, homologous populations and the complementary Domain never enter the
candidate set.

`query` is optional. When present it is whitespace-normalized and performs a
case-insensitive substring match against the already-bounded candidates'
statements. Matching only narrows visibility; it cannot add candidates. `limit`
defaults to 5 and is constrained to 1–10. Matching determines membership while
actual backward lineage distance determines order, nearest to oldest.

Each result is deliberately thin:

```json
{
  "proposition_id": "…",
  "statement": "…",
  "register": "…",
  "tier": "…",
  "domain": "emergence",
  "lineage_distance": 7
}
```

`lineage_distance` is structural distance from the Instrument head, not elapsed
time. Excluded visible, non-matching or complementary-Domain nodes still occupy
their real hops, so returned distances are never renumbered.

The persisted Receipt is type `proposition_search`, readable-only, and uses
`load_mode: memory`. Once loaded its `proposition_collection` appears in
Participant cognition outside the loaded-evidence prefix. It has no evidence
signature, never enters ordinary Region Receipt catalogues, never counts as an
instrumented evidence source, never produces Reinforcement, and never satisfies
Attestation or Commitment grounding.

Observation identity includes the normalized query and material
PropositionCollection identity: head, Domain, ordered returned Proposition IDs
and structural distances. The requested limit is not identity when it produces
the same observed collection. The same query over the same collection is
duplicate-suppressed; the same query may produce a distinct immutable Receipt
when later history changes the observed collection.

Search results are observational only. Returning a Proposition creates no
Supersession edge, Gate, Traverse target, movement destination, Receipt-scope
extension, Homology relation, recovery target or other action authority. A later
operation involving that Proposition must independently satisfy the ordinary
Commons structural rules.

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

