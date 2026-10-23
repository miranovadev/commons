# Participant Context C2 implementation

> Stage 3B capability refinement: creator loading applies only to readable Receipts.
> Load File / Download is ordinary and non-loadable; its acquisition metadata and
> Artifact access remain available under the existing causal scope. See
> [the Download contract](load-file-download-stage-3b.md).


Authority: [approved C2 target](participant-context-c2-target.md), subordinate to
[the ten Core Invariants](core-invariants.md). C1 is the current-main baseline
`62a4f065e3333176c26403cc12881c3fe2507998`.

## Readiness decisions

| Boundary | Question | Resolution and authority | Status | Verification |
| --- | --- | --- | --- | --- |
| Cognitive vs operational evidence | Does showing a body activate measurement evidence? | No. C2 §4.1/§7 changes presentation only; operational `active` remains independent. | Documented | Visibility matrix; unchanged state snapshots |
| Foreign comparison operands | Does visibility admit loads? | No. Preserve existing Region/Horizon qualification; extend eligible local Read/Write associations. C2 §4.6/§7. | Documented | Foreign and unrelated IDs rejected |
| Memory outside visible propositions | Should loaded scoped memory disappear? | Preserve existing memory admission and content independently (C2 §3.3/§7). | Documented | Scoped memory tests |
| Failed observations | Does loading imply verified evidence? | Auto-load a legitimate Receipt; present its recorded error; retain null qualification/signature (C2 §4.2/§4.4). | Documented | Failure vs empty-result tests |
| Hidden loaded observations | Can their owner unload? | Yes, with same-substrate/current-Phase validation; no visibility requirement (C2 §4.7). | Documented | Ownership, isolation and rollback tests |
| Rest | Does C2 introduce a standalone memory action? | No; retain existing Rest schema and forward-contribution mechanism. C2 §4.6/§4.8 preserves valid-action timing. | Recovered | Existing Rest rejection test; no new action catalog |
| Loading effects | Should creator loading suppress Reinforcement? | No exceptions: use the existing load event/invalidation/measurement path (C2 §4.5). | Documented | Participant-specific load and Reinforcement tests |

## Presentation

Top-level order: participant, receipts, read, gate, write, actions. Receipt IDs
are sorted and deduplicated; array order for causal history and evidence results
is preserved. Canonical association and Commit inheritance are retained. The
catalogue is scoped to visible proposition references and existing scoped memory.

The provider receives the system message and one cognitive JSON message. The
old evidence and support messages are omitted, eliminating duplicate content.
The existing strict response schema remains. The system's explicit cache
breakpoint and routing-family mechanism remain; the old independent evidence
prefix is removed. Cache hits are not guaranteed by immutable evidence. C2
versions the routing family so old C1 prefixes cannot be mistaken for this layout.

## Loading and evidence

Creator loads occur inside Instrument completion's transaction and use the same
load persistence, ledger event, invalidation and recalculation path as ordinary
loading. Failed observations load with no qualifying signature. No-Receipt
failures create no load. There is no historical backfill.

Read and Gate allow loaded content; Write alone does not. Scoped loaded memory
retains its existing independent visibility. Search/hyperlink content is the
stored canonical observation; failed observations expose their recorded errors
with `content_verified: false`. Missing evidence is omitted. Citation resolution
uses the existing canonical same-substrate object resolver.

Load options use operational local Read/Gate/Write selectors plus the existing
ReceiptRegionService and qualification checks. Foreign comparison visibility
never bypasses Region/Horizon scope. Unload options independently enumerate the
acting Participant's active same-substrate loads, even outside the current view.
A second unload of an already-cleared Receipt is rejected, as C2 explicitly
requires unload IDs to be currently loaded. Already-loaded eligible load IDs
remain idempotent. Rest retains its existing no-options contract.

## Cognitive minimalism

Inference omits observables, aggregate Attestation summaries, Reinforcement
counts, structural position, exploration epoch, and per-Proposition commitment
calculation reports. Operational planning and workspace serialization retain them.
Individual `attestation.direction` and `attestation.rationale`, recorded creator
identity, useful register/tier and causal/recovery relationship labels remain.
All seven C1 questions and the C1 causal projection are unchanged.

## Visibility matrix

| Association | Unloaded | Loaded |
| --- | --- | --- |
| Read | Metadata only | Metadata and inline content |
| Gate | Metadata only | Metadata and inline content |
| Write alone | Metadata only | Metadata only |
| Any combination including Read or Gate | Metadata once | Metadata and content once |
| Existing independently scoped memory | Metadata when admitted | Canonical memory content once |

`loaded_inline_content` is omitted when unavailable, not null. Successful empty
search results remain an empty `results` array; failed observations retain the
recorded error with `content_verified: false`. No file attachment support is added.

## Invariance and compatibility

