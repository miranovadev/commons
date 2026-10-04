# Post-c21 production lifecycle diagnostic

Isolated continuation of `chirality-v3-local-reconcile` at `a65ad7b3e612ca9e91e2f55a76f7e160aa6ba0b0`. No production files or candidate formulas are changed.

The replay reproduces the exact 33-action prefix and c0–c21 continuation. It then abandons the obsolete c22 Rest and reads the actual production schemas. The first offered Bias selects R and the first authorized Receipt in schema order, following the existing NaturalRhythmTest fixture convention. Receipt choices and provenance are recorded before dispatch. No measurement is used to rank choices.

The existing `LocalReconcileMeasurements` adapter is reused unchanged. Both causal parents remain in production inverse-square attenuation; Reconcile's local source alone supplies its beat cursor. All-zero mature intervals remain maximally regular. Structural and Cycle events do not change cached local snapshots.

The continuation evaluates real Order/Commit availability without a Tension threshold. A missing fresh-source Attestation may be supplied only by an existing participant offered that operation. It does not add votes to mature Cadence. The diagnostic stops at the first ordinary Commit, a production obstruction, or an unresolved semantic case.

Run with the `post-bias.yml` workflow, or after restoring the archived fixture:

```sh
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-post-bias/bootstrap.php diagnostics/chirality-v3-post-bias/PostBiasReplayTest.php
```

`candidate-trace.json` includes the full replay, every local snapshot, real action schemas, real Commit eligibility/Closure previews, decision inputs, Receipt provenance, attenuation components, and immutable Commit snapshots if reached. `REPORT.md` presents the findings and points to focused tables.
