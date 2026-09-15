# Reading the substrate graph

The graph composes independent visual channels:

| Channel | Meaning |
| --- | --- |
| Clipped SunWind / SunHigh silhouette | Settlement / Emergence, from the proposition's owning Context Horizon |
| Register accent and badge | Proposition classification |
| Analytical background wash | Selected lens; independent of Domain and selection |
| Indigo lineage outline and edges | Causal ancestry of the selected proposition |
| Strong selection ring | Proposition currently selected for inspection |

Selection follows every causal parent path in the loaded dataset, including reconciliation and historical Committal boundaries. Shared ancestors appear once; descendants are not ancestors. Missing graph history is never invented. Closing the inspector retains selection so the lineage remains available to study.

Domain silhouettes are decorative and hidden from assistive technology; the Domain is also supplied as text. An unavailable Context Horizon does not receive a guessed watermark. Refute is canonically Blue and Support Violet, independently of stored direction polarity. Participant identity colours remain independent.

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

`GraphProjectionService` performs five fixed queries for the substrate graph, measurements, edges, snapshots and visible Phase counts. The 500-card display bound does not truncate the calculation's history or continuations. Multi-parent region ownership uses the same stop-at-first-Committal rule as `CommitRegionService`; ambiguous cross-region ancestry has no invented Potential. Traversals use in-memory indexes: at worst O(K(V + E)) for K visible nodes, with no per-node SQL. Existing 500-node query-budget tests cover the fixed query count.

Graph cards and their inspector receive `field` directly from the backend. The frontend does not derive graph Tension or Potential from raw observables. Committal snapshots can supply genuine field values even when a disposable measurement is missing. Expressive missing measurements remain unavailable; stale projections are labelled as last measured. A Committal whose region contains unmeasured expressive nodes has unavailable Potential rather than a fabricated zero. Observation never repairs persisted state. Potential is shown without participant-relative distance attenuation: recovery applies its own positional weighting when choosing a move.

For synthetic visual review, run `node tests/ui/serve-graph.mjs` or export it with `--export <directory>`; no production API/data is used.
