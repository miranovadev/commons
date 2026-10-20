# Hyperlink Artifacts Stage 1

Authority: Claire's approved 20 October 2026 implementation handoff, subordinate
to the ten [Core Invariants](core-invariants.md). This stage preserves retrieved
resources and adds descriptive metadata; it grants no Artifact reading authority.

## Inspection and readiness

Inspected main `9d040d8b2763b25a293f69947540bcf216b7a72e` before implementation.

| Boundary | Current implementation / incompatibility | Resolution | Authority / verification | Status |
| --- | --- | --- | --- | --- |
| Retrieval | `HyperlinkVerifier` pins public DNS, validates redirects, disables proxy/decompression, enforces TLS and 2 MiB bounds. It overwrites its body variable during normalization. | Retain the entity body separately; preserve transport and fingerprint operations exactly. | Handoff §§4,7; byte-preservation and fingerprint tests | Documented |
| Identity | `HyperlinkIdentity` strips queries/fragments; `ReinforcementSignatureService` uses normalized content and a global seven-day server clock. | Neither consumes Artifact identity or descriptive metadata. | Handoff §§2,7; existing version tests | Documented |
| Observation / transaction | `OperationalInstrumentService::observe` precedes contribution locking; `execute` revalidates and `InstrumentExecutionService::complete` writes Receipt, ledger, creator load and measurements. | Finish storage and extraction in verification before returning the request-local observation; completion performs no storage I/O. | Handoff §8; transaction and rollback tests | Documented |
| Failures | Verification errors become completed unverified observations; execution errors may leave no Receipt. | Retrieval/verification failures keep that contract. Storage/integrity/configuration failures propagate as execution failures, never as successful or misleading resource observations. | Handoff §§4,8; no-Receipt storage failure tests | Documented |
| Media handling | Current verifier has no MIME allowlist: nonempty opaque/binary content is hashed without a text excerpt. Empty, partial, oversized or compressed responses fail. | Preserve acceptance/failure behavior. Normalize the descriptor MIME token, using `application/octet-stream` for missing/malformed headers; do not add parsers for opaque formats. | Handoff §§2,4,6; malformed and binary tests | Documented |
| Private immutable storage | Default local disk permits serving and ordinary filesystem writes overwrite; default S3 disk needs its Flysystem adapter dependency. | Dedicated non-serving private disk; atomic local publication or S3 conditional creation, full hash/size verification on reuse and after publication. Unsupported drivers fail closed. | Handoff §5,8,11; collision/concurrency tests | Documented |
| Cognitive projection | `ReceiptEvidenceService` allowlists external observation fields; C2/C3 determine loading and causal visibility independently. | Keep old persisted excerpt fields. Project the bounded selected description through `content_excerpt`, with bounded title/source fields; exclude Artifact paths and bytes. Historical Receipts retain their old projection. | Handoff §§6,9; C2/C3 and historical compatibility tests | Documented |

No measurement, capability, eligibility, ownership, version clock, movement,
recovery or canonical boundary needs a semantic change. No backfill or Artifact
table is required. The obsolete `commons:receipt-create` command does not call
the current Instrument pipeline and is outside this change.

## Identity and metadata

An **Artifact** identifies immutable stored bytes. A **Receipt** identifies a
historical observation; distinct Receipts can reference one Artifact. Existing
Hyperlink evidence identity continues to use normalized content and its existing
version qualification. Cosmetic HTML changes can change an Artifact hash while
preserving the evidence fingerprint and signature.

Implementation, configuration and validation details follow below.

## Stored contract and extraction

Successful verification preserves the exact received entity body, including HTML
markup, links and scripts. Transport headers/TLS framing are not part of it.
The HTTP client continues requesting identity encoding and rejecting compressed
responses. Original bytes never enter Receipt JSON or inference packets.

New metadata coexists with the unchanged validation, resource, fingerprint,
observation timestamps, version-clock and historical excerpt fields:

