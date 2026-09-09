# Operational Commitment

Phase 7 (Order) uses `CommitmentAdmissibilityService` in both schema generation
and execution. It requires an effective Attestation directly on the current Gate
and a Receipt from a completed instrument execution attached to that Gate or
one of its effective Attestations. Ancestor evidence and Receipts on superseded
Attestations do not satisfy these preconditions. The Commit edge retains the
grounded expressive source as provenance; its evidence is not copied to the new
Committal proposition.

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

An ungrounded Order returns `status: blocked`, reason code
`commitment_preconditions_unsatisfied`, and no navigation. `plan.commitment`
contains `admissible`, individual requirement flags, and machine-readable
reasons. Each fresh read reevaluates eligibility. Explicit traversal requests
at Order are rejected too; an empty Commit schema is not expressive exhaustion.

Missing grounding must be supplied through existing eligible phases, potentially
by another participant. There are no new Attestation/instrument actions in
Order and no automatic transfer of evidence across a reconciliation. In
particular a newly reconciled Phase 6 Gate needs its own Attestation and Receipt;
selecting or instrumenting an already-grounded local candidate is a direct path
to Commitment. The operational regression test exercises a complete seven-phase
cycle with other participants preparing and attesting local candidates.

Proposition payloads in the participation plan include instrument execution
status, failure reason, and receipt ID. The UI displays blocked requirements and
the inspector distinguishes absent, pending, failed, and completed instruments.
Automated participants skip inference/traversal while blocked and recheck on the
next wake using the existing scheduling behaviour.

## Deferred: Closure admissibility

`requirements.closure` is null and `closure_enforced` is false. Depth/Cadence
direction alignment and their separate hysteretic state are deliberately not
implemented in this milestone. Existing observable calculation, immutable
Commitment snapshots, Tension-based Domain changes, and Context Horizon
transitions still execute on a successful Commit. Observations before the next
milestone therefore reflect an operational prototype, not the final Closure gate.

No database migration or historical evidence rewrite is required.
