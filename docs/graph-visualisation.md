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

Domain remains available as accessible text; decorative silhouettes are omitted. Unrelated cards are faded and greyscale while ancestral paths stay saturated. Isolate Selected Lineage filters the loaded nodes and their connecting edges without changing selection, overlay scales or graph topology. Refute uses Blue 6 and Support Violet, independently of stored direction polarity. Participant identity colours remain independent. Default Tension readouts use dark/light Indigo for negative/positive values; only the explicit Tension lens switches readouts to Blue/Violet. A shared graph context also carries that choice into the inspector.

Topology and parent indexes are memoized per dataset. Ancestry traverses the loaded nodes and edges once per selection, with cycle protection (O(V + E)). Selection never changes topology or the inspector target.

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

`GraphProjectionService` performs five fixed queries for the substrate graph, measurements, edges, snapshots and visible Phase counts. The 500-card display bound does not truncate the calculation's history or continuations. Multi-parent region ownership uses the same stop-at-first-Committal rule as `CommitRegionService`; ambiguous cross-region ancestry has no invented Potential. A topological pass resolves seeds in O(V + E) for valid graphs, with a cycle-protected fallback for malformed legacy data. Potential traverses each Committal's expressive region, stopping at further Commits. There is no per-node SQL. Existing 500-node query-budget tests cover the fixed query count.

Graph cards and their inspector receive `field` directly from the backend. The frontend does not derive graph Tension or Potential from raw observables. Committal snapshots can supply genuine field values even when a disposable measurement is missing. Expressive missing measurements remain unavailable; stale projections are labelled as last measured. A Committal whose region contains unmeasured expressive nodes has unavailable Potential rather than a fabricated zero. Observation never repairs persisted state. Potential is shown without participant-relative distance attenuation: recovery applies its own positional weighting when choosing a move.

For synthetic visual review, run `node tests/ui/serve-graph.mjs` or export it with `--export <directory>`; no production API/data is used.

## Participant position and activity

`GET /api/substrates/{slug}/graph-participants` exposes only callsign, ID, canonical current proposition and ephemeral activity. It uses the same view authorization as the graph, including public guest observation. It never exposes connection configuration, user information, inference or lease tokens. Multiple avatars overlap compactly; overflow names and activity remain accessible in a tooltip. Avatars stay outside the card's clipping boundary. Inactive identity colours are desaturated; active colours remain unchanged and receive an indigo pulse. Reduced-motion preferences disable pulsing. Lineage de-emphasis affects cards, not position avatars.

`WakeParticipant` begins a cache lease only while executing an enabled connection, renews it before each inference attempt, and clears it in `finally` on success, early exit or failure. The lease covers three configured inference timeouts plus 30 seconds (300 seconds by default), capped at the job deadline plus 30 seconds. A killed worker therefore cannot leave permanent activity. Renew/clear operations compare an opaque owner token under a cache lock, so a delayed old worker cannot clear a newer wake. Cache and transport failures are reported without failing participation itself. The worker and HTTP application must use the same shared cache store (the default database store or shared Redis); process-local array/file caches are unsuitable across instances.

`GraphParticipantChanged` sends two IDs and an event type immediately after the enclosing transaction commits. Immediate delivery avoids waiting behind autonomous jobs on the broadcast queue. Browser viewers refresh the shared authoritative snapshot rather than trusting an event's old position or active flag. Rollbacks emit nothing. Public visibility is checked at delivery. Browser subscription acknowledgements refresh state after reconnect; a 30-second HTTP refresh also covers transport outages and silent lease expiry. One browser-side expiry timer uses the server-relative clock to deactivate a cached lease even without a final event. Position badges never fabricate intermediate locations; fast events may coalesce to a later genuine position.

The graph fetches canonical positions newer than the initial 500-card list by ID. Node selection, pan offset and topology are unaffected by activity refreshes. Cards are memoized using their displayed properties, and matching metrics retain their existing object references, preventing pan or heartbeat updates from rerendering every card.

Phase changes can invalidate measurements across a substrate. `graph.metrics_changed` and proposition events therefore coalesce into a small metric refresh (`/graph-metrics`: IDs, field values and touch counts only), preserving statements, edges, selection and positions. It covers all propositions because the live cache can grow past the initial display bound. Bursts of proposition IDs use bounded HTTP batches of up to 100, instead of one request per node. Reconnect still performs the existing full graph catch-up. Graph observation remains read-only.
