# Hyperlink reference discovery — Stage 3A

A successful HTML Hyperlink observation now describes the webpage and a bounded
collection of references found in its anchors. This extends the existing Receipt;
it creates no additional Receipt, Artifact, execution, causal edge or measurement.
The [Core Invariants](core-invariants.md) and Stages 1/2A–2D remain authoritative.

| Before Stage 3A | With Stage 3A |
| --- | --- |
| Hyperlink preserves the exact response and extracts title/description. | The same response also supplies bounded outgoing reference metadata. |
| Loaded evidence contains the selected description and title. | That evidence also contains destinations, anchor text and count/truncation metadata. |
| Finding links requires deliberate HTML Preparation. | Loaded references provide lightweight navigation; raw/sanitised/text Preparation remains independently available. |
| Unloaded evidence follows existing metadata visibility. | Unchanged: mere catalogue presence does not disclose reference lists. |

## Extraction and URL resolution

`HyperlinkMetadataExtractor` reuses its deterministic, non-network DOM parse of the
already retrieved HTML. `HyperlinkVerifier` supplies the effective URL actually
fetched after its established redirect handling. Relative paths, parent segments,
scheme-relative references and query-only references resolve against that URL.
The original requested URL and HTML `base` elements do not override it.

For example, at `https://example.org/research/index.html`, `../data.csv?v=2`
becomes `https://example.org/data.csv?v=2`. HTTP(S) schemes, query strings, meaningful
ports and destination fragments remain navigation data. Resolution uses the URI
library, not the Hyperlink retrieval-identity canonicalizer, so it does not drop
query strings to establish evidence identity.

Only anchors with syntactically valid HTTP(S) destinations are included. Empty,
fragment-only, credential-bearing, malformed, control-character and unsupported
scheme destinations are excluded. The scheme is checked before URI parsing because
host/port parsing can otherwise misinterpret values such as `tel:123`. Unicode
paths are URI-encoded; internationalized hosts are checked with their IDNA form.
No DNS lookup occurs. A reference does not establish that its destination is
public, reachable, safe or eligible for acquisition. Any later Instrument must
independently apply existing retrieval/SSRF protections.

Anchor text is literal descendant text, with entities decoded once and incidental
whitespace normalized. Nested markup contributes readable text. Script, style and
inert template content contributes neither labels nor navigable anchors. Empty
labels remain empty; image alt text is not inferred as anchor wording. No LLM,
ranking, categorization or paraphrasing participates.

No scripts run, external entities resolve, images/styles load, or references get
fetched. Parsing uses `LIBXML_NONET` without DTD loading, entity substitution or
XInclude. It operates on a separate parsed copy; stored source bytes never change.

## One bounded extraction policy

The extractor owns these implementation defaults in one place. They are not new
Commons domain invariants:

| Limit | Default |
| --- | --- |
| Retrieved source | Existing 2 MiB verifier ceiling, unchanged |
| Retained unique destinations | 25 |
| Anchor label | 200 Unicode characters |
| Retained destination | 2,048 URI bytes; never shorten a URL |
| Complete serialized link array | 16 KiB using the cognitive JSON transport's escaping |

Process anchors in document order and deduplicate identical resolved destinations,
keeping the first eligible occurrence. Different queries remain distinct. Overlong
destinations are counted but omitted rather than rewritten. After the count or
combined JSON budget is reached, later links are counted but not selected as
replacement candidates. No relevance ranking changes the prefix.

`links_count` counts all eligible unique resolved destinations observed in the
bounded parsed document, including destinations omitted by presentation limits.
The uniqueness set retains fixed-size hashes rather than another copy of every
URL. `links_truncated` is true whenever fewer links are retained than counted.
Label clipping has its own 200-character policy and does not change the destination
count. Complete original wording remains in the Artifact.

HTML without usable anchors has `links: []`, `links_count: 0`, and
`links_truncated: false`. Non-HTML observations retain existing metadata without
an invented reference collection.

## Persistence, identity and failures

The existing `VerifiedHyperlink` description metadata flows through
`EvidenceExecutionService` into the immutable execution and Receipt. No schema
migration or second persistence path is needed. Artifact hash/path/media type/size,
content-addressed storage, normalized fingerprint, seven-day stability, evidence
signature and duplicate suppression are unchanged. Changing only `href` values can
change the original Artifact and reference list while preserving the normalized
fingerprint/signature. References do not enter invocation identity or replay authority.

Malformed HTML uses existing parser recovery. An unexpected reference-processing
error discards the whole reference result and records:

```json
{"links": [], "links_count": null, "links_truncated": false,
 "links_error": "html_reference_extraction_failed"}
```

The unknown count distinguishes extraction failure from a successful empty result.
Internal exception details and partial lists are not persisted. Title/description
and successful original HTTP verification remain independent. The ordinary Receipt
qualifies according to its existing verification/version contract. Retrieval,
storage and infrastructure failure semantics are unchanged; no recovery request
is made to another URL.

## Loaded context and human presentation

`ReceiptEvidenceService::observation()` adds bounded reference fields to the existing
evidence representation. C2 creator loading and C3 causal selection still determine
whether it appears as `loaded_inline_content`. Each Receipt appears once in the
top-level cognitive catalogue, even with multiple Proposition/role associations.
Lists are not repeated in another prompt layer. The selected description still
occupies `content_excerpt`, avoiding duplicate description text.

`ParticipantContextSerializer` permits these fields through the existing active
evidence boundary for the human workspace. The Receipt panel renders escaped
titles/labels, explicit empty/error/truncation states and ordinary external links.
There is no prefetch or automatic acquisition. Existing human active-Read and
autonomous C3 visibility rules are preserved. Unloaded Receipts do not expose the
collection. Preparation remains the path for deliberate transient Artifact reading.

No global context limits increase. The reference array adds at most 16 KiB per
loaded, causally visible Hyperlink Receipt; existing title/description bounds and
causal selection remain intact. Tests exercise several Unicode-heavy loaded
collections without duplicate projection or history mutation.

## Example, ownership and remaining boundaries

[The persisted example](evidence/hyperlink-reference-discovery/receipt.json) is
captured from an isolated persistence test using real verification and Artifact
preservation. Its descriptive fields include:

```json
{
  "title": "Evidence page",
  "description": "Research and supporting data.",
  "links": [
    {"url": "https://example.com/first?edition=1", "text": "Paper"},
    {"url": "https://example.com/data.csv", "text": "Supporting data"}
  ],
  "links_count": 2,
  "links_truncated": false
}
```

Regenerate the complete example with
`COMMONS_HYPERLINK_REFERENCE_EXAMPLE=docs/evidence/hyperlink-reference-discovery/receipt.json php artisan test tests/Feature/HyperlinkArtifactReceiptTest.php --filter=replay_persistence`.
The replay fixture verifies provenance/persistence independently; effective
same-Gate evidence still suppresses operational replay.

JavaScript, event handlers, CSS, `iframe`, `area`, `link`, forms, image attributes
and generated DOM are not reference sources in this stage. HTML `base` support,
crawling, ranking and automatic Prepare/Instrument actions are deferred. No new
Read Type or Download/Upload/Load File Instrument is introduced. Future File
acquisition may preserve a deliberately selected resource through the ordinary
Receipt/Artifact model; discovery alone provides no such authority.

The existing extractor owns discovery/bounds, the verifier owns retrieval, and the
existing evidence projection owns disclosure. Remaining coupling is the serializer's
explicit field allowlist; tests cover it alongside C2/C3 projection. No new service
or general consolidation was required.

See [validation and baseline comparison](evidence/hyperlink-reference-discovery/validation.md).
