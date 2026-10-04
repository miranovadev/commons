# Post-c21 Bias → Order → ordinary Commit

**The real lifecycle completed its first ordinary Commit after three new actions. Settlement remained Settlement. No Emergence occurred.** No negative-Tension gate was added, no Rest was dispatched, and no extra vote was authored to improve Cadence.

There is a newly exposed **Commit-specific scope conflict**: the unchanged candidate adapter gives the fresh Committal local Orientation `+0.5` and local Chirality `+0.25`, despite zero own Attestations. Production Commit deliberately exposes its source's contribution history by reference. Therefore this run establishes the actual lifecycle trajectory under the preserved adapter, but **does not certify the blanket fresh-local-state invariant at Commit**. The Bias result satisfies that invariant. Neither the adapter nor production semantics was patched to settle this distinction.

Tested code: `4a89eea87a6a5fec6fa31e55aa15026ad9132bda` on `diagnostic/chirality-v3-post-bias`, based on `a65ad7b3e612ca9e91e2f55a76f7e160aa6ba0b0`. [Successful Actions run](https://github.com/miranovadev/commons/actions/runs/37163662049): seven tests, 956 assertions, including the existing single-participant controls and canonical Reclassification, Reconcile, and stale-Bias tests. The tests' success does not erase the newly observed Commit-local conflict.

## Exact lawful sequence

The same 33-action prefix and c0–c21 history reproduced the reference state. All three existing participants were at Bias; all were offered `proposition.reclassify` on R. The prior c22 Rest was not replayed.

| Boundary/action | Actor, Cycle, Phase before → after | Target/result | Actual production reason |
| --- | --- | --- | --- |
| c21 reference | A0 C7 Bias | R | Bias generation true; O/AC Positive/Positive; 12 authorised Receipt choices; Tension −0.0137710 |
| post-bias:0 `proposition.reclassify` | A0 C7 Bias → Order | R → B | Offered Bias; selected authorised Receipt; real contribution service created B |
| post-bias:1 `attestation.create`, Support | A1 C3 Bias → Order | B | B lacked an effective Attestation. A1 and A2 were offered Attest; A1 is first in stable participant order. One vote supplies grounding, maintaining the fixture's supportive stance |
| post-bias:2 `proposition.commit` | A0 C7 Order → C8 Distinguish | B → C1 | Actual schema now offers Commit; every production Commit predicate passes. A0, the original acting participant, dispatches it |

Exactly two new propositions, one new Attestation, zero new Receipts, and one automatic post-Commit Cycle entry were created. Population remained three. The run stops immediately after the first ordinary Commit; it does not explore C8.

The exact Bias input was:

```json
{"action":"proposition.reclassify","proposition_id":"01m423n2w0061rt1833xtqar72","receipt_id":"01m423jd32dpnvz7grqrjr6zgb"}
```

R is `01m423n2w0061rt1833xtqar72`; B is `01m423nqtvgwzjs177tewyjyjn`; C1 is `01m423p51vzz0sd29xv6qe2htg`.

## Receipt selection

The first Receipt in the actual schema order was selected, following the existing NaturalRhythmTest `enum[0]` convention. It is the completed hyperlink Receipt authored by A2 at prefix:5 against P1, reference `https://example.test/evidence/2/5`. The selection was announced before dispatch and verified against the runtime Receipt reference. No alternative was evaluated for advantageous field values.

The other choices were P1 Receipts at prefix:10,13,17,18,23,24,29,30, then S1 at c7, D2 at c13, and S2 at c20. [RECEIPTS.md](RECEIPTS.md) records all twelve IDs, original targets, references, producing Phases, executions and Horizon. The real Bias operation consumes the selected evidence; no source Receipt was fabricated or pre-attached to R. B consequently has valid Receipt grounding immediately after creation, despite no new Receipt row.

## Measurements and the first Order boundary

| State | Own intervals | Local O / AC | O / AC directions | Local χ | Normalized ancestral χ | Field χ | Correspondence | Closure | Tension | Charge |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| c21 R | [0,0] | +0.5 / +1 | Positive / Positive | +0.7500000 | +0.1093732 | +0.4930049 | −0.5343177 | 0 | −0.0137710 | 0.9862290 |
| Fresh B, first Order reachability | [] | 0 / 0 | Negative / Negative | 0 | +0.1730811 | +0.1730811 | −0.5476827 | 0 | −0.1248672 | 0.8751328 |
| B after its one grounding Attestation; immediately before Commit | [0] | +0.5 / 0 | Positive / Negative | +0.2500000 | +0.1730811 | +0.3156786 | −0.5476827 | 0 | −0.0773348 | 0.9226652 |
| C1, actual ordinary Commit observation* | [] | +0.5 / 0 | Positive / Negative | +0.2500000 | +0.1306198 | +0.2795971 | −0.5442648 | 0 | −0.0882226 | 0.9117774 |

*The C1 local values expose the Commit-specific conflict explained below; they are recorded measurements, not a claim that the fresh-local candidate invariant passed.*

At fresh B, A0 is already at Order. Its schema offers only Rest, but the diagnostic does not take it: the other existing actors are offered Instrument and Attest. `CommitmentAdmissibilityService` identifies precisely one missing requirement: **`missing_effective_attestation`**. Receipt, Closure, Phase position, causal write, active-region candidacy and write opportunity already pass.

A1's genuine vote resolves that requirement. Both A0 and A1 then receive `proposition.commit` as their offered action. Every requirement passes; reasons are empty. B's Chirality component directions disagree, and its Tension is negative, but **neither condition is an Order/Commit prohibition**. Bias's component-agreement rule is not reapplied as an invented Commit condition.

The real Closure preview and immutable C1 birth observation agree: committed cardinal position `0`, Cadence `0`, Closure `0`, both governing directions retained Negative, admissibility true, ordinary Closure cadence interval `15`, and committed lateral position `1`. This Closure Cadence is distinct from Attestation Cadence. No coefficient, direction default, or Closure calculation changed.

## Fresh B and cursor continuity

B receives no Attestation rows, no effective Attestation, no local interval samples, no local Orientation, and no local Chirality from R. Its fresh local tuple is `(0,0,0)`. Its initial component directions are the existing Negative defaults/hysteresis, not inherited R directions.

B's incoming cursor is R's last genuine Attestation, event order `29`, represented Cycle-entry position `15`. R's own `[0,0]` does not cross. A1's B Attestation is event order `30`, still represented position `15`, so it closes **one local B interval `[0]`**. Mean and SD are zero, CV unavailable, and maturity remains insufficient: the mature-all-zero rule requires at least two intervals. B's Orientation changes `0 → +0.5`, Cadence remains `0`, and local Chirality changes `0 → +0.25`. This is the only post-c21 Attestation-triggered change.

C1 receives B's outgoing cursor at event order `30`/position `15`. C1 has no own Attestation, so its local interval history stays empty and no interval is appended at Commit or at automatic Cycle entry. There is no additional multi-parent case or cursor-selection ambiguity. No unattested multi-hop pass-through is needed after c21; the earlier control remains passing.

## Attenuation and invariance

R's local `+0.75` contributes `+0.1069482` to B at distance 1, through production weight `1/4` and normalisation. At C1 it contributes `+0.0496591` at distance 2, through weight `1/9`. R's contribution is never copied into either descendant's local Chirality. Both sides of R's Reconcile ancestry remain present, with shared ancestors counted once. [ATTENUATION.md](ATTENUATION.md) lists every term.

R and Q1 keep their entire local snapshots unchanged across Bias creation, the unrelated B vote, Commit, and the automatic Cycle entry. Q1 remains `[0,0]`, Cadence `+1`. Across the complete replay there are 261 existing-local-snapshot comparisons: 248 unchanged and 13 own-Attestation changes; twelve changes are in the replay and one is B's vote. These comparisons concern already existing propositions and **do not test the correctness of a new Committal's initial local snapshot**.

There is no lineage-wide interval accumulation: R stays `[0,0]`, B gets `[0]`, C1 gets `[]`. Only B's genuine vote adds a post-c21 interval. No structural event alters any existing proposition's local measurements.

## Commit result and the newly exposed scope conflict

An ordinary, production-accepted Commit exists. Its immutable snapshot records:

- creating Domain Settlement; `direction_before = settlement`, `direction_after = settlement`;
- Correspondence `−0.5442648`, Chirality `+0.2795971`, Closure `0`;
- prominence `0.4558887`, turbulence `0.5441113`, Tension `−0.0882226`;
- Attestation Cadence `0`, Orientation `+0.5`; Closure cardinal/Cadence directions both Negative;
- Closure interval `15`, matching the pre-write preview.

The acting participant automatically enters Cycle 8 in the same Settlement Horizon, with C1 as entry Committal and only Variant offered. We stop there. The snapshot's `tension_before = −0.1666666` refers to its preceding **committed** observation (Origin), not to B's immediately preceding `−0.0773348` field measurement.

**The remaining semantic issue is at Commit, not Bias or Order reachability.** Production `PropositionStateService` explicitly documents that only Commit inherits contribution history by reference to original rows. `sourceIds()` traverses `commitSources`, while expressive Reclassify results stop at their own IDs. `AttestationEvidenceService::valueFor()` uses those source IDs, and the reused Orientation formula calls that service. Consequently the unchanged local adapter calculates C1 Orientation `+0.5` from B's vote even though C1 has no own vote; C1 local Chirality becomes `+0.25` on its structural creation. C1's local interval collection remains empty because Cadence independently queries own Attestation rows.

This is a concrete mismatch between the blanket candidate statement “all new propositions start locally fresh and change only on their own Attestation events” and the reused adapter's handling of production Committals. It is not direct copying of R's values, and no new Attestation row is fabricated, but it does expose effective source history and a nonzero initial local Orientation outside the candidate's stated scope. Production behavior is established; how the candidate should apply to it is not resolved by this run. We neither weakened Commit grounding nor altered the Orientation/measurement adapter. No counterfactual Commit field is substituted for the recorded one.

Therefore: **ordinary Commit reached; no Settlement → Emergence; no lifecycle dead end; stop condition satisfied.** The negative final Tension explains the observed retained Settlement state. A fully conforming candidate Commit measurement needs this newly exposed scope distinction resolved before promotion or further claims.

## Evidence and exact files

New runnable diagnostic files:

- `diagnostics/chirality-v3-post-bias/PostBiasReplayTest.php`
- `diagnostics/chirality-v3-post-bias/PostBiasRecorder.php`
- `diagnostics/chirality-v3-post-bias/bootstrap.php`
- `diagnostics/chirality-v3-post-bias/export.py`
- `.github/workflows/post-bias.yml`

New report/reproduction files in the same diagnostic directory: `report.py`, `README.md`, `REPORT.md`, `TRACE.md`, `EVENTS.md`, `ATTENUATION.md`, `RECEIPTS.md`, `summary.json`, `commit-boundary.json`, `run-manifest.json`, and `runtime-results.tar.gz`. The lossless `candidate-trace.json` is available locally and inside the archive; it records all 59 boundaries, all actual action schemas, decisions, IDs, local snapshots, predicates and Commit snapshot. It is compressed for repository preservation.

Reused unchanged: `chirality-v3-local-reconcile/LocalReconcileMeasurements.php`, its bootstrap and single-participant test; the propagating-cursor formula and event-order machinery; the positive-window authored fixture/archive. No existing diagnostic file, production file, canonical fixture or formula was modified. No PR or merge.

Relevant production implementation at the base revision:

- `InputSchemaService::forParticipant()` — offered Bias Receipt combinations and Order Commit options.
- `PhaseGenerationService::availableBias()` — actual Bias conditions, without positive Tension.
- `ContributionDispatcher::reclassify()` / `commit()` — real fresh Bias write, evidence consumption, Commit source link, immutable Commit creation.
- `CommitmentAdmissibilityService::evaluate()` — complete Order/Commit requirements.
- `ClosureGateService::preview()` and `ClosureBirthService` — actual frozen Closure calculation.
- `PropositionStateService::sourceIds()` and `AttestationEvidenceService::valueFor()` — Commit-specific source-history behavior.
- `CommitmentSnapshotService::record()` — grounding recheck and actual Domain transition from persisted Commit measurements.

[TRACE.md](TRACE.md) contains every post-c21 actor state and available action. [EVENTS.md](EVENTS.md) contains the event positions, interval statistics, cursor provenance, and every structural invariance check. [commit-boundary.json](commit-boundary.json) preserves the complete measured Commit boundary.
