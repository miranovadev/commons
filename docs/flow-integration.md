# Receipt-backed phase flow

[Core invariants](core-invariants.md#persistence-exhaustion-recovery-and-observation) govern this cycle. Domain mappings select registers; the producing phase position determines eligibility.

Producing position is necessary but does not confer traversal eligibility.
Forward movement additionally requires an explicit causal continuation from
the current Gate; recovery follows recorded ancestry backward. Commit Region,
Horizon, visible history and calculation membership never create movement edges.
No edge means no movement.

Mediate — Read and Bias — Gate may calculate regionally; only Mediate exposes
foreign Gates as reconciliation operands, with both causal edges created by
Reconcile. Bias exposes its current causal Gate. Closure, performed during
Order — Write, evaluates each local expressive Write independently. These
calculations do not relocate the Participant. Traverse means the current Gate's causal
Write continuation; Order follows only the Commit directly produced from its
current classification. Frozen successful routes retain their existing edge,
not region-wide traversal eligibility.

| Phase | Structural flow |
| --- | --- |
| 1 Distinguish | Empty viable position: Variant. Existing uninstrumented Proposition: Instrument, even with other shared Receipts. Own completed Instrument plus available Horizon Receipt: Attest. Personal earlier-Cycle Attestation: Traverse on that side. |
| 2 Persist | Develop the selected Distinguish Proposition through Variant; shared structural viability applies. |
| 3 Constrain | Reclassify a grounded, attested Persist source with explicit `receipt_id`; attach/consume and advance atomically. |
| 4 Disperse | Same Variant / Instrument / Attest / later-Cycle Traverse pattern as Distinguish. |
| 5 Mediate | Existing directed reconciliation and Correspondence conditions develop Disperse plurality. |
| 6 Bias | Reclassify an eligible Mediate source with explicit `receipt_id`, preserving existing lineage agreement. |
| 7 Order | Existing grounded, attested, Closure-aligned Commit or established Committal Traverse. |

Receipt availability is Horizon-scoped and independent of authorship, age, loading and Participant presence. Completed ordinary Instruments create one persistent Receipt. Tragedy creates its one Receipt without a fake InstrumentExecution. Only Reclassify consumes; reading is free and does not advance the Phase. Commit may retain already attached evidence without consuming it again.

Reclassification copies the exact statement and attaches only the selected Receipt. Effective Attestations and phase touches start fresh. It rejects missing, consumed, foreign-Horizon or structurally invalid inputs before publishing any new target. Other historical evidence remains on its original source. Shared classifications are unique per source/destination register.

Another Variant is an option only after every current viable Variant at that position has its own completed Instrument and effective Attestation, and positive Horizon Potential Charge remains. Each new Variant repeats this sequence; a Receipt from another Variant does not replace its Instrument prerequisite. Receipt consumption alone never reopens Variant. There is no Participant quota, fixed sibling cap or prior Mediate Rest requirement. Mediate still needs a second Proposition. This is the [confirmed Variant clarification](horizon-proof-variant-clarification.md).

A no-Potential Rest opens Tragedy's final attempt. Reclassify may exercise the Tragedy Receipt at either required boundary. A later no-Potential Rest alone can close the Horizon after that exercise. A successful continuation remains alive. No single failed probe, numeric zero, Participant count or graph filter proves Exhaustion.

## Review stack

The nine dependent stages are Receipt lifecycle, receipt-gated Reclassification, action gating, authoritative Rest reasons, Tragedy Receipt, final-attempt Exhaustion, recursive evidence references, participant/realtime context, and normative integration coverage. Each stage targets its predecessor. The final integration branch contains the complete stack and targets main for review; no stage or integration PR is merged automatically.

See [full-cycle validation](operational-closure-path.md), [migration notes](horizon-proof-migration.md), and [invariant test map](horizon-proof-tests.md).

