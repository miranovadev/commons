# Participant Context C2 implementation

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
