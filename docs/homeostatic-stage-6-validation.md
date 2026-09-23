# Homeostatic Stage 6 validation

Stage 6 validates the completed Phase 5–7 integration. It changes no runtime
service, schema, measurement formula, authority or action semantics. Stage 5's
merged baseline is `e281cddd10ac9fe8b22fb0ea689b4a934d863672`.

## Constitutional boundaries

Proposition measurements remain independently lineage-derived. Structural
homology compares the same substrate, proposition Domain, canonical Phase/register,
tier and full committed depth from a validated shared Origin. Regions and Horizons
may differ. Population comparison neither writes measurements nor moves a participant.

Ordinary local continuation stays independently eligible. Mediate adds operands
matching dominant Correspondence; Bias adds sources matching dominant Chirality;
Order adds existing Committals matching dominant resolved Closure. Every additional
option must satisfy its operation's prerequisites. Zero means retain supplied
population history or initialize from the observable's canonical default. Order's
unresolved component disagreement contributes no resolved measurement.

The immutable Cycle entry owns history, resources and accounting while authorized
actions advance the participant's causal route. Actual Order Commit or Traverse
chooses the next entry. PhasePositionService remains Horizon-local. Comparison
never imports Receipt stock, creates causal edges, changes Potential, or grants
generic Traverse. See [the architecture](homeostatic-populations.md).

## Downstream proof map

| Boundary | Coverage |
| --- | --- |
| Valid homologous continuation excludes Rest | `HomologousOrderTest`: no local Commit, eligible foreign Traverse, rejected Rest leaves state/failure records unchanged |
| Structural visibility is not continuation | `HomologousOrderTest`: two structurally valid but unresolved Committals, Rest-only plan, real Rest without propositions, edges or Receipt consumption |
| Genuine Tragedy and regional exhaustion | `AuthoritativeRestReasonTest`, `HorizonFailureInvariantTest`, `FinalAttemptExhaustionTest`, `HorizonTragedyTest`, `TragedyTest` |
| Shared final attempt, epoch reopening, propagation | `FinalAttemptExhaustionTest`, `ExplorationEpochTest`, `ParticipationLocalEpochTest`, `ParticipationStaggeredEpochTest`, `ParticipationTragedyLifecycleTest` |
| Backward recovery, Potential and Horizon projection | `CausalRecoveryTest`, `ParticipationRecoveryTest`, `PotentialChargeServiceTest`, `PotentialProjectionTest`, `ContextHorizonProjectorTest` |
| Causal lineage and graph history | `BiasLineageTest`, `CausalHorizonLineageTest`, `GraphActivityTest`, `ExhaustedParticipantContextTest` |
| Receipt isolation and consumption | `HomologousMediationTest`, `HomologousBiasTest`, `HomologousOrderTest`, `HorizonReceiptLifecycleTest` |
| Closure/Cadence, natural completion and next entry | Existing Closure tests, `HomologousOrderTest`, `ParticipationLifecycleRegressionTest`, fresh HTTP probes |
| Concurrency and bounded queries | Existing Load / Concurrency and Query Regression workflows; fresh request-count/runtime windows |

Existing lifecycle fixtures and historical natural captures are unchanged. The
new structural fixtures freeze deliberately disagreeing Depth/Cadence at creation;
they do not rewrite immutable snapshots to obtain a desired Rest outcome.

## Fresh natural probes

`tests/Acceptance/HomeostaticNaturalRunTest.php` starts each fresh substrate and
three participants through HTTP. Participants take turns for 600 scheduled steps.
It uses published schema choices exclusively, with deterministic external evidence
transport. No calculator, population, recovery or progression service is mocked.
The inherited harness forbids database fixture writes outside application requests.

Two policies explore different decisions: `local-first` prefers ordinary options
when coavailable; `field-first` prefers additional schema-authorized options when
offered. Both otherwise use the existing HTTP harness's action priority and support
Attestations. They are deterministic acceptance probes, not human/LLM behavioral
forecasts. They do not assert target Rest, Commit, crossover or Tragedy rates.
Round-robin scheduling is imposed by the probe, so worker scheduling fairness is
covered separately by the existing suites.

Additional-option classification is diagnostic only. Bias excludes the current Gate;
Order subtracts direct causal Committal Writes; Mediate subtracts the original
regional Gate pool under corresponding local Reads. Overlap is not counted as an
additional option. The public schema remains the sole source of selected actions.

Each JSONL record preserves before/after public state, the actual contribution,
additional choices and request query/runtime metrics. Summaries distinguish Rest
rollover, observed recovery relocation and explicit entry attempts. Tragedy and
regional exhaustion are actual persisted row counts. Request metrics include the
GET and contribution POST, excluding read-only diagnostic classification and
assertion queries. Waiting observations are recorded separately; they are not
invented contributions. Runtime includes diagnostics and setup.

Every contribution checks immutable Cycle entry and completed Phase. Traverse
checks no new proposition, edge, snapshot or Receipt consumption. Order checks
completion and exact selected next entry. Every Rest plan must be exclusive.
The workflow first passes focused Homeostatic/downstream tests, then runs the
probes. Existing Core, Integration, Query Regression and Load gates are unchanged.

Reproduce with PostgreSQL and the ordinary test environment:

```sh
COMMONS_NATURAL_POLICY=local-first php artisan test tests/Acceptance/HomeostaticNaturalRunTest.php
COMMONS_NATURAL_POLICY=field-first php artisan test tests/Acceptance/HomeostaticNaturalRunTest.php
```

The opt-in `Homeostatic natural acceptance` workflow also supports manual dispatch.
Full traces and summaries are uploaded as Actions artifacts for 14 days. Compact
review evidence and observations follow below after the completed runs.
