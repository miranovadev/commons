# Full canonical Chirality-state preservation

**The focused replay supports a coherent frozen canonical record: source component values and resolved directions can be preserved alongside the resolved aggregate, without creating Committal Attestations or local Cadence history. Existing read consumers accept that metadata. Raw calculation APIs still require an explicit canonical-boundary branch; this is not a drop-in production implementation.**

Candidate C1 remains Settlement: Correspondence `−0.5476827`, Chirality `+0.3156786`, real frozen Closure `0`, Tension `−0.0773348`, Charge `0.9226652`. Adding component metadata changes none of these scalar-preservation results. No Emergence occurs.

## 1. Exact source B observation

The identical authored history, deterministic Receipt selection, required B Attestation, ordinary Commit and first child were replayed through real services. Immediately before Commit, B exposed:

| Source field | Value |
| --- | --- |
| Resolved Correspondence | −0.5476827 |
| Published Orientation | +0.5000000 |
| Resolved Orientation direction | Positive |
| Published Attestation Cadence | 0.0000000 |
| Resolved Cadence direction | Negative |
| Resolved/attenuated Chirality | +0.3156786 |
| Combined Chirality direction | Positive |
| Own local Chirality | +0.2500000 |
| Own Cadence intervals | [0], immature |
| Prospective Commit Closure | 0, admissible |
| Tension | −0.0773348 |
| Charge | 0.9226652 |

**Scope matters:** production exposes the head-local component values with independently resolved directions, while only their combined scalar is attenuated across ancestry. The component mean is `0.25`, not the resolved aggregate `0.3156786`. `ChiralityValues` documents this explicitly; `LineageMeasurementService::calculate()` returns the distance-zero components separately from its accumulated scalar. The diagnostic preserves the actual exposed source state; it does not invent separately attenuated Orientation/Cadence values.

## 2. Canonical C1 representation

The preceding hard-edge diagnostic's read-only canonical record is retained and extended with `canonical_components`, copied directly from B's already published measurement:

```json
{
  "orientation": 0.5,
  "orientation_direction": "positive",
  "attestation_cadence": 0.0,
  "attestation_cadence_direction": "negative",
  "chirality": 0.3156786,
  "chirality_direction": "positive"
}
```

The record separately carries C1 ID, source B ID, `kind = canonical_resolved_baseline`, resolved Correspondence, the real Commit Closure birth, field values, Domain before/after, and `component_origin = preserved_source_observation_not_committal_local_calculation`.

`local_expressive_components` and `local_cadence_samples` remain null: C1 is not acquiring a new expressive local observation. Its own rows/samples are empty. The source's `[0]` interval is diagnostic provenance on B, not a C1 interval or a copied rhythm history.

No `sourceIds()`/evidence-based evaluation is used to construct these candidate components. The comparator's production C1 calculation still occurs as before, but candidate metadata is copied from the captured pre-Commit B observation. No C1 zero hysteresis, primitive default, component averaging or scalar-to-component direction inference is used.

## 3. Three Commit interpretations

All rows use the same genuine B → C1 edge and actual frozen Closure.

| Interpretation | Orientation | O direction | Cadence | AC direction | Chirality | Correspondence | Closure | Tension | Charge | Domain |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A. Existing Commit measurement path under the expressive candidate | +0.5 | Positive | 0 | Negative | +0.2795971 | −0.5442648 | 0 | −0.0882226 | 0.9117774 | Settlement |
| B. Hard-edge scalar preservation | not represented | not represented | not represented | not represented | +0.3156786 | −0.5476827 | 0 | −0.0773348 | 0.9226652 | Settlement |
| C. Full canonical-state preservation | +0.5, preserved | Positive, preserved | 0, preserved | Negative, preserved | +0.3156786 | −0.5476827 | 0 | −0.0773348 | 0.9226652 | Settlement |

In A, C1 is a newly measured head and its component values arise from source-history exposure. In C, the same numerical pair happens to occur, but it is copied frozen source metadata, not C1 local state. Equal numbers do not establish equal provenance. A is not a fresh run of unmodified production Chirality: it is the existing production Commit path with the previously established expressive diagnostic adapter, exactly as in the prior comparisons.

