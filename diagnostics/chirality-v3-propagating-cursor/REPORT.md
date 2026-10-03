# Propagating beat cursor and zero-mean regularity

**Both authorized refinements work in the real-service replay. Q1 now reaches Cadence+1 and positive Tension, and the single-participant control passes its former unattested-predecessor stop.** The primary replay subsequently reaches an unresolved two-parent cursor choice at Reconcile, before a fresh Bias source can be completed. No later Bias or Emergence result is fabricated.

## Execution

Base `22d6862696170e884a5fa8ae6afe3faee2294391`; tested code `a0ea5fdf655f71ffe27a568ba6cf6c97abab9512`.

Actions: https://github.com/miranovadev/commons/actions/runs/37160200730 ; job `111311896318`.

PHP8.5 / PostgreSQL18: **7 tests, 867 assertions passed** (primary666, two single-participant controls145, canonical Reclassify/Reconcile/stale-Bias tests56). All 33 original prefix actions plus continuation c0–c17 complete unchanged: **51 successful authored actions, 52 captured boundaries, three genuine participants**. Reconcile c18 is attempted through the real contribution service, then rolls back at the retained diagnostic cursor-selection guard. Zero ordinary Commits.

The previous candidate is an archived comparison, not rerun with the new semantics. No production, existing fixture, authored action, participant, grounding, Correspondence, Closure, direction or attenuation changes.

## 1. Cursor representation and propagation

The diagnostic stores an event-order endpoint for the last beat and provenance identifying how it reached the child. Cycle position is the count of represented Cycle entries before that endpoint within the existing Horizon/entry-Committal population. No wall-clock spacing is used.

At each unary causal birth:

- If the source has own historical Attestation acts, capture its latest own beat.
- Otherwise copy the source's already-carried endpoint unchanged, recording the unattested pass-through.
- If neither exists, retain no endpoint. Do not synthesize position0 or copy an arbitrary ancestor. That bootstrap case is not encountered in this Origin-attested history; the existing Origin calibration remains unchanged.

A child's first own act completes the boundary interval. Subsequent own acts complete local act-to-act intervals. Only that proposition's interval list changes. Its outgoing cursor advances to its latest own act. No parent samples or Cadence values are copied. Parent/child IDs and cursor-path lists are provenance only.

Birth-time capture is retained from the previous candidate: later ancestor votes do not retroactively rewrite a descendant's captured endpoint. This run changes only pass-through of an existing cursor and the zero-mean rule.

## 2. Concrete pass-through and no inheritance

In the primary history:

| Boundary | Authored event | Cursor | Local state |
|---|---|---|---|
| c10 | Origin→D2 | Origin beat at event2 | D2 has no votes, no intervals, local O/AC/Chirality0 |
| c11 | Unattested D2→P2 | Same event2, passed through D2 | P2 has no votes, no intervals, local O/AC/Chirality0 |
| c14 | P2's first own vote, event26 | Interval from event2 to26 =15 represented entries | P2 owns `[15]`; D2 remains locally unchanged |

The old candidate stopped at c11. This run crosses it without inheriting a vote or grounding P2 before its own action. Cursor provenance is `Origin→D2→P2`, with D2's own act count0 at the child birth.

The supported history supplies repeated witnesses with **one unattested intermediate**. It does not supply the requested four-distinct-proposition chain with two successive unattested intermediates followed by an attested descendant before the Reconcile stop. No extra structural actions were authored to manufacture that longer witness. The same recursive pass-through operation is implemented, but that longer runtime case remains unexercised.

## 3. Single-participant result

Both reset and cursor models complete the exact same existing 17-action single-participant prefix. The previous boundary-beat candidate completed only the first action before stopping at D1→P1. The refined cursor model completes all17 with one genuine participant.

| Proposition | Cursor received from | Final own acts | Reset intervals | Refined local intervals |
|---|---|---:|---|---|
| D1 | Origin | 1 | `[]` | `[4]` |
| P1 | Origin through unattested D1 | 1 | `[]` | `[2]` |
| Q1 | P1 | 1 | `[]` | `[1]` |
| S1 | P1 through unattested Q1 | 1 | `[]` | `[2]` |

