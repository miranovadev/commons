# Direction autopsy of the preserved lawful continuation

## Finding and boundary correction

The archived history does **not** contain the stated combination of grounded Bias, positive Tension and component disagreement. The +0.0094410 boundary is **Constrain**, with **Positive/Positive** component directions. It succeeds through ordinary Reclassify. The later Bias states have positive combined Chirality but **negative Tension**.

At the fully grounded Bias refusal, both component directions follow current, nonzero measurements: Orientation +0.5 and Cadence −0.3445034. This is actual sign opposition under the unchanged diagnostic candidate, not a default or stale zero direction at that boundary. The components describe different things: positive effective evidence relative to Origin versus a now highly uneven history of Cycle-entry intervals.

All indices below are zero-based. `pN` means original prefix action N; `cN` means continuation action N. A boundary labelled “before cN” is the selected actor's pre-action state; “after cN” denotes an observation after the contribution. Different gates have different causal histories; they are never treated as one global evolving interval vector.

## Exact failing state

The original archived source is `01m40h3nh5vyja2hpnzvkfjbf0` (R), an expressive Stance created by A0's Mediate Reconcile at c18. Its statement is `Author 0 grounding continuation 17`. It is not the original Persist source P, `01m40h0fez4qjtwznrf7d4n5ek`, grounded at c0.

| Property | First selected Bias refusal, before c22 | First fully grounded Bias refusal, before c34 |
|---|---:|---:|
| Domain / actor / Cycle / Phase | Settlement / A0 / C7 / Bias (6) | Settlement / A0 / C9 / Bias (6) |
| Source | R | R |
| Effective source Attestation | Present | Present: three Support votes |
| Source Receipt / grounding | Missing completed Receipt / false | Completed Receipt / true |
| Correspondence | −0.5343177 | −0.5343177 |
| Closure | 0 | 0 |
| Tension | −0.1266918 | −0.1403502 |
| Charge | 0.8733082 | 0.8596498 |
| Orientation local = stored | +0.5000000 | +0.5000000 |
| Orientation direction | Positive | Positive |
| Cadence local = stored | −0.2773958 | −0.3445034 |
| Cadence direction / maturity | Negative / formation_cv, mature | Negative / formation_cv, mature |
| Interval count | 11 | 16 |
| Mean interval | 1.0909090909 | 0.8125 |
| Population standard deviation | 1.9284730396 | 1.6665364532 |
| CV | 1.7677669530 | 2.0511217886 |
| Local component mean | +0.1113021 | +0.0777483 |
| Attenuated combined Chirality | +0.1542422 | +0.1132670 |
| Bias coherence | false: component_directions_disagree | false: component_directions_disagree |
| Generation / available action | Reclassify false / Rest only | Reclassify false / Rest only |

Intervals before c22: `[5,5,0,0,0,0,0,2,0,0,0]`.

Intervals before c34: `[5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0]`.

At c34, the source is structurally accepted, no existing classification occupies its write register, lineage evidence direction is Positive, and authorized Receipts exist. Grounding's requirements are `attestation=true`, `receipt=true`, `closure=null` with `closure_enforced=false` and no grounding reasons. This does not assert Order/Commit admissibility: the current Phase is Bias. The source's Chirality coherence fails and no Bias Reclassify is offered. No invalid write was dispatched merely to elicit an exception.

The direct Receipt was acquired by A0's ordinary Mediate Instrument at c33, original Receipt ID `01m40h5q4hs2yj7bskc8mwrw7j`. The source's three effective Support Attestations were authored at c19, c21 and c27, by A2, A1 and A0 respectively. This current grounding is distinct from c0's Attestation of P.

Neither Orientation nor Cadence has a separately attenuated stored component. Production stores the local head components and their retained directions. It attenuates their combined local mean across causal positions to obtain combined Chirality. Thus +0.1132670 is not the mean of two separately reported attenuated components.

## Direction rules and component interpretation

`ObservableDirection::after` uses the sign of any nonzero value; at exactly zero it returns the prior direction. `PropositionMeasurementService::componentAttributes` resolves prior state in this order: the proposition's persisted Chirality observation, its measurement projection, its entry Commitment snapshot, then the primitive component default. The defaults are Orientation Negative and Cadence Positive. Maturity is not an additional argument to this production resolver.

**Orientation-only:** the candidate compares effective current lean with the Origin empirical reference. At R, current lean is +1, reference lean is 0, and Orientation is (+1−0)/2 = +0.5. This describes aligned current evidence. It is not the derivative of the Orientation value over time, and it does not mean every new Attestation increases Orientation. Continuing positive Support can leave Orientation exactly +0.5.

