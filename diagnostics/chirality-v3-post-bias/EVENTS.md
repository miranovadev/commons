# New events only

Event order distinguishes acts from entries; Cycle position counts represented entries before the beat. Structural writes do not append samples. Raw CV can be undefined while effective CV is explicitly zero for a mature all-zero sequence.

| Boundary | Dispatched input | Event | Target | Parents | Previous beat order | Act order | Previous Cycle position | Current Cycle position | New interval | Local intervals | Mean | SD | Raw CV | Effective CV | Zero special | Maturity | O | AC | Directions | Local χ | Normalized ancestry | Field χ | C | Closure | Tension | Charge |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| continuation:21 | {'action': 'attestation.create', 'proposition_id': '01m423n2w0061rt1833xtqar72', 'direction': 'support'} | attestation_create | R | S2,S1 | 28 | 29 | 15 | 15 | 0 | [0, 0] | 0 | 0.0 | None | 0.0 | True | formation_zero_mean_regular | 0.5000000 | 1.0000000 | positive/positive | 0.7500000 | 0.1093732 | 0.4930049 | -0.5343177 | 0.0000000 | -0.0137710 | 0.9862290 |
| post-bias:0 | {'action': 'proposition.reclassify', 'proposition_id': '01m423n2w0061rt1833xtqar72', 'receipt_id': '01m423jd32dpnvz7grqrjr6zgb'} | proposition_write | B | R | 29 | None | 15 | None | None | [] | None | None | None | None | False | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.1730811 | 0.1730811 | -0.5476827 | 0.0000000 | -0.1248672 | 0.8751328 |
| post-bias:1 | {'action': 'attestation.create', 'proposition_id': '01m423nqtvgwzjs177tewyjyjn', 'direction': 'support', 'rationale': 'Continue the existing supportive authored stance; ground the fresh Bias result.'} | attestation_create | B | R | 29 | 30 | 15 | 15 | 0 | [0] | 0 | 0.0 | None | None | False | formation_immature | 0.5000000 | 0.0000000 | positive/negative | 0.2500000 | 0.1730811 | 0.3156786 | -0.5476827 | 0.0000000 | -0.0773348 | 0.9226652 |
| post-bias:2 | {'action': 'proposition.commit', 'proposition_id': '01m423nqtvgwzjs177tewyjyjn'} | proposition_write | C1 | B | 30 | None | 15 | None | None | [] | None | None | None | None | False | formation_immature | 0.5000000 | 0.0000000 | positive/negative | 0.2500000 | 0.1306198 | 0.2795971 | -0.5442648 | 0.0000000 | -0.0882226 | 0.9117774 |

## Cursor propagation

| Child | Source | Edge | Incoming beat order | Provenance | Cursor path |
| --- | --- | --- | --- | --- | --- |
| B | R | reclassify | 29 | immediate_predecessor | ['R'] |
| C1 | B | commit | 30 | immediate_predecessor | ['B'] |

## Complete local-snapshot invariance

| Boundary | Proposition | Own Attestation changed | Entire snapshot unchanged |
| --- | --- | --- | --- |
| continuation:21 | Origin | False | True |
| continuation:21 | D1 | False | True |
| continuation:21 | P1 | False | True |
| continuation:21 | Q1 | False | True |
| continuation:21 | S1 | False | True |
| continuation:21 | D2 | False | True |
| continuation:21 | P2 | False | True |
| continuation:21 | Q2 | False | True |
| continuation:21 | S2 | False | True |
| continuation:21 | R | True | False |
| post-bias:0 | Origin | False | True |
| post-bias:0 | D1 | False | True |
| post-bias:0 | P1 | False | True |
| post-bias:0 | Q1 | False | True |
| post-bias:0 | S1 | False | True |
| post-bias:0 | D2 | False | True |
| post-bias:0 | P2 | False | True |
| post-bias:0 | Q2 | False | True |
| post-bias:0 | S2 | False | True |
| post-bias:0 | R | False | True |
| post-bias:1 | Origin | False | True |
| post-bias:1 | D1 | False | True |
| post-bias:1 | P1 | False | True |
| post-bias:1 | Q1 | False | True |
| post-bias:1 | S1 | False | True |
| post-bias:1 | D2 | False | True |
| post-bias:1 | P2 | False | True |
| post-bias:1 | Q2 | False | True |
| post-bias:1 | S2 | False | True |
| post-bias:1 | R | False | True |
| post-bias:1 | B | True | False |
| post-bias:2 | Origin | False | True |
| post-bias:2 | D1 | False | True |
| post-bias:2 | P1 | False | True |
| post-bias:2 | Q1 | False | True |
| post-bias:2 | S1 | False | True |
| post-bias:2 | D2 | False | True |
| post-bias:2 | P2 | False | True |
| post-bias:2 | Q2 | False | True |
| post-bias:2 | S2 | False | True |
| post-bias:2 | R | False | True |
| post-bias:2 | B | False | True |
