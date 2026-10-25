# Active causal Attestation Cadence

Authority: Claire's 25 October 2026 Chirality vNext corrective handoff, following merged participant-relative exploration PR #280. Implementation continues draft PR #281 from main `231dbd8498d75cd8a280f17bd5edb0a4a39a5629`. The ten Core Invariants and unchanged observable/Commit contracts govern this correction.

## Selection and measurement

An expressive Proposition starts with fresh local Orientation, zero local acts and an empty own interval array. Its first genuine ledgered Attestation selects up to two recent **own intervals** from the active selected causal ancestry, in chronological lineage order. Selection continues across short or unattested ancestors; an ancestor's own historical reference is never copied as if it were a new interval.

The selected producing edge is the existing `proposition_attestation_cursors.source_supersession_id`: unary operations select their actual source, while Reconcile selects its producing Phase's acting Gate. The foreign operand never supplies rhythm, regardless of edge insertion order or newer beats. Own Attestations advance the beat but now retain this birth provenance instead of clearing it. Additional later edges cannot replace the selected source.

All non-Variant Supersessions are hard under `SupersessionStrength`. A source with such an outgoing edge is excluded as an interval/beat contributor, including when the replacement is off the selected route. Its provenance remains traversable to the next eligible predecessor. Neither the replacing branch nor unrelated siblings become selected ancestors. Reconcile's Gate is therefore traversed for provenance but, once hard replaced, does not contribute its own intervals. No movement or exploration authority is inferred from this selection.

The same selection supplies the latest qualifying beat for the first own interval. Inherited cursors remain useful birth-time references; the first own event rechecks active ancestry so a replacement or new qualifying beat between birth and Attestation cannot select inconsistent rhythm. A parent event alone never rewrites its already-born descendants.

The current Proposition's first local event freezes a small `cadence_reference`: at most two `{proposition_id, interval_index, interval}` records. Subsequent local Attestations retain that reference and append only their own completed intervals. The reference belongs to the measurement context, not the child's event ownership. No parallel Attestation log, inherited vote, fabricated interval or ancestor measurement update is introduced.

For reference R and own intervals L, Cadence consumes `R ++ L` using the unchanged population SD/CV formula and rounding. Fewer than two total intervals remains immature with numeric zero. Genuine zero intervals remain samples; mature all-zero input has effective CV zero and Cadence +1. Orientation displacement, component hysteresis, combined Chirality, attenuation and Bias admissibility are unchanged.

The interval clock is unchanged: count the ending Proposition's represented Cycle entries with `previous_beat_sequence <= chirality_entry_after_sequence < current_attestation_sequence`, using its existing entry Committal/Horizon scope. Other ledger events do not become beats. Missing legitimate history contributes no invented samples.

## Normative example

A records own intervals `[2, 3, 2]`. B is a soft child; C then hard replaces B. C's first act is four represented entries after A's last qualifying beat, not four after B's excluded beat.

| Event | Reference | Own intervals | Calculator input | Cadence |
| --- | --- | --- | --- | --- |
| B first Attestation | A's `[3, 2]` | `[3]` | `[3, 2, 3]` | 0.6995578 |
| C first Attestation | A's `[3, 2]` | `[4]` | `[3, 2, 4]` | 0.5721225 |
| C second Attestation | Same A reference | `[4, 3]` | `[3, 2, 4, 3]` | 0.6185129 |

A and B retain their recorded measurement states. C owns two actual Attestations and two intervals, not four inherited events. Reclassification and Revision both exercise this contract.

## Canonical and event boundaries

A Committal remains a hard measurement boundary. Its existing genuine beat cursor may be transported, as required by Chirality Stages 2–3, but it receives no local interval array or Attestation history. Historical interval selection stops there and never rewalks pre-Commit expressive ancestry. The first post-Commit expressive reference is empty; its own first interval may still close against the canonical boundary's existing cursor. Canonical scalars and component directions remain immutable and independently preserved.

Only an own Attestation computes local Cadence. Cycle advancement, structural progression, read projections, hard supersession and unrelated activity do not remeasure existing local state. The existing substrate transaction/lock serializes beat selection, interval recording and publication. Re-recording one ledger act is idempotent; rollback removes the attempted new context and measurement together.

This remains a fresh/prospective-state implementation. Existing historical substrates are not backfilled or reconstructed. An already-attested state missing its event-born reference/cursor is explicitly unavailable for live continuation; historical snapshots are not rewritten. The migration defines the new compact field for fresh databases.

## Resolved boundary register

| Boundary | Question | Resolution and authority | Verification |
| --- | --- | --- | --- |
| Active vs historical | Do hard replacements contribute? | No own intervals/beats; keep traversing their selected provenance. Handoff §§2–4, `SupersessionStrength`; documented | Normative A/B/C and off-route replacement |
| Short histories | Must one parent provide two samples? | No; collect the latest two own samples across active ancestry. §3; documented | Multi-position reference test |
| Reconcile | Which branch supplies rhythm? | Recorded acting-Gate lineage only; hard inputs remain provenance. §3 and existing birth selection; documented | Both insertion orders, foreign zero history |
| Snapshot timing | Can structural activity change local measurements? | No. First own Attestation selects beat/reference together; later own acts retain that reference. §§5, 9.4; documented | Event-time selection and immutable ancestor state |
| Canonical edge | Inherit intervals or transport a cursor? | No interval inheritance; preserve existing cursor-only transport. §7 and Chirality Stages 2–3; recovered | Post-Commit reference and canonical Commit suite |
| Missing/zero history | Can absence be filled or zeros discarded? | Neither. Existing maturity and all-zero CV rules remain. §§4, 6; documented | Bootstrap, singleton, mixed/all-zero tests |
| Independent contracts | May changed Cadence alter action outcomes? | Ordinary consumers see the changed input; thresholds and exploration remain unchanged. §§6, 8, 12; documented | Bias, personal exploration and natural lifecycle validation |

## Architecture and cost

`ActiveCadenceLineageService` follows only the recorded selected edge in one recursive SQL query, terminating at a Committal; it does not query recursively one hop at a time or scan a Horizon. Results are indexed in memory and only the two needed own intervals are retained. A shared lineage selection supplies both cursor and historical reference. The first own act freezes the reference; subsequent own acts use it without traversing ancestors again. SQL call count is constant with lineage depth; recursive row work remains proportional to selected ancestry length, which is a separate cost to measure.

The unchanged `LocalChiralityCalculator` remains the only formula authority. `LocalChiralityStateService` changes only its Cadence inputs. `AttestationBeatCursorService` retains selected provenance and applies the same active selection to inherited beats. No frontend field/type or public action schema changes are required.

Validation and any consequential fixture/trajectory changes will be recorded with this PR.