Thus a lone participant can preserve observable spacing through fresh, initially unattested propositions in this control. No Attestation crosses a proposition boundary. Each ordinary proposition has only one own vote here, so each has one interval and remains Cadence-immature0. This control demonstrates continuity, **not mature single-participant Cadence or an Emergence transition**. Longer/new actions were not invented to produce those outcomes.

## 4. Zero-mean regularity and mixed CV

At c5, Q1's second genuine support vote completes its local history `[0,0]`:

| Measurement | Runtime result |
|---|---:|
| Usable interval count | 2 |
| Mean | 0 |
| Population SD | 0 |
| Raw mathematical CV | undefined / null |
| Authorized effective CV | 0 |
| Cadence maturity | mature |
| Developmental Cadence | +1 |
| Cadence direction | Positive |
| Orientation / direction | +0.5 / Positive |
| Local Chirality | +0.75 |
| Normalized ancestral contribution | +0.0665410498 |
| Field Chirality | +0.5933703 |
| Correspondence | −0.3700546 |
| Closure | 0 |
| Tension | **+0.0744386** |
| Charge | 0.9255614 |
| Grounding | satisfied |
| Current Phase | Disperse4 |
| Available next action | `proposition.variant` |
| Bias structurally reachable | no |

Raw CV remains separately visible; it is not falsely reported as ordinary division yielding0. The positive direction is produced by the existing direction service from Cadence+1, not by overriding direction state.

The same run verifies P1's mixed `[10,0]`: mean5, SD5, raw/effective CV1, zero-special-casefalse, Cadence0. Its direction remains Negative through ordinary zero hysteresis. A singleton `[0]` remains immature/neutral, not+1. No zero is removed, and no mixed history uses the new special case.

## 5. Q1 continues; fresh S1 still resets

The c5 Attestation now persists and ordinary lifecycle progression continues. Q1's local snapshot, including Cadence+1 and local Chirality0.75, remains identical all the way through the last defined boundary c17. Creating descendants and other propositions' votes do not degrade Q1's rhythm.

| Boundary | Source | Local intervals | Local Chirality | Ancestors | Field Chirality | Tension | Directions | Grounded / Phase |
|---|---|---|---:|---:|---:|---:|---|---|
| c5 | Q1 | `[0,0]` | 0.75 | 0.0665410 | 0.5933703 | **+0.0744386** | +/+ | yes / Disperse4 |
| c6 | Fresh S1 | `[]` | 0 | 0.1578182 | 0.1578182 | −0.1010438 | −/− | no / Mediate5 |
| c8 | S1 first own vote | `[0]` | 0.25 | 0.1578182 | 0.3286286 | −0.0441070 | +/− | yes / Mediate5 |
| c17 | Fresh S2 | `[]` | 0 | 0.0480167 | 0.0480167 | −0.1376444 | −/− | no / Mediate5 |

The positive Q1 field therefore does not automatically become positive local Chirality in S1. S1 gets a beat cursor, not Q1's samples or Cadence+1. Its single local zero is immature; its positive Orientation meets retained Negative Cadence direction. This is a fresh-source/maturity distinction, not renewed lineage-wide zero accumulation. Q1 itself remains strongly positive and agreed.

At c17 A0/C7/Mediate can lawfully select Reconcile using S2 and S1. This is the closest structural approach to Bias reached by the candidate. Q1 at c5 is the later positive, grounded, direction-agreed window, but it is still at Disperse. No measured boundary has all four target conditions.

## 6. Exact remaining blocker: two incoming cursors

At c18, real Reconcile attempts to create R from two causal sources:

| Incoming source | Own acts | Cursor provenance | Endpoint event order | Represented Cycle position |
|---|---:|---|---:|---:|
| Acting source S2 | 0 | Q2's beat passed through unattested S2 | 27 | 15 |
| Other source S1 | 1 | S1's own last vote | 23 | 13 |

Propagation is well-defined on each input branch. A **single cursor for their joint result** has not been specified. The earlier candidate already had an explicit multi-parent selection guard; neither of the two authorized refinements selects between these inputs. Choosing the acting source, latest incoming beat, or other branch silently would add a merge-selection rule.

