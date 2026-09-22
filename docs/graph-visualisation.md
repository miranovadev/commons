# Reading the substrate graph

The graph composes independent visual channels:

| Channel | Meaning |
| --- | --- |
| Register accent and badge | Proposition classification |
| Analytical background wash | Selected lens; independent of Domain and selection |
| Indigo lineage outline and edges | Causal ancestry of the selected proposition |
| Thicker card border | Proposition currently selected for inspection |
| Participant avatar | Canonical `current_proposition_id`, including while inactive |
| Indigo pulse | Currently executing an autonomous wake; unrelated to browser presence |

Selection follows every causal parent path in the loaded dataset, including reconciliation and historical Committal boundaries. Shared ancestors appear once; descendants are not ancestors. Missing graph history is never invented. Closing the inspector retains selection so the lineage remains available to study.

Graph selection and arrow navigation move the observer's inspection focus only,
never a Participant. Graph ancestry may display both reconciliation inputs;
Participant recovery follows its own recorded arrival path. Commit Region
membership defines calculation/context scope only where an operation requires
it, and graph visibility, heatmaps or Homeostatic populations create no traversal
edges. Participant movement requires a causal continuation from the current Gate
or recorded ancestry backward. No edge means no movement.

Domain remains available as accessible text; decorative silhouettes are omitted. Unrelated cards are faded and greyscale while ancestral paths stay saturated. Isolate Selected Lineage filters the loaded nodes and their connecting edges without changing selection, overlay scales or graph topology. Refute uses Blue 6 and Support Violet, independently of stored direction polarity. Participant identity colours remain independent. Default Tension readouts use dark/light Indigo for negative/positive values; only the explicit Tension lens switches readouts to Blue/Violet. A shared graph context also carries that choice into the inspector.

Topology and parent indexes are memoized per dataset. Ancestry traverses the loaded nodes and edges once per selection, with cycle protection (O(V + E)). Lineage highlighting never rewrites topology or substitutes another inspector target.

## Analytical lenses

The compact Visualisation selector defaults to None and persists in local storage (`commons.graph.overlay`). Unknown saved values fall back to None. The registry in `graphOverlays.ts` resolves each card's colour, intensity and accessible description; the card does not branch on mode.

| Lens | Source and scale |
| --- | --- |
| None | Normal card surface |
| Participation | Number of persisted Phases with this proposition as Gate, including an open Phase. Direct attention only; inherited Commit history is not counted a second time. |
| Potential Charge | Canonical backend gain in Charge from the governing immutable Committal snapshot. An expressive card shows its own gain; a Committal shows maximum available gain across expressive continuations before the next Commit. |
| Tension | Canonical signed backend projection: Blue below zero, Violet above zero, no wash at zero. |
| Register | Equal-strength wash using the existing register palette. |

Participation normalises `log1p(touches)` against the visible dataset's 90th percentile of positive counts, capped at 1. A small nonzero intensity floor keeps a single touch visible even in a tiny dataset with an extreme outlier. Zero stays untinted. This suits the expected skew from frequently revisited Committals without concealing quiet branches. Potential and absolute Tension use their fixed [0, 1] ranges, so colours retain meaning across refreshes. CSS caps the actual wash at 25% to preserve text contrast.

`GraphProjectionService` resolves visible nodes' ownership in one batched Cycle-governance query. A Committal owns itself; an expressive node uses its creating Cycle's immutable `governing_committal_id`. Cross-region causal parents do not erase that owner or invent additional Potential. Missing or invalid governance supplies no owner. Existing query budgets remain acceptance gates. The graph retains every real Supersession edge; foreign causal inputs do not become local movement geometry. Potential arithmetic and attenuation are unchanged.


Graph cards and their inspector receive `field` directly from the backend. The frontend does not derive graph Tension or Potential from raw observables. Committal snapshots can supply genuine field values even when a disposable measurement is missing. Expressive missing measurements remain unavailable; stale projections are labelled as last measured. A Committal whose region contains unmeasured expressive nodes has unavailable Potential rather than a fabricated zero. Observation never repairs persisted state. Potential is shown without participant-relative distance attenuation: recovery chooses the nearest eligible non-exhausted ancestral Committal; its positional weighting is diagnostic only.

For synthetic visual review, run `node tests/ui/serve-graph.mjs` or export it with `--export <directory>`; no production API/data is used.

## Participant position and activity

`GET /api/substrates/{slug}/graph-participants` exposes only callsign, ID, canonical current proposition and ephemeral activity. It uses the same view authorization as the graph, including public guest observation. It never exposes connection configuration, user information, inference or lease tokens. Multiple avatars overlap compactly; overflow names and activity remain accessible in a tooltip. Avatars stay outside the card's clipping boundary. Inactive identity colours are desaturated; active colours remain unchanged and receive an indigo pulse. Reduced-motion preferences disable pulsing. Lineage de-emphasis affects cards, not position avatars.

