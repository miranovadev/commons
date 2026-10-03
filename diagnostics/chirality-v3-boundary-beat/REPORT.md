# Boundary-beat Chirality diagnostic

**Boundary-beat continuity works without inherited Attestations: P1 forms `[10]`, then mature `[10,0]`. It does not produce a positive grounded Bias witness in the defined replay.** The primary run stops at Q1's second own Attestation because its local samples become `[0,0]` and CV is undefined. The single-participant control independently encounters the explicitly guarded unattested predecessor.

## Runtime and limits

- Tested commit: `dcf1f25c93308c6ca8c97474ccdba0ab2c3b95d3`.
- Run: https://github.com/miranovadev/commons/actions/runs/37158801910 ; job `111307682059`.
- PHP8.5 / PostgreSQL18; **7 tests, 623 assertions passed**: primary483, single-participant controls84, canonical tests56.
- Same three-participant primary history: 33 prefix actions plus c0–c4 completed, **38 successful actions / 39 boundaries**. c5 was attempted through `ParticipationService::contribute` and rolled back at undefined CV.
- No ordinary Commit, no Settlement→Emergence transition, no four-way overlap in the defined prefix. Downstream Bias results are unresolved, not inferred failures.
- Existing complete-reset and lineage-live developmental-v3 runs provide the baselines. The superseded completed-interval-anchor run is comparison C only. No superseded sample inheritance remains in the new candidate.

## 1. Exact position representation

The only rhythmic boundary state is `endpoint_order`: the immediate causal predecessor's last own historical Attestation event-order coordinate, captured when the child's causal edge is written. A process-local map indexes it by child ID and records parent ID / edge type for provenance. It is not a timestamp or a copied Attestation row. Parent interval arrays in the audit are never calculator inputs.

Represented Cycle-entry population is the existing primitive: relevant entry-Committal seeds in the child's exact Horizon. The first child interval counts Cycle-created events strictly between endpoint and child act. In the event trace, Cycle position means the count of represented entries before that beat; differences therefore equal the original interval count. Event-order coordinates are also recorded to disambiguate multiple acts at the same position.

Capture time is explicit: **birth**, not a fresh lookup at the child's first vote. Later parent votes do not move the boundary beat. This matters for P1. No completed parent interval is required, and no completed interval is transported as a sample. No arbitrary ancestor lookup occurs.

Origin keeps its constitutional calibration. The Origin beat is observed at event2; in the first Settlement's represented population it has position0. The three existing Settlement entry Cycles precede D1's first vote, so that first boundary interval is3. These are the same genuine three participants, not added entrants.

## 2. Actual propagation and child ownership

| Child | Immediate predecessor's beat captured | First own beat | First child interval | Subsequent local development |
|---|---|---|---|---|
| D1 | Origin: order2 / Settlement position0 | order6 / position3 | 3 | `[3]` → `[3,5]` → `[3,5,5]` |
| P1 | D1: order6 / position3 | order19 / position13 | 10 | `[10]` → `[10,0]` |
| Q1 | P1: order19 / position13 | order20 / position13 | 0 | `[0]` → attempted `[0,0]`, undefined |

D1 has only one own act when P1 is created. The new rule correctly works without requiring D1 to have completed an internal interval. D1 later receives votes at orders12 and18; those update D1 only and do not replace P1's stored order6 endpoint.

At c0 P1 receives its first genuine vote. P1 owns `[10]`; D1 retains `[3,5,5]` unchanged. At c3 Q1 owns its boundary interval `[0]`; P1 retains `[10]` unchanged. At c4 P1's second own vote extends P1 to `[10,0]`; Q1's existing local snapshot remains unchanged. These are direct runtime witnesses that the interval belongs to the child, not the parent or a shared lineage accumulator.

## 3. Reset, invariance and attenuation

All three new ordinary propositions D1, P1 and Q1 begin with zero own/effective Attestations, empty local interval arrays, Orientation0, Cadence0/immature and local Chirality0. A stored boundary coordinate does not ground a proposition or copy parent Orientation/direction, local Chirality, Cadence value or samples. Production snapshot/default direction resolution remains untouched.