## 4. No Attestation or local-history inheritance

Runtime checks found:

- C1 own Attestation rows: **0**;
- C1 effective **own** Attestations: **0**;
- C1 own local Cadence samples: **none**;
- production Commit source references: intact;
- source-resolved effective evidence: the genuine B Attestation remains accessible by its original reference;
- no extra Attestation or Receipt during canonical construction or the child write.

The own/effective-source distinction is intentional. Production Commit can resolve B's contributions without turning those rows into C1's own rows. Canonical metadata does not fabricate evidence or satisfy grounding by itself.

## 5. Real consumer probes

The diagnostic supplies read-only, in-memory measurement views to the existing services. Neither view is saved over C1's database measurement or snapshot. A SQL mutation guard covers the consumer calls; the persisted measurement is checked unchanged afterward.

| Consumer | Scalar-only canonical view | Full canonical view |
| --- | --- | --- |
| `ChiralityGateService::evaluate` | `component_directions_unavailable`; component directions null | `component_directions_disagree`; Positive/Negative exactly |
| `PropositionMeasurementService::chiralityAttributes(..., components: null)` | Component values/directions null | Orientation +0.5/Positive; Cadence 0/Negative unchanged |
| `PropositionResource::toArray` | Publishes preserved C/χ/Closure | Publishes the same preserved C/χ/Closure |

The combined Chirality direction is Positive in both views. The Gate service does not infer component agreement from that scalar direction. Preserving Negative direction for zero Cadence is essential: the source's zero retains a resolved historical direction, not a new C1 default.

The Gate probe tests interpretation of a supplied measurement; it does **not** claim that C1 is an operational Bias source or that a new Bias action was performed. Commit's separate Closure admissibility remains untouched.

## 6. API compatibility and required explicit branches

[API-AUDIT.md](API-AUDIT.md) inventories the inspected production services, serializers and UI consumers, distinguishing runtime probes from static inspection.

Frozen-state readers already have suitable behavior: existing-snapshot paths in `PropositionMeasurementService`, `CommitmentSnapshotService`'s field storage, `PotentialProjectionService`'s snapshot branch, graph scalar publication, and scalar population observation can retain the values and directions. Gate diagnostics and their plan/prompt/UI forwarding can consume the preserved pair without arithmetic reconstruction.

An explicit canonical boundary remains necessary in raw/local calculation, `LineageMeasurementService::calculate/prospectiveCommit/chirality`, `ObservableService` delegation and the **first** canonical projection before its snapshot exists. In particular, `LineageMeasurementService::chirality()` currently obtains each member's scalar from its component mean. Feeding it the preserved `(0.5,0)` pair alone would incorrectly use `0.25` as C1's baseline instead of `0.3156786`.

Likewise, initial canonical publication must accept the source's resolved direction strings rather than invoke `componentAttributes()` as a fresh C1 observation. Existing snapshot-copy branches already avoid that re-resolution once the snapshot exists.

Thus a scope-tagged canonical observation must retain **both** the independently resolved aggregate and component metadata. Reusing a plain local `ChiralityValues` object as if it fully represented the canonical scalar is unsafe. This is the concrete representation hazard, not a reason to alter expressive formulas or attenuation.

## 7. Closure and Domain result

The real prospective Closure and actual frozen birth compare equal. Depth/cardinal/lateral values and Closure Cadence are all `0`; governing directions are Negative/Negative; Closure admissibility is true. The frozen ordinary cadence interval is `15`, lateral position `1`, with Origin as committed history predecessor. Existing histories are Emergence `[1]` and Settlement `[15]`; comparative Closure CV remains immature.

The canonical record uses that newly evaluated Commit Closure, not B's Closure observation. Existing projection computes Tension `−0.0773348`, Charge `0.9226652`; `ContextDomain::after` returns Settlement. Component preservation changes neither field formula nor Domain decision.