The diagnostic records both real input cursors and rolls back the attempted write. R is not persisted, and no first Bias-reachable or grounded-Bias candidate measurement exists in this run. This is an unresolved diagnostic semantic choice, **not** a production Reconcile-admissibility failure or demonstrated negative field result for R.

For this particular merge, acting-source and latest-incoming selection would both choose event27, while the other branch carries event23. They remain distinct possible general rules; this run chooses neither.

## 7. Invariance and ownership evidence

- All eight ordinary births completed before the stop have no own/effective inherited votes, empty local intervals and local O/AC/Chirality0. Carrying a cursor does not satisfy grounding.
- 190 existing-proposition comparisons: **180 unchanged local snapshots**, ten own-Attestation changes. Forty-two unchanged comparisons occur at Cycle-entry boundaries.
- P1→Reclassify→Q1 leaves P1's local snapshot unchanged. Q1→S1 leaves Q1's completed zero-regularity measurement unchanged. Later unrelated votes also leave Q1 unchanged through c17.
- P2's new interval15 belongs to P2 only; D2's own history remains empty. Cursor updates identify the ending act without adding it to ancestors or descendants.
- Existing production inverse-square attenuation terms and their normalization are retained, with every captured aggregate checked against the sum of head and ancestral contributions. Correspondence and Closure match the preserved same-boundary baseline throughout all51 completed actions.
- Historical influence remains separate: S1 begins local0 but receives ancestral contribution0.1578182, including Q1's increased measurement. This does not import Q1's Cadence or votes.
- No replacement-Attestation event occurs in the supported prefix. Existing historical-act/change-signature behavior is retained; replacement behavior is not independently demonstrated by this run.

## 8. Comparison and result

| Question | Previous boundary beat | Refined cursor + zero rule |
|---|---|---|
| Single-participant control | Stops at action1: predecessor D1 has no beat | All17 original actions complete; each ordinary node gains one own interval |
| Primary unattested D2→P2 | Previously unreached; archive exposes missing own beat | Cursor passes unchanged; first P2 vote owns interval15 |
| Q1 `[0,0]` | Undefined CV stops c5 | Effective CV0, Cadence+1, Tension+0.0744386; continues |
| Mixed P1 `[10,0]` | CV1, Cadence0, +/− | Unchanged |
| First Bias source | Not reached | Reconcile reached, but incoming cursor selection unresolved |
| Grounded Bias attempt | Not reached | Not reached; no fabricated source or ground state |
| Emergence | Not established | Not established; downstream outcome unresolved |

`COMPARISON.md` also retains complete-reset and live-v3 diagnostic baselines, explicitly labelled as archival evidence. No baseline value is presented as a new candidate measurement after the stop.

The measurements support the two refinements where evaluated: temporal continuity survives unattested structural nodes without vote inheritance, and a mature identically-zero local rhythm becomes maximally regular. They do not yet determine a full Settlement→Emergence lifecycle. The next required semantic decision is **how Reconcile selects or combines its incoming beat cursors**. No other rule was tuned.

## Files added

- `diagnostics/chirality-v3-propagating-cursor/CursorFormulaMeasurements.php`: preceding diagnostic formula/primitive adapter with a protected interval extension point; formula bodies unchanged.
- `PropagatingCursorMeasurements.php`: own-Attestation cache, cursor pass-through, raw/effective CV distinction, and zero-regularity refinement.
- `CursorRecorder.php`, `CursorReplayTest.php`, `SingleCursorTest.php`: exact real-service replay, traces and invariant assertions.
- `bootstrap.php`, `export.py`, `report.py`: opt-in execution and evidence rendering.
- `README.md`, `REPORT.md`, `EVENTS.md`, `TRACE.md`, `BEATS.md`, `COMPARISON.md`, `IDENTITIES.md`, `SINGLE-PARTICIPANT.md`.
- `runtime-results.tar.gz`: lossless measured prefix, both controls, invariant and summary JSON; `run-manifest.json`.
- `.github/workflows/propagating-cursor.yml`.

Existing source, fixtures and earlier diagnostic files were reused read-only. No PR or merge. Stop for review.
