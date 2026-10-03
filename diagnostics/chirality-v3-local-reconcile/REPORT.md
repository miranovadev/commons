# Acting-source Reconcile cursor: runtime result

**Reconcile now succeeds and R reaches operationally admissible Bias after its second own Attestation. R's Tension remains slightly negative, and the unchanged authored replay cannot execute its next Rest because Bias Reclassify is now available.** No Settlement→Emergence transition was established. No new semantic ambiguity appeared.

## Execution and scope

- Base: `521a2cc3dd2b183735b3fca85dbf8a0a99240866`.
- Tested code: `acd0f13243275fa0c3f22a4290ca0a95e2ca67d9`.
- Run: https://github.com/miranovadev/commons/actions/runs/37161911741 ; job `111316935228`.
- PHP8.5 / PostgreSQL18; **7 tests, 945 assertions passed** (primary744, single-participant controls145, canonical tests56).
- All33 prefix actions and continuation c0–c21 complete: **55 unchanged authored actions**,56 boundaries, three genuine participants. No ordinary Commit.
- At c22 the archived `participant.rest` is absent from the real action schema. The diagnostic records that schema and stops without submitting an invalid write or selecting a different action.

Only the isolated Reconcile cursor selection changed. The previous diagnostic's own-Attestation trigger/cache, birth-time cursor capture, ordinary pass-through, local reset, developmental formula, mature-zero rule and real production calculators remain unchanged.

## 1. Reconcile selection is local, not latest-global

At c18, the real contribution service creates R with both genuine causal parents. The diagnostic selects its cursor by comparing the edge source with the creating Phase's persisted `gate_proposition_id`. Thus selection uses the **acting Gate**, not edge order, largest endpoint, average or a synthetic position.

| Incoming source | Role | Own acts at this birth | Provenance | Beat event order | Represented Cycle position | Selected |
|---|---|---:|---|---:|---:|---|
| S2 | Local / acting source | 0 | Q2's last own beat passed through unattested S2 | 27 | 15 | yes |
| S1 | Other causal source | 1 | S1's own last Attestation | 23 | 13 | no |

Only event27 becomes R's prior beat. S1 remains a real parent and contributes to the field through ancestry. If the acting source has no cursor, the implementation retains null and returns from the other-source hook without adopting its cursor. This missing-local-cursor edge was **not encountered** in this replay.

The read-only alternative calculation uses the actual first R act at event28 / position15:

| Cursor choice | First R interval |
|---|---:|
| Local / acting, position15 | 0 |
| Other source, position13 | 2 |
| Latest of these two, position15 | 0 |

These alternatives do not execute writes or altered histories. Local and latest happen to coincide in this specimen; the implementation explicitly selects local and does not generalize that coincidence.

## 2. R is fresh locally; both ancestries remain real

At creation R has:

- two genuine causal parents, S2 and S1;
- zero own/effective Attestations and no attached source Receipt;
- empty local Cadence intervals;
- local Orientation0, immature Cadence0, local Chirality0;
- the selected beat cursor at position15, dormant until its first own vote;
- production component directions Negative/Negative under the existing zero-history resolution.

No parent Attestation, Orientation, local Chirality, Cadence sample or Cadence value is inherited.

Production attenuation at R's birth has total normalization mass1.955. Partitioning the real unique ancestry positions gives:

| Ancestry group | Normalized Chirality contribution |
|---|---:|
| Local-only: S2, Q2, P2, D2 | +0.0222009 |
| Other-only: S1, Q1, P1, D1 | +0.0942765 |
| Shared Origin, counted once | −0.0071043 |
| **Total historical contribution** | **+0.1093732** |

The other branch contributes more historical Chirality here even though it supplies no local cursor. This directly demonstrates the independence of rhythmic continuity and causal attenuation. `ANCESTRY.md` records every member, production distance, inverse-square weight and normalized term. Shared ancestry is not double-counted.

