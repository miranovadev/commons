# Participant-relative exploration

A Participant explores an expressive position by judging it. Both Support and Refute count. The plan derives the explored badge from the Participant’s effective Attestations in the hydrated graph. Each new expression begins personally unjudged, including a Revision at an existing position. A newer scoped observation reopens judgment while the last effective stance continues to count. A known judgment in another Horizon is still personal history. Movement from the current Gate has its own eligibility checks. Authorship, votes by other Participants and Rest never cast a vote on the Participant’s behalf.

| Decision | Authority |
| --- | --- |
| Offer a first judgment | A mutable, eligible Write at this Phase. A hard relationship can retire an independent head while its operand still qualifies as a Write. |
| Offer reconsideration | An ordinary recorded observation in the expression’s causal catalogue has a higher substrate-ledger sequence than the latest personal Attestation. Either direction is available on a lawful encounter. The new Attestation supersedes the previous one, including on reaffirmation. |
| Offer expressive Traverse | A base-authorised hop that leads to a lawful new judgment, acquisition, Revision, Reconcile, Reclassification, Commit or Committal exit. Each subsequent hop must qualify in its own operating context. |
| Offer fresh acquisition | The Participant has judged the viable frontier, its heads offer no advancing generation, and the ordinary structural and evidence requirements are met. An admitted attempt retains its own resolution contract. |
| Offer Rest | The only remaining choice is optional reconsideration, or no action remains. Pending evidence retains its resolution priority. |

Judging a new child leaves its parents’ judgment histories intact. A new child can make a return through a judged parent worthwhile. Actual expressive Traverse uses the effective judgment on the **exact** Write from an earlier Cycle. Committed-source passage and Order’s canonical Committal exits retain their independent authority. The two causal crossover routes use the recorded edge and the destination’s governing context.

An Exploration Event records a qualifying Rest encounter. Publication still checks the actual arrival, personal route, exact head judgment and field version inside the contribution transaction. Historical witnesses remain available for inspection. Recovery follows the current causal Region.

## Receipt visibility and Revision

The current Gate determines the displayed catalogue. Its own observations come first, followed by observations on its causal ancestors in the same Region, nearest first. At a governing Committal, the actual committed lineage carries evidence from the immediately preceding Region. A second Commit ends that carry. Write statements remain visible before arrival. Selecting a Write as the next Gate brings its own observations into scope. A personal load preserves the observation in history and supplies its body when it is in scope. Special Tragedy warrants retain their own access rules.

The judgment cutoff uses the observation’s original ledger entry. Loading, clearing or consuming a Receipt creates no new observation. A prior stance continues to supply the effective Orientation until the Participant rejudges. Cadence retains each recorded act.

A Participant with an effective Refute from an earlier Cycle may revise a Variant at its producing Write position, including another author’s Variant with existing continuations. Revision can draw on already consumed observations and requires no fresh acquisition. The first direct Revision of an exact expression closes it to further Revision by any Participant, even if its child later becomes unavailable. The new expression starts unjudged and can itself be revised after a qualifying personal Refute. The sibling has a soft Variant edge from the common parent and a hard Revision edge from the refuted expression. Both expressions occupy the same Phase row. The original continues on its other paths and contributes no measurement to the revised branch. Historical single-edge Revisions and sources with multiple direct Revision children retain their recorded provenance.

## Historical Stage B design and validation

The following records the preceding event-based policy for comparison. The rules above govern current participation.

> Personal route completion and witness provenance are refined by [Participatory Exploration Completion — Stage 3](participatory-exploration-completion.md). Its requirements supersede the earlier head-only encounter predicate below.

Stage B replaces shared continuation failures and Exploration Epochs on a **fresh database**. Authority: Claire's approved 25 October 2026 handoff, “Exploration Events, Active Heads and Recovery”, the subsequent forward-generation correction, and the ten Core Invariants. The separate rolling Attestation Cadence correction is outside this change.

An immutable Exploration Event records one Participant's completed Rest encounter at an expressive continuation head. Its creating Phase establishes the Participant, Cycle, entry Committal and Context Horizon. The witness preserves arrival, effective personal judgment, route completion and the guarded absence of forward-generating opportunities. Events do not mutate Propositions, retire Regions, change another Participant's options, or assert global failure.

