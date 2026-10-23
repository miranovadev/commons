# Load File: Download (Stage 3B)

> Stage 3C adds native PDF text, CSV tables and HTML table/list reading with adaptive budgets. See [reader capabilities](artifact-readers-stage-3c.md). Acquisition remains unchanged; references below to PDF being unsupported describe the Stage 3B reader boundary.

Load File acquires a directly referenced public HTTP(S) resource as an immutable
Artifact. Its initial and only method is `download`:

```json
{"action":"instrument.execute","proposition_id":"CURRENT_PERSIST_GATE","instrument":{"kind":"load_file","method":"download","reference":"https://example.org/research.pdf?edition=2"}}
```

It uses the existing Persist Instrument opportunity, unified EvidenceExecution,
once-per-Participant/Cycle allowance and same-Phase refresh. An available ordinary
Receipt still excludes acquisition; completion supplies an ordinary initially
unattached Receipt. There is no additional structural contribution or allowance.

## Acquisition and reading remain separate

| Operation | Receipt describes | Persistently loadable | Artifact |
| --- | --- | --- | --- |
| Hyperlink | Page title, description and bounded references | Yes | Original response bytes |
| Load File / Download | Requested/final address, filename, duration and Artifact descriptor | No | Original response bytes |

HTML acquired through Load File remains a Download observation: no title,
description, outgoing-reference extraction or content excerpt is produced. A
Hyperlink of a PDF remains a Hyperlink. Neither operation follows discovered links.
Download does not execute code or decode/re-encode document content.

Acquisition uses the existing private, content-addressed ArtifactStore. It hashes
and verifies the exact body bytes, atomically publishes without overwriting a
concurrent winner, and reuses an existing object only after verifying its contents.
The `path`, `hash`, `media_type`, `size` descriptor contract is unchanged. No new
Artifact table or file-serving endpoint exists.

## HTTP policy and bounds

`PublicResourceRetriever` owns the shared low-level HTTP safeguards; Hyperlink and
Download retain their separate URL/content/identity policies. Each hop validates a
public DNS address and pins that address for the request, with TLS verification,
no ambient proxy, no automatic redirects and no credential-bearing URLs. Only
standard HTTP(S) ports are accepted. At most three redirects/four requests are
permitted; connect and request timeouts are three and ten seconds per hop.

Download preserves query bytes/order and path escaping. Host case/IDNA and default
ports normalize; fragments are omitted because they are not transmitted. Source
and effective addresses are both retained. URLs are limited to 4,096 bytes.
Hyperlink retains its existing query-independent canonical retrieval identity.

`COMMONS_DOWNLOAD_MAX_BYTES` defaults to **16 MiB**. The transport rejects excessive
or malformed Content-Length headers and aborts via its transfer-progress callback
when received bytes exceed the limit, including unknown-length responses. A final
body-size check also protects alternate/fake transports. The cURL transport may
receive a transfer chunk before the progress callback runs; this is bounded
acquisition, not an unbounded full-body download followed by rejection.

The established policy requests `Accept-Encoding: identity`, disables automatic
content decoding and rejects non-identity Content-Encoding. HTTP transfer framing
is removed by the client; the remaining response body is preserved unchanged.
Non-2xx responses and partial 206 responses do not claim complete acquisition.
An empty successful body is a valid exact-byte Artifact.

Storage limits are independent of reading budgets. The existing Preparation
registry currently inspects sources up to 8 MiB and applies its smaller selection
and cognitive-delivery budgets. A larger Download or unsupported PDF/image/archive
can be successfully stored without an available reader. Stage 3B adds no readers.

## Receipt and cognitive contract

A Download Receipt is ordinary (`SPECIAL_RECEIPT_TYPES` remains `['tragedy']`) and
can satisfy Receipt-governed generation or be selected by lawful Reclassification.
Consumption is unchanged. Its capabilities contain `replayable`, **not `readable`**;
there is no creator load event, loaded payload or synthetic Reinforcement.

Catalogue and C3 projections show a bounded `acquisition` object containing method,
source/effective URL, descriptive filename, processing time, success/failure and
Artifact hash/media type/size. Private storage paths remain in immutable Receipt
metadata, not model prompts or browser controls. No file body is injected into
persistent context or automatically rendered/downloaded by the workspace.

