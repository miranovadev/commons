# Cadence Anchor diagnostic result

**The birth-time anchor does not restore P1's positive window in the preserved history.** P1 predates D1's completed `[5,5]` rhythm. The first actual anchor activation is instead S1's `[0,0]`, where unchanged CV semantics are undefined. The real-service candidate replay therefore stops before Bias. No genuine Emergence transition was established; this is not a completed negative result for the rest of the history.

## Execution and scope

- Base: `489f0cf1d09f84f1bb6b536243dadbf512cd23ad`.
- Tested code: `e1f937507a7574575cb14fd0d72e2235887f7706`.
- Actions run: https://github.com/miranovadev/commons/actions/runs/37157181897 ; job `111302863642`.
- PHP 8.5 / PostgreSQL 18. **7 tests, 941 assertions passed**: primary replay 523; two single-participant controls 362; canonical Reclassify/Reconcile/stale-Bias checks 56.
- Primary: all 33 original prefix actions and continuation c0–c7 completed unchanged, with the same three genuine participants. c8 was attempted using `ParticipationService::contribute`, and rolled back at undefined CV. There were zero ordinary Commits and zero four-way overlaps in the defined prefix.
- Baselines A (lineage-live developmental v3 diagnostic) and B (complete-reset candidate) are the previously completed runs preserved on the base branch. They are not rerun or relabelled as anchor results.

## 1. Representation and minimal metadata

Each causal edge at child birth stores one nullable integer `anchor`: the parent's latest **completed local** interval available then. `endpoint_order` records where that interval ended, solely to count represented Cycle entries before the child's first own Attestation. The existing child's Horizon and entry-committal scope determine the represented Cycle population. Parent/child IDs, birth order and parent interval list are trace provenance, not calculator inheritance.

The first successor gap counts represented Cycle-created events strictly after the recorded endpoint and strictly before the child's first Attestation. These are event-order coordinates, not wall-clock spacing. The endpoint does not create an effective Attestation or an inherited act on the child. No completed parent interval means no anchor or endpoint. A dormant anchor is not a completed child interval to forward again.

The numeric sample is captured **at birth** and is not refreshed from later parent activity. A Reconcile with two causal parents would need a predecessor-selection rule; the adapter stops rather than silently choosing insertion order. That issue lies after the first unresolved CV in this history.

## 2. Actual anchor propagation

| Boundary | Parent at birth | Child | Anchor | First child observation |
|---|---|---|---|---|
| prefix:0 | Origin: one act, no interval | D1 | absent | own first act gives no interval |
| prefix:3 | D1: one act, no interval | P1 | absent | c0: samples `[]`, immature |
| c1 | P1: one act, no interval | Q1 | absent | c3: samples `[]`, immature |
| c6 | Q1: two acts, interval `[0]` | S1 | 0, endpoint order22 | c8: successor0, samples `[0,0]`, undefined CV |

D1's own acts occur at event orders6,12,18. P1 is created after the first of these, before either completed interval exists. Its birth action is **Variant**, not Reclassify. D1's later `[5,5]` cannot causally become P1's birth-time anchor. P1 receives its second own vote only at c4, after it has already created Q1 at c1. Q1's acts at orders20 and22 supply the first transferable interval, zero.

The full preserved lineage birth audit is in `ANCHORS.md`. Entries after c8 explicitly remain baseline-only evidence; they are not claimed candidate propagation.

## 3. Invariants observed

