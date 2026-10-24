# Evidence Preparation resilience

Claire's Evidence Resilience handoff supersedes the earlier rules that failed
Preparations never create Receipts and explicit lengths must fit exactly. The
[Core Invariants](core-invariants.md), Region/Horizon selectors, structural
admissibility and separate Instrument/Preparation allowances remain authoritative.

## Failure observations

An authorized Preparation start reserves its existing once-per-Cycle allowance.
A genuine reading, delivery or inference failure records `status = failed`,
`outcome = failed`, `failed_at` and an ordinary initially unattached Preparation
Receipt in one transaction. `completed_at` remains null. The Receipt has no
reinforcing signature and `content_verified = false`: failure metadata says
nothing about whether source content supports or refutes a Proposition.

The Receipt records execution, Gate, Participant, purpose, immutable requested
selections, bounded stage/classification/reason and known reader identity. Only
previously acknowledged deliveries appear in `deliveries`; extraction alone does
not prove delivery. A failure before acknowledgement records an empty list. No
Artifact text is persisted or invented. Trusted adapters supply diagnostics; no
client action or route accepts a failure report. Inference errors use a fixed
safe summary rather than copying provider responses into authoritative history.

Malformed selections, unsupported requested Read Types, foreign ownership and
stale reads are rejected. An offset discovered beyond EOF terminates the execution
as `invalid_parameters` without a Receipt. Existing stale/expired recovery also
creates no Receipt. A reader that disappears *after* a valid start is instead a
genuine `missing_capability` failure. These cases do not grant a second allowance.

Failure persistence retains the Substrate → Participant → Cycle → Phase → execution
lock order. Terminal retries return without mutation; unique execution/Receipt and
Preparation/Cycle indexes remain additional race barriers. Persistence or ledger
failure rolls back both outcome and Receipt. No live database is modified by this
implementation; validation uses fresh isolated databases.

Ordinary observation discovery accepts completed executions or precisely a failed
Preparation with a recorded failed outcome. Instrument completion, replay and
allowance predicates are unchanged. Failure Receipts retain the ordinary causal
Region/Horizon filters, consumption and C3 association rules. Loading presents
only failure provenance, with no evidence signature or measurement recalculation.
Receipt creation refreshes evidence availability without marking measurements
dirty. Variant, Revise and Reclassification must still pass their own ordinary
structural and ownership checks; Tragedy authority is unchanged.

## Bounded reading preferences

`max_chars` is an optional positive integer preference. It is retained verbatim
in immutable selections and request identity even when larger than a delivery
ceiling. All existing source, extraction, character, byte and selection ceilings
are unchanged. Explicit offsets still address the resolved representation and
are never moved to make a request fit.

Explicit requests retain priority over automatic requests. Their shared capacity
is allocated proportionally to requested maxima using deterministic weighted fair
allocation: the next complete character goes to the selection with the smallest
`(delivered characters + 1) / requested maximum`; ties follow submitted order.
Finished or per-selection-capped requests leave the pool immediately. This
redistributes unused capacity without increasing a limit. Byte ceilings remain
authoritative; a next UTF-8 character must fit both byte limits. No character is
split or skipped. Automatic requests then use the unchanged equal water filling
algorithm and submitted-order remainders.

For example, explicit maxima 20,000 and 10,000 share 9,000 available characters as
6,000 and 3,000. If the first source contains only 500 characters, it receives 500
and the second can receive the remaining 8,500. Each delivery preserves requested
offset/maximum alongside actual offset/end/length/bytes/total/has_more and reader
provenance. All selections remain represented, including truthful zero-length
allocations when EOF or byte exhaustion leaves nothing deliverable. Human and
autonomous adapters use the same allocator, schema and acknowledgement validation.

## Resolved ambiguity register

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| Failed execution vs observation | Must failure become successful completion? | Failed status and failed Receipt outcome coexist; no signature | Handoff §1 | Documented | Storage, extraction, inference and model tests |
| Rejected request | Can invalid input manufacture evidence? | No Receipt for malformed, unauthorized, stale or invalid-offset requests | Handoff safeguards | Documented | Rejection and stale-read tests |
| Recovery cancellation | Does expiration prove an attempted read? | Preserve recovery cancellation without Receipt | Handoff §§1,4; Stage 2D recovery | Documented | Existing expiration test |
| Presentation | Does extraction alone count as delivery? | Only acknowledged boundaries are historical deliveries | Handoff §§1,3; Stage 2D acknowledgement | Documented | Before/after-presentation failure tests |
| Mixed allocation | Do explicit preferences lose existing priority? | Proportional explicit allocation first, unchanged automatic water filling second | Handoff §2; Stage 3C allocation invariant | Documented | Mixed, redistribution, UTF-8 and adapter tests |
| Evidence authority | Does failure grant structural admissibility? | Ordinary evidence availability only; all independent guards remain | Handoff §§1,4 | Documented | Lawful Reclassification and unchanged structural snapshots |

No new measurement, exceptional warrant, execution type or allowance is introduced.
The live cook diagnosis is supplied context, not a database finding made here.
