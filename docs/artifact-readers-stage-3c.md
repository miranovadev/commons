# Expanded Artifact readers and adaptive Preparation allocation

Stage 3C extends the [Stage 2D Preparation](epistemic-evidence-stage-2d.md) reading
contract. Execution ownership, allowances, causal access, contributed/deferred
resolution, ordinary Receipts and structural measurements are unchanged.

## Before and after

Previously `text` required `max_chars`; raw and sanitised reads requested an entire
representation and failed above their byte limit. Each selection was read separately.
Now every supported textual representation accepts optional `offset` and `max_chars`.
Omitting the maximum requests automatic allocation from one shared Preparation
budget. This includes raw: the selected original UTF-8 bytes are unchanged, while
the recorded boundaries explicitly distinguish a selection from the whole source.
Explicit selections that cannot fit are rejected, never silently shortened to fit.

The existing Source Type × Read Type → Representation → Delivery Method model
remains authoritative. Acquisition through Hyperlink or Download does not choose a
reader. Loading and consumption do not control Artifact access: a selected Receipt
must remain within `accessibleOrdinary()` causal Region/Horizon scope, and must
actually reference the selected hash. There is no substrate-wide hash lookup.

## Implemented capabilities

| Source Type | Read Types | Reader and version |
| --- | --- | --- |
| HTML | raw, sanitised, text, tables, lists | commons-native-text / 2 |
| Plain text, PHP/common UTF-8 source, Markdown, JSON, XML | raw, text | commons-native-text / 2 |
| CSV | raw, text, tables | commons-native-text / 2 |
| PDF | text | commons-poppler-text / 1/poppler-`runtime version` |

Both `inline-text` and `human-text` use identical allocation. A capability is only
advertised when its reader is available. PDF requires the local executable and its
resource limiter; it is omitted when unavailable. Format recognition uses preserved
bytes and controlled MIME hints, never acquisition type or filename. A PDF header
is recognised before UTF-8 validation; actual extraction validates the document.
CSV uses `text/csv` or `application/csv` after textual validation. Automatic delimiter
sniffing, semicolon/TSV dialects, OCR, images, archives, provider attachments, regex,
JSON-path and source-code variable extraction are not implemented.

- **raw** selects unchanged UTF-8 source characters, preserving original line endings.
- **text** selects original textual source for existing text formats; PDF text is
  Poppler's extracted reading-order representation. HTML text still includes markup.
- **sanitised** extracts readable HTML, omitting executable/irrelevant elements.
- **HTML tables** emit numbered tables and one JSON cell-array per row. Each cell
  retains text, header status, row span and column span (bounded at 1,000). Empty
  rows/cells survive; nested tables are separate, without duplicating inner rows.
- **HTML lists** retain item order, ordered-list numbers and indentation for nested
  lists. Nesting beyond 32 fails explicitly. Malformed HTML uses the deterministic
  DOM parser's recoverable structure, without pretending to reconstruct lost markup.
- **CSV tables** emit one JSON string-array per CSV record. Commas, quoted fields,
  doubled quotes, embedded newlines, empty cells and UTF-8 values retain field
  boundaries. Formula-like values remain strings; nothing evaluates them.

HTML parsing uses `LIBXML_NONET`, never fetches linked resources or executes script.
XML DTD/entity declarations remain unsupported. Representation bodies stay transient.

## Allocation and budgets

Defaults in `config/preparation.php`:

| Limit | Default | Environment override |
| --- | --- | --- |
| Combined character budget | 12,000 Unicode characters | `PREPARATION_TEXT_BUDGET` |
| Maximum characters per selection | 12,000 | `PREPARATION_MAX_CHARS` |
| Per-selection delivery bytes | 16 KiB | `PREPARATION_SELECTION_BYTES` |
| Combined delivery bytes | 48 KiB | `PREPARATION_DELIVERY_BYTES` |
| Source inspection | 8 MiB | `PREPARATION_SOURCE_BYTES` |
| Intermediate representation | 16 MiB | configuration only |
| PDF extracted text | 4 MiB | configuration only |

The existing 1 MiB domain ceiling additionally caps delivery, regardless of configured
limits. It is not the cognitive target. A Download can preserve a larger resource
than today's reader can inspect. Acquisition remains successful even if reading is
unsupported or exceeds processing limits.

For each unique request, construct its representation once, then measure Unicode
characters after its requested offset. Reserve explicit selections first, using
`min(max_chars, available characters)`. Reject explicit ranges that exceed per-selection
or combined character/byte budgets. EOF is the only reason an explicit maximum can
produce fewer characters. Its unused capacity becomes available to automatic requests.

Water filling gives one Unicode character per active automatic selection in submitted
order. Finished, per-selection-capped or byte-constrained selections leave capacity
for others. Repeat until all content fits or the character/byte budget is exhausted.
This is equivalent to equal sharing with redistribution; unavoidable remainder
characters follow authored order. The loop is bounded by the configured character
budget and at most eight associations. It never splits a UTF-8 character, skips source
characters to fit, extends an explicit range, or depends on database query ordering.
A remaining byte that cannot fit any next character can remain unused.

Identical hash/Read Type/parameters share one delivery and one budget allocation.
Omitted offset and explicit zero are equivalent. All source Receipt associations
survive in `receipt_ids` and original `selections`. Different Read Types, offsets or
explicit maximums remain distinct requests, even if their delivered bytes happen to
coincide. The same source representation is reused for different ranges.

