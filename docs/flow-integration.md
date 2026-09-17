# Receipt-backed phase flow

[Core invariants](core-invariants.md#persistence-exhaustion-recovery-and-observation) govern this cycle. Domain mappings select registers; the producing phase position determines eligibility.

| Phase | Structural flow |
| --- | --- |
| 1 Distinguish | Empty viable position: Variant. Existing Proposition without available Horizon Receipt: Instrument. With Receipt: Attest. Personal earlier-Cycle Attestation: Traverse on that side. |
| 2 Persist | Develop the selected Distinguish Proposition through Variant; shared structural viability applies. |
| 3 Constrain | Reclassify a grounded, attested Persist source with explicit `receipt_id`; attach/consume and advance atomically. |
| 4 Disperse | Same Variant / Instrument / Attest / later-Cycle Traverse pattern as Distinguish. |
| 5 Mediate | Existing directed reconciliation and Correspondence conditions develop Disperse plurality. |
| 6 Bias | Reclassify an eligible Mediate source with explicit `receipt_id`, preserving existing lineage agreement. |
| 7 Order | Existing grounded, attested, Closure-aligned Commit or established Committal Traverse. |

Receipt availability is Horizon-scoped and independent of authorship, age, loading and Participant presence. Completed ordinary Instruments create one persistent Receipt. Tragedy creates its one Receipt without a fake InstrumentExecution. Only Reclassify consumes; reading is free and does not advance the Phase. Commit may retain already attached evidence without consuming it again.

Reclassification copies the exact statement and attaches only the selected Receipt. Effective Attestations and phase touches start fresh. It rejects missing, consumed, foreign-Horizon or structurally invalid inputs before publishing any new target. Other historical evidence remains on its original source. Shared classifications are unique per source/destination register.

Healthy lineages cannot produce redundant Variants merely because a Receipt appeared or was consumed. Generation has no Participant quota. Mediate needs a binary counterpart: the current implementation treats a completed Mediate Rest with exactly one viable Disperse candidate as a structural deficit that permits one additional counterpart. This is a review assumption resolving the tension between binary plurality and the prohibition on redundant healthy siblings. It neither marks the first candidate failed nor permits unbounded siblings.

A no-Potential Rest opens Tragedy's final attempt. Reclassify may exercise the Tragedy Receipt at either required boundary. A later no-Potential Rest alone can close the Horizon after that exercise. A successful continuation remains alive. No single failed probe, numeric zero, Participant count or graph filter proves Exhaustion.

## Review stack

The nine dependent stages are Receipt lifecycle, receipt-gated Reclassification, action gating, authoritative Rest reasons, Tragedy Receipt, final-attempt Exhaustion, recursive evidence references, participant/realtime context, and normative integration coverage. Each stage targets its predecessor. The final integration branch contains the complete stack and targets main for review; no stage or integration PR is merged automatically.

See [full-cycle validation](operational-closure-path.md), [migration notes](horizon-proof-migration.md), and [invariant test map](horizon-proof-tests.md).