| Field | Meaning |
| --- | --- |
| `artifacts` | Array of server-generated descriptors; normally one for Hyperlink |
| `artifacts[].path` | Private disk key `artifacts/sha256/ab/cd/<64 hex characters>` |
| `artifacts[].hash` | `sha256:<64 hex characters>` over the exact stored bytes |
| `artifacts[].media_type` | Lowercase MIME token without parameters; malformed/missing becomes `application/octet-stream` |
| `artifacts[].size` | Exact byte count, not character count |
| `title`, `title_source` | Selected source wording, at most 300 Unicode characters; absent values are null |
| `description`, `description_source` | Selected source wording, at most 1,200 Unicode characters; absent values are null |
| `description_truncated` | Whether the selected description exceeded 1,200 characters |

Title priority: first nonempty HTML `title`, then `meta property="og:title"`,
then nonempty `h1`. Description priority: first nonempty
`meta name="description"`, then `meta property="og:description"`, then body text.
Each field resolves independently. The DOM parser decodes entities once;
selected metadata wording is trimmed, not paraphrased or combined. Body fallback
excludes head, script and style text and normalizes whitespace. Source labels are
`html_title`, `og_title`, `h1`, `meta_description`, `og_description`, `body_text`,
and `normalized_text` (existing non-HTML text/JSON excerpt).

Parsing operates on a separate UTF-8 copy bounded by the existing 2 MiB retrieval
limit. It uses `LIBXML_NONET`, without DTD loading, external entity substitution,
XInclude, script execution or follow-up resource retrieval. Malformed HTML is
handled by the parser's recovery behavior. Opaque responses (including PDFs)
remain opaque; this stage adds no PDF or binary document reader.

A complete illustrative persisted observation for the exact three bytes `abc`
served as `text/plain` (timestamps and version qualification shown for the example):

```json
{
  "validation": "http-content-v1",
  "content_verified": true,
  "resource": "example.com/page",
  "fingerprint": "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad",
  "observed_at": "2026-10-20T00:00:00+00:00",
  "last_modified": null,
  "version_clock": "server-observed",
  "content_excerpt": "abc",
  "excerpt_truncated": false,
  "title": null,
  "title_source": null,
  "description": "abc",
  "description_source": "normalized_text",
  "description_truncated": false,
  "artifacts": [{
    "path": "artifacts/sha256/ba/78/ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad",
    "hash": "sha256:ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad",
    "media_type": "text/plain",
    "size": 3
  }],
  "reinforcement_version": "accepted-version",
  "reinforcement_status": "not-loaded"
}
```

The Receipt's existing `signature` column remains separate from this metadata.
For this plain-text example the two SHA-256 digests happen to match; HTML
normalization commonly makes them differ. Descriptive metadata never participates
in signature resolution. A meta-description change alone retains evidence identity
when the unchanged normalization algorithm excludes it; changing a `title` can
already change the old fingerprint because `strip_tags` retains its text.

## Publication, failures and durability

Order remains HTTP retrieval → verification → Artifact publication/read-back →
metadata extraction → current execution revalidation under the existing write
lock → immutable Receipt/ledger/creator load/measurement completion.

The dedicated local Laravel disk stages a private object and atomically hard-links
it into its final key. It never renames over or directly writes an existing final
key. S3 uses the Laravel disk's configured AWS client with `If-None-Match: *`;
ordinary Flysystem writes alone do not provide this no-replace guarantee. A 409/412
race is reusable only when the winning object exists and verifies. Unsupported
storage drivers fail closed; there is no fallback to an overwriting write.

Every reuse and new publication is read back with a bounded stream and checked
against exact size and SHA-256. Identical objects are not uploaded again. Different
bytes select different keys. A wrong object under an existing key causes an
execution-level `ArtifactStorageException`; it is neither overwritten nor returned
as a successful reference. Configuration, write and read failures do the same.
Storage exceptions use safe public messages, without provider bodies or credentials.

HTTP/verification failures retain the existing completed, unverified observation
Receipt and creator-load behavior, with no Artifact. Storage failure happens before
execution persistence and creates no Receipt, load, ledger entry or version-clock
change. A stale contribution or database completion rollback can leave an immutable
unreferenced Artifact. This is deliberate: never delete a published object during
rollback because another observation may reference it. Only unpublished temporary
names are removed. Crashes may leave temporary names too. No automatic expiration,
backfill or garbage collection is introduced; any future orphan audit must account
for all Substrates/Receipts and in-flight observation windows.

## Configuration