`processing_ms` is a nonnegative integer capped at 2,147,483,647. It measures the
server's acquisition interval: retrieval, hashing, publication and read-back
verification, excluding execution/Receipt database persistence and later cognition.
It is operational metadata, never a substrate measurement.

Filenames prefer a UTF-8 Content-Disposition filename (or ordinary filename), then
the final URL path. They are untrusted descriptive metadata: path components,
control/direction characters are removed, length is bounded to 255 characters,
and no filename is invented for an unnamed resource. Storage keys never use them.
Declared media types are normalized; malformed types become
`application/octet-stream`. Source validation remains the reader registry's job.

Artifact accessibility uses the existing causal Region/Horizon catalogue, including
consumed ordinary Receipts. Knowing a hash alone grants no access. No loading step
is needed to Prepare a Download Artifact, and consumption does not remove that
access. Equivalent bytes are read through the same Source Type × Read Type registry
regardless of acquisition method. Prepared content remains transient.

## Observation identity, duplicates and replay

Successful Download identity is SHA-256 of the versioned tuple:

```
load-file:download:v1\n{normalized requested URL}\n{effective URL}\n{sha256:exact-byte-hash}
```

Different source addresses may share one stored Artifact without becoming the same
observation. Changed bytes or changed effective destinations produce distinct
observation identities immediately; no Hyperlink normalized-text fingerprint or
seven-day clock applies. Repeated failed retrieval observations use the same source
plus `retrieval-failed` as their identity, but have no qualifying evidence signature.

RelevantInstrumentService compares these identities against the existing effective
causal evidence set. A URL alone cannot exclude an unknown future byte version, so
Load File does not publish invocation-only exclusions. After retrieval, the
contribution transaction rechecks both ordinary Instrument eligibility and observed
identity under the existing Substrate lock. An unchanged relevant observation is
rejected without a second execution/Receipt. This is scoped duplicate suppression,
not a new substrate-global URL authority.

Replay retains the original Load File invocation and source Receipt relationship;
it retrieves again through the same safeguards and revalidates byte identity.
An available Receipt or spent allowance still prevents replay. A later eligible
Cycle may observe a changed version; replay is not a bypass for identical evidence.

## Failures and reliability

Retrieval/status/redirect/size/transport failures complete the existing failed
Instrument observation contract: an ordinary non-loadable Receipt records concise
failure metadata, no Artifact and no successful signature. As for other completed
failed Instrument observations, the ordinary catalogue is based on completion and
scope, not truth verification or loading.

Storage or integrity failures propagate as infrastructure errors before execution
persistence. A database rollback cannot leave a successful Receipt pointing at an
unpublished Artifact. An already-published content-addressed object may remain
unreferenced after a duplicate/stale/rolled-back contribution; it remains private
and grants no access by itself. Download introduces no object deletion/GC policy.

No network I/O takes place inside structural write transactions. Existing lock
ordering and independent Instrument/Preparation partial unique indexes are retained.
The additive migration widens the existing execution-kind and Receipt-type checks
only; SQLite partial-index SQL is preserved during its table rebuild.

## Ownership and remaining coupling

- PublicResourceRetriever owns HTTP safety and transfer bounds; DownloadAcquisition
  owns Download URL policy, exact-byte storage and compact acquisition metadata.
- EvidenceExecutionService remains the single ordinary Receipt persistence owner.
- RelevantInstrumentService owns effective duplicate suppression; existing planners
  and contribution boundaries retain action authority.
- ReceiptContextService skips creator loading for non-readable capabilities.
  HorizonReceiptService and PreparationService already separate catalogue availability,
  Artifact access and reading support; their authority does not change.
- ReceiptEvidenceService projects compact acquisition metadata for the existing
  serializer/projection/UI. It does not treat non-loadable metadata as loaded evidence.

Execution observation payloads remain explicitly typed per Instrument; this is a
small existing coupling, not a new ingestion framework. Upload, provider-native
file inputs, PDF/image/archive readers, crawling and automatic link acquisition
remain future work.

See [sample Receipts](evidence/load-file-download/receipts.json) and the
[validation report](evidence/load-file-download/validation.md).
