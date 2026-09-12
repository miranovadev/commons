# Operational Commitment

Phase 7 (Order) uses `CommitmentAdmissibilityService` in both schema generation
and execution. Order selects an explicit `proposition_id` from Closure-filtered
Commit Region Write candidates. It requires an effective Attestation in the selected source's
evidentiary state and a Receipt from a completed instrument execution attached
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

`OperationalInstrumentService` runs the supported reference instruments
synchronously and atomically through `InstrumentExecutionService::start/complete`:

- `citation`: the bare ID of an existing Proposition or Attestation in the same
  substrate. Records the resolved target identity, not a claim that it is true.
- `hyperlink`: a trimmed HTTP(S) URL without embedded credentials. Records URL
  syntax validation only. It does **not** fetch the URL, verify reachability, or
  verify page contents. This avoids server-side URL fetching and SSRF exposure.

Both issue unsigned Receipts, satisfying grounding without additional signed
Reinforcement. Artefacts remain unsupported until their storage/verification
path is implemented. Origin uses the same execution service and exposes only
Hyperlink in the API/UI because no prior Commons citation target exists.

Low-level pending/complete/fail remains available for future asynchronous
instruments. Existing pending executions are not retroactively completed by this
change. Invalid operational input is rejected without persisting a partial
contribution or advancing the Phase.

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