Run `composer install` to install the locked S3 Flysystem adapter. Existing AWS SDK
and Laravel filesystem abstractions are reused. No schema migration is needed.

Local development defaults:

```dotenv
ARTIFACT_FILESYSTEM_DISK=artifacts
# Optional durable private root, outside public/ and all storage:link targets:
# ARTIFACT_LOCAL_ROOT=/private/shared/commons-artifacts
```

The default root is `storage/app/private-artifacts`, with private object/directory
permissions, serving disabled and no public link. Tests use `Storage::fake` for
this same dedicated disk. The concurrency test uses independent PHP processes
sharing only its disposable private test root.

For multiple application instances, configure **every instance** to use the same
durable backend and namespace. Local container disks do not satisfy this contract.
Either mount the same persistent POSIX volume with atomic hard-link support, or use:

```dotenv
ARTIFACT_FILESYSTEM_DISK=artifacts-s3
ARTIFACT_S3_BUCKET=your-private-shared-bucket
AWS_DEFAULT_REGION=your-region
# Existing server-side AWS credentials or workload role; optional AWS_ENDPOINT
# and AWS_USE_PATH_STYLE_ENDPOINT for a compatible service.
```

Enable bucket public-access blocking and bucket-owner-enforced ownership; grant the
application only the required `GetObject`/`PutObject` access to the Artifact prefix
(and permissions needed to return reliable not-found responses on existence checks).
The application does not send public ACLs, sign URLs or serve stored HTML. A bucket
policy can require conditional writes on the Artifact prefix as additional protection.
S3-compatible endpoints must support conditional create and consistent read-after-write;
there is no unsafe compatibility fallback. Remote operations have a 3-second connect
and 10-second request timeout, with SDK retries disabled. A failed race without a
visible winning object fails safely and can be explicitly retried later.

Keep the disk/root/bucket namespace stable for the lifetime of historical Receipts.
If changing it, copy and verify the entire namespace before switching all instances;
metadata paths are relative to this one configured storage domain. Exclude referenced
Artifacts from lifecycle expiration, deletion and deployment cleanup; back up the
storage alongside the database. Application no-replace behavior cannot protect
against an administrator or external process deleting/altering objects. Live bucket
policy and durability need deployment verification; mocked SDK tests cannot establish
those infrastructure properties. No production storage was accessed for this change.

## Cognitive and lifecycle compatibility

`content_excerpt` and `excerpt_truncated` remain persisted under their old meaning.
For new observations, cognitive projection uses the selected description in that
same bounded `content_excerpt` slot and includes title/source labels. It does not
repeat the description or expose `artifacts`, internal paths, URLs for object
retrieval, backend configuration or full resource bytes. Historical observations
without description/Artifact fields keep their old excerpt presentation.

C2 creator auto-loading and C3 causal catalogue selection are untouched; Read,
Gate, Write and extended associations retain the same qualification and personal
load rules. Readable/replayable capabilities, duplicate suppression, Reinforcement,
version stability, movement, recovery and all three measurements are unchanged.
An Artifact reference grants no reading authority. No `artifact.read`, Receipt Read
action, persistent document loading, section selection or reading history is added.
Those are deferred to Stage 2.

## Performance and validation

The reproducible offline script `php scripts/benchmark-hyperlink-artifacts.php`
uses a unique temporary private root and removes only its own benchmark objects.
It measures 20 samples per size, with no network, database or provider calls.
On local PHP 8.5.8, median milliseconds were:

| Resource bytes | Existing normalization + fingerprint | New Artifact publication + verification | Deduplicated verification | Metadata extraction |
| ---: | ---: | ---: | ---: | ---: |
| 49,152 | 0.583 | 0.786 | 0.399 | 0.246 |
| 524,288 | 6.788 | 4.968 | 4.167 | 1.947 |
| 2,097,152 | 29.251 | 18.516 | 16.869 | 9.364 |

The added work is measurable: approximately 1.0 ms for a new 48 KiB local Artifact
and 27.9 ms at the 2 MiB limit in this run. These are isolated local component
medians, not end-to-end guarantees. S3 normally adds HEAD + PUT + GET for a new
object, or HEAD + GET for reuse; remote latency and production storage cost have
not been measured. Reuse avoids upload but deliberately still verifies content.

Final automated validation results are recorded in the PR and validation report.