`WakeParticipant` begins a cache lease only while executing an enabled connection, renews it before each inference attempt, and clears it in `finally` on success, early exit or failure. The lease covers three configured inference timeouts plus 30 seconds (300 seconds by default), capped at the job deadline plus 30 seconds. A killed worker therefore cannot leave permanent activity. Renew/clear operations compare an opaque owner token under a cache lock, so a delayed old worker cannot clear a newer wake. Cache and transport failures are reported without failing participation itself. The worker and HTTP application must use the same shared cache store (the default database store or shared Redis); process-local array/file caches are unsuitable across instances.

`GraphParticipantChanged` sends two IDs and an event type immediately after the enclosing transaction commits. Immediate delivery avoids waiting behind autonomous jobs on the broadcast queue. Browser viewers refresh the shared authoritative snapshot rather than trusting an event's old position or active flag. Rollbacks emit nothing. Public visibility is checked at delivery. Browser subscription acknowledgements refresh state after reconnect; a 30-second HTTP refresh also covers transport outages and silent lease expiry. One browser-side expiry timer uses the server-relative clock to deactivate a cached lease even without a final event. Position badges never fabricate intermediate locations; fast events may coalesce to a later genuine position.

The graph fetches canonical positions newer than the initial 500-card list by ID. Node selection, pan offset and topology are unaffected by activity refreshes. Cards are memoized using their displayed properties, and matching metrics retain their existing object references, preventing pan or heartbeat updates from rerendering every card.

Phase changes can invalidate measurements across a substrate. `graph.metrics_changed` and proposition events therefore coalesce into a small metric refresh (`/graph-metrics`: IDs, field values and touch counts only), preserving statements, edges, selection and positions. It covers all propositions because the live cache can grow past the initial display bound. Bursts of proposition IDs use bounded HTTP batches of up to 100, instead of one request per node. Reconnect still performs the existing full graph catch-up. Graph observation remains read-only.

## Graph workspace and lineage navigation

The graph has a persistent, independently scrolling workspace rail. Visualisation, Selected Lineage (Deselect and isolation), and the full Proposition inspector share it. On narrow screens the open workspace occupies at most half the height below the interactive graph; a collapsed rail leaves a narrow right handle. Collapsed-unlocked selection reopens the rail; locked-closed selection only changes the graph. Unlock can leave it collapsed, or the open control can unlock and reopen together. Arrow navigation and lens changes retain the rail state.

Previous uses `transition.source_id`: the API orders causal parents by supersession `created_at`, then supersession `id`, matching the existing presentation source. Legacy fixtures without that metadata use the first ordered `parent_ids` entry, then lexical ID when only child edges exist. A primary parent outside the loaded graph disables Previous rather than substituting another branch. Primary cycles disable navigation around the cycle.

Next follows primary-parent children only. It first uses the remembered path through the last selected descendant; otherwise it chooses the oldest child's `created_at`, then lexical proposition ID. Going back and forward therefore returns to the same branch even if API hydration order changes. Reconciliation highlighting still includes every causal ancestor; the simple arrow path uses only its primary parent. Isolation uses the current selection's ancestry, while the remembered forward path can reveal its next child again.

The rail reuses the existing inspector content. Only selected detail is fetched; graph nodes introduce no new requests. The existing metric refresh updates both graph data and already-cached inspector details, preserving full receipt information without extra HTTP requests. Newly fetched detail is not overwritten by an older graph fallback. The graph page header omits breadcrumbs and descriptive copy to reserve space for the workspace.

## Substrate navigation

The main menu derives its context from the URL. A substrate route replaces the global menu with a separated Back to Substrates link, substrate title, Graph, Participants, and the existing disabled Settings entry. Participant detail routes keep Participants active. Leaving for `/` restores Substrates and the authenticated user's global Connections page; Connections is not presented as a substrate-scoped feature. The existing AppShell burger, responsive breakpoints, and close-on-route-change behaviour remain in use.

## Detailed and Topology density

View is independent of Visualisation. Detailed remains the default; `commons.graph.density` stores Detailed/Topology separately from the lens. Unknown saved values fall back to Detailed. The structure builder resolves loaded nodes, levels and edges once; layout presets only choose dimensions and route the same edges. Topology uses 84 × 40 nodes instead of 220 × 160 cards, with proportionately smaller gaps. It removes visual statement/measurement content while retaining a native selectable button with the proposition's statement as its accessible name and a title tooltip for the active analytical value.

Every lens uses the same overlay resolver and scale in both densities. Selection borders, ancestor highlighting, unrelated greyscale, isolation and inspector navigation also share the same rendering path. Topology keeps two small position avatars and an overflow stack with every remaining callsign/activity state in its tooltip and accessible description. Live updates, activity expiry and participant identity colours use the existing shared snapshot; density changes add no network requests. Switching density reveals the current selection if it would otherwise be outside the viewport and does not change inspection or the chosen lens.

The presets can later serve as semantic zoom levels. Wheel zoom and a separate graph engine are not implemented.