R's initial field is Chirality0.1093732, Correspondence−0.5343177, Closure0, Tension−0.1416482, Charge0.8583518. Its whole Chirality field is historical at this point; its local contribution is0.

## 3. Complete actual R event trace

| Boundary | Event / author | Previous → current Cycle position | New interval | R local samples | Cadence maturity / value | Orientation | Local Chirality | Field Chirality | Tension | Charge |
|---|---|---|---:|---|---|---:|---:|---:|---:|---:|
| c18 | Reconcile / A0 | prior cursor15 | — | `[]` | immature /0 | 0 | 0 | 0.1093732 | −0.1416482 | 0.8583518 |
| c19 | First own support / A2 | 15→15 | 0 | `[0]` | immature /0 | +0.5 | 0.25 | 0.2372504 | −0.0990224 | 0.9009776 |
| c20 | Instrument on S2 / A1 | no R Attestation | — | `[0]` unchanged | immature /0 | +0.5 | 0.25 | 0.2372504 | −0.0990224 | 0.9009776 |
| c21 | Second own support / A1 | 15→15 | 0 | `[0,0]` | mature /+1 | +0.5 | 0.75 | 0.4930049 | **−0.0137710** | 0.9862290 |

Across these states, Correspondence remains−0.5343177, Closure0, and normalized ancestral Chirality0.1093732.

For `[0]`: mean0, SD0, raw CVundefined, effective CVundefined; the one-sample maturity rule supplies neutral Cadence0. Directions are OrientationPositive / CadenceNegative via existing zero hysteresis.

For `[0,0]`: mean0, SD0, raw CVundefined, authorized effective CV0; Cadence+1 and Positive direction. Orientation remains+0.5/Positive. No interval is appended by c20's instrument action. Only R's two own genuine Attestations build its local history.

`R-TRACE.md` provides exact event orders, raw/effective statistics and every current participant state. No third R vote or later Receipt event is claimed: the unchanged replay stops before those archived actions can be reached lawfully.

## 4. First Bias reachability and admissibility

R is first structurally at Bias at c18 for A0/C7/Phase6. It initially lacks both an effective Attestation and a Receipt, so Bias generation is false despite its two retained directions agreeing.

At c19 it has an effective Attestation, but its component directions disagree; Bias generation remains false. The c20 instrument adds another ordinary Receipt to the acting Region's available evidence but does not attach a Receipt to R or change its Chirality.

At c21 all three participants now stand at Bias on R: A0/C7, A1/C3 and A2/C6. Actual production state shows:

| Predicate / measurement | Result |
|---|---|
| Bias structurally reachable | true |
| R effective Attestation | true; two own support votes |
| Component direction agreement | true; Positive/Positive |
| Bias evidence source eligibility | R eligible |
| Authorized ordinary Receipt choices | 12 |
| Existing Bias generation/admissibility | **true** |
| Offered action | **`proposition.reclassify`** |
| R's own attached Receipt | absent |
| Full current-source grounding helper | false: Receipt missing |
| Tension positive | false: −0.0137710 |
| Requested four-way overlap | false |

**Bias admissibility and the full current-source grounding helper are different checks in existing production semantics.** `PhaseGenerationService::available` permits Bias Reclassify using a valid source, effective Attestation, component agreement, unused classification and authorized Receipt selection. It does not require Tension>0, and the offered Reclassify action selects the Receipt to consume. `CommitmentAdmissibilityService::grounding` separately reports that R itself still lacks a Receipt. This diagnostic neither equates those checks nor weakens either one.

Accordingly, Bias itself is now usable. The stronger requested combination of positive Tension and fully grounded current source is not present. The first fully grounded Bias state is **not reached in this replay**; the later archived R Receipt at c33 is beyond the lawful replay limit.

## 5. Exact stop: the next archived Rest is no longer lawful

The old history's c22 action is A0's `participant.rest`. After c21 the real plan exposes only `proposition.reclassify`, selecting R and one of12 ordinary Receipt IDs. Rest is not available because an admissible non-Rest action exists. `ParticipationService` documents that Rest is exposed only when no admissible non-Rest action remains.