- All four ordinary proposition births in the supported primary prefix have no own effective Attestations, local Orientation0, local Cadence0, and local Chirality0. Origin retains its existing constitutional calibration.
- Reclassify P1→Q1 at c1 leaves P1's local Chirality0.25 unchanged. Q1 begins locally0 despite having historical field contribution0.0804878.
- S1 gets exactly one numeric anchor,0; its own act list and own interval list are empty at c6. It remains locally0 through the instrument action c7. No mature calculation occurs until its own c8 Attestation is attempted.
- 125 existing-proposition comparisons: 118 identical snapshots with unchanged own-Attestation state; seven actual own-Attestation changes. Thirty unchanged comparisons coincide with new Cycle entries. Full witnesses are in `invariants.json`.
- First P1 vote at c0 changes its Orientation0→0.5 and local Chirality0→0.25; Cadence remains immature0 because P1 has no anchor and no completed own interval.
- Only the intended boundary sample is added. No Attestation, effective grounding, parent Orientation, local Chirality, or complete parent Cadence stream is copied.

The successful prefix does not contain a defined nonzero anchor activation. Accordingly the run proves dormant/reset behavior and detects the first activation's undefined measurement; it does not empirically establish a successful mature anchor comparison or later propagation.

## 4. P1 and the three-model comparison

| State / model | Samples | O / direction | AC / direction | Local Chirality | Ancestors (normalized) | Field Chirality | C | Closure | Tension | Charge |
|---|---|---|---|---|---|---|---|---|---|---|
| D1 mature: all three | `[5,5]` | 0.5 / Positive | 1 / Positive | 0.75 | -0.1 | 0.5 | -0.0114382 | 0 | 0.1628540 | 0.8371460 |
| P1 first grounded: live v3 | `[5,5,0]` | 0.5 / Positive | 0.1715729 / Positive | 0.3357865 | ~0.0969388 | 0.3436391 | -0.3153158 | 0 | 0.0094410 | 0.9905590 |
| P1 first grounded: reset | `[]` | 0.5 / Positive | 0 / retained Negative | 0.25 | 0.0969388 | 0.2806122 | -0.3153158 | 0 | -0.0115678 | 0.9884322 |
| P1 first grounded: anchor | `[]` | 0.5 / Positive | 0 / retained Negative | 0.25 | 0.0969388 | 0.2806122 | -0.3153158 | 0 | -0.0115678 | 0.9884322 |

P1 is genuinely grounded at c0, but the acting participant is at Constrain, not Bias. Reclassify is available. The direction component check reports disagreement, but this does not prohibit that Constrain action. The candidate does not extend the positive/agreed window. All 126 current-actor states through c7 numerically match complete reset for both components, local/field Chirality, Correspondence, Closure, Tension and Charge.

`COMPARISON.md` contains the requested three-way critical-boundary tables, including later Reclassify, Mediate/Reconcile and Bias baseline values. Undefined candidate cells are left undefined.

## 5. Exact first unresolved boundary

At c8 participant A2 uses the existing genuine `attestation.create` action against S1. The attempted own support vote gives Orientation+0.5. Q1's final completed interval supplied anchor0 ending at event22. S1's act has order23, with **no represented Cycle entry between them**. Therefore:

- local completed successor intervals: `[0]`;
- numeric Cadence samples: `[0,0]` (one anchor plus one new interval);
- count2, mean0, population SD0;
- CV: undefined (0/0); Cadence, combined local Chirality and subsequent field: not assigned.

The adapter raises the existing diagnostic `SemanticBoundary` class. The real service transaction rolls back: S1 has no persisted Attestation afterward. This is a diagnostic mathematical boundary, not evidence that production rejects the underlying lawful Attestation. The last fully measured candidate boundary is c7: S1 local0, dormant anchor0, field Chirality0.0800437, C−0.4609498, Closure0, Tension−0.1269687, Charge0.8730313. Its current source still lacks its own effective Attestation.

Stopping preserves the user's unchanged-CV instruction. Choosing zero, positive one, negative one, or a special immaturity rule here would add another candidate semantic change.

## 6. Bias, directions and transition

The anchor run does not reach R's birth or its grounded Bias state. We therefore cannot give R an anchor result, infer a late-phase admissibility outcome, or determine whether Emergence would occur after resolving `[0,0]`.

