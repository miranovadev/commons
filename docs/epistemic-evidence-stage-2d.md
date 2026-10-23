# Epistemic evidence Stage 2D

Stage 2D activates one Preparation experience for human and autonomous Participants.
It uses the Stage 2C `EvidenceExecution` lifecycle and ordinary Receipt contract.
The ten [Core Invariants](core-invariants.md) and existing structural contribution,
measurement, C3, Tragedy and consumption contracts remain authoritative.

## Artifact access is independent of Receipt consumption

Claire's Stage 2D clarification supersedes Stage 2C's restriction against selecting
Artifacts from consumed Receipts. Preparation selects preserved resources for reading.
`HorizonReceiptService::accessibleOrdinary()` uses the same ordinary Commit Region,
Horizon and completed-execution provenance as the available evidence catalogue, but
includes consumed Receipts. `catalogue()` and `ordinaryAvailable()` retain the
unconsumed filter. Tragedy remains special and grants no Artifact-reading shortcut.

A selection must name both an accessible Receipt and an Artifact actually preserved
in that Receipt's immutable metadata. An arbitrary hash, a loaded foreign Receipt,
or an unrelated substrate resource grants no access. Loading is independent.
Consumption after selection does not erase authority already acquired for that
immutable request. Neither reading nor generation consumes the source Receipt.

## Source Type, Read Type, representation and delivery

`ArtifactReaderRegistry` is the single implemented capability registry. It resolves
validated UTF-8 bytes, controlled MIME hints and an implemented Read Type into a
versioned representation. Acquisition by Hyperlink does not select the reader.

| Validated source | Read Types | Reader | Delivery |
| --- | --- | --- | --- |
| HTML | raw, sanitised, text | commons-native-text / 1 | inline-text, human-text |
| Plain text | raw, text | commons-native-text / 1 | inline-text, human-text |
| PHP and common textual source | raw, text | commons-native-text / 1 | inline-text, human-text |
| Markdown | raw, text | commons-native-text / 1 | inline-text, human-text |
| JSON, XML | raw, text | commons-native-text / 1 | inline-text, human-text |

Raw preserves every original UTF-8 byte, including line endings. It requests the
whole source and fails if over budget. Sanitised HTML removes scripts, styles,
head and embedded content, decodes entities and preserves readable text. It never
changes the preserved resource or fetches linked resources. Text selects original
UTF-8 source characters, including markup when the source is HTML. `offset` is
zero-based; `end` is exclusive. Responses include total characters and `has_more`.
A bounded text request is an explicit selection, not a silently shortened raw read.

JSON and XML are parsed for format validation. Malformed declared JSON/XML fails.
HTML parsing tolerates malformed markup. XML DTD/entity declarations are unsupported;
no reader resolves entities, executes source code or retrieves external resources.
Binary/non-UTF-8 sources, PDF, images and native provider attachments are unsupported.
Regex, tables, lists and specialised code extraction are not advertised.

Read Type and delivery remain distinct fields. Future real readers can extend the
registry's supported combinations without changing execution identity, selections,
Receipt identity, purpose, outcomes or consumption. Method/version is persisted for
the actual representation, rather than inferred later from current capabilities.

## Request and budgets

```json
{
  "action": "preparation.start",
  "proposition_id": "CURRENT_GATE_ULID",
  "purpose": "Compare the documented mechanism with its implementation.",
  "artifacts": [
    {"receipt_id": "RECEIPT_ULID", "artifact_id": "sha256:CANONICAL_HASH", "read_type": "sanitised"},
    {"receipt_id": "OTHER_RECEIPT_ULID", "artifact_id": "sha256:OTHER_HASH", "read_type": "text",
     "parameters": {"offset": 0, "max_chars": 4000}}
  ]
}
```

There are 1–8 selections. Purpose preserves exact authored wording, up to 240
characters. Equivalent requests for identical bytes share one delivery while every
Receipt association survives. Different Read Types or ranges get distinct request
identities. Duplicate identical Receipt/Artifact/representation selections fail.

`config/preparation.php` provides server-enforced limits: 8 MiB source inspection,
16 KiB per representation, 48 KiB combined delivery, up to 8,000 requested text
characters (also subject to byte limits), and a 4,000-character UI default. The
Stage 2C 1 MiB domain ceiling remains an additional upper bound. Known oversized
raw requests are omitted from advertised options. No output is silently truncated.

ArtifactStore reads only canonical private-store paths, verifies exact size/hash,
rejects local paths resolving outside the store, and rejects reads inside write
transactions. Capability discovery caches format metadata, never source bodies.
Reader and combined-budget failures record explicit unsuccessful executions.

## Eligibility and resolution

| Disperse state | Generative options | Preparation |
| --- | --- | --- |
| Available ordinary unattached Receipt | Structurally lawful Variant/Revise | Withheld |
| None; allowance unused; accessible readable Artifact | Withheld | Offered |
| None; allowance spent or no readable Artifact | Withheld | Withheld |
| Preparation pending/presented | Exact execution's resolution only | No second start |

Attestation, Traverse and Rest keep their independent rules. Persist's Instrument
policy remains unchanged. Distinguish bootstrap remains exempt. Prepared resolution
is explicitly tied to a validated presented execution; it bypasses only the fresh
Receipt prerequisite, never structural generation restrictions. Revision ownership,
effective earlier self-Refute, viable continuations, epoch spentness, Tragedy and
terminal constraints still apply.

