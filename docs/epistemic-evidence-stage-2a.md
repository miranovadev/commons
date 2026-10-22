# Epistemic Evidence Stage 2A

Approved scope: phase-neutral Instrumentation only. Instrument executes in Persist
against the exact current Gate, at most once per Participant Cycle. It produces
its ordinary immutable execution, Receipt, ledger history and C2 creator load.
The Receipt starts unattached; its originating Gate is not a consumption.
Reclassification remains responsible for attachment and consumption.

A completed observation consumes the allowance even if retrieval or verification
failed. Infrastructure failure without a completed execution does not. Count
completed originating executions through their immutable Phase/Cycle ownership,
not current Receipt association, loading, consumption or evidence equivalence.
Other Participants have independent allowances. Duplicate-invocation suppression
and replay provenance remain independent constraints.

Observation and remote Artifact storage happen before the contribution write
transaction. The existing Substrate → Participant → Cycle → Phase lock order and
field-version check remain. The dispatcher checks the exact Gate and unused
allowance again under the Substrate lock. A stale prepared request conflicts
before execution, Receipt, ledger or loading writes. Instrument dispatch skips
PhaseProgressionService; the ordinary state builder then constructs fresh
cognition for the same Phase, Cycle, Gate and position. The autonomous wake loop
already re-enters cognition until the chosen Phase/Cycle completion boundary.

No formula, structural generation gate, Attestation rule, Reclassification
requirement, Tragedy authority, recovery or canonical Commit rule changes.
Instrumentation remains subject to existing active/mutable Gate eligibility.
Origin bootstrap is unchanged and requires no Instrument.

## Readiness register

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| Target | Gate or Write? | Exact Persist Gate; retain proposition_id with singleton enum | Approved Stage 2A handoff §3 | Documented | Invalid target and Gate tests |
| Allowance | Receipt count or execution? | Completed executions in acting Cycle; consumption/loading irrelevant | Handoff §2 | Documented | Cycle, actor and consumed Receipt tests |
| Failure | Does failure spend allowance? | Completed failed observation does; aborted infrastructure does not | Handoff §2, §7 | Documented | Verification/storage failure tests |
| Race | Same Phase still active? | Recheck allowance under existing root lock before dispatch writes | Handoff §7 | Documented | Two pre-observed requests |
| Refresh | Must structural position advance? | Rebuild state at same Persist; later ordinary action advances | Handoff §5 | Documented | Neutrality and subsequent birth tests |
| Origin | New grounding? | None; existing constitutional bootstrap | Handoff §2 | Documented | Existing Origin suite |
| Measurements | Receipt as trigger? | No new structural observations or changed values | Handoff §8; Core Invariants 1, 4, 9 | Documented | Structural/measurement snapshot |

## Future architecture (not implemented)

Stage 2B will introduce Receipt-governed Variant/Revise eligibility in Persist and
Disperse. Stage 2C will provisionally generalise executions as EvidenceExecution,
with `instrument` and `prepare` kinds producing immutable Receipts. Stage 2A does
not rename instrument_executions or introduce Preparation.

Future Preparation is Disperse-only, once per Participant Cycle and phase-neutral.
It selects multiple Artifacts from eligible catalogued Receipts, with a required
purpose of at most 240 characters. Prepared cognitive material is followed by
Variant/Revise or explicit Deferment (reason at most 400 characters). Its completed,
initially unattached Receipt records the resulting Proposition or deferment reason,
participates in the shared catalogue, and may later attach through Reclassification.
Stage 2D supplies readers, cognitive presentation, provider delivery and history.

## Fixture changes

PreOrderInstrumentPath previously offered Instruments outside Persist;
PersistInstrumentTest required a Write target and immediate Constrain advancement.
Those expectations are superseded, while authoritative rejection, ordinary
Reclassification consumption and subsequent structural progression remain tested.
The Artifact contribution fixture now supplies a lawful Persist Gate; its byte,
transaction-boundary, loading and cognitive assertions are retained.
