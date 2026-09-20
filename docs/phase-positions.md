# Phase positions

**Register identity does not confer phase identity.** A register classifies a
Proposition. Its producing `Phase.kind` fixes its position in the seven-phase
process. Ownership remains `Proposition → Phase → Cycle → Context Horizon → Domain`.
Another Participant, Cycle, receipt, attestation or measurement cannot promote
that position. A producing Phase cannot be changed once it owns Propositions.

**Phase-position compatibility and Commit Region membership do not confer
traversal eligibility.** Traversal additionally requires the appropriate causal
relationship to the Participant's current Gate. Forward movement follows that
causal continuation; recovery follows recorded ancestry backward. A matching
register, Horizon or visible card supplies neither relationship. **No edge means
no movement.**

| Producing phase | Emergence Write | Settlement Write | Operational role |
| --- | --- | --- | --- |
| 1 Distinguish | Activity | Signal | Persist Gate |
| 2 Persist | Frontier | Stance | Constrain Gate |
| 3 Constrain | Canon | Concord | Disperse Gate and Mediate Read |
| 4 Disperse | Activity | Signal | Mediate Gate |
| 5 Mediate | Frontier | Stance | Bias Gate and Order Read |
| 6 Bias | Canon | Concord | Order Closure candidate |
| 7 Order | Committal Canon | Committal Concord | Terminal Write and next Cycle boundary |

`RegisterPosition` names the three vocabulary slots. It is deliberately not a
phase-position enum. `PhasePositionService` uses the existing `PhaseKind`, exact
creating Horizon, tier and Domain register. No persisted late-phase flag or data
migration is required. Its SQL scope and hydrated predicate have the same matrix.

`CommitRegionService::membersFor` retains early material as contextual and causal
history. Commit Region membership defines calculation/context scope only where
an operation explicitly requires it; it never creates a traversal edge. The
Homeostatic populations select an explicit producing position:
Mediate reads Constrain, Bias consumes Mediate, and Order consumes Bias. Mediate's
anchor must be the preceding Disperse Gate in the same Cycle, at Constrain;
reconciliation counterparts must be its corresponding Reads' immediate Disperse
children. Direction, evidence, activity and consumption rules still apply within
those pools. Order Read ancestry stops at Mediate-position material.

Mediate — Read and Bias — Gate may calculate across regional lineages. Mediate's
compatible foreign Gates are reconciliation operands only; Reconcile creates
both incoming causal edges. Bias exposes only the current causal Gate, never the
other Gates in its calculation result. Closure, performed during Order — Write,
evaluates each causally local Bias-produced Write independently using its own
lineage and participation history. It does not enumerate regional Writes.
Local expressive Writes remain visible, with their ordinary expressive actions;
Commit requires that specific Write's full prerequisites. Only its own direct
Committal child is a Traverse target.

Writes, instruments, attestations, revisions and expressive Traverse preserve
the current producing position. Revision chains cannot cross repeated register
positions. Bias Traverse selects only the causal Write continuation of the
current Gate, including its frozen successful classification when otherwise
eligible. Order Traverse selects only the established Committal produced directly
from the current Bias classification Gate. Another Bias-position source or
Committal in the same region is not a traversal target. Generation and progression check
the source and resulting positions, so direct calls cannot bypass schemas.

Commit admissibility separately reports `requirements.phase_position` and
`requirements.causal_write`. Region
membership, grounded evidence, aligned Closure and the unused direct source are
also required. An attractive Constrain Canon or Concord remains ineligible.
Even a matching Bias position does not supply a missing causal movement edge.
Calculation-local region pools are cached; direct admissibility checks inspect
the selected source and its ancestry without rescanning the whole region.

Workspace register browsing is contextual. Its broad register grouping grants no
actions: `write_options`, `read_options`, `gate_options` and specialized schemas
come from the structural selectors. Measurement services that classify or replay
historical registers do not grant operational eligibility and retain their scope.

## Historical inspection

Existing propositions, commits and ledger records are preserved. The correction
applies to future operations; it does not certify or rewrite earlier commits.
Run the following read-only diagnostic against a chosen database:

```sh
php artisan commons:validate-phase-positions --substrate=SUBSTRATE_ID
```

Omit `--substrate` to scan all substrates. JSON lines identify register/phase
mismatches and invalid operational edges, including early Commit sources. A
nonzero exit status means violations were found. The command performs no repair,
projection rebuild, participant relocation, deletion or ledger mutation. It
checks structural position and edge type; it does not replay historical numeric
Closure, evidence availability or temporal consumption conditions.

## Regression coverage

`PhasePositionInvariantTest` covers both Domains, all seven positions and all
three register slots, including SQL/hydrated parity. Identically labelled early
and late propositions coexist in one region across Participants and Cycles;
only the correct position reaches schemas, options and direct dispatch. The
complete collaborative path remains covered by `FullEvidenceFlowTest` and
`OperationalClosurePathTest`. Semantic fixtures specify their producing position
explicitly rather than substituting a matching register label.

