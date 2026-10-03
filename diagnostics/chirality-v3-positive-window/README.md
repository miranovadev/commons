# Positive-window lifecycle autopsy

Diagnostic-only observation of the exact three-participant `sparse_5` specimen and its preserved 80-action grounding continuation. No alternative history, participant, interval primitive, production rule, fixture, or PR is introduced.

Read [REPORT.md](REPORT.md), [ACTION-PATH.md](ACTION-PATH.md), [ALL-BOUNDARIES.md](ALL-BOUNDARIES.md), and [IDENTITIES.md](IDENTITIES.md). `window-trace.json` contains complete schemas, predicates, actual new records and read-only counterfactuals; `result-3-sparse_5.json` retains the existing per-action inspector output. Both are in `runtime-results.tar.gz`.

The isolated bootstrap reuses the **unchanged provisional first-Settlement developmental Cadence candidate** and EventOrder from the earlier boundary apparatus. It does not install that candidate in production and does not load the occupied-position counterfactual. Production services continue to calculate Correspondence, attenuation, Closure, projection, directions, schemas and lifecycle writes.

Reproduction (PHP 8.5, PostgreSQL 18, repository dependencies):

```sh
tar -xzf diagnostics/chirality-v3-natural-rhythm/runtime-results.tar.gz -C diagnostics/chirality-v3-natural-rhythm
tar -xzf diagnostics/chirality-v3-grounding-continuation/runtime-results.tar.gz -C diagnostics/chirality-v3-grounding-continuation
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-positive-window/bootstrap.php diagnostics/chirality-v3-positive-window/PositiveWindowTest.php --filter 3-sparse_5
python3 diagnostics/chirality-v3-positive-window/report.py
```

`PositiveWindowTest.php` compares the replay with the archived action sequence, target identity mapping, actor/Cycle/Phase, intervals, field values and component directions. `WindowContinuation.php` preserves the existing scheduler; its only addition is observation after each contribution. `WindowRecorder.php` observes every current actor, prohibits SQL writes during inspection, and evaluates frozen-value counterfactuals through unsaved model clones. It never submits a counterfactual action.

“First Bias boundary” means first entry to Phase 6 on a Phase-5 causal Gate, not the later turn on which that actor is selected, and not proof that a Bias Write is admissible. The full preserved history is bounded: no result here establishes impossibility for all lawful schedules.
