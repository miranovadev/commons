# Focused first-Settlement boundary autopsy

This directory replays only the archived three-author `sparse_5` and solo
`regular_3` specimens from `fa7ec337570ffa4438b6013ae4b21d70bc749f98`.
It changes no application source, semantic candidate, coefficient, topology
fixture, Gate rule, scheduler priority, participant population or Attestation placement.
No PR is required or opened.

`BoundaryAutopsyTest.php` is the archived `NaturalRhythmTest.php` with its provider
restricted to these two cases, plus inspection and replay assertions. The
original candidate implementation is required through its original bootstrap.
Its diagnostic binding is inherited from the earlier experiment, not an added
counterfactual override. All other calculators/services remain production code.
The normal repository test double supplies hyperlink verification; receipts are
created through ordinary instrument participation, not injected measurements.

The test verifies all 100 archived actions, actor/Cycle/Phase positions, scheduling
gaps, occurrence order, and input targets through a bijective ID mapping. It also
verifies the archived field observations and interval sequences. Different fresh
ULIDs and timestamps are expected. Timestamps never determine intervals.

`BoundaryInspector.php` keeps live field calculations, stored measurement
directions, full action schemas, effective source evidence and lifecycle predicates
separate. It records every selected action boundary and each participant's final
readiness. Prospective Commit calculations are only reported where the existing
history actually reaches Order. Positive expressive-Gate Tension is not relabelled
as a successful or even admissible Commit.

Counterfactuals operate on disposable value objects using production attenuation,
projection and Domain sign resolution. They never assign to a persisted model,
change a Gate/Phase, or replace a calculator. Head-only mature Cadence is the narrow
counterfactual; the all-first-Settlement-node calculation is a separately labelled
sensitivity, not a claim about when evidence should become available.

The inherited specimen ends at 100 actions. Rest can lawfully end a failed forward
route and permit recovery. A Rest-only Phase is not by itself proof that every
participant has no future action. The final readiness record makes this limitation
explicit; no universal terminality or reachability result is inferred from a bound.

Run from the repository root (PHP/composer dependencies and test PostgreSQL required):

```sh
tar -xzf diagnostics/chirality-v3-natural-rhythm/runtime-results.tar.gz -C diagnostics/chirality-v3-natural-rhythm
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-boundary-autopsy/bootstrap.php diagnostics/chirality-v3-boundary-autopsy/BoundaryAutopsyTest.php
python3 diagnostics/chirality-v3-boundary-autopsy/report.py
```

The dedicated Actions workflow runs one specimen per isolated database.
`export.py` emits gzip/base64 transport for lossless evidence retrieval; it does
not compute fields. `report.py` renders recorded runtime values into Markdown.
