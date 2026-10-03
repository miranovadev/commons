# Cadence Anchor: diagnostic only

Based on `489f0cf1d09f84f1bb6b536243dadbf512cd23ad`. This directory and its opt-in workflow are the only changes. No production source, existing fixture, authored action, participant, measurement coefficient, admissibility rule or direction rule changes.

## Candidate representation

`AnchorMeasurements` composes the previous proposition-local adapter. Each successful causal edge snapshots one **already completed local interval** from its source at child birth. A dormant anchor is not itself a locally completed interval and cannot be forwarded as if the child had acted. A missing completed interval produces no anchor. The adapter never fills that absence with a future parent interval.

The numeric anchor is an integer count. Technical boundary metadata is the event-order endpoint of that completed interval. Parent/child IDs, edge type, birth event order and parent interval history are audit fields only; the calculator passes exactly one numeric sample into the child's interval stream. The child's existing Horizon / entry-committal scope supplies the represented Cycle population. The first successor interval counts those Cycle-created events strictly between the endpoint and the child's first genuine Attestation. No old Attestation row is assigned to the child.

The birth hook is on a real Supersession creation, after its two propositions exist and before the completed write is measured. This establishes causal metadata; it does not perform a mature local calculation. Own-Attestation signature changes remain the only invalidator of existing local measurements. No-direction defaults or zero hysteresis are overridden. A second causal parent is an unresolved selection boundary, rather than an implicit choice based on insertion order.

## Preserved services

The previous adapter still computes own effective lean and Orientation using the real Chirality calculator and the existing complementary reference. Correspondence, Closure, inverse-square lineage attenuation, field projection, grounding, schemas and contribution services remain production implementations. Only anchor activation adds the single boundary sample and the first successor gap. The developmental formula, population SD, CV and rounding are unchanged. An all-zero mature sample stops the diagnostic, as the existing diagnostics do; no new CV policy is introduced.

`AnchorRecorder` is a directory-local copy of the previous recorder with anchor audit fields added. It checks that SQL inspection is read-only, maps real service-created identities to the archived history, and retains every current actor field and every local snapshot. `AnchorReplayTest` is the same preserved primary replay, with an explicit expected semantic stop rather than fabricating downstream values. The attempted write at the undefined measurement must roll back.

`SingleAnchorTest` reuses the existing natural-rhythm scheduler for exactly its archived first 17 single-participant actions under reset and anchor models. It asserts identical actions, Phase/Cycle sequence and target identity consistency. It creates no extra actors or new authored sequence. This is a bounded bootstrap control, not a claim that every possible single-participant history stays immature.

## Run

The opt-in `.github/workflows/cadence-anchor.yml` restores the archived runtime fixtures, runs the two diagnostic tests and canonical grounding/Reconcile/stale-Bias tests on PHP 8.5 / PostgreSQL 18, and exports lossless JSON. The local scratch environment has no PHP runtime. `report.py` renders the actual results and the two previously tested baselines without recomputing field formulas.

## Deliberate limits

An undefined `[0,0]` CV cannot be used to calculate a field or select lawful later actions. Therefore later baseline states are comparative evidence only, not counterfactually claimed anchor results. A full operational replay past that point requires a semantic decision outside this task's authorization. Multi-parent anchor selection is also unspecified, but occurs later than the zero-mean stop.