**Cadence-only:** the candidate observes historical acts on the current causal lineage, including acts that are not effective current-source votes. Intervals are counts of represented Cycle entries strictly between consecutive acts; equal-entry acts yield zero. CV greater than 1 yields a negative developmental Cadence. The initial regular `[5,5]` history has acquired many zero gaps and a few positive gaps by Bias. Its current dispersion is genuinely high relative to its mean. Negative Cadence is neither an Attestation polarity nor a negative vote.

**Scalar versus coherence:** at grounded R, (+0.5−0.3445034)/2 remains +0.0777483. Production lineage attenuation gives +0.1132670. A positive sum can contain opposite-sign terms; coherence deliberately asks a different question from their net magnitude. Correspondence −0.5343177 exceeds that positive Chirality in magnitude, and Closure is 0, giving Tension −0.1403502. Consequently this specimen supports “positive Chirality despite incoherence,” but not “positive Tension at grounded, incoherent Bias.”

## Initial grounding effect and first divergences

| Source and observation | Orientation / direction | Cadence / direction | Intervals | Chirality | Tension | Meaning |
|---|---:|---:|---|---:|---:|---|
| P immediately before c0 | +0.5 / Positive | +1 / Positive | [5,5] | +0.6479592 | +0.1108812 | Current P vote missing |
| P immediately after c0; Constrain before c1 | +0.5 / Positive | +0.1715729 / Positive | [5,5,0] | +0.3436391 | +0.0094410 | Grounded; next Reclassify succeeds |
| Q after c3 | +0.5 / Positive | 0 / retained Positive | [5,5,0,0] | +0.2711625 | −0.0329640 | Mature CV=1; zero does not flip direction |
| Q after c4, before c5's Attestation of Q | +0.5 / Positive | −0.1010205 / Negative | [5,5,0,0,0] | +0.2206172 | −0.0498124 | Upstream Attestation of P enters Q's historical lineage |
| Q after c5; Disperse before c6 | +0.5 / Positive | −0.1715729 / Negative | [5,5,0,0,0,0] | +0.1958378 | −0.0580722 | Disagreement persists with nonzero mature Cadence |

Q is the Constrain Reclassification result `01m40h25gv291jzcpkv0j3f56n`. The first grounding act c0 does **not** create disagreement. Later ordinary Support acts change the historical spacing; c4's Attestation of ancestor P is enough to turn Q's mature Cadence negative. P itself then has `[5,5,0,0]`, Cadence zero and retained Positive. This different result on parent and descendant is a real scope distinction, not inconsistent direction resolution.

There is also an earlier, developmentally different mismatch: after p2's first ordinary Attestation, before p3, the Distinguish result D has Orientation +0.5 / Positive but immature Cadence 0 / retained Negative. Its Chirality is +0.1 and Tension +0.0295206. The actor is at Persist (Phase 2), not Bias. Origin supplied O=0 with default Negative and constitutional Cadence=−1 with measured Negative. The first ordinary nonzero Orientation displaces the former; immature zero Cadence retains the latter. Thus the first mismatch predates maturity. The later `[5,5]` observation resolves that early mismatch by establishing a fresh Positive Cadence.

## Nearby states and scope cautions

Immediately before the first selected Bias refusal c22, the selected actor at c21 is on a different Mediate Gate, with Orientation +0.5 / Positive, immature Cadence 0 / retained Negative, Chirality +0.2188271 and Tension −0.0807076. Its Attestation targets R; R changes from Cadence −0.2476430 to −0.2773958, with Negative direction on both sides.

Immediately before fully grounded Bias c34, c33's selected Mediate Gate has Orientation +0.5 / Positive, Cadence −0.3333333 / Negative, Chirality +0.0927449 and Tension −0.1227350. The Instrument targets R. On R itself, Cadence remains −0.3445034, Chirality +0.1132670 and Tension −0.1403502: that Receipt changes grounding, not component direction.

R enters Bias already incoherent after c18. It has no agreeing observation in this replay. The nearest preceding selected agreeing boundary before c34 is c29's Origin Gate, with O=0 / Negative, Cadence=−1 / Negative, Chirality −0.5 and Tension −0.1666666; Bias is not applicable. Before c22, the nearest such selected boundary is c14's new, unattested Distinguish result: O=0 / Negative, immature Cadence=0 / Negative, Chirality −0.1, Tension −0.0371460, Persist Phase. These are different propositions and do not show R recovering coherence. The meaningful mature same-branch comparison is Q's zero/retained-Positive state after c3 followed by its fresh-negative state after c4. The full boundary and persistence tables preserve those distinctions.

