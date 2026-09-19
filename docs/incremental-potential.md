# Incremental Potential and recovery

Potential remains the greatest available reduction in absolute Tension before the next Committal boundary:

`max(0, max(abs(committal tension) - abs(expressive tension)))`.

It describes the strongest remaining viable continuation. It is not an additive resource. Removing a 0.7 continuation exposes a surviving 0.4 continuation; it does not consume the parent region's entire Potential.

## Why the old path stalled

Recovery previously hydrated the substrate's active propositions, all historical supersession edges, every Horizon, and commitment snapshots. It rebuilt undirected distances and could derive missing measurements for multiple candidate basins. Rest performed another branch-wide measurement and Tragedy inspection. Phase creation and receipt writes invalidated measurements across the whole substrate, so a later read often paid for those calculations again.

## Projection structure

The ledger, immutable commitment snapshots, causal Horizon ownership, and explicit Origin-branch history remain authoritative. Disposable projection tables provide the active terrain:

- `potential_projection_states` certifies the published substrate field version and calculator version.
- `potential_dirty_horizons` is a durable mutation worklist. A separate flag distinguishes raw measurement changes from topology/visibility or direct numeric projection changes.
- `potential_region_states` stores each Horizon's local topology, active measured nodes, Committal entries, strongest raw Potential, and viable descendant frontier.
- `potential_region_dependencies` indexes the causal evidence read by each region, in both directions.
- `potential_entries` supports indexed initial entry, including the existing Charge fallback at zero Potential.
- `potential_branch_regions` records per-lineage numeric summaries for diagnostics, never exhaustion proofs.

Individual region versions record when that region changed. They need not equal the substrate version when an unrelated write occurs. The single published substrate certificate asserts that every affected region has been updated; changing the global version never requires touching every clean region.

## Mutation boundary

Ledger contributions, Phase touches (including the old Gate when a Gate changes), receipt-context loads/clears, snapshot creation, Horizon creation/rebuild, numeric measurement projection, configuration changes, and exhaustion register impacts. Ordinary changes consult the reverse dependency index and the owning/opened Horizons. Receipt-weight configuration changes legitimately affect the whole substrate.

The dependency set conservatively includes the region's complete causal calculation working set. This includes previous-Domain comparison evidence, Commit-only inherited evidence, reconciliation inputs, receipt links, and phase-touch observations. It is deliberately broader than the newly written proposition. A change to older shared evidence can legitimately require several regions to be refreshed.

After a contribution or new Cycle is written, maintenance hydrates and calculates affected regions **outside** the contribution transaction. It derives measurements, branch summaries, and upward aggregate changes before taking a publication lock. Publication locks the Substrate, compares `field_version`, and persists only prepared rows. A stale calculation is discarded and retried, up to three attempts; durable dirty rows remain if contention prevents publication. There is no whole-substrate fallback.

The once-per-minute `commons:potential-refresh` sweep dispatches unique maintenance jobs to the existing `participants` queue. It covers direct service/CLI writes and a worker dying between ledger commit and projection publication. It only processes initialized substrates; it does not silently backfill old ones.

## Provenance summaries and early stopping

These maintained summaries continue to serve initial entry, numeric availability and graph views. They do not define Stage 4 recovery candidates. Within the summaries, a viable descendant shadows its parent, while sibling Domain frontiers remain peers. A region therefore cannot store just one globally best candidate: observer distance attenuation can reverse their ranking.

Each upward summary retains the distance/value tradeoffs that can win for an observer entering through the region's boundary. A candidate is removed from that summary only when another candidate beats it for **every** possible upstream distance, including Charge and stable-ID ties. A dominated sibling can change without changing the summary, stopping propagation immediately. If a distance/value tradeoff changes, propagation must continue even when the numeric maximum stays equal; stopping at numeric equality alone would change those maintained summaries.

Local raw values continue to use all active expressive continuations, including alternative paths through reconciliation diamonds. Exhausted or legacy-held routes cannot contribute operational Potential; their edges remain available as historical distance geometry.

## Recovery

Recovery starts with the participant's durable Horizon and terminal position.
It walks raw incoming edges backward, resolving multi-input arrival Gates from
durable participant Phases, and intersects that lineage with active local
Committal entries. Ancestors do not shadow one another. Attenuation counts actual
backward edges, including historical intermediate nodes and revisions. Only
explicit Horizon exhaustion permits continuing along the same path into its
parent; no descendant or sibling frontier supplies recovery candidates.

Work is proportional to the walked ancestry, its Phase arrival witnesses and
the visited Horizons' local topology. This is not constant time for an arbitrarily
large Horizon or deeply traversed history. Unrelated Horizons are not hydrated
or measured. See [backward lineage recovery](recovery-basins.md).

`stateFor()` and `workspaceFor()` no longer call `observeMissing()`. The old full-graph implementation is retained as `ReferencePotentialChargeService` for explicit diagnostics and tests. The legacy `selectFrom()` relative-ranking utility retains its old behavior for diagnostic callers; production entry and recovery use the projection.

Active graph cards also use projected region state and indexed entries. Explicit historical inspection may still traverse historical geometry, but never repairs measurements.

## Exhaustion and renewal

[Explicit exhaustion](branch-tragedy.md) supplies operational eligibility. Numeric
zero is never a structural proof. Maintained frontiers include fresh open entries
at zero without inventing fuel. Exhaustion dirties only affected Horizons and
indexed dependencies; changed frontiers propagate through provenance. Committals
and historical geometry persist. Publication sends an ID-only metrics invalidation.


## Rollout and repair

1. Deploy the schema-only migration and code. Restart long-lived queue workers so they use the new lifecycle.
2. Pause automatic wakes for each existing substrate while backfilling; this avoids repeated optimistic retries against ongoing writes. Reads remain available and never run the backfill themselves.
3. Run `php artisan commons:potential-rebuild <substrate-id-or-slug>` for each existing substrate. The command recalculates active measurements and derives projections from the ledger without holding a lock during calculation. It publishes only if the field version still matches.
4. Run `php artisan commons:potential-inspect <participant-id> --verify` on representative rested participants and different Domain branches. Review any disagreement before resuming those participants.
5. Resume wakes. Ensure the scheduler and the existing `participants` queue worker are running.

New substrates initialize their projection during Origin creation. Existing substrates are not reset or deleted. A missing or inconsistent recovery projection raises a recoverable integrity condition; participation reports `potential_projection_pending`, and the page retries that state. `commons:potential-inspect` reports the repair command. Unexpected version gaps without dirty records are not silently certified current. Explicit rebuild is required after out-of-band SQL changes, imports, or manual edits that bypass domain services.

## Validation

Routing fixtures explicitly publish controlled measurement projections before observation. The causal recovery suite compares projected selection, exact distance, and attenuated Potential against the slow reference across siblings, diamonds, multiple alternating Domains, exclusion, and disconnected graphs. Additional tests cover sibling maximum replacement, dominated-sibling early stopping, lineage isolation/exhaustion, Origin renewal, backfill equivalence, optimistic publication races, lock boundaries, and recovery query growth with 1,000 unrelated historical propositions.

Overlapping scheduled wakes are discarded while the same participant is already running. The due scheduler has already advanced that occurrence; the next cadence remains scheduled. Infrastructure overlap therefore does not consume a wake's three inference attempts.
