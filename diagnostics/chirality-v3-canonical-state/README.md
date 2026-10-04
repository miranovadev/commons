# Canonical Chirality component preservation

Diagnostic-only continuation of `chirality-v3-hard-edge` at `1274cbf934f74061f578a540740ff34513846451`.

Replay the identical lawful B → C1 Commit and first expressive child E1. Keep the previous read-only canonical scalar model, and preserve B's existing published Orientation/Cadence values and resolved directions as canonical metadata. Do not recalculate them at C1 or supply them as E1's local values.

The scalar and components have intentionally distinct existing scopes: B's head-local component pair is `(0.5, 0)`, while its attenuated aggregate is `0.3156786`. The candidate stores both without recomputing the aggregate from that pair.

Read-only in-memory measurement views exercise the real ChiralityGateService, PropositionMeasurementService::chiralityAttributes, and PropositionResource. A SQL write guard protects these probes. No candidate projection or snapshot is persisted over production data. The real child write is unchanged from the previous diagnostic; its candidate field uses the same isolated canonical-ancestor provider and production lineage calculator.

```sh
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-canonical-state/bootstrap.php diagnostics/chirality-v3-canonical-state/CanonicalStateReplayTest.php
```

`canonical-state.json` contains exact source/canonical metadata, all three Commit observations, consumer results, Closure, contribution references, and the child probe. `REPORT.md` distinguishes runtime-tested readers from statically audited integration paths.