The recorder checks every existing proposition across every boundary: **112 comparisons; 106 identical snapshots when its own Attestation state was unchanged; six own-Attestation updates.** Thirty unchanged comparisons coincide with new Cycle entries. For example, the Rest actions at prefix6–8 add Cycle entries while D1 retains `[3]`, Orientation0.5, immature Cadence0, local Chirality0.25. Future spacing changes only when its next act arrives at prefix15.

Reclassify P1→Q1 at c1 leaves P1's `[10]`, Orientation0.5, Cadence0 and local Chirality0.25 unchanged. Q1 starts empty and local0. Its field Chirality is nevertheless **0.0665410**, entirely normalized ancestry. This is historical influence through the unchanged production attenuation service, not local inheritance.

The first P1 vote changes its own Orientation0→0.5 and local Chirality0→0.25; Cadence remains numerically0 while gaining the immature sample `[10]`. Its second vote matures the sample list to `[10,0]`, but leaves those three scalar values unchanged. Recalculation is an event, not necessarily a numerical change.

Full invariant witnesses, complete local snapshots, effective vote IDs, stored directions and per-ancestor inverse-square terms are in the lossless runtime JSON. No Attestation replacements occur in the supported prefix, so replacement-event semantics are reused but not independently exercised here.

## 4. Main comparison: complete reset versus boundary beat

| Boundary | Model | Local intervals | Cadence / maturity | O / AC directions | Local Chirality | Field Chirality | Tension |
|---|---|---|---|---|---|---|---|
| D1 second vote, prefix15 | Reset | `[5]` | 0 / immature | + / − | 0.25 | 0.1000000 | +0.0295206 |
| D1 second vote, prefix15 | Boundary beat | `[3,5]` | +0.6000000 / mature | + / + | 0.55 | 0.3400000 | +0.1095206 |
| D1 third vote, prefix32 | Reset | `[5,5]` | +1 / mature | + / + | 0.75 | 0.5000000 | +0.1628540 |
| D1 third vote, prefix32 | Boundary beat | `[3,5,5]` | +0.6426143 / mature | + / + | 0.5713072 | 0.3570458 | +0.1152026 |
| P1 first grounded, c0 | Reset | `[]` | 0 / immature | + / − | 0.25 | 0.2806122 | −0.0115678 |
| P1 first grounded, c0 | Boundary beat | `[10]` | 0 / immature | + / − | 0.25 | 0.2477911 | −0.0225082 |
| P1 second own vote, c4 | Reset | `[0]` | 0 / immature | + / − | 0.25 | 0.2806122 | −0.0115678 |
| P1 second own vote, c4 | Boundary beat | `[10,0]` | 0 / mature | + / − | 0.25 | 0.2477911 | −0.0225082 |

P1 at c0/c4: C−0.3153158, Closure0, Charge0.9774918, normalized ancestral Chirality0.064117649. Grounding is satisfied. Cadence's zero retains Negative while Orientation is Positive. The relevant actor is at Constrain, where Reclassify remains available; this is not a Bias refusal. P1 is the closest grounded non-Origin source to zero Tension among measured current-Gate states in this supported prefix.

A subtle attenuation effect explains why P1's field is lower than reset despite its identical local Chirality. D1 now includes its lawful initial boundary interval3, so its local Cadence is0.6426143 instead of1. The resulting smaller ancestral contribution reaches P1 through unchanged attenuation. Rhythm continuity is not automatically an increase in regularity or field magnitude.

`COMPARISON.md` provides all four models, full C/Closure/Charge, directions, ancestry, grounding and action availability. Comparator A is the prior **lineage-live developmental-v3 diagnostic**, not a claim that provisional developmental Cadence has become canonical production semantics.

## 5. Exact first unresolved primary event

At c5 participant A0 genuinely attempts another support Attestation on Q1 through the existing API/service. Q1's boundary beat is P1's order19; Q1's own acts are orders20 and22. All three occupy represented Cycle position13. Therefore Q1's local samples are `[0,0]`: the first zero spans P1→Q1, the second spans Q1's own two acts. No unrelated vote is a Cadence beat for Q1.

The attempted raw statistics are count2, mean0, population SD0, CVundefined. Orientation remains+0.5, but Cadence, local Chirality and subsequent field are not assigned. The service transaction rolls back the second Q1 vote, leaving its first vote intact. This diagnostic exception is not a newly imposed production Attestation-admissibility rule.