## Semantic provenance

Pinned code and specifications establish an intentional **existing-source component-coherence** requirement, independent of combined scalar direction, grounding and dominance:

- `app/Services/ChiralityGateService.php::evaluate` compares available retained component directions for exact equality. It never measures a prospective Reclassification.
- `app/Services/PropositionMeasurementService.php::componentAttributes` and `::observeChirality` preserve independent component direction history at mutation boundaries.
- `app/Domain/Observables/ChiralityValues.php::resolveDirections` and `app/Enums/ObservableDirection.php::after` establish independent zero hysteresis, and separate local components from their attenuated mean.
- `docs/observables.md`, “Bias Write admissibility,” explicitly separates component coherence from ordinary local evidence/ancestry agreement, structural eligibility, effective Attestation and Receipt authority. Both Positive/Positive and Negative/Negative are coherent. It is not a requirement that both components be positive or increasing.
- `tests/Feature/HomologousBiasTest.php::test_causal_chirality_coherence_controls_local_write_and_preserves_visibility` covers all four sign pairs, including a deliberately positive scalar with opposite components. `::test_zero_components_use_independent_retained_directions` checks the same decision survives both components becoming zero. These controlled canonical fixtures are contract evidence, not natural-history witnesses.
- `tests/Feature/ChiralityV2Test.php::test_component_directions_retain_independently_and_calculation_is_read_only` checks independent retention and read-only calculation.
- Additional inspected tests, not required to expand this diagnostic run: `HomologousBiasTest::test_inadmissible_member_determines_dominance_before_action_filtering`, `::test_stale_local_or_homologous_source_is_rejected_without_any_write`, and `::test_reclassification_result_uses_ordinary_measurement_without_operand_coherence_constraint`. They separate source admissibility, whole-population measurement and the result's later observations.

History reinforces that this was deliberate: commit `3d48b7623e65356c929012f12dcf06bc33cdf8f4`, “Require Chirality-coherent existing sources for Bias Reclassify,” explicitly changed component disagreement from descriptive-only to a Write blocker. Commit `ff4914bb1357eb0e9d16701b66e790dc5df7b469`, “Correct prospective Closure and retain Chirality observation history,” added persistent mutation-boundary history and changed Orientation's primitive default to Negative.

These sources establish the equality contract and its retention semantics. They do **not** establish a philosophical equivalence between the new provisional CV candidate and production Orientation, or prove that this candidate's zero/CV=1 boundary is the correct canonical Bias criterion. The CV candidate remains diagnostic-only. Calling the two components “opposed developmental tendencies” is safe only in the limited sense of their observed numerical signs under this candidate, not as a claim of opposing participant intent.

## Classification

- **A — actual nonzero directional opposition at the terminal Bias state.** Both current values determine their own signs; neither direction depends on zero retention or a primitive default there.
- **B/C/D — relevant to the earlier immature mismatch, not the terminal cause.** Origin bootstrap and zero retention let positive Orientation meet an immature zero Cadence retaining Negative. Mature `[5,5]` subsequently removes that mismatch. Q's later zero retains Positive, temporarily preserving agreement rather than causing rejection.
- **E — not caused by the initial grounding act.** The `[5,5]→[5,5,0]` act leaves directions aligned. Subsequent genuine grounding/support activity does degrade spacing and eventually create fresh negative Cadence in descendants; this is an authored-history effect, not stale direction bookkeeping.

The late failure is therefore not simply components out of developmental phase. Under the candidate, Orientation still reports aligned effective evidence while historical rhythm has become sufficiently bursty to report negative Cadence. At grounded Bias, field magnitude and source coherence are both unfavorable; the earlier positive field should not be carried forward across the intervening authored history and topology changes.

## Verified persistence lineage and execution

The focused replay passed **7 tests / 1,018 assertions**: history replay 1/897, canonical Bias coherence and zero-retention 5/118, independent retention/read-only calculation 1/3. The 33 prefix actions and 34 continuation actions match the preserved archive for authored inputs, actors, Cycle/Phase, interval sequences, component directions and measured fields. The replay stops after c33; all three actors then observe the same fully grounded, incoherent R at Bias. No action placements were changed.

Actions: https://github.com/miranovadev/commons/actions/runs/37115067694

Code commit: `9111915fc5bf5a08bf736a902ca0d342ea882fa2`.

There are **75 changed persistence snapshots** and **568 measurement/history SQL writes** in `direction-records.json`. Each capture verified it produced no additional measurement/history writes. `DIRECTION-LINEAGE.md` shows all changed snapshots, including previous and current directions; `BOUNDARIES.md` includes unchanged observations and singleton-interval immaturity between changes. The raw SQL evidence also preserves observations before and after a single mutation.

