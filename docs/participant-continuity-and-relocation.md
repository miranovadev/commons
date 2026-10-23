# Participant continuity and relocation

All movement is lineage-constrained. Forward progression follows an explicit
causal continuation from the current Gate; recovery walks recorded ancestry
backward. Commit Region membership, Context Horizon membership, matching
phase/register position, visibility and greater Potential cannot relocate a
Participant. No edge means no movement.

A successful Commit creates local continuation. Phase 7 completes the current Cycle and stores the new Committal as the committing Participant's `current_proposition_id`. After the terminal write commits, preparation creates the next Cycle and Phase 1, using that Committal as both Gate and Provenance, even when it has no descendants and zero Potential.

## Entry paths

Previously, every positioned Participant's ordinary re-entry called `PotentialChargeService::selectFrom()`. A developed Origin could outrank a newly created Commit, sending its author away immediately. The waiting-state preview repeated that selection.

`CycleEntryService::continuationFor()` now distinguishes local continuity from relocation:

- The retained Committal reached at normal terminal completion is already the
  next local seed. No Potential ranking runs during either continuity preview
  or entry; a snapshot elsewhere does not authorise relocation.
- Ordinary terminal completion is at the exact Committal reached through Commit
  or its direct existing Commit edge. An anomalous retained expressive position
  is not authority to jump to any Committal in its region; recovery must follow
  its recorded backward lineage.
- Rest ends the Cycle and supplies a possible exploration witness. Only the explicit structural proof can mark a path or basin exhausted; an empty fresh basin remains open. Recovery uses the maintained `recoverFrom()` search and may retain that basin. A positioned Participant with no valid local Committal seed also needs recovery.
- Initial entry without a position still uses `selectFor()`. Origin creation keeps its existing bootstrap path.

Recovery walks backward along the participant's actual lineage, starting in the completed Cycle Horizon. The nearest eligible non-exhausted Committal on that path is selected. A multi-input Write uses the participant's recorded arrival Gate; inherited history follows creating provenance. After genuine Horizon exhaustion, the same walk can continue into the immediate parent. See [recovery basins](recovery-basins.md).

Each Horizon contains its opening boundary Committal and non-boundary Committals created there. Recovery stops at the first eligible Committal on the actual backward path, regardless of older ancestors' Potential or Charge. An eligible retained Committal is the distance-zero boundary. Only explicit exhaustion permits recovery past an unresolved basin; numeric zero is not proof. Attenuated Potential and Charge remain available as diagnostics for the selected destination and do not select it.

Raw Potential still uses measurable outgoing expressive continuations up to the next Committal, restricted to the candidate's Domain basin. Snapshots and measurements retain their existing definitions. Initial entry, ordinary continuity, and local Phase traversal remain unchanged. Origin remains eligible through provenance, including at zero Potential. A qualifying direct expressive Write encounter with resulting canonical Charge zero establishes the Region’s unique Tragedy and Receipt and permanently terminates that continuation. Resolution prohibits Variant and Revise throughout the Region while preserving ordinary authority on viable routes. Exhaustion follows atomically only when no lawful continuation remains; viable siblings and live committed descendants prevent it. Descendant Exhaustion closes only its corresponding route. No second catastrophe, Rest or Receipt consumption is required. Passive measurements and reads establish neither event. Recovery follows actual recorded backward lineage. See the approved [Tragedy/Resolution lifecycle](tragedy-resolution-target.md). Origin remains persistent even when its Horizon eventually exhausts.

Ordinary terminal responses now return `ready` in the eagerly prepared Phase 1. For initial or legacy unpositioned state, the API reports `entry.mode = continuity` for local entry and retains `potential` / `selection = recovery` for relocation. A wake prepares available entry without inference. See [continuous Cycles](continuous-cycles-and-receipt-relevance.md), including `recovery_pending` for retryable entry and `recovery_unavailable` for a genuinely invalid/unavailable entry. Only the committing Participant's position changes; other Participants keep their own Cycles, Horizons and positions.

## Horizon ownership

If Commit X directly opens H3, selecting X enters H3 even after H3 has a recorded closure boundary. If X is a non-boundary Committal, selecting X enters its own creating Horizon. It never enters an arbitrary open descendant of H3.

Recovery can move backward across any number of Domains. The selected Horizon supplies the new Cycle's vocabulary: Signal/Stance/Concord for Settlement, Activity/Frontier/Canon for Emergence. Historical Horizon closure markers, ownership and boundary snapshots remain intact. Later boundary-producing Commits can establish additional direct child Horizons of that historical owner.

## Operational registers

Commit Region membership defines calculation/context scope only where a Commons
operation explicitly requires it. It never creates a traversal edge. Mediate —
Read, Bias — Gate and Closure / Order — Write may evaluate their specified
regional populations; the Participant remains on its own causal lineage.
Register/phase compatibility, contextual visibility and calculation membership
do not replace structural authority from the current Gate.

`RegisterPosition` describes structural roles for comparisons: Activity/Signal, Frontier/Stance and Canon/Concord remain analogous. Correspondence calculations retain that structural mapping.

Operational eligibility requires an exact register. `ContextDomain::registerAt()` resolves the role through the Cycle's owning Horizon. Both database and hydrated `CommitRegionService` pools use that exact identity. Mediate validates its anchor as well as its Read and Gate candidates; Bias selects heads in the exact register and compares each with its own causal lineage, excluding that head; Order/Closure and Commit execution use the same scoped pool. Early Write, traversal and revision targets use the exact Cycle-derived Phase register. Instrument targets only the current Persist Gate, not the Phase's Write register.

These checks preserve Commit Region boundaries and existing eligibility rules. They do not impose an extra Horizon-ID equality filter on same-Domain region members, restrict structural historical comparisons, or grant cross-Domain operations merely because two registers occupy the same role. Mediate continues to include superseded inputs for competing same-Domain reconciliations. No global "already superseded" restriction is restored.

Regression coverage includes real boundary and non-boundary Commits, zero-Potential continuity despite Origin winning relative Potential, automated continuity entry, Tragedy recovery through ancestral Committals and later outward Exhaustion recovery while retaining history, both Domains' exact candidate pools through both query paths, opposite-Domain action rejection, Bias history isolation, and competing reconciliations.
