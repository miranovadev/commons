# Personal Gate Acquisition — Stage 2

Claire's Stage 2 implementation handoff authorizes personal acquisition as a prerequisite to fresh generation. This contract supersedes the per-Cycle allowances and shared-Receipt acquisition switches in the earlier epistemic Stage 2A/2B and Disperse fallback documents. It does not supersede Stage 1 Receipt cognition or structural admissibility.

## Identity and lifecycle

Completion is Participant × exact evidence-execution Gate × acquisition role. `PersonalAcquisitionService` derives it from durable `EvidenceExecution` history, joining the owning Cycle's Participant and the execution Phase's kind. No completion table, copied owner, exploration epoch or memory event is introduced.

| Phase | Role | Outstanding acquisition |
| --- | --- | --- |
| 1 Distinguish | `distinguish_discovery` | Instrument at the governing Committal, including the actual Origin Gate |
| 2 Persist | `persist_examination` | Preparation if an accessible Artifact has a supported reader; otherwise Instrument |
| 4 Disperse | `disperse_acquisition` | Instrument or accessible Preparation; either completes the same role |
| 3, 5, 6, 7 | None | No acquisition |

Existing executions qualify under their original Phase, Participant and Gate. A historical Persist Instrument satisfies Persist; it does not satisfy Distinguish. Existing shared Receipts never waive a different Participant's responsibility. A returning Participant retains completion at that Gate, even after consumption, Rest, Traverse, Attestation, Reconsideration or exploration reopening. A new Gate establishes a new identity.

Completion requires a recorded observation and its Receipt: a completed Instrument, a contributed/deferred Preparation, or a failed Preparation with the existing explicit `failed` outcome. Pending/presented rows reserve the current Phase's opportunity but do not complete it. Stale reservations in an old Phase cannot resolve through the active Preparation boundary and do not obstruct a later encounter. Expiry before presentation, stale execution and invalid reading parameters produce no observation and can be retried. Expiry after presentation and actual reader/delivery failures retain the existing non-verifying failure Receipt.

The Instrument lifecycle already distinguishes recorded retrieval/provider failure from an invalid invocation or storage failure. A completed non-verifying failure Receipt counts as an attempted acquisition and cannot reinforce evidence. Input rejection and Artifact storage failure leave no completed execution. Load File retains the same distinction. No successful evidence is synthesized from an error.

## Authority and resolution

Fresh Variant/Revise in Phases 1, 2 and 4 require personal completion. Persist and Disperse retain their separate unconsumed ordinary Receipt prerequisite and all existing structural restrictions. Acquisition does not automatically generate a Proposition, consume or select a Receipt, grant traversal or complete exploration. Attestation and Traverse through existing structure retain their independent rules.

Persist Preparation offers `preparation.defer` after acknowledged delivery. This resolves a real reading and creates the ordinary deferred Preparation Receipt. It leaves the Phase open; the Participant then chooses a separately authorized Persist generating action. It cannot execute a Disperse Variant or advance through Disperse. Disperse retains its existing atomic contributed Variant/Revise workflow and the option to defer.

Matching retries of a resolved Preparation return its existing Receipt. A different resolution for that execution conflicts. Instrument completion retries return the existing Receipt for the same execution and Gate. New acquisition requests are rechecked under the existing Substrate lock; an obsolete open Phase cannot bypass a later active Phase or its reservation. Exhausted governing Committals remain unavailable for discovery. Preparation also uses Participant, Cycle, Phase and execution locks in the established order. An Instrument observation is prepared outside the database transaction, then validated and persisted atomically under the lock. Competing observations can perform external I/O, but only one can reserve/complete the personal role. Preparation reservations block a competing Instrument, and vice versa.

The old unique Preparation-per-Cycle index is replaced by an acquisition lookup index. A Cycle can legitimately contain both Persist and Disperse Preparations, or retry an abandoned reservation. Migration preserves every execution and Receipt. Downgrade refuses to restore the old constraint when it would require discarding history.

Equivalent effective Instrument invocation exclusions are unchanged. Personal completion governs whether acquisition is outstanding; the existing instrument-specific checks still determine which invocation is lawful.

## Projection and performance

The shared input plan publishes `acquisition` with role, Gate, status, execution ID and recorded outcome. Autonomous context includes the same information on the Gate. Only the three acquisition Phases add this field; the other Phase packets keep their existing shape. Human notices explain required, pending and completed acquisition without adding a blocking modal. Both use the same backend schema and completion service.

History lookup selects at most one completed row and one pending row; it does not hydrate all historical attempts or Artifact bodies. ParticipationContext memoizes the result only for that calculation. No persistent cache or loaded Receipt state changes. Artifact capability discovery remains outside structural write transactions and uses the existing request-local descriptor cache.

Stage 1 summaries, causal evidence unions, canonical Commit references, Receipt visibility and Read-context inclusion remain unchanged. Consumption remains Constrain/Bias Reclassification only. Exploration Events, active-head semantics, recovery and first-time Attestation requirements are outside this stage.

## Boundary decisions

| Boundary | Question | Resolution | Authority / status | Verification |
| --- | --- | --- | --- | --- |
| Existing history | New completion table or migration backfill? | Derive roles from existing execution Phase and Cycle ownership; preserve history | Handoff §§2–3; documented | Personal identity and returning-Cycle tests |
| Origin | Which Proposition owns discovery? | Actual recorded governing Origin Committal | Handoff §4; documented | Natural Origin → Instrument → Distinction |
| Persist reading | Must reading generate a Variant immediately? | Resolve via deferred outcome, then ordinary Persist generation in a separate action | Handoff §§3, 5; documented | No Proposition/Phase change on resolution |
| Failure | Does any execution row count? | Only recorded qualifying observation with Receipt; abandoned reservations remain outstanding | Handoff §3 and existing failure lifecycle; documented | Failure matrix, invalid/stale/pending boundaries |
| Disperse choice | Do readable Artifacts suppress Instrument? | No; both can be offered, with one shared personal role | Handoff §6; documented | Instrument/Preparation competing requests |
| Returning route | Does Traverse require acquisition again? | Completion persists; traversal remains independently authorized | Handoff §§4, 9; documented | Natural repeated-Cycle journeys and traversal fixtures |
| Shared Receipt consumed | Does the allowance reopen? | No; structural evidence availability can change independently | Handoff §§7–8; documented | Stale generation and consumption tests |
| Preparation uniqueness | Can Persist and Disperse both reserve in one Cycle? | Yes, different roles/Gates; remove obsolete Cycle uniqueness | Handoff §§2, 5–6; documented | Migration and natural journeys |
| Exploration | Does completion depend on a personal exploration event? | No; no event prerequisite or reset | Handoff §§9, 11; documented | Existing exploration regression suite |

## Validation

Validation is recorded in the PR against merged `main` (`35d8851a3efdd5bbf68e4560272ac3623b77f7fe`). Natural fixtures use actual acquisition operations; isolated structural fixtures explicitly acquire before testing generation. No fixture inserts completion records to bypass the policy.

The existing Core, Integration, C3, query and frontend suites are required. Baseline failures are distinguished from changed acquisition expectations and regressions. Numerical, consumption, canonical history and movement assertions remain authoritative.
