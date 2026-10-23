# Epistemic Evidence Stage 2B

Approved implementation contract: Claire / Chat Nova's Stage 2B handoff.
The ten Core Invariants govern this contract.

Persist selects acquisition or generation from the existing shared catalogue:

| Current ordinary Receipt availability | Persist actions governed here |
| --- | --- |
| At least one eligible unattached Receipt | Structurally lawful Variant / Revise; no Instrument |
| None; completed Instrument allowance unused | Instrument on the current Gate; no Variant / Revise |
| None; completed Instrument allowance used | Neither Instrument nor Variant / Revise |

This is not the entire action inventory. Attestation, Traverse and Rest retain
their independent eligibility. An ordinary observation can be shared by another
Participant or originate elsewhere within the permitted Region and Horizon.
Loading, authorship, previous execution counts and attached historical evidence
do not substitute for current catalogue availability.

`HorizonReceiptService::ordinaryAvailable` supplies the prerequisite. The
catalogue's existing consumption exclusion, ordinary evidence type restriction,
completed observation provenance and Region/Horizon scope remain authoritative.
ReceiptConsumption is the unique attachment record. A Receipt's original subject
is provenance, not an attachment. Variant and Revise neither select a receipt_id
nor consume or attach a Receipt. Constrain and Bias retain explicit selection and
their existing consumption authority.

An Instrument still completes at most once per Participant Cycle and refreshes
the same Persist Phase. An ordinary Receipt switches the refreshed plan to
generation where structurally eligible. Citation, Hyperlink, Web Search and
Proposition Search are ordinary Receipts; successful empty searches qualify.
Tragedy alone is special and does not satisfy this ordinary prerequisite. Another Participant can later supply
shared evidence without restoring the first Participant's allowance.

## Ownership and enforcement

`PhaseGenerationService::epistemicActionAvailable` owns the acquisition/generation
choice. Its existing `available` method combines that choice with structural
opportunity. `PropositionOptionService` uses it for Instrument options and obtains
revision eligibility through `available`; ownership and earlier effective
self-Refute remain mandatory. `InputSchemaService` uses the same generation owner,
so manual actions and autonomous response schemas share the policy.

`ContributionDispatcher` rechecks the prerequisite under the existing Substrate
lock, using a fresh catalogue rather than a previously hydrated plan. A stale
generative request conflicts when the final Receipt has been consumed.
`OperationalInstrumentService` also revalidates the authoritative target selector
under that lock for direct callers and observations prepared before a competing
contribution. Observation/network work remains outside write transactions.
The lower-level InstrumentExecution persistence primitive continues to record
execution and Receipt provenance; it is not an alternative contribution API.
Catalogue and measurement component tests construct observation records through
that primitive. Their fixtures do not authorize live acquisition on historical
Writes or outside Persist. Operational journeys acquire before development.

Existing coupling remains between schema specialization, option lists and
dispatch target validation. Keeping these callers on the same policy is useful;
combining every service or adding another wrapper service is unnecessary here.
A later consolidation could centralize the repeated Instrument conflict message
and target assertion without moving catalogue filtering into action consumers.

## Disperse activation boundary

Disperse generation remains enabled by its existing structural rules in Stage
2B, including its existing evidence and epoch conditions for renewed exploration.
No Prepare action is exposed. No feature flag or dormant preparation service is
introduced.

When Preparation **and its reading workflow** are available, extend the same
epistemic policy to Disperse: eligible unattached ordinary Receipt means lawful
Variant / Revise and no Prepare; absence means no Variant / Revise, with Prepare
subject to its independent once-per-Cycle allowance. Attestation and Traverse
remain independent. Activation belongs to Stages 2C–2D, not this deployment.

## Readiness and verification

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| Catalogue | Historical or currently available? | Existing scoped ordinary unconsumed catalogue | Handoff §3 | Documented | Shared, consumed, foreign-Region and empty search cases |
| Loading | Personal cognitive uptake required? | No | Handoff §3 | Documented | Loaded/unloaded and other-Participant evidence |
| Structure | Can evidence bypass viable continuations or revision ownership? | No; additive prerequisite | Handoff §6 | Documented | Viable-route and revision tests |
| Race | Receipt consumed after validation? | Fresh check under existing root lock; reject stale action | Handoff §7 | Documented | Independent PostgreSQL connections and direct dispatch |
| Disperse | Withhold generation before Prepare exists? | No; defer activation | Handoff §5 | Documented | Phase boundary test and unchanged operational journeys |
| Measurements | Alter formulas or force a Commit? | No; preserve admissibility and classify chronology effects | Handoff §10–12 | Documented | Existing mathematical and lifecycle suites |

Before Stage 2B, an unused Instrument and structural generation could both be
offered in Persist. After Stage 2B, the catalogue selects the branch, while
structural rules still determine whether any generative action is lawful.
Distinguish's initial Variant and Origin bootstrap remain exempt.

Validation results and cause-level baseline comparisons are recorded in the
implementation PR. PostgreSQL CI is authoritative for the two-connection tests.

## Proposition Search classification correction

`Receipt::SPECIAL_RECEIPT_TYPES` contains only Tragedy. The former memory
classification incorrectly excluded Proposition Search from ordinary catalogue,
grounding, consumption, cognitive evidence associations and qualifying uptake.
`InstrumentExecutionService` now persists the existing immutable collection
identity as its evidence signature; `ReceiptEvidenceService` qualifies completed
collections, including empty results. Existing catalogue and Reclassification
owners apply their normal Region/Horizon and one-use rules without new filters.

The same classification governs ParticipationContext, Reinforcement and ledger
field invalidation. No measurement formula changes. Search-specific selection,
identity, duplicate suppression, stale observation checks and bounded collection
presentation remain: a result is never a new causal edge or movement option.
Loaded historical collections remain personal cognition; their presence does not
restore consumed or out-of-scope catalogue availability. Tragedy loading retains
its separate non-evidential behavior.

The affected seams reuse existing owners rather than adding services. The
remaining search-specific serializer/projection branches preserve bounded loaded
result visibility; they do not implement eligibility. A future consolidation of
Receipt presentation could reduce those branches independently of domain policy.
