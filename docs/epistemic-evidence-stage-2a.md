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

## Unresolved lifecycle blocker

Stage 2A is **not ready to merge**. `PropositionOptionService::attestationOptionsFor`
requires both a shared ordinary Receipt and an exact `instrumentedIds` match for
Distinguish/Disperse Writes. `HorizonReceiptService::instrumentedIds` matches the
completed execution's originating Proposition, not shared catalogue availability.
A new Disperse Write cannot acquire that observation through Persist-only,
Gate-targeted Instrumentation: the Persist Gate is Distinguish-produced. On fresh
populations, Disperse Attestation therefore has an unreachable prerequisite.

`EpistemicInstrumentationBoundaryTest` isolates this with available shared Persist
evidence and a valid Disperse Write: Attestation is absent and Instrumentation is
absent. This is a diagnostic assertion of the conflict, not approval of the stall.
`HorizonProofCycleTest` also stalls at Disperse after updating its motor to observe
first in Persist. No mathematical formula or Attestation rule was weakened to make
that fixture pass.

The smallest missing decision is the evidence prerequisite for **Disperse
Attestation** under Stage 2A. Keeping exact-Write Instrumentation, making
Instrumentation Persist-only, and preserving this Attestation rule are not jointly
compatible with the existing fresh-population lifecycle. The handoff explicitly
preserves Attestation rules and reserves new Receipt-governed generation for 2B;
this implementation does not guess which rule should supersede which.

## Readiness register

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| Target | Gate or Write? | Exact Persist Gate; retain proposition_id with singleton enum | Approved Stage 2A handoff §3 | Documented | Invalid target and Gate tests |
| Allowance | Receipt count or execution? | Completed executions in acting Cycle; consumption/loading irrelevant | Handoff §2 | Documented | Cycle, actor and consumed Receipt tests |
| Failure | Does failure spend allowance? | Completed failed observation does; aborted infrastructure does not | Handoff §2, §7 | Documented | Verification/storage failure tests |
| Race | Same Phase still active? | Recheck allowance under existing root lock before dispatch writes | Handoff §7 | Documented | Two pre-observed requests |
| Refresh | Must structural position advance? | Rebuild state at same Persist; later ordinary action advances | Handoff §5 | Documented | Neutrality and subsequent birth tests |
| Origin | New grounding? | None; existing constitutional bootstrap | Handoff §2 | Documented | Existing Origin suite |
| Disperse Attestation | How can a fresh Disperse Write receive its required exact Instrument observation? | Requires design decision; no production change to Attestation | Handoff §2/§3 versus §6; current exact-Write rule | **Unresolved** | Boundary diagnostic and HorizonProofCycle trace |
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

The replay/relevance fixtures now place prior observations in historical Phases,
so current-Cycle allowance and duplicate suppression are tested independently.
Web Search expects same-Phase refresh. C3's terminal diagnostic uses an actual
Persist Gate Instrument encounter, retaining its evidence/projection invariants.
The structural-history fixture uses a second Participant's lawful Attestation
before Persist development, replacing its obsolete Distinguish Instrument call.

## Validation checkpoint

- Focused local PHP run: 113 tests, 931 assertions, zero failures/errors; two
  PostgreSQL-specific checks skipped on SQLite (including the independent-connection
  allowance race). The race is included in Core for PostgreSQL CI.
- TypeScript/build passed; full UI suite: 130/130 passed.
- Existing main Core run 39392027019: four shards passed; shard 3 failed only
  ChargeBoundaryTragedyTest's ordinary unmocked observed-tragedy expectation.
- Stage 1 parent PR head a7ada7e Integration run 39332312153: 11 failures across
  four shards (baseline, not introduced by Stage 2A).
- Stage 2A Core/Integration were launched through PR #267. No full green result
  is claimed. Early Integration failures include the newly unreachable Disperse
  path described above; old scripted populations also encode obsolete actions.
- Local full Core runs on both main and Stage 2A were started for comparison,
  but are not the authoritative PostgreSQL CI result (the local runtime lacks
  some CI dependencies and uses SQLite). They are not claimed as passing.
- The focused HorizonProofCycle diagnostic still fails after selecting the lawful
  Persist Instrument before structural advancement: its trace reaches Disperse
  and cannot obtain the exact-Write observation demanded by Attestation.

Do not merge. The unresolved semantic boundary must be decided before completing
lifecycle fixture updates or declaring Stage 2A ready. No shared/production database
was modified, no historical data migration was introduced, and no Stage 2B–2D
behaviour was implemented.
