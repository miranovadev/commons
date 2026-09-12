# Operational Commitment

Phase 7 (Order) uses `CommitmentAdmissibilityService` in both schema generation
and execution. Order selects an explicit `proposition_id` from Closure-filtered
Commit Region Write candidates. It requires an effective Attestation in the selected source's
evidentiary state, at least one counted Reinforcement, and a Receipt from a completed instrument execution attached
to that state or one of its effective Attestations. A Committal Gate inherits
this state through Commit edges; arbitrary ancestors and Receipts on superseded
Attestations do not satisfy these preconditions. The grounded Expressive source
remains provenance. Original evidence records are resolved by reference, never
copied or retargeted. The new Committal proposition also inherits the selected source's
Phase-touch history for Cadence, identically in preview and actual Commitment.

These inheritance rules apply
[Invariant 8: Provenance Is Never Erased](core-invariants.md#8-provenance-is-never-erased)
and [Invariant 9: Evidence Must Not Be Invented or Reinterpreted](core-invariants.md#9-evidence-must-not-be-invented-or-reinterpreted).
Changing active participation must preserve original contribution history and
meaning.

## Instruments

`OperationalInstrumentService` records Cite evidence without reinforcement and
verifies Hyperlink resources through the bounded public-HTTP verifier. Qualifying
later verification resolves a canonical resource/version signature and counts at
most once per Participant/Proposition. Originating evidence and a Receipt alone do
not unlock Commit. Failed retrieval and versions awaiting stability remain
non-reinforcing Receipt history. See [Reinforcement](reinforcement.md) for exact URL,
version/freshness, count, measurement ordering and bootstrap rules.

## Observing and recovering blocked Order

An inadmissible Commit retains `commitment_preconditions_unsatisfied` and
`plan.commitment` diagnostics, but the plan stays actionable through any
remaining Order actions. Order does not admit `proposition.traverse`; only
complete exhaustion of all admissible non-Rest actions exposes `participant.rest` as the sole action. Automated participants choose through
the same inference contract as human participants.

Reclassification no longer gathers evidence implicitly. During Bias, normal
`instrument.execute` can ground an existing Concord/Canon Proposition, including
an eligible historical node. A Receipt stays on its exact Proposition across
Cycles; later Attestation or Closure alignment may enable Commitment on a later
revisit. Only Commit provides inherited participation/evidence state; other
Supersessions do not transfer it. Constrain and Order also expose the separate Instrument action. Every valid
Order contribution completes its Cycle, whether Attestation, Instrument or Commit;
further work requires explicit next-Cycle entry. Bias now creates fresh Concord/Canon through unary directional
selection; that new Proposition must earn its own evidence and support. See
[Bias Chirality Gate](participation.md#bias-chirality-gate).

See [participation](participation.md#traverse-tragedy-and-rest) for local traversal,
Tragedy and nearest-distance Rest recovery. Instrument status, failure reason
and Receipt ID remain exposed in the plan and inspector.

## Closure admissibility

The subsequent [Closure Hysteresis milestone](closure-hysteresis.md) adds enforced
Depth/Cadence direction alignment alongside these grounding requirements,
immutable component snapshots, and Domain-transition observation. It requires
an additive database migration; historical evidence and snapshots are not rewritten.

## Canonical late-phase regression

[Operational Closure path](operational-closure-path.md) documents the complete
Disperse → Mediate → Bias → fresh Concord → rebuilt history → Order → Commit
scenario. `OperationalClosurePathTest` uses real measurements and normal
participation throughout. The narrower `OperationalCommitmentTest` continues to
isolate action/evidence behaviour with a Closure mock; it is not the end-to-end
Closure proof.
