# Participant-relative exploration

> Personal route completion and witness provenance are refined by [Participatory Exploration Completion — Stage 3](participatory-exploration-completion.md). Its requirements supersede the earlier head-only encounter predicate below.

Stage B replaces shared continuation failures and Exploration Epochs on a **fresh database**. Authority: Claire's approved 25 October 2026 handoff, “Exploration Events, Active Heads and Recovery”, the subsequent forward-generation correction, and the ten Core Invariants. The separate rolling Attestation Cadence correction is outside this change.

An immutable Exploration Event records one Participant's completed Rest encounter at an expressive continuation head. Its creating Phase establishes the Participant, Cycle, entry Committal and Context Horizon. The witness preserves arrival, judgment, evidence and the guarded absence of forward-generating opportunities. Events do not mutate Propositions, retire Regions, change another Participant's options, or assert global failure.

Current status is derived. Follow the complete authorized forward frontier within the acting Cycle's Region and Horizon; stop at Committal boundaries. Hard-superseded inputs are history, not independent heads. Local Reconcile results retain their existing Attestation routes through either local operand. Foreign Reconcile operands, homologous comparison members and foreign selected sources do not grant movement. An ancestor is explored only when every reachable active head has an applicable personal encounter and no newly lawful forward-generating operation. A new Cycle or field version is not an exploration reset. A retired input with no active authorized head does not require an invented encounter and does not independently block Variant.

Base action lawfulness precedes personal selection. Ordinary Attestation reversals and Revise require a personally unexplored Write. Neither availability nor execution alone renews exploration or prevents Variant. Revision replaces the same position: applicable personal encounters follow that exact revision ancestry without mutating the events, and the resulting head is checked for forward-generating admissibility. Existing traversal opportunities use personal exploration; concrete Revise, Reconcile, Reclassify and Commit opportunities retain their existing structural, measurement, evidence and lifecycle authority. Fresh Variant is excluded from its own prerequisite frontier and is considered only after existing relevant heads are explored.

Rest publication is atomic with its existing completion transaction and unique by Rest Phase. Stale, rolled-back, incomplete, untested, evidence-starved or Preparation-only encounters do not establish exploration. An event remains immutable after a later continuation releases its current restriction. Personal state notifications refresh the actor; existing structural and evidentiary notifications still refresh their ordinary consumers.

## Semantic boundary register

| Boundary | Resolution | Authority / status | Verification |
| --- | --- | --- | --- |
| H1, repeat judgments | Reversal remains lawful; only a generating opportunity reopens | Handoff §3, documented | Reversal and enabled-generation cases |
| Variant circularity | Existing continuation frontier first, exclude fresh Variant | §4–5, documented | Personal Variant ordering / concurrent writes |
| Branch propagation | All authorized active heads, not one failed descendant | §2, documented | Two heads, revisions, new head, inaccessible branch |
| Participant identity | Rest Phase owns history; another actor cannot inherit it | §1, documented | Independent actors / different Cycle intervals |
| Arrival authority | Recorded forward arrival, exact Region/Horizon, no foreign-operand shortcut | §2, §7, documented | Reconcile and recovery cases |
| Event integrity | Append-only, one Phase, genuine tested dead end, transaction guard | §6, documented | Rollback, stale, idempotence and immutability |
| Terminal lifecycle | Tragedy, Resolution and regional Exhaustion remain independent | §8, §11, documented | Existing lifecycle regression suites |
| Mathematics / evidence | No changes to calculations, snapshots, allowances or Receipt authority | §11, documented | Phase Profile, C3, Commit, Receipt and Preparation suites |
| Migration strategy | Remove obsolete exploration schema; no old-state backfill | §8, documented | Fresh SQLite and PostgreSQL migration tests |

## Implementation and validation

Implementation and test results are recorded with the PR. Historical epoch-based reports describe the previous system and are not current eligibility contracts. The active source-provenance resolver is `StructuralSourceService`; no epoch identity or global spent predicate remains.

The returning Mediate probe uses the Participant’s recorded Disperse arrival anchor, not the new Cycle’s absent Disperse Phase or another actor’s journey. Forward active-head discovery and historical arrival provenance serve different purposes.

Comparison boundaries are batched by canonical position within the acting entry/Horizon. Directed incoming Bias relationships and used Reconcile pairs are queried in batches. Context-local terminal results are shared by Horizon; snapshot relations are populated from the existing batch. No persistent exploration cache or new observation counter is introduced.

See [validation and acceptance coverage](participant-relative-exploration-validation.md) for exact results and remaining limitations.

## Forward generation, forward movement and reconsideration

| Category | Operations | Exploration effect |
| --- | --- | --- |
| Forward generation | Variant, Reconcile, Reclassify, Commit | New authorized active structure or newly lawful generation can reopen the relevant frontier |
| Forward movement | Traverse | Accesses existing unexplored structure; availability alone never reopens a head |
| Reconsideration | Attestation, Revise | Available on unexplored Writes. Personally explored Writes stay visible with their explored badge and have no reconsideration actions. |

`ContinuationOpportunityService::nonVariantGenerationAvailable` evaluates base Reconcile, Reclassify and Commit lawfulness. Fresh Variant is deliberately excluded: it must never supply its own prerequisite for reopening or authorizing another Variant. Existing reachable Variants remain structural continuations in active-head discovery. Acquisition is required only for an admitted fresh Variant attempt; exploring an existing route no longer requires historical Gate acquisition. First effective personal judgment, active-route authority and forward-generation reopening remain required.

`ParticipantExplorationService::canGenerateFromHead` uses that non-recursive predicate for each historical active head. Traverse destinations still use ordinary authority and personal exploration filtering, including the existing Commit traversal rules. Traversal through an ancestor remains available when an authorized head beyond it is unexplored. Base Traverse back toward an explored destination does not undo that destination's personal history. Region, Horizon and recorded arrival restrictions remain unchanged.

`ExplorationEventService::completionWitness` independently qualifies an actual tested arrival without calling InputPlan. Schema construction may offer Rest alongside forward movement and reconsideration when all relevant option targets are already explored or the current encounter qualifies. Traversal destinations are included in that target check, so a remaining unexplored reachable head still prevents an ancestor from treating its exploration as complete. An authorized homologous Order Traverse can coexist with a qualified local completion: visibility or navigation across that boundary does not make the foreign position a local causal continuation.

Ordinary actionless Rest still does not imply an Exploration Event. Pending Preparation or Instrument execution blocks Rest and event publication, including a locked recheck. Transient evidence shortage, unrecorded arrival and untested positions cannot establish completion.

Revision applicability follows only same-position revision edges within the exact entry/Horizon. A new forward child, or newly admissible Reconcile, Reclassify or Commit, releases suppression; unrelated structure and reconsideration without generating effects do not. Historical events remain immutable, independent for each Participant. The complete reachable frontier remains authoritative before presentation limits.