Contributed resolution creates the actual Variant/Revise, its ordinary unattached
Preparation Receipt, and normal Phase progression atomically. Outcome proposition ID
is provenance, not attachment. Deferment requires an exact authored reason of 1–400
characters, creates the ordinary Receipt and preserves Phase, Gate and epoch. The
fresh Receipt may enable subsequent ordinary generation, including after deferment.
Later Reclassification alone attaches/consumes it without rewriting its outcome.

## Human and autonomous interaction

The workspace offers Prepare only from the authoritative plan. Participants choose
Artifact associations, supported Read Types/ranges and purpose. The resulting
pending execution owns a transient, private `no-store` reading response. React
renders source strings as text, including raw HTML. A server-authenticated delivery
token acknowledges the exact content after rendering. It proves the application
supplied a representation, not that a human understood it. Acknowledgement retries
are idempotent. Variant/Revise and explicit Defer resolve the same execution.

An autonomous wake selects Prepare through its existing schema. Its next iteration
reads the selected bytes outside write transactions, adds purpose/provenance/methods/
boundaries/content to that same Phase's inference request, and supplies a resolution-
only schema bound to execution ID. A successful inference call acknowledges that
presentation before resolving its returned action. Extraction alone is not delivery.
A failed API/inference call never becomes an inferred deferment or a successful
Receipt. No additional structural Cycle or Phase is created for this inference.

Prepared material is a separate untrusted-source block in the dynamic user request.
It is not added to the stable instruction prefix, loaded Receipt content, or normal
participation state. After resolution, subsequent ordinary requests contain only
normal bounded Receipt history according to C3/loading rules. The human interaction
likewise clears its transient content when closed. Historical purpose, selections,
methods and outcomes remain available; deferment reasons are Participant statements.

## Failure, retry and concurrency

Substrate → Participant → Cycle → Phase → execution lock order is preserved.
Preparation starts reserve one allowance with the existing partial unique index.
Fresh scope, current Gate and lack of available evidence are checked under the root
lock. Structural contributions recheck Receipt eligibility, and ordinary dispatch
cannot bypass an unfinished Preparation. Reader work and HTTP inference occur outside
write transactions. Competing resolutions produce at most one Proposition/Receipt.

Successful extraction without acknowledgement leaves `pending`. A retry can deliver
the same immutable representation and acknowledge it once. Acknowledged deliveries
are immutable. Reader errors identify Artifact, Read Type, method/version, stage and
classification (`missing_capability`, `invalid_source`, `payload_limit`, etc.). Failed
inference records its diagnostic without an outcome. Both leave the allowance spent.
They do not permanently label the Artifact unreadable for future versions/Cycles.

Unresolved executions whose Phase/Gate changed, or whose age exceeds the configured
30-minute recovery interval, fail explicitly on the next state refresh. This releases
the operational block without restoring the allowance or inventing a completion.
Normal lawful options and later-Cycle attempts remain available. A stale or duplicate
resolution returns the existing validation/conflict response.

## Ownership and consequential corrections

- EvidenceExecutionService remains the common persistence and Receipt constructor.
- PreparationService owns accessibility validation, allowance, presentation and
  resolution; the registry owns source validation and representations.
- InputSchemaService/InputPlanService and PhaseGenerationService reuse this policy.
  ContributionDispatcher requires the exact presented execution for prepared writes.
- Existing ParticipationService, WakeParticipant, prompt transport and workspace
  carry the interaction. No separate planner, Receipt catalogue or execution store
  was introduced. The dormant reader interface was replaced by the implemented
  registry rather than retaining two capability definitions.
- HTTP trimming now excludes authored Preparation purpose/deferment reason, preserving
  the same exact wording as autonomous/domain callers.
- Existing Preparation fixtures now consume source evidence lawfully before Prepare;
  consumed-source rejection and dormant-activation assertions were superseded.

Remaining coupling is practical: PreparationService composes structural schema,
dispatch and Phase progression, while ParticipationService also orchestrates ordinary
contributions. A later focused extraction of that common structural transaction may
reduce orchestration coupling. Stage 2D does not attempt a general service rewrite.

## Reproducible examples and validation

The focused reader, Preparation lifecycle, HTTP/autonomous journey and PostgreSQL
race tests exercise this contract. Frontend tests render real escaped content,
acknowledge only after rendering, clear it on unmount, and enforce selection bounds.
The PR records complete Core, Integration, Phase Profile, C3 and frontend outcomes,
including baseline failures by cause rather than test name alone.


[Persisted examples and actual prepared inference requests](evidence/epistemic-evidence-stage-2d/examples.json)
come from the isolated autonomous journey test with deterministic provider responses.
They include contributed and deferred executions/Receipts, a failed execution with
`receipt: null`, and the following ordinary request after deferment (without Artifact
content). Generate them with `COMMONS_PREPARATION_READING_EXAMPLES=docs/evidence/epistemic-evidence-stage-2d/examples.json php artisan test tests/Feature/PreparationJourneyTest.php`.
The API key is test-only and is not part of the recorded request body.

Lifecycle fixtures now preserve readable resources and perform Preparation after
Constrain consumes the available evidence. The fixed skeptical HTTP transcript
retains its structural lineage and Commit measurements, while five Disperse
Preparations replace later Persist acquisitions. Preparation integration cases use
fresh isolated schemas with real transaction boundaries, because a test-wide write
transaction cannot truthfully exercise out-of-transaction reading.
