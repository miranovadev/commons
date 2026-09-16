# Phase positions

**Register identity does not confer phase identity.** A register classifies a
Proposition. Its producing `Phase.kind` fixes its position in the seven-phase
process. Ownership remains `Proposition → Phase → Cycle → Context Horizon → Domain`.
Another Participant, Cycle, receipt, attestation or measurement cannot promote
that position. A producing Phase cannot be changed once it owns Propositions.

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
history. Operational pools additionally select an explicit producing position:
Mediate reads Constrain, Bias consumes Mediate, and Order consumes Bias. Mediate's
anchor must be the preceding Disperse Gate in the same Cycle, at Constrain;
reconciliation counterparts must be its corresponding Reads' immediate Disperse
children. Direction, evidence, activity and consumption rules still apply within
those pools. Order Read ancestry stops at Mediate-position material.

Writes, instruments, attestations, revisions and expressive Traverse preserve
the current producing position. Revision chains cannot cross repeated register
positions. Order Traverse selects established Order Committals sourced from
Bias-position material in the current region. Generation and progression check
the source and resulting positions, so direct calls cannot bypass schemas.

Commit admissibility separately reports `requirements.phase_position`. Region
membership, grounded evidence, aligned Closure and the unused direct source are
also required. An attractive Constrain Canon or Concord remains ineligible.
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