A single `[0]` at c3 was correctly immature/neutral and allowed progression. At c5 two samples require the unchanged CV mathematics, exposing 0/0. Choosing a fallback would introduce another semantic change, so the run stops.

## 6. Missing-predecessor ambiguity

The primary runtime stops at c5 before encountering a beatless immediate predecessor. A read-only audit of the preserved full authored history finds the first such case at **c11: D2→P2**, where D2 has no own Attestation. Later examples include S2→R at Reconcile c18 and D3→P3 at c36. These are archive findings, not candidate-reached states.

The unchanged single-participant control reaches this exact ambiguity immediately: its first action creates D1 from attested Origin; its second action attempts Variant D1→P1 before D1 has received any Attestation. The adapter stops there under the user's explicit guard. The real write rolls back; only Origin and D1 remain. No Origin beat is forwarded through D1, no creation position is substituted, and no participant or vote is added.

The complete-reset control completes its original first17 actions. The boundary-beat control completes one and attempts the second. Therefore this lawful single-participant history **does not yet establish sustained boundary-beat continuity or mature Cadence**. It exposes the unresolved predecessor case instead. The three-participant runtime nevertheless directly establishes child-owned first intervals and mature local Cadence without Attestation inheritance. No universal claim of single-participant impossibility follows.

Reconcile also has two causal predecessors; selecting one last beat would need an explicit rule if reached. The primary run encounters undefined CV earlier, and its preserved Reconcile additionally includes an unattested immediate source.

## 7. Old pathologies and late-phase result

| Question | Observed result |
|---|---|
| Retroactive Cycle drift? | Eliminated in checked local snapshots; 30 Cycle-entry invariance witnesses. |
| Structural retroactivity? | No parent local changes at child birth/Reclassify; unchanged Attestation signatures stay cached. |
| Complete-reset interval starvation? | Corrected where a predecessor beat exists: first child vote creates a child-owned interval; P1 matures on its second vote. Missing-predecessor case remains explicitly unresolved. |
| Lineage-wide zero explosion? | Not restored. Only own child events append local samples. Genuine local `[0,0]` still occurs. |
| Component agreement? | D1 becomes mature/agreed earlier. P1 remains +/−; its mature Cadence0 retains Negative. |
| Positive grounded Bias window? | None in the defined prefix. Bias is not yet structurally reached. |
| First Bias / first grounded Bias measurements? | Not defined for this candidate: the runtime stops before S1, Reconcile and R. Baseline values are shown only as comparisons. |
| Settlement→Emergence? | Not established. Zero ordinary Commits in the supported replay; downstream outcome unresolved. |

For context only, the complete-reset archive reaches fresh R with local0 and Tension−0.1499018, then grounded R with `[0,1]`, Cadence0/retained Negative, Orientation0.5/Positive, Tension−0.0949146 and `component_directions_disagree`. Those are **not** boundary-beat predictions. Neither inheritance nor frozen parent field values were supplied to manufacture a fresh Bias-source result.

## 8. Semantic interpretation

Within its defined prefix, the candidate exhibits the requested separation: a predecessor's beat position allows a temporal measurement across the causal edge; the resulting interval belongs to the child; the child has no inherited Attestation or grounding; local measurements change only on its own Attestation events; historical measurement influence remains independently attenuated.

That mechanism is empirically demonstrated. A full lifecycle result remains blocked by undefined zero-mean CV, with the separately demonstrated missing-predecessor ambiguity still requiring resolution. Neither was silently filled in. No production rule is promoted by these results.

## Files and review artifacts

Only these additions were made:

- `diagnostics/chirality-v3-boundary-beat/BoundaryBeatMeasurements.php`
- `BeatRecorder.php`, `BeatReplayTest.php`, `SingleBeatTest.php`
- `bootstrap.php`, `export.py`, `report.py`
- `README.md`, `REPORT.md`, `EVENTS.md`, `TRACE.md`, `BEATS.md`, `COMPARISON.md`, `IDENTITIES.md`, `SINGLE-PARTICIPANT.md`
- `runtime-results.tar.gz` (lossless runtime JSON, summary and invariant witnesses), `run-manifest.json`
- `.github/workflows/boundary-beat.yml`

The previous own-Attestation adapter, interval primitive, archived authored histories and production services were reused read-only. Existing source/fixtures were not changed. No PR or merge. Stop for review.