Current status is derived. Follow the complete authorized forward frontier within the acting Cycle's Region and Horizon; stop at Committal boundaries. Hard-superseded inputs are history, not independent heads. Local Reconcile results retain their existing Attestation routes through either local operand. Foreign Reconcile operands, homologous comparison members and foreign selected sources do not grant movement. An ancestor is explored only when every reachable active head has an applicable personal encounter and no newly lawful forward-generating operation. A new Cycle or field version is not an exploration reset. A retired input with no active authorized head does not require an invented encounter and does not independently block Variant.

Base action lawfulness precedes personal selection. Ordinary Attestation reversals and Revise require a personally unexplored Write. Neither availability nor execution alone renews exploration or prevents Variant. Revision replaces the same position: applicable personal encounters follow that exact revision ancestry without mutating the events, and the resulting head is checked for forward-generating admissibility. Existing traversal opportunities use personal exploration and reachable generation. Concrete Revise, Reconcile, Reclassify and Commit opportunities retain their existing structural, measurement, evidence and lifecycle authority. Fresh Variant is excluded from its own prerequisite frontier and is considered only after existing relevant heads are explored.

Rest publication is atomic with its existing completion transaction and unique by Rest Phase. Stale, rolled-back, incomplete, unjudged or Preparation-only encounters do not establish exploration. An event remains immutable after a later continuation releases its current restriction. Personal state notifications refresh the actor; existing structural and evidentiary notifications still refresh their ordinary consumers.

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

Comparison boundaries are batched by canonical position within the acting entry/Horizon. Directed incoming Bias relationships and bound Reconciliation operands are queried in batches. Context-local terminal results are shared by Horizon; snapshot relations are populated from the existing batch. No persistent exploration cache or new observation counter is introduced.

See [validation and acceptance coverage](participant-relative-exploration-validation.md) for exact results and remaining limitations.

## Forward generation, forward movement and reconsideration

| Category | Operations | Exploration effect |
| --- | --- | --- |
| Forward generation | Variant, Reconcile, Reclassify, Commit | New authorized active structure or newly lawful generation can reopen the relevant frontier |
| Forward movement | Traverse | Reaches an unexplored Write or a specific lawful generating action along authorised Traverse hops. Movement preserves personal completion. |
| Reconsideration | Attestation, Revise | Available on unexplored Writes. Personally explored Writes stay visible with their explored badge and have no reconsideration actions. |

`ContinuationOpportunityService::nonVariantGenerationAvailable` evaluates base Reconcile, Reclassify and Commit lawfulness. Fresh Variant is deliberately excluded: it must never supply its own prerequisite for reopening or authorizing another Variant. Existing reachable Variants remain structural continuations in active-head discovery. Acquisition is required only for an admitted fresh Variant attempt; exploring an existing route no longer requires historical Gate acquisition. First effective personal judgment, active-route authority and forward-generation reopening remain required.

Each Participant’s unexplored reachable Writes take priority over fresh acquisition. The [retired Crossover calculation](rest-crossover-experiment.md) has no role in that decision or in publishing an Exploration Event.

`ParticipantExplorationService::canGenerateFromHead` uses that non-recursive predicate for each historical active head. An explored Write can be a Traverse destination if the Participant can reach a lawful Variant, Reconcile, Reclassify or Commit through it. Every hop requires its ordinary movement authority. The evaluation uses the current Cycle, register, Region, Horizon and arrival path. At a potential Variant Gate it checks whether acquisition could begin there. Acquisition and contribution occur only after arrival. A new request evaluates the route again. See the [reproduced second Disperse journey](generative-traversal.md).

`ExplorationEventService::completionWitness` independently qualifies an actual tested arrival without calling InputPlan. Schema construction may offer Rest alongside forward movement and reconsideration when the current encounter qualifies. With no actionable Traverse, the all-targets-explored check also remains available. A Write that offers a Traverse toward new generation cannot justify Rest solely through its explored badge. An authorized homologous Order Traverse can coexist with a qualified local completion: visibility or navigation across that boundary does not make the foreign position a local causal continuation.

Ordinary actionless Rest still does not imply an Exploration Event. Pending Preparation or Instrument execution blocks Rest and event publication, including a locked recheck. An unrecorded arrival or a missing lawful personal judgment prevents completion. A shortage of ordinary Receipts alone does not.

Revision applicability follows only same-position revision edges within the exact entry/Horizon. A new forward child, or newly admissible Reconcile, Reclassify or Commit, releases suppression; unrelated structure and reconsideration without generating effects do not. Historical events remain immutable, independent for each Participant. The complete reachable frontier remains authoritative before presentation limits.
