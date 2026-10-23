# Epistemic evidence Stage 2C

> Stage 2D supersedes the dormant activation and reader-interface details below.
> Claire explicitly corrected consumed-source selection: attached and unattached
> ordinary Receipts within the same lawful Region/Horizon both grant Artifact
> access. Consumption still controls evidence availability, never reading. See
> [the active Stage 2D contract](epistemic-evidence-stage-2d.md).

Stage 2C introduces one `EvidenceExecution` / `evidence_executions` lifecycle for
`instrument` and `prepare`. It establishes the Preparation domain without exposing
Prepare, readers, provider attachments, or a new human/autonomous interaction.
Claire's Stage 2C handoff, the [Core Invariants](core-invariants.md), and the retained
[Stage 2A](epistemic-evidence-stage-2a.md) / [Stage 2B](epistemic-evidence-stage-2b.md)
contracts govern this change.

## Persistence and ownership

The fresh schema replaces `instrument_executions` and its foreign key with
`evidence_executions` and `receipts.evidence_execution_id`. There is no compatibility
model or second execution store. Initialize a **fresh isolated database** when
validating this branch; applying only the new migration to an old populated schema
is not a supported upgrade. This change does not modify a shared/production database.

Every execution preserves its immutable creating Phase, Cycle, originating Gate,
type, validated input, start time and optional Instrument replay source. Instrument
kind/reference are nullable for Preparation, which never invents an invocation.
Preparation also stores successful presentation acknowledgements, presentation time,
completed outcome, optional resulting Proposition, or technical failure diagnostics.
The model rejects invocation mutation, illegal transitions and terminal edits.

Execution identity, Receipt identity, originating Gate, resulting Proposition and
later attachment are distinct. Receipt type/Phase/origin must match its execution.
`resulting_proposition_id` and `metadata.outcome.proposition_id` describe the actual
contribution; neither consumes evidence or creates an extra causal edge.

## Domain contract and transaction boundaries

`PreparationService` is the dormant authoritative domain boundary. Its methods are
not routes, input-schema actions, or frontend controls.

| Operation | Validation and durable result | Structural consequence |
| --- | --- | --- |
| `start` | Active Disperse Phase, current lawful Gate, Participant/Cycle ownership, available branch, unused allowance, exact catalogue selections; persist `pending` | None |
| `present` | Trusted successful delivery acknowledgement, registered reader method/version and supported representation, all selected distinct hashes delivered exactly once; persist `presented` | None |
| `contribute` | Presented/unresolved execution and fresh ordinary Variant/Revise schema; dispatch the real contribution, complete Receipt and advance in one transaction | Exactly the ordinary contribution's Phase and epoch effects |
| `defer` | Presented/unresolved execution and exact nonempty reason up to 400 characters; complete ordinary Receipt | None; Disperse remains active |
| `fail` | Unresolved execution, bounded method/version, stage (`unsupported`, `read`, `delivery`) and actual diagnostic; persist `failed` | None; **no Receipt or completed outcome** |

Every domain method rechecks current ownership, active Phase and Gate under the
existing **Substrate → Participant → Cycle → Phase → execution** lock order.
Starting reserves the Preparation allowance, including after technical failure.
The partial unique index on Cycle for `type = 'prepare'` provides a second race
barrier. Another Participant and a later Cycle have independent allowances.
Completed Instrument allowance queries explicitly filter `type = 'instrument'`.

Resolution rejects duplicates. A stale Gate/Phase or foreign actor cannot resolve.
Contribution, Receipt/ledger/creator load, completed execution, epoch and Phase
progression commit together or all roll back. No network/storage read occurs inside
these write transactions. Instrument observation continues outside its transaction.

## Selection and reading contract

`PreparationInput` requires exact authored purpose (1–240 characters) and 1–8
Receipt/Artifact pairs. Only the current causal shared catalogue grants selection authority.
Every canonical `sha256:` hash must appear in its claimed Receipt's preserved
Artifact metadata. **Corrected in Stage 2D:** attached and unattached ordinary
Receipts are equally eligible sources within the existing Region/Horizon scope.
Unknown, foreign-scope, malformed, excessive and identical duplicate selections
are rejected. Loading is irrelevant. Several Receipt associations may name the
same bytes; identical requested representations share delivery while different
Read Types/ranges remain distinct. Every original association is retained.

Selection authority is checked at start and remains attached to that immutable
request. Later consumption does not erase the already selected provenance or allow
additional selections. A new Preparation may select the same consumed source
Receipt when its causal scope and the independent Preparation allowance permit it.

`PreparationReader` supplies a pure capability contract: method, version, and
support for the preserved Artifact descriptor plus requested representation. There
are **no production reader adapters** registered in Stage 2C. MIME alone is not a
capability. `PreparationPresentation` is a trusted adapter acknowledgement, never
Participant-authored input. It identifies each exact delivered representation by
hash, byte count, delivery ID, method and version. It accepts no body content,
missing selections, duplicate delivery or silently truncated requested material.
The provisional combined cognitive payload ceiling is 1 MiB. Stage 2D must enforce
this budget against actual delivery and implement explicit reader support; the DTO
cannot itself prove that external delivery occurred.