Exact direction-establishing observations:

- D: p0 creates O=0/Negative, AC=0/Negative. p2 first establishes O=+0.5/Positive; AC remains immature zero/Negative. The available one-interval observation remains immature. p32 establishes AC=+1/Positive on `[5,5]` for D and P.
- P: p3 birth already observes O=+0.5/Positive. AC is initially zero/Negative. p32 changes AC to +1/Positive. c0 changes it to +0.1715729/Positive; c4 changes it to zero/retained Positive. Orientation remains +0.5/Positive throughout those observations.
- Q: c1 birth observes O=+0.5/Positive and AC=+0.1715729/Positive. c3 observes AC=0 and retains Positive. The c4 SQL log explicitly records the pre-mutation zero/Positive observation followed by −0.1010205/Negative. This is the first mature negative direction on that branch. At that moment A0 is advancing through Constrain and A1/A2 have reached Disperse; Bias is not yet relevant. c5 observes −0.1715729/Negative. Orientation remains +0.5/Positive.
- R: its **first measurement INSERT at c18** records O=+0.5/Positive and AC=−0.2129404/Negative. These actual nonzero values establish the pair on this proposition; neither is inherited at zero. Every subsequent R observation remains nonzero with the same signs. The c33 publication again records O=+0.5/Positive and AC=−0.3445034/Negative; no stale projection causes the final refusal.

Complete changing Cadence observations on R are below. Orientation is +0.5/Positive at every row; Cadence is mature `formation_cv` and Negative at every row. The previous pair is absent on birth, then Positive/Negative for every subsequent observation.

| After contribution | R intervals | Cadence | Previous → current directions (O, AC) |
|---|---|---:|---|
| c18 birth | [5,5,0,0,0,0,0,2,0] | −0.2129404 | none → (+,−) |
| c19 | [5,5,0,0,0,0,0,2,0,0] | −0.2476430 | (+,−) → (+,−) |
| c21 | [5,5,0,0,0,0,0,2,0,0,0] | −0.2773958 | (+,−) → (+,−) |
| c23 | [5,5,0,0,0,0,0,2,0,0,0,1] | −0.2604922 | (+,−) → (+,−) |
| c24 | [5,5,0,0,0,0,0,2,0,0,0,1,0] | −0.2850591 | (+,−) → (+,−) |
| c25 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0] | −0.3069495 | (+,−) → (+,−) |
| c26 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0] | −0.3266429 | (+,−) → (+,−) |
| c27 through terminal c33 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0] | −0.3445034 | (+,−) → (+,−) |

Replay IDs: D `01m40kf6mah3shn5ajwe987jz3`; P `01m40kfaq9h2q3mct0jk90qe6m`; Q `01m40kh042nb9gvm89ndr88r86`; R `01m40kjgsebr7tx5fqer62sbg0`. Original archived IDs above identify the prior specimen; these fresh IDs identify the exact equivalent replay. The recorded mapping is supported by the unchanged actor/action/target sequence and matching numeric assertions.

## Files used or changed

Only these new diagnostic files and workflow were added:

- `diagnostics/chirality-v3-direction-autopsy/DirectionAutopsyTest.php`
- `diagnostics/chirality-v3-direction-autopsy/DirectionContinuation.php`
- `diagnostics/chirality-v3-direction-autopsy/DirectionRecorder.php`
- `diagnostics/chirality-v3-direction-autopsy/bootstrap.php`
- `diagnostics/chirality-v3-direction-autopsy/export.py`
- `diagnostics/chirality-v3-direction-autopsy/report.py`
- `diagnostics/chirality-v3-direction-autopsy/README.md`
- `diagnostics/chirality-v3-direction-autopsy/REPORT.md`
- `diagnostics/chirality-v3-direction-autopsy/BOUNDARIES.md`
- `diagnostics/chirality-v3-direction-autopsy/DIRECTION-LINEAGE.md`
- `diagnostics/chirality-v3-direction-autopsy/run-manifest.json`
- `diagnostics/chirality-v3-direction-autopsy/runtime-results.tar.gz`
- `.github/workflows/direction-autopsy.yml`

The archive contains the three raw generated JSON files, also retained locally: `result-3-sparse_5.json`, `direction-records.json`, `continuation-checkpoint.json`.

Reused unchanged: the natural-rhythm candidate, EventOrder and archive; `BoundaryInspector.php` and its bootstrap; the grounding continuation's original test, policy and archive; all production implementations and canonical tests cited above. No production or existing fixture files were changed. No PR or merge was created. This observation pass stops here for review.