## 8. E1: expressive measurement starts fresh

The same first lawful Variant from C1 is dispatched through `ParticipationService`. E1 begins with:

| E1 local state | Result |
| --- | --- |
| Own/effective own Attestations | none |
| Local Orientation | 0 |
| Local Cadence intervals | [] |
| Local Cadence | 0, immature |
| Local Chirality | 0 |
| Incoming beat cursor | event order 30, unattested pass-through |

C1's canonical Orientation `+0.5` is not assigned to E1. Neither canonical Cadence metadata nor B's `[0]` interval becomes E1 local history. Existing direction/hysteresis semantics are not changed; this test distinguishes local values/history from reading committed directional provenance where production already does so.

The unmodified production walker includes only E1 at distance 0/weight 1 and C1 at distance 1/weight 1/4. The same canonical-baseline provider used in the previous diagnostic supplies C1's resolved scalars and delegates expressive nodes to the unchanged local adapter. It does not pass canonical component metadata into E1's local calculation.

| E1 field | Value |
| --- | --- |
| Correspondence | −0.5908537 |
| Chirality | +0.0631357 |
| Closure | 0 |
| Tension | −0.1759060 |
| Charge | 0.8240940 |

Attestation/Receipt counts remain 14/14. All existing local snapshots and the production Commit snapshot remain unchanged. Full metadata produces the same child scalar result as scalar preservation: normal downstream attenuation resumes, and B/R are not independently counted behind the canonical boundary.

## 9. Invariants, ambiguity and limits

No new semantic choice was required to preserve the source state that the current APIs actually expose. “Resolved components” here means B's published **head-local** component values plus resolved directions—not a newly defined pair of separately attenuated components. Their mean need not equal the attenuated scalar; this is already true at B. If an aggregate-decomposing component pair were intended instead, that would require a different, untested rule. None was invented.

The earlier hard-edge candidate still deliberately differs from production's Committal-as-new-head and raw-only attenuation contracts. The new metadata preservation is compatible with existing immutable snapshot diagnostics and zero-direction retention, provided the first canonical write copies source values/directions and raw consumers are routed explicitly.

This remains a **read-only canonical representation and consumer diagnostic**, not a production storage migration or an end-to-end installation of the candidate across every API. The audit identifies the remaining integration work; it does not hide it behind passing read probes. No database candidate snapshot was substituted, no grounding was weakened, and no production code was changed.

## 10. Reproduction and exact files

Tested commit: `afcff30b1a84b9c04ee6fda888b9be9efceda9fd`, based on `1274cbf934f74061f578a540740ff34513846451`.

[Actions run 37170193512](https://github.com/miranovadev/commons/actions/runs/37170193512) passed **7 tests, 985 assertions**: 784 focused assertions, 145 unchanged single-participant controls, and 56 canonical Reclassification/Reconcile/stale-Bias assertions. The runtime evidence is `canonical-state.json`.

Added code:

- `diagnostics/chirality-v3-canonical-state/CanonicalStateAudit.php`
- `diagnostics/chirality-v3-canonical-state/CanonicalStateReplayTest.php`
- `diagnostics/chirality-v3-canonical-state/bootstrap.php`
- `diagnostics/chirality-v3-canonical-state/export.py`
- `.github/workflows/canonical-state.yml`

Added evidence/documentation in that directory: `README.md`, `REPORT.md`, `API-AUDIT.md`, `canonical-state.json`, `run-manifest.json`.

Reused unchanged: `chirality-v3-hard-edge/HardEdgeAudit.php`'s canonical-ancestor provider and replay approach; post-Bias recorder/fixtures; `LocalReconcileMeasurements.php`; all upstream candidate formula/cursor machinery; real lineage, Closure, projection, Domain and contribution services; existing canonical/single-participant tests. The old recorder writes an ephemeral runtime checkpoint during replay; no previous archived result or source file is changed in the branch.

Only the new diagnostic directory and workflow were added. No production changes, PR or merge. Stopped after the same first expressive child and the read-only consumer checks.