For comparison only, the completed reset baseline reaches R with fresh local state and Tension−0.1499018. Its later grounded R has own intervals `[0,1]`, Cadence0 retaining Negative, Orientation+0.5/Positive, field Chirality0.2495737 and Tension−0.0949146; Bias refuses `component_directions_disagree`. The live-v3 baseline at that grounded boundary has Cadence−0.3445034 and Tension−0.1403502. Neither is an anchor prediction.

Within the defined candidate prefix, P1's positive Orientation and retained Negative Cadence still disagree. D1 has genuine positive Tension and agreeing mature directions but is not structurally at Bias. No supported boundary has all four target conditions. The first remaining obstruction to finishing this diagnostic is undefined anchor CV, before any late Bias determination.

## 7. Single-participant continuity control

The existing `regular_1` history's first17 actions were replayed unchanged under both reset and anchor models, using one genuine participant. Both complete the same prefix with zero ordinary Commits. At its end D1, P1, Q1 and S1 each have one own Attestation: Orientation+0.5, no own completed intervals, immature Cadence0, local Chirality0.25. Every birth-time anchor is absent.

This demonstrates a bootstrap limitation: a rule transporting a completed interval cannot create the first completed interval where none existed at the relevant births. It does **not** prove that all single-participant histories are perpetually blocked, nor test a separately seeded nonzero anchor. No extra acts or participants were invented to create such a seed. Details and lossless per-action snapshots are in `SINGLE-PARTICIPANT.md` and the two single-model JSON files.

## 8. Local continuity versus attenuation

The adapter never reconstructs the lineage-wide `[5,5,0,0,...]` stream. It retains a single boundary sample plus the child's own completed successor intervals. Same-position zeros remain meaningful and are not deduplicated; the first anchor is itself zero. Thus lineage-wide accumulation stays excluded, but local zero-mean data can still arise immediately.

Attenuation and local invariance continue to express different things. P1's local Chirality stays0 from birth through prefix32 while D1's own votes mature D1 from local0.25 to0.75. P1's field Chirality consequently rises from0.0051020 to0.0969388 through unchanged ancestry attenuation. This lawful historical influence does not create a P1 vote or populate a missing P1 anchor. Q1 likewise starts local0 with positive attenuated ancestry0.0804878.

## 9. Interpretation and review boundary

The candidate is causal as implemented, but does not solve the observed discontinuity in this exact history: the strong predecessor rhythm arrives after the relevant child birth. Its first usable boundary anchor then exposes a zero-mean CV case earlier than complete reset did.

Two unresolved semantics would be needed for a full later replay: treatment of a mature `[0,0]` sample, and selection of one predecessor at multi-parent Reconcile. Neither was decided here. Making P1 obtain D1's later interval would additionally require a different anchor-capture time; it is not the tested birth-time rule.

No production changes, fixture changes, PR or merge. Stop for review.

## Files added

- `diagnostics/chirality-v3-cadence-anchor/AnchorMeasurements.php`: isolated adapter.
- `AnchorRecorder.php`: existing recorder extended with anchor provenance.
- `AnchorReplayTest.php`: exact primary replay and semantic-stop assertions.
- `SingleAnchorTest.php`: unchanged single-participant prefix under both models.
- `bootstrap.php`, `export.py`: opt-in runtime and lossless export.
- `report.py`: render observed results and archived comparisons.
- `README.md`, `REPORT.md`, `TRACE.md`, `COMPARISON.md`, `ANCHORS.md`, `IDENTITIES.md`, `SINGLE-PARTICIPANT.md`: reviewable reports.
- `runtime-results.tar.gz`: lossless runtime JSON and derived invariant/summary JSON.
- `run-manifest.json`: execution and artifact provenance.
- `.github/workflows/cadence-anchor.yml`: opt-in PHP/PostgreSQL diagnostic runner.

Existing source and diagnostic files were only read/reused. The raw runtime JSON files are available in the workspace and archive; they are not duplicated as loose repository blobs.
