# Disperse Instrument fallback

Disperse previously withheld generation without an available ordinary Receipt,
and could only acquire evidence by preparing a readable Artifact. With neither,
an unused Instrument allowance could not help. An isolated fixture reproduces
that state on merged Stage 3C, with otherwise valid structural Variant eligibility.
This verifies the code-level hypothesis, not the state of any live cook database.

## Current action policy

| Disperse evidence | Epistemic option |
| --- | --- |
| Eligible unattached ordinary Receipt | Structurally lawful Variant / Revise |
| No available Receipt; accessible supported Artifact | Prepare if its allowance remains |
| No available Receipt; no accessible supported Artifact | Instrument if its allowance remains |
| Required allowance spent | Independently lawful Attestation / Traverse / eventual Rest |

Readable means supported by the existing Preparation discovery contract, including
source validation, configured source limits and available reader capabilities.
Acquisition intent, Receipt loading and Receipt consumption do not determine
Artifact readability. Attached ordinary Receipts remain legitimate source
provenance, within the same Horizon / Commit Region access boundaries.

Preparation retains priority whenever readable Artifacts exist. Exhausting its
allowance does not authorize Instrument fallback. A pending/presented Preparation
must still be resolved before ordinary participation. Preparation's reservation,
failure, delivery and completion rules are unchanged.

The fallback uses `instrument.execute`, its existing invocation/replay forms and
the exact current Disperse Gate. It shares the one-completed-Instrument allowance
with Persist. Execution is Phase-neutral and refreshes the current plan. A new
eligible Receipt enables structurally lawful generation. No second Instrument
allowance, execution type, Receipt category, mathematical event or reader is added.

Receipt qualification remains unchanged. In particular, a completed failed
verification can still constitute an ordinary observation under the existing
catalogue rules. The fallback does not reinterpret failure as ineligible evidence.
Consumption never restores an Instrument allowance; another Participant's new
eligible Receipt can restore generation independently of that allowance.

## Ownership and authoritative validation

`PhaseGenerationService::epistemicActionAvailable()` owns the shared Receipt versus
acquisition policy, including Disperse's readable-Artifact exclusion.
`PropositionOptionService::instrumentOptionsFor()` retains exact-Gate, active
Phase/Cycle and completed Instrument allowance checks. The phase configuration,
human workspace, input schema, autonomous transport, replay and direct dispatcher
all reuse these owners. Persist's Receipt-governed policy is unchanged.

`PreparationService` owns readable Artifact discovery. It now reuses only immutable
descriptor capability results within the request/worker scope; it never caches
catalogue membership or Artifact bodies. Its existing source-type cache remains.
Shared ordinary availability and causal Artifact access are queried again under
the existing Substrate-first write lock. Storage reads and PDF capability probes
remain outside that transaction.

If a newly accessible descriptor was not probed before the lock, locked validation
conservatively withholds fallback and returns the normal conflict response. Unknown
readability is not absence. A fresh plan probes it outside the lock and can offer
Prepare or Instrument as appropriate. Failed discovery is remembered only within
that request; future requests can retry. This prevents cold-cache or concurrent
Artifact arrival from bypassing Preparation priority.

No frontend-specific eligibility branch or additional service is necessary. The
human and autonomous paths use the same action schema and Phase-neutral execution.

## Validation

Focused coverage includes the initial dead-end reproduction, Receipt/Artifact/
allowance combinations, consumed readable and unsupported source Artifacts,
stale Receipt/Artifact arrival with and without prior capability discovery,
same-Phase refresh, exact structural and measurement snapshots, C2 loading,
shared evidence, independent Attestation/Traverse/Rest, autonomous re-inference,
and independent PostgreSQL contention for the shared Instrument allowance.

The HTTP journey uses two Participants and real application requests: shared
Persist acquisition → generation by another Participant → Reclassification
consumption → Disperse fallback → same-Phase refresh → ordinary Variant → Mediate.
It needs no fixture writes, new lifecycle authority or fabricated Commit.

## Resolved implementation boundaries

Authority: Claire's approved Disperse Instrument Fallback handoff, continued on
24 October 2026; the ten [Core Invariants](core-invariants.md) and existing
Preparation / ordinary Receipt contracts remain governing constraints.

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| No available ordinary Receipt | When may Disperse acquire evidence? | Prepare accessible supported Artifacts; otherwise use the remaining Instrument allowance. | Handoff §§1–3 | Documented | Fallback eligibility and readable Artifact tests |
| Spent Preparation allowance | Does this permit Instrument despite readable Artifacts? | No. Readability excludes fallback independently of the allowance. | Handoff §3 | Documented | Spent Preparation test |
| Instrument used in Persist | Is Disperse a new allowance? | No; both share one completed Instrument execution per Participant Cycle. | Handoff §§1–2 | Documented | Persist/Disperse spent-allowance cases and PostgreSQL contention |
| Evidence arrives after preflight | May the old fallback still execute? | Recheck shared Receipt and causal Artifact access under the Substrate lock; an unprobed descriptor requires refresh. | Handoff §4; Invariant 8 | Documented | Stale Receipt and probed/unprobed Artifact tests |
| Completed failed verification | Is its Receipt automatically non-qualifying? | No; preserve existing ordinary observation qualification and consumed allowance. | Handoff §§2, 7; existing Receipt contract | Documented | Failed-verification and later-consumption cases |
| Tragedy-only catalogue | Does a warrant satisfy ordinary evidence eligibility? | No; it neither enables ordinary generation nor suppresses an otherwise lawful acquisition. | Handoff §7; Invariants 3, 8, 9 | Documented | Tragedy warrant separation in Persist and Disperse |
| Phase and measurements | Does fallback create a structural event? | No Phase/Cycle/Gate advancement, structural birth or independent measurement update. | Handoff §§1–2, 6; Invariants 1, 4, 6, 7 | Documented | Exact structural snapshot, HTTP and autonomous refresh tests |

No unresolved domain decision, data migration or production/shared-database
modification is required. The live-cook database hypothesis remains unverified;
the isolated reproduction establishes only the existing eligibility dead end.
