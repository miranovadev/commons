# Lifecycle provenance

Inspected at the diagnostic parent `2644a51cf6a90d67eec4da933e25cb9eddc44c98`. These files were not changed.

| Source | Established behavior |
| --- | --- |
| `docs/phase-positions.md`, `PhasePositionService` | Producing Phase is structural identity. Persist Stance/Frontier is a Constrain Gate; Mediate Stance/Frontier is a Bias Gate. Another Participant, Cycle, Receipt, Attestation or measurement cannot promote a position. |
| `PhasePositionInvariantTest::test_bias_never_selects_persist_and_never_offers_constrain_as_its_write` | An attractive grounded Persist source cannot bypass Mediate, even with the same register as a late source. |
| `PhasePositionInvariantTest::test_progression_rejects_a_same_register_result_from_another_position` | Same register does not authorize a phase-position jump. |
| `PhaseProgressionService::advance`, `ParticipationService::contribute` | A successful contribution must return a Write at the current producing Phase position; ordinary advancement opens the next position. Field sign does not skip phases. |
| `MediationReadService`, `ReconciliationOptionService` | Mediate resolves a Constrain Read neighborhood and its immediate Disperse children. Reconcile needs another eligible, coherent, direction-compatible operand and an unused directed pair. The current Gate is excluded as its own partner. |
| `ReconciliationTest::test_reconcile_is_structural_selection_with_exact_gate_content` | Reconcile creates a new expressive result with both causal edges and exact Gate content, but zero inherited Attestations and Receipts. |
| `ReclassificationEvidenceTest::test_reclassification_preserves_evidence_and_content_without_copying_judgments_or_uptake` | Reclassify preserves content/evidence and the selected Receipt, but resets judgments/touches. Q does not inherit P's effective vote. |
| `BiasGateService::forPhase`, `::operationalFor` | Regional evidence/ancestry agreement and source authority are distinct from component coherence. An operational local Bias source must also have aligned retained component directions. |
| `PhaseGenerationService::available`, `BiasReceiptAuthorityService::receiptsFor` | Current source must remain structurally valid, active/unexhausted, expressive, uncommitted, unused for the Write register, effectively Attested and authorized to use an available Receipt. Bias additionally checks component agreement. Positive Tension is not a Bias prerequisite. |
| `CommitmentAdmissibilityService::grounding` | Effective Attestation and qualifying source evidence are separately reported grounding conditions. This Commit-grounding helper is not identical to Bias's authorized-Receipt catalogue rule. |
| `docs/observables.md`, “Unified late-phase admissibility” / “Bias Write admissibility” | Admissibility constrains the current transition, not the resulting source's future admissibility. A valid Reconcile may reach Bias with opposed Chirality components. Dispatch revalidates the selected existing source, not an earlier motivating snapshot or hypothetical Reclassification. |
| `HomologousBiasTest::test_stale_local_or_homologous_source_is_rejected_without_any_write` | A source that becomes inadmissible is rejected at dispatch without writing a result. |

The producing-position implementation entered in commit `5a7ef23f1a1a3eba05d3cace9c7f3a3dec684199`, “Enforce producing phase position across operational eligibility.” Its explicit rule is not inferred from this failed specimen.

The documentation establishes current-source checking and denies automatic preservation of downstream admissibility. It does **not** promise that a positive early source will remain positive at Bias, or that measurement and structural readiness must overlap in every lawful history. Therefore this autopsy identifies a non-overlap in one preserved history; it does not establish a lifecycle bug or a universal impossibility theorem.

The cited observable documentation includes earlier v2 numerical descriptions. It is used here for unchanged authority/position/freshness rules, not to replace the preserved v3 diagnostic candidate with v2 rates or Orientation.