The C1 register selector, committed ancestry, producing-Phase Reconcile provenance,
all seven questions, canonical authorship, and individual Attestation direction and
rationale remain unchanged. No calculator, movement selector, reconciliation
operation, lifecycle/recovery implementation, database migration or historical
record is changed. The only action-eligibility changes are the approved Receipt
load/unload options. Presentation comparisons use the same stored state and schema
and assert unchanged registers, questions, actions and persistent state.

The existing Reinforcement implementation counts distinct Participants with active
non-memory loads; it does not filter that count by `evidence_signature`. Thus even
a legitimate failed observation can contribute under that existing rule after its
new automatic load. Its signature remains null and its content remains explicitly
unverified. C2 introduces no count exception or mathematical change. Zero-load
invariant tests explicitly clear prospective creator loads; historical migration
fixtures explicitly represent pre-C2 unloaded history and continue proving no
backfill. Existing stored, unloaded Receipts are never automatically loaded.

## Offline prompt comparison

See [machine-readable results](evidence/participant-context-c2/prompt-comparison.json).
The diagnostic loads the C1 presentation classes from main at `62a4f065` alongside
C2 and gives both the same persisted fixtures and operational plan. It makes no
provider call. Prompt bytes sum all message contents; strict response schema bytes
are reported separately. Token estimates use bytes/4, not actual tokenization.
C1's broader supplementary catalogue is counted; changes in scope are distinct
from duplicate entries. Query counts exclude operational plan construction.

| Phase | Prompt bytes C1 → C2 | Estimated tokens C1 → C2 | Receipt metadata bytes C1 → C2 | Evidence bytes C1 → C2 | Projection queries C1 → C2 |
| --- | --- | --- | --- | --- | --- |
| Distinguish | 5,587 → 4,750 | 1,397 → 1,188 | 328 → 2 | 0 → 0 | 6 → 16 |
| Persist | 5,433 → 4,596 | 1,359 → 1,149 | 328 → 2 | 0 → 0 | 1 → 11 |
| Mediate | 19,765 → 15,897 | 4,942 → 3,975 | 1,034 → 328 | 243 → 486 | 1 → 11 |
| Bias | 8,418 → 7,926 | 2,105 → 1,982 | 328 → 110 | 0 → 243 | 8 → 18 |
| Order | 27,207 → 4,357 | 6,802 → 1,090 | 328 → 2 | 0 → 0 | 1 → 10 |

Mediate eliminates four repeated Receipt entries. Its evidence bytes increase
from 243 to 486 because loaded Gate evidence becomes visible; Write-only content
remains absent. Bias similarly gains its loaded causal Read body. The empty
catalogue costs two bytes (`[]`). Order’s large reduction includes removal of
per-Proposition commitment diagnostics. No body was summarized or truncated by C2.
Local projection times were 0.38–2.13 ms for C1 and 2.15–4.11 ms for C2; these
are illustrative, not performance guarantees.

The catalogue is sorted by Receipt ID, Proposition references are sorted unique
IDs, and ranked evidence results retain their original order. C2 adds batched
creator-load, stored-observation and inherited-association queries. There is no
per-Receipt ancestry traversal. Citation resolution may still hydrate the existing
ParticipationContext and resolve canonical cited objects as before.

The former four-message transport becomes two messages: stable system instructions
and the six-component cognitive JSON. The strict response schema and supported
system cache breakpoint remain. The separate evidence-prefix breakpoint is removed:
a changed Participant or Receipt array can reduce reuse of the former evidence
prefix. The cache family is versioned `commons-context-c2`. Stable ordering helps
reproducibility but does not guarantee cache hits; actual cache hit rate and token
usage need production observation. No new cache architecture or paid inference
experiment was introduced. Smaller prompts do not establish cognitive or Emergence
improvement.

## Deferred experiments

Provider-native evidence files, Proposition Search enhancements, individual
Attestation visibility experiments, and behavioral/semantic-convergence studies
remain outside C2. The supplied content, author identity and evidence do not grant
ownership, load, replay, attachment, traversal or commitment permission.

## Validation evidence

Focused SQLite validation: 235 tests, 2,535 assertions, one existing PostgreSQL-only
skip; no failures. Additional Closure/Receipt/Reconcile checks: 48 tests, 417
assertions, all pass. Gate/Write Phase-boundary, migration and ownership coverage:
36 tests, 311 assertions, all pass. The repaired archived epoch transcripts pass
2 tests with 3,974 assertions; the genuine committed-descendant history test passes
1 test with 129 assertions. The offline comparison passes 1 test, 129 assertions.
These groups overlap and must not be summed as a unique-test count.

Frontend: 130 tests pass; production build succeeds. Full current-main baseline
results and exact failing identities are stored in
[baseline validation](evidence/participant-context-c2/baseline-validation.json).
Final-head GitHub Actions outcomes are reported on the PR; baseline failures are
kept separate from new regressions. No Core Invariant or lifecycle expectation was
weakened to obtain a pass. Archived transcript load status is derived only from its
authored Instrument/Participant identities, never actual application responses.
