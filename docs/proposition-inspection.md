# Graph Proposition inspection

The graph owns selection, camera translation, density, lens and lineage isolation. Workspace owns only its expanded/collapsed state. The selected Inspector is a non-modal overlay inside the graph stage, beside the drag viewport and outside the translated canvas. Desktop uses a fixed upper-right card; mobile uses a scrollable bottom card. Nearby navigation keeps that placement stable. Selection reveals a node within the unoccupied area when needed; deselection never adjusts the camera. Workspace does not follow selection, and its former lock control is retired.

The Inspector contains Proposition and Field State tabs; **Closure is available only on Committals**. Previous/next lineage navigation and the deselect X live in its header. Tab state is local, and switching to an expressive node while Closure is selected shows Proposition content. No modal overlay or focus trap blocks graph exploration. Closing returns keyboard focus to the selected node without scrolling. The card does not inherit the drag surface’s `touch-action: none`, so mobile contents remain scrollable. Existing participant preview drawers retain their projected readouts and actions.

Field State uses three signed SVG measurement rails and a signed Tension gauge. These read the observation payload independently: no averaging or Closure calculations occur in React. The gauge uses the Mantine Indigo scale (9 → 6 → 3), with text and an explicit marker in both themes. Charge and Potential Charge remain available. No application dependency was added.

Closure uses two aligned SVG sparklines over frozen Commitment snapshots, oldest to selected. The backend follows creating Cycle `entry_committal_id` through the existing `CommittalLineageService`; timestamps and sibling records never select predecessors. Snapshot Depth/Cadence and their stored directions are returned verbatim. Missing legacy values remain unavailable. Both positive directions use Indigo 4, both negative use Indigo 9, and opposite directions use grey. Numeric zero does not select a new direction. Alignment is not represented as complete Commit eligibility.

`SpecialPropositionBadges` provides one registry and resolver for full cards, inspection content and Topology nodes. Tragedy and Renewal retain their existing detection and icons. Committal is a quiet bolt. All markers expose tooltip text, an accessible name and a native title; multiple markers wrap in a compact cluster. No decorative silhouettes were restored.

## Historical event detection

`PropositionInspectionService::loadMarkers` combines all four historical marker kinds into one batched UNION query, preserving the existing graph-read query ceiling. It never reruns action authorization or population calculations:

- **Confluence:** an expressive Bias result has one recorded `reclassify` supersession owned by its producing Phase and a matching `reclassification_results` source/target/register row. Its source is Mediate-produced expressive work in the same substrate. Either the source's creating Cycle has a different `entry_committal_id` from the Bias Cycle, or the source's exact two-input recorded Reconcile includes a non-Gate operand from another entry Committal. The latter requires both Reconcile edges, the producer's actual Gate edge, matching Phase IDs, and the incoming operand's substrate. Arbitrary two-parent nodes and ordinary same-region mediation/classification are not marked. “Foreign” here uses the existing Commit Region/entry-Committal distinction; separate expressive branches under the same entry are not a foreign-region Confluence.
- **Domain transition:** a Committal is the recorded opener of a child Context Horizon whose parent is its creating Cycle's Horizon. The frozen snapshot's before/after Domains must match the parent/child Domains, differ, and belong to the same substrate. Tension sign is never used. The marker is attached to that exact Committal.

These are presentation attributes only. Existing Tragedy/Renewal detection predicates, topology, movements, observables, cognition, Receipt scope and all eligibility services remain unchanged. Closure history is requested only for selected Committal detail, not the graph list.

## Navigation and review

Show Origin changes only the existing canvas offset using a 240ms CSS transform transition. This canvas has no graph-library camera or zoom API. Reduced-motion preferences disable the transition. Deselect preserves offset, density, lens, isolation preference and Workspace state.

Tests cover mounted selection/deselection and tabs, live detail refresh, both node densities, event labels, zero-direction colors, historical provenance and canonical snapshot ordering. `tests/ui/inspector-browser.mjs` reviews synthetic desktop/mobile layouts in both themes; the path-filtered Inspector browser review workflow uploads screenshots. It installs isolated browser test tooling only, without changing package manifests or the application bundle.
