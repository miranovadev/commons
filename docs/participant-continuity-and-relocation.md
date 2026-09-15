# Participant continuity and relocation

A successful Commit creates local continuation. Phase 7 completes the current Cycle and stores the new Committal as the committing Participant's `current_proposition_id`. The next Cycle uses that Committal as both Gate and Provenance, even when it has no descendants and zero Potential.

## Entry paths

Previously, every positioned Participant's ordinary re-entry called `PotentialChargeService::selectFrom()`. A developed Origin could outrank a newly created Commit, sending its author away immediately. The waiting-state preview repeated that selection.

`CycleEntryService::continuationFor()` now distinguishes local continuity from relocation:

- A retained Committal with a snapshot is already the next local seed. No Potential ranking runs during either the continuity preview or entry.
- Non-tragic completion at an expressive Proposition resumes its own unambiguous Committal region. The expressive position remains stored until the next Cycle begins.
- Rest marks the local path exhausted. Recovery then uses the existing `recoverFrom()` search. A positioned Participant with no valid local Committal seed also needs recovery.
- Initial entry without a position still uses `selectFor()`. Origin creation keeps its existing bootstrap path.

Recovery follows incoming Supersession edges from the exhausted position through every ancestral path, including multiple Domain boundaries. Shared ancestors are deduplicated at their minimum causal distance. It compares all measured ancestral Committals with positive raw Potential after multiplying by the existing `1 / (1 + d)^2` attenuation. A farther candidate can beat a nearer positive one. Effective Potential ties use effective Charge, then shorter distance and stable ID. There is no zero-Potential fallback, age bonus, recency bonus, descendant/sibling traversal, or self-entry at the exhausted position.

Raw Potential still uses measurable outgoing expressive continuations up to the next Committal, excluding the exhausted position. Measurements and immutable snapshots are not discounted again. Initial and relative selection retain their existing geometry; this directional search is specific to recovery.

The API reports `entry.mode = continuity` for local entry and retains `potential` / `selection = recovery` for relocation. Scheduled wakes and the existing entry controls accept both paths. Only the committing Participant's position changes; other Participants keep their own Cycles, Horizons and positions.

## Horizon ownership

If Commit X directly opens H3, selecting X enters H3 even after H3 has a recorded closure boundary. If X is a non-boundary Committal, selecting X enters its own creating Horizon. It never enters an arbitrary open descendant of H3.

Recovery can move backward across any number of Domains. The selected Horizon supplies the new Cycle's vocabulary: Signal/Stance/Concord for Settlement, Activity/Frontier/Canon for Emergence. Historical Horizon closure markers, ownership and boundary snapshots remain intact. Later boundary-producing Commits can establish additional direct child Horizons of that historical owner.

## Operational registers

`RegisterPosition` describes structural roles for comparisons: Activity/Signal, Frontier/Stance and Canon/Concord remain analogous. Correspondence calculations retain that structural mapping.

Operational eligibility requires an exact register. `ContextDomain::registerAt()` resolves the role through the Cycle's owning Horizon. Both database and hydrated `CommitRegionService` pools use that exact identity. Mediate validates its anchor as well as its Read and Gate candidates; Bias selects heads in the exact register and compares each with its own causal lineage, excluding that head; Order/Closure and Commit execution use the same scoped pool. Early Write, traversal, revision and instrument targets already use the exact Cycle-derived Phase register.

These checks preserve Commit Region boundaries and existing eligibility rules. They do not impose an extra Horizon-ID equality filter on same-Domain region members, restrict structural historical comparisons, or grant cross-Domain operations merely because two registers occupy the same role. Mediate continues to include superseded inputs for competing same-Domain reconciliations. No global "already superseded" restriction is restored.

Regression coverage includes real boundary and non-boundary Commits, zero-Potential continuity despite Origin winning relative Potential, automated continuity entry, Tragedy recovery to Origin in its direct Settlement child while retaining historical closure markers, both Domains' exact candidate pools through both query paths, opposite-Domain action rejection, Bias history isolation, and competing reconciliations.

