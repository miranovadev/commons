# Proposition-local, Attestation-triggered Chirality

Diagnostic only. Parent: `575999e704e72ed8ad535ba69a55f78d9234080f`, preserving the exact three-participant history and the 33 + 80 authored contributions from the positive-window autopsy. Production code, previous diagnostics, canonical fixtures and lifecycle operations are unchanged.

## Candidate scope

`PropositionLocalMeasurements` extends the existing diagnostic `CandidateLocalMeasurements`. It passes a **singleton current-proposition population** to the existing Orientation/developmental-Cadence calculation, with no ancestral current-population Supersessions. Thus only that proposition's own effective Attestations supply its current lean, and only its own historical Attestation acts supply its interval endpoints. Its existing creating entry-Committal/Horizon supplies the represented Cycle-entry population. Act multiplicity, zero intervals, population standard deviation, maturity threshold, formula and seven-place rounding are unchanged.

The existing v3 diagnostic Orientation formula/reference is retained; this does not switch back to production v2's lean product. Origin remains constitutional (`O=0`, `AC=-1`, local Chirality `-0.5`) and establishes polarity through the same genuine Origin Attestation. Ordinary fresh propositions start at local `0/0/0`, with no inherited votes. The complementary reference is unchanged and sampled at the target's Attestation observation; this first-Settlement history does not validate later-Domain comparative behavior.

The local snapshot is cached by the proposition's own historical Attestation state signature. Creating/changing an Attestation on P invalidates P only. New Cycles, Receipts, descendants, or votes on another proposition do not invalidate P. The snapshot includes the observation event order and full raw statistics, allowing invariance to be checked by equality of the entire snapshot, not just rounded Chirality. Production calculations can request it repeatedly without changing it.

The ordinary API represents an Attestation replacement by a new row with `supersedes_attestation_id`. That invalidates the target and contributes another historical act; old rows remain historical endpoints. A state/label edit also changes the signature, without inventing a new act row. No extra replacement action is authored solely for this experiment. The preserved history's create/change coverage is reported separately.

`attestation_observed` and `own_effective_attestations` identify whether a fresh proposition has its own observations. The reused legacy `orientation_mature` metadata means the complementary reference exists; it must not be interpreted as proof that an unattested child has an observed local tendency.

## Attenuation and directions

Only **local** components are cached. The real `LineageMeasurementService` still traverses unique causal ancestors at minimum distance, uses `1/(1+d)^2`, includes neutral denominator mass, and rounds the accumulated field value normally. An ancestor's later genuine Attestation may therefore alter a descendant's accumulated Chirality without changing that descendant's local snapshot. That is historical influence through attenuation, not inherited local state.

No component direction is assigned by this adapter. The existing projection/observation services retain nonzero signs, zero hysteresis, entry-snapshot fallback and primitive defaults. In this specimen Origin supplies Negative/Negative entry directions; fresh neutral children can therefore be direction-coherent without mature local rhythm. Bias authority and grounding remain separate.

## Replay and verification

`LocalAttestationTest.php` maps archived identities to fresh service-created records and submits the **same actor/action/payload/target sequence** through `ParticipationService`. It checks actor Phase, Cycle and Gate before each action. If an archived action becomes unavailable, it stops rather than bypassing the schema or choosing a more favorable action. Created record counts are checked against the original history.

The recorder observes all current participants and every existing proposition's local snapshot after each action. It checks unchanged local state whenever the own-Attestation signature is unchanged, records every Write/Attestation event, and prohibits SQL writes during inspection. Tests compare unchanged Correspondence/Closure with the archived v3 values, verify fresh-result vote/component resets, and verify the real attenuated aggregate against its recorded constituent terms.

Run on PHP 8.5 / PostgreSQL 18 with repository dependencies:

```sh
tar -xzf diagnostics/chirality-v3-positive-window/runtime-results.tar.gz -C diagnostics/chirality-v3-positive-window
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-local-attestation/bootstrap.php diagnostics/chirality-v3-local-attestation/LocalAttestationTest.php
python3 diagnostics/chirality-v3-local-attestation/report.py
```

`report.py` renders runtime output; it does not recalculate measurements. The lossless raw trace is preserved in `runtime-results.tar.gz`. The workflow also runs canonical Reclassify-reset, Reconcile-freshness and stale-source Bias tests in separate normal-bootstrap processes.

Origin's two event rows describe the completed atomic Origin entry (including its required creator Attestation), not an externally actionable unattested Origin state. Subsequent event rows describe post-contribution states. The local cache is process-local diagnostic machinery; restart durability and a canonical persistence design are outside this experiment.
