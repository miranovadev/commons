# Evidence Preparation resilience

## Failure observations

An admitted Preparation that fails records its terminal status, timestamp and one
ordinary `failure` Receipt in the same transaction. A rejected start creates no
execution. Failures during reading, delivery, inference and recovery carry the
recorded stage and classification. An offset beyond EOF is discovered after
admission and produces an `invalid_parameters` observation. Expiry before
presentation records the expiry with an empty delivery history. Only acknowledged
deliveries enter the Receipt.

The creator can read the diagnostic. Participants can consume the Receipt under
the ordinary Region and Horizon rules. The Gate-causal cognitive catalogue
keeps its provenance after consumption. Earlier Preparation failures with
`type = preparation` remain readable. See the [acquisition contract and November
2 replay](evidence-failure-resilience.md).

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

## Reading budget decisions

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| Presentation | Does extraction alone count as delivery? | Only acknowledged boundaries are historical deliveries | Handoff §§1,3; Stage 2D acknowledgement | Documented | Before/after-presentation failure tests |
| Mixed allocation | Do explicit preferences lose existing priority? | Proportional explicit allocation first, unchanged automatic water filling second | Handoff §2; Stage 3C allocation invariant | Documented | Mixed, redistribution, UTF-8 and adapter tests |
| Evidence authority | Does failure grant structural admissibility? | Ordinary evidence availability only; all independent guards remain | Handoff §§1,4 | Documented | Lawful Reclassification and unchanged structural snapshots |
