# Stage 3C validation

Base: merged Stage 3B, `c712615125e59652f641ae15eca17e280ee370cb`.
Validated implementation: `6924c395524d526945aaaf5d2a318d197a368ee0`.
The final report commit changes documentation only; production and test code remain
identical to this validated implementation.

## Behaviour and coverage

Preparation uses one shared character/byte allocation across all unique selected
representations. Explicit ranges reserve capacity; automatic selections use bounded
water filling with deterministic authored-order remainders. Tests cover short-source
redistribution, mixed explicit/automatic ranges, EOF, offset-only selections, Unicode,
per-selection and combined byte limits, and exact requested/delivered history.

PDF text, HTML tables/lists and CSV tables use the existing registry and transient
presentation. Coverage includes malformed/textless PDFs, runtime absence, timeout,
process memory/CPU/write limits, continuously excessive output, private temporary-file
cleanup, inert PDF actions/URIs, CSV quoting/embedded newlines/formula-like data,
HTML structure/entities/malformed markup and unchanged source bytes.

Human and autonomous delivery produce identical boundaries and hashes. Actual
inference requests contain the adaptively allocated material and resolution-only
schema; subsequent ordinary prompts omit source bodies. HTTP coverage acquires a
CSV via Download, consumes its non-loadable Receipt through Reclassification,
prepares two representations with explicit/automatic ranges, then defers without
advancing Disperse. Prepared Variant/Revise, immutable history after later consumption,
independent allowances, and existing rollback/stale-state contracts remain exercised.

## Results

| Check | Result |
| --- | --- |
| [Core / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/40032117861) | **1,462 passed, 1 baseline failure**, 1,463 cases and 15,149 assertions across all five completed shards. |
| [Integration / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/40032123213) | **33 passed, 3 baseline failures**, 9,427 assertions. All 36 discovered cases executed without skips. |
| [Phase Profile / SQLite and PostgreSQL](https://github.com/miranovadev/commons/actions/runs/40032117813) | **All 12 jobs passed**, including canonical committed history and lifecycle contracts. |
| [C3 context](https://github.com/miranovadev/commons/actions/runs/40032123154) | **108 passed**, 2,414 assertions, including the causal-context comparison. |
| [Frontend](https://github.com/miranovadev/commons/actions/runs/40032117861/job/120682646635) | **137 passed**; TypeScript and production build passed. |
| SQLite portability | **14 passed**, 46 assertions, separate from the PostgreSQL Core total. |

Full Core also passes the existing PostgreSQL independent-process Preparation
allowance and competing contributed/deferred completion tests. Receipt consumption,
Artifact storage, Instrument allowances, Phase-neutral refresh and Reinforcement
contracts remain covered.

Local evidence (overlapping selections; do not add these counts to full CI):

- Focused acquisition, reader, allocation and lifecycle run: **125 passed, 919 assertions**.
- Additional input/history assertions and documented-record validation: **61 passed,
  427 assertions** (overlaps the focused run).
- Final bounded PDF process and adaptive lifecycle checks: **17 passed, 77 assertions**
  (overlaps the focused run and includes the new runaway-output case).
- Corrected JSON-history assertions: **4 passed, 34 assertions** locally and the
  complete adaptive journey class passes in PostgreSQL Core.
- Pint and whitespace checks pass.

## Cause-level baseline comparison

Compared with [merged Stage 3B validation](../load-file-download/validation.md),
including original CI logs, not just matching test names. Retained baseline failures
are the natural boundary expected `settlement` versus observed `emergence`, and the
two aligned-policy FreshBootstrap histories that exhaust their driver budget without
an admissible Commit. Both complete 107-action traces and final Bias diagnostics are identical to Stage 3B,
excluding generated Gate IDs only. Each trace includes six Preparation/contributed
Variant pairs and ends at Cycle 25 / Constrain Reclassification. Bias reports
`component_directions_disagree`: positive orientation, negative Attestation cadence,
positive Chirality. The comparison uses original Stage 3B job 120646362172 and final
Stage 3C job 120682666817.

The natural-boundary assertion remains at `tests/Fixtures/NaturalBoundaryHistory.php:63`:
expected `settlement`, observed `emergence`. Integration job 120682667046 matches
Stage 3B job 120646362697. Core job 120682948714 matches Stage 3B job 120646272509 at the same unchanged assertion.

After the JSON-object assertion correction described below, no new failure or
changed failure cause remains. No independent mathematical or causal assertion was weakened; no baseline failure
was suppressed or lifecycle forced to Commit.

## Consequential corrections and limits

- Every supported textual Read Type now accepts automatic allocation and optional
  representation-relative ranges. The old whole-resource raw check now distinguishes
  an exact raw selection from a complete Artifact; both retain original byte identity.
- Trusted presentation validates actual ranges, representation hashes and all source
  Receipt associations. Invalid ranges report the offending selection, even when it
  is not last in the submitted list.
- Schemas, human range controls, live presentation and historical metadata follow the
  same server policy. Obsolete fixed-length fixtures were reconstructed while keeping
  structural and mathematical assertions.
- PDF runtime probing happens before presentation write transactions. Extraction uses
  bounded fixed-size pipe reads, fixed argv and private temporary storage; no shell or
  document-directed command, URL fetch or output path is introduced.
- PostgreSQL JSON object key ordering exposed one new test-portability failure.
  The history assertion now canonicalises object keys only; list order, scalar types,
  source associations, boundaries and hashes remain exact. No production behaviour
  or independent assertion was changed to address it.
- No database migration or production/shared-record modification was needed. No new
  catalogue, allowance, structural operation or measurement was introduced.

Deployment needs Poppler `pdftotext` and util-linux `prlimit` for PDF capability.
OCR, images, archives, provider attachments, regex, JSON-path and specialised source
extraction remain unsupported. Full representations must fit processing limits even
when the requested delivered range is small. Details, configuration and the complete
Receipt example are in the [Stage 3C contract](../../artifact-readers-stage-3c.md).