Failures preserve method/version/stage and diagnostic history. Failed delivery is
not a successful observation, not a deferment, and not a generated Proposition.
A failure after presentation retains the truthful presentation already recorded.
No actual native extraction, provider attachment, reading history UI, purpose input
control or autonomous re-inference is implemented here.

## Ordinary Receipt contract

Completed contributed and deferred Preparations create `type = 'preparation'`
Receipts through the same immutable construction, ledger and creator-uptake path as
Instruments. They are initially unattached, qualified ordinary observations with
purpose, original selections, successful delivery provenance and exactly one outcome.
They have readable history capability and **no Instrument replay capability**.
`SPECIAL_RECEIPT_TYPES = ['tragedy']` remains unchanged.

The existing Region/Horizon catalogue admits completed execution-backed ordinary
Receipts without filtering their production operation. Existing loading,
Reinforcement, Stage 2B availability and Constrain/Bias consumption rules apply.
Reclassification can consume a Preparation Receipt for a different Proposition and
Participant; the historical outcome remains immutable. No receipt_id parameter was
added to Variant or Revise. No new measurement weighting or Tragedy authority exists.

Completed history is discoverable through the originating Gate using the existing
C3 Receipt projection. C2 creator loading loads observation metadata, not selected
Artifact bodies. Purpose/reason are quoted historical data, not instructions.
Selection and outcome IDs do not expand cognition, authorize movement, grant
Supersession, or automatically introduce referenced Proposition/Artifact contents.
Instrument packets remain Instrument-only; pending/failed Preparation presentation
and interaction are deferred to Stage 2D.

## Activation and architectural ownership

Stage 2B production actions remain unchanged: Persist uses Receipt-governed
Instrument-versus-generation eligibility; Disperse generation remains operational.
Prepare is absent from plans, response schemas, human controls and dispatch actions.
Stage 2D will add actual presentation/resolution interactions and activate the
already documented mutually exclusive Disperse policy together.

- `EvidenceExecutionService` owns common execution persistence and the single
  ordinary Receipt construction path. Instrument verification and Preparation
  completion retain explicit type-specific handling.
- `OperationalInstrumentService` retains Instrument invocation, duplicate/replay
  and operational eligibility checks. `PreparationService` owns Preparation's
  catalogue selection, trusted delivery validation and atomic resolution.
- `HorizonReceiptService` remains the only shared availability catalogue;
  `ReceiptEvidenceService` describes qualifying cognitive observations;
  `ReceiptContextService` retains loading/Reinforcement behavior. These distinct
  responsibilities were generalized, not duplicated.
- Preparation reuses `InputPlanService`, `ContributionService::validateSchema`,
  `ContributionDispatcher` and `PhaseProgressionService`. Their orchestration is
  still coupled; a future consolidation could extract the transactional structural
  contribution portion from participation orchestration. This stage does not add
  a generic execution framework or rewrite that lifecycle.
- The low-level persistence service remains trusted internal infrastructure, as
  before. Participant-facing adapters must use the authoritative operational/domain
  services, never call persistence methods with unvalidated acknowledgements.

## Readiness register

| Boundary / question | Resolution | Authority / status | Verification |
| --- | --- | --- | --- |
| Instrument versus Preparation allowance | Completed Instruments only; start reserves one Preparation per Cycle | Handoff §§3,8–9; documented | Independent allowance and competing-start tests |
| Technical failure: Receipt or successful outcome? | Diagnostic failed execution only; no qualifying Receipt | Explicit observation-completion contract under §6; documented implementation choice | Failure and presentation rejection tests |
| Request versus examination | Only trusted supported successful delivery can mark presented | §§6,10; documented | Reader/version/representation, omission and payload tests |
| Consumption after selection | Start validates current catalogue; immutable selected provenance persists; no new access | §§4,9 and invariant 6; documented implementation consequence | Consumption-between-start-and-presentation test |
| Bounds before readers exist | 8 associations; 1 MiB combined supplied representation; no truncation | §10 authorizes bounded request limits; documented implementation choice | Bounds/duplicate tests |
| Outcome versus attachment | Actual generated ID is historical; Reclassify alone consumes | §§7–9; documented | Contributed/deferred Receipt consumption and rollback tests |
| Stage 2D unavailable | No Prepare action and no Disperse suppression | §12; documented | Production action/reader absence test |

No unresolved decision is required to implement the Stage 2C domain. Actual reader
verification and interaction remain explicitly unimplemented Stage 2D work.

## Examples and validation

[evidence-execution-examples.json](evidence-execution-examples.json) contains actual
persisted rows exported by `PreparationExecutionTest` in an isolated database for
an Instrument, contributed Preparation, deferred Preparation and failed attempt.
The acknowledged reader in those examples is test-only. Regenerate with
`COMMONS_PREPARATION_EXAMPLES=docs/evidence-execution-examples.json` when running
`PreparationExecutionTest::test_persisted_examples_cover_each_execution_outcome`.

Focused tests cover real Variant/Revise and Reclassification, atomic rollback both
before Receipt creation and after it during Phase progression, lifecycle and epoch
neutrality, immutable outcomes, C2/C3 visibility, invalid scope/provenance, bounded
purpose/reason/delivery, distinct allowances and PostgreSQL competing requests.
The fixed C3 historical comparison receives a schema-only import adaptation; its
recorded graph, observations and baseline projection policy remain unchanged.

Full validation results and remaining failures are recorded in the PR after CI.
