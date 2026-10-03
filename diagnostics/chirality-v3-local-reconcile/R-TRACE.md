# Actual Reconcile result and own Attestation events

No descendant or parent Attestation is inserted into R. The separate state table includes Receipt/structural boundaries even when local measurements are unchanged. Raw CV is mathematically undefined for an all-zero list; effective CV follows the authorized rule.

| Boundary | Event | Previous beat order | Current act order | Previous Cycle position | Current Cycle position | New local interval | Local intervals | Maturity | Mean | SD | Raw CV | Effective CV | Zero special | Cadence | Orientation | Directions | Local Chirality | Ancestral contribution | Field Chirality | Correspondence | Closure | Tension | Charge |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| continuation:18 | proposition_write | 27 | None | 15 | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | undefined / not recorded | False | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.1093732 | 0.1093732 | -0.5343177 | 0.0000000 | -0.1416482 | 0.8583518 |
| continuation:19 | attestation_create | 27 | 28 | 15 | 15 | 0 | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | undefined / not recorded | False | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 0.1093732 | 0.2372504 | -0.5343177 | 0.0000000 | -0.0990224 | 0.9009776 |
| continuation:21 | attestation_create | 28 | 29 | 15 | 15 | 0 | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | True | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 | 0.1093732 | 0.4930049 | -0.5343177 | 0.0000000 | -0.0137710 | 0.9862290 |

## R at every actual participant boundary

Grounding is the production current-source Attestation/Receipt grounding check. Bias generation separately uses its authorized Receipt selections; these are not interchangeable predicates.

| Boundary | Actor | Cycle | Phase | R intervals | Field Chirality | Tension | Directions | Current-source grounded | Grounding requirements | Bias reached | Bias generation | Authorized Receipt count | Four-way overlap | Actions |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| continuation:18 | 0 | 7 | 6 | [] | 0.1093732 | -0.1416482 | negative/negative | False | {'attestation': False, 'receipt': False, 'closure': None} | True | False | 11 | False | participant.rest |
| continuation:19 | 0 | 7 | 6 | [0] | 0.2372504 | -0.0990224 | positive/negative | False | {'attestation': True, 'receipt': False, 'closure': None} | True | False | 11 | False | participant.rest |
| continuation:19 | 2 | 6 | 6 | [0] | 0.2372504 | -0.0990224 | positive/negative | False | {'attestation': True, 'receipt': False, 'closure': None} | True | False | 11 | False | participant.rest |
| continuation:20 | 0 | 7 | 6 | [0] | 0.2372504 | -0.0990224 | positive/negative | False | {'attestation': True, 'receipt': False, 'closure': None} | True | False | 12 | False | participant.rest |
| continuation:20 | 2 | 6 | 6 | [0] | 0.2372504 | -0.0990224 | positive/negative | False | {'attestation': True, 'receipt': False, 'closure': None} | True | False | 12 | False | participant.rest |
| continuation:21 | 0 | 7 | 6 | [0,0] | 0.4930049 | -0.0137710 | positive/positive | False | {'attestation': True, 'receipt': False, 'closure': None} | True | True | 12 | False | proposition.reclassify |
| continuation:21 | 1 | 3 | 6 | [0,0] | 0.4930049 | -0.0137710 | positive/positive | False | {'attestation': True, 'receipt': False, 'closure': None} | True | True | 12 | False | proposition.reclassify |
| continuation:21 | 2 | 6 | 6 | [0,0] | 0.4930049 | -0.0137710 | positive/positive | False | {'attestation': True, 'receipt': False, 'closure': None} | True | True | 12 | False | proposition.reclassify |