Therefore the unchanged authored sequence cannot continue to c23 and beyond. The diagnostic stops at the schema check before dispatching c22. It does not submit an invalid Rest, skip it, simulate its Cycle entry, choose a Receipt, or substitute Bias Reclassify. Any of those would alter participant behavior or lifecycle rules contrary to this task.

This is **not a new semantic ambiguity** and not another refusal of Bias. It is an observed change in lawful action availability: the candidate opens Bias, invalidating the old recovery trajectory. To investigate the new route through Bias/Order/Commit, a separately authorized authored continuation would need to select an offered Bias Reclassify and its Receipt.

## 6. Emergence result and closest state

No Settlement→Emergence transition occurs in the executed prefix: zero ordinary Commits are authored. No lawful measured boundary simultaneously has Bias reachability, full current-source grounding, positive Tension and component agreement.

The closest reached Bias state is c21: component agreement and actual Bias admissibility are satisfied, but Tension is−0.0137710 and R lacks its own Receipt. This is insufficient evidence to predict the field or eligibility of a subsequent Bias result or Committal; fresh-node reset and attenuation must be measured through real future writes.

The closest positive, grounded, direction-agreed precursor remains Q1 at c5 (Disperse, Tension+0.0744386), which is not structurally at Bias. Its local state remains unchanged while R develops.

The remaining issues are distinct: (1) R's measured Tension is still negative; (2) its full source grounding lacks a Receipt; and (3) continuing the old Rest-based authored history is no longer lawful because Bias now opens. The third item is the immediate replay stop, not a field threshold or an unresolved cursor rule.

## 7. Regression evidence

- All156 current-actor states in the previous successful prefix through c17 numerically match this replay for Orientation, Cadence, local/field Chirality, Correspondence, Closure, Tension and Charge.
- Q1's `[0,0]` stays Cadence+1 and its entire local snapshot remains unchanged from c5 through c21.
- Ordinary unattested cursor pass-through and local resets remain intact. Nine ordinary births through R have no inherited votes and fresh local O/AC/Chirality0.
- 229 existing-proposition comparisons: **217 unchanged snapshots**,12 own-Attestation changes. Forty-two unchanged comparisons coincide with new Cycle entries.
- Both single-participant controls still execute the same17 authored actions. The cursor model retains its one-interval local histories `[4]`, `[2]`, `[1]`, `[2]`; no new participant is introduced. This confirms the previous spacing-continuity result, not mature single-participant Cadence.
- R grows `[]→[0]→[0,0]` solely through its own two acts. The lineage-wide zero stream does not return.
- Canonical Reclassify no-judgment-inheritance, structural Reconcile and stale-Bias tests pass separately without the diagnostic adapter.
- No missing-local-cursor fallback case or new semantic ambiguity is observed.

## Files added

Only these additions were made:

- `diagnostics/chirality-v3-local-reconcile/LocalReconcileMeasurements.php`: acting-Phase-Gate selection, with no other-source fallback.
- `ReconcileRecorder.php`: both incoming cursors and production ancestry partition audit.
- `ReconcileReplayTest.php`, `SingleReconcileTest.php`: unchanged primary/control actions with real lifecycle checks.
- `bootstrap.php`, `export.py`, `report.py`.
- `README.md`, `REPORT.md`, `R-TRACE.md`, `ANCESTRY.md`, `EVENTS.md`, `TRACE.md`, `BEATS.md`, `COMPARISON.md`, `IDENTITIES.md`, `SINGLE-PARTICIPANT.md`.
- `runtime-results.tar.gz`: lossless replay/control JSON, invariants, focused Reconcile record and read-only cursor alternatives; `run-manifest.json`.
- `.github/workflows/local-reconcile.yml`.

The existing formula adapter and fixture archives were reused read-only. Production source, previous fixtures and authored actions were not changed. No PR or merge. Stop for review.