Offsets are zero-based Unicode character positions **in the resolved representation**;
`end` is exclusive. An offset equal to total length produces an empty selection.
An offset beyond it fails. An automatic allocation can deliver zero characters if
explicit selections exhaust the budget; its history records zero and `has_more`
truthfully rather than claiming the whole source was supplied.

## Human and autonomous interaction

The workspace defaults to automatic allocation. Every supported Read Type has an
optional offset and maximum; clearing the maximum restores automatic allocation.
Changing Read Type resets ranges because offsets are representation-relative.
The presentation shows actual start/end/total, delivered characters and bytes,
requested maximum or automatic allocation, and remaining-content indication.

The generated action schema accepts omitted parameters or offset-only requests:

```json
{
  "action": "preparation.start",
  "proposition_id": "CURRENT_GATE_ULID",
  "purpose": "Compare the table with the paper's later discussion.",
  "artifacts": [
    {"receipt_id": "CSV_RECEIPT_ULID", "artifact_id": "sha256:CSV_HASH", "read_type": "tables"},
    {"receipt_id": "PDF_RECEIPT_ULID", "artifact_id": "sha256:PDF_HASH", "read_type": "text",
     "parameters": {"offset": 4000}}
  ]
}
```

Autonomous re-inference receives the existing untrusted-source envelope, exact purpose,
provenance, reader version, boundaries and bounded content. It resolves the same
execution through lawful Variant/Revise or explicit Defer. Human delivery uses that
same domain path. Acknowledgement records actual supplied material, not extraction
alone and not inferred comprehension. The additional inference does not advance a
Phase or Cycle. Source bodies are absent from later ordinary prompts and Receipts.

## Complete allocation example

[The illustrative JSON record](evidence/artifact-readers-stage-3c/example.json) gives
submitted selections and a completed deferred Receipt. Its three immutable sources
contain respectively 500 `A`, 3,000 `B` and 15,000 `C` characters. The first requests
an explicit maximum of 2,000; the other two are automatic. With a 12,000-character
budget, the actual lengths are **500, 3,000 and 8,500**. The unused explicit allowance
and the short second representation release capacity to the third.

Each delivery records original requested parameters, actual offset/end/length/total,
bytes, `has_more`, source Receipt associations, representation hash, payload hash,
method/version and delivery method. The example contains hashes, not source bodies.
A contributed outcome instead records the actual resulting Proposition ID. Both
outcomes create ordinary initially unattached Receipts. Later Reclassification can
consume them without rewriting their reading history or outcome provenance.
Existing completed records remain unchanged when reader versions change.

## PDF runtime and failure contract

Install **Poppler `pdftotext` (`poppler-utils`) and util-linux `prlimit`** on Linux
application/worker hosts. CI installs the same dependencies. Defaults are
`/usr/bin/pdftotext` and `/usr/bin/prlimit`; trusted deployment configuration can
change `PREPARATION_PDF_BINARY` and `PREPARATION_PDF_LIMITER`. No Composer PDF parser,
external document service, OCR engine or provider-specific API is introduced.

The adapter passes fixed argv directly through Symfony Process, without a shell.
It writes only a random private temporary directory (0700, input 0600), uses the
local input path, UTF-8/Unix output and no page-break control characters, and removes
temporary input in `finally`. It does not execute PDF actions, JavaScript, remote
URIs or attachments. The extractor has no document-directed command or URL argument.
Keep Poppler patched and apply normal worker-host network/filesystem isolation.

Processing is limited to 10 seconds wall/CPU time, 256 MiB address space, no writable
output files/core dumps, 32 open descriptors, 4 MiB captured text and 16 KiB diagnostics.
Hard adapter bounds cap configured time at 30 seconds and memory at 512 MiB.
Output is drained incrementally and the process is stopped on timeout/output failure.
PDF bytes are never rewritten; actual Poppler version is included in history because
extraction results can differ between releases. See the upstream packaged
[pdftotext manual](https://manpages.debian.org/unstable/poppler-utils/pdftotext.1.en.html)
for reading-order and encoding options.

Failures distinguish missing capability, invalid source/parameters, representation
processing limit, delivery budget, timeout, failed reader/resource limit, and no
extractable text. Image-only PDFs produce `no_extractable_text`, not an empty successful
reading or a failed acquisition. The existing failed EvidenceExecution records
Artifact, Read Type, identified Source Type, attempted method/version and concise
reason. It creates **no successful Preparation Receipt**, contributed outcome or
inferred deferment. Existing allowance, retry, stale-state and failure recovery
semantics remain in force.

## Ownership and remaining coupling

`ArtifactReaderRegistry` owns capabilities and representation construction;
`PdfTextReader` isolates the bounded subprocess. `PreparationAllocation` owns shared
Unicode/byte allocation. `PreparationService` still owns lawful selection, request
identity, transient delivery and resolution; `PreparationPresentation` checks trusted
acknowledgements; `EvidenceExecutionService` remains the sole persistence/Receipt owner.
Existing plans, schemas, inference and workspace paths consume those same contracts.
No database migration, new execution type, catalogue or structural action is needed.

Capability discovery still reads/verifies preserved bytes and caches source metadata;
actual representations are only request-local. Historical UI supports older bounded
metadata without rewriting it. A future source-inspection cache consolidation or
reader adapter split may be useful as formats grow; neither is required by this stage.
