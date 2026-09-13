# Participant continuity and relocation

A successful Commit creates local continuation. Phase 7 completes the current Cycle and stores the new Committal as the committing Participant's `current_proposition_id`. The next Cycle uses that Committal as both Gate and Provenance, even when it has no descendants and zero Potential.

## Entry paths

Previously, every positioned Participant's ordinary re-entry called `PotentialChargeService::selectFrom()`. A developed Origin could outrank a newly created Commit, sending its author away immediately. The waiting-state preview repeated that selection.

`CycleEntryService::continuationFor()` now distinguishes local continuity from relocation:

- A retained Committal with a snapshot is already the next local seed. No Potential ranking runs during either the continuity preview or entry.
- Non-tragic completion at an expressive Proposition resumes its own unambiguous Committal region. The expressive position remains stored until the next Cycle begins.
- Rest marks the local path exhausted. Recovery then uses the existing `recoverFrom()` search. A positioned Participant with no valid local Committal seed also needs recovery.
- Initial entry without a position still uses `selectFor()`. Origin creation keeps its existing bootstrap path.

Recovery keeps its existing nearest-viable-distance search, positive-Potential requirement, measurement attenuation and tie-breaks. Initial and relative Potential calculations are unchanged. There is no age, recency, Horizon-opening or new-Commit bonus. Origin can legitimately win recovery when its reachable region has viable continuation; without a viable destination, the Participant waits at the exhausted position.

The API reports `entry.mode = continuity` for local entry and retains `potential` / `selection = recovery` for relocation. Scheduled wakes and the existing entry controls accept both paths. Only the committing Participant's position changes; other Participants keep their own Cycles, Horizons and positions.

## Horizon ownership

Entry continues to use the causal Horizon resolver introduced by the preceding Horizon/Cycle PR. If Commit X opens H3, continuation at X enters H3 while it is open, with H3's Domain vocabulary. A later closure resolves entry through X's open causal descendants. Historical Horizons are never reopened and existing Cycles are never reassigned.

Recovery may select a Committal associated with another historical Domain or lineage. That choice positions the Participant toward its structure; it does not make the old Horizon open again. Entry still requires an open descendant in that selected lineage. Any later boundary-producing Commit establishes a new child Horizon with the opposite Domain relative to its own parent.

## Operational registers

`RegisterPosition` describes structural roles for comparisons: Activity/Signal, Frontier/Stance and Canon/Concord remain analogous. Correspondence calculations retain that structural mapping.

Operational eligibility requires an exact register. `ContextDomain::registerAt()` resolves the role through the Cycle's owning Horizon. Both database and hydrated `CommitRegionService` pools use that exact identity. Mediate validates its anchor as well as its Read and Gate candidates; Bias uses the exact register for both its population and retained aggregate direction; Order/Closure and Commit execution use the same scoped pool. Early Write, traversal, revision and instrument targets already use the exact Cycle-derived Phase register.

These checks preserve Commit Region boundaries and existing eligibility rules. They do not impose an extra Horizon-ID equality filter on same-Domain region members, restrict structural historical comparisons, or grant cross-Domain operations merely because two registers occupy the same role. Mediate continues to include superseded inputs for competing same-Domain reconciliations. No global "already superseded" restriction is restored.

Regression coverage includes real boundary and non-boundary Commits, zero-Potential continuity despite Origin winning relative Potential, automated continuity entry, Tragedy recovery to Origin without reopening history, both Domains' exact candidate pools through both query paths, opposite-Domain action rejection, Bias history isolation, and competing reconciliations.
