# Same history, three models

A and B are the previously completed real-service baselines. C is this run. Values beyond c8 are intentionally not assigned to C. `None` for Bias generation means the actor is not at Bias. In the second table only the v3 ancestor contribution is inferred (~) by subtracting its weighted head from the recorded total using the unchanged production attenuation mass; these inferred terms are approximate because the source values were already rounded.

| Boundary | Label | Model | Samples | Maturity | Cadence | Orientation | Directions | Local Chirality | Field Chirality | Tension |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| D1 mature | prefix:32 | A: live v3 | [5,5] | formation_cv | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 | 0.5000000 | 0.1628540 |
| D1 mature | prefix:32 | B: complete reset | [5,5] | formation_cv | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 | 0.5000000 | 0.1628540 |
| D1 mature | prefix:32 | C: Cadence Anchor | [5,5] | formation_cv | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 | 0.5000000 | 0.1628540 |
| D1 -> P1 birth | prefix:3 | A: live v3 | [] | formation_immature | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 0.1887755 | -0.0421801 |
| D1 -> P1 birth | prefix:3 | B: complete reset | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0051020 | -0.1034046 |
| D1 -> P1 birth | prefix:3 | C: Cadence Anchor | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0051020 | -0.1034046 |
| P1 first vote | continuation:0 | A: live v3 | [5,5,0] | formation_cv | 0.1715729 | 0.5000000 | positive/positive | 0.3357865 | 0.3436391 | 0.0094410 |
| P1 first vote | continuation:0 | B: complete reset | [] | formation_immature | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 0.2806122 | -0.0115678 |
| P1 first vote | continuation:0 | C: Cadence Anchor | [] | formation_immature | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 0.2806122 | -0.0115678 |
| P1 -> Q1 | continuation:1 | A: live v3 | [5,5,0] | formation_cv | 0.1715729 | 0.5000000 | positive/positive | 0.3357865 | 0.3314223 | -0.0128774 |
| P1 -> Q1 | continuation:1 | B: complete reset | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0804878 | -0.0965222 |
| P1 -> Q1 | continuation:1 | C: Cadence Anchor | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0804878 | -0.0965222 |
| Q1 -> S1 | continuation:6 | A: live v3 | [5,5,0,0,0,0] | formation_cv | -0.1715729 | 0.5000000 | positive/negative | 0.1642135 | 0.1775879 | -0.0944540 |
| Q1 -> S1 | continuation:6 | B: complete reset | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0800437 | -0.1269687 |
| Q1 -> S1 | continuation:6 | C: Cadence Anchor | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0800437 | -0.1269687 |
| S1 first vote | continuation:8 | A: live v3 | [5,5,0,0,0,0,0] | formation_cv | -0.2251482 | 0.5000000 | positive/negative | 0.1374259 | 0.1592855 | -0.1005548 |
| S1 first vote | continuation:8 | B: complete reset | [] | formation_immature | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 0.2508541 | -0.0700319 |
| S1 first vote | continuation:8 | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| P2 -> Q2 | continuation:15 | A: live v3 | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0219512 | -0.1160344 |
| P2 -> Q2 | continuation:15 | B: complete reset | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0219512 | -0.1160344 |
| P2 -> Q2 | continuation:15 | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| Mediate S2 | continuation:17 | A: live v3 | [0] | formation_immature | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 0.2188271 | -0.0807076 |
| Mediate S2 | continuation:17 | B: complete reset | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0480167 | -0.1376444 |
| Mediate S2 | continuation:17 | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| Reconcile / first Bias | continuation:18 | A: live v3 | [5,5,0,0,0,0,0,2,0] | formation_cv | -0.2129404 | 0.5000000 | positive/negative | 0.1435298 | 0.1707269 | -0.1211970 |
| Reconcile / first Bias | continuation:18 | B: complete reset | [] | formation_immature | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 0.0846121 | -0.1499018 |
| Reconcile / first Bias | continuation:18 | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| R grounded Bias | continuation:33 | A: live v3 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0] | formation_cv | -0.3445034 | 0.5000000 | positive/negative | 0.0777483 | 0.1132670 | -0.1403502 |
| R grounded Bias | continuation:33 | B: complete reset | [0,1] | formation_cv | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 0.2495737 | -0.0949146 |
| R grounded Bias | continuation:33 | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |

| Boundary | Model | Normalized ancestors | Correspondence | Closure | Charge | Grounded | Bias reachable | Bias generation | Direction rejection | Actions |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| D1 mature | A: live v3 | ~-0.1000000 | -0.0114382 | 0.0000000 | 0.8371460 | True | False | None | None | instrument.execute,attestation.create |
| D1 mature | B: complete reset | -0.1000000 | -0.0114382 | 0.0000000 | 0.8371460 | True | False | None | None | instrument.execute,attestation.create |
| D1 mature | C: Cadence Anchor | -0.1000000 | -0.0114382 | 0.0000000 | 0.8371460 | True | False | None | None | instrument.execute,attestation.create |
| D1 -> P1 birth | A: live v3 | ~0.0051020 | -0.3153158 | 0.0000000 | 0.9578199 | False | False | None | component_directions_disagree | participant.rest |
| D1 -> P1 birth | B: complete reset | 0.0051020 | -0.3153158 | 0.0000000 | 0.8965954 | False | False | None | None | participant.rest |
| D1 -> P1 birth | C: Cadence Anchor | 0.0051020 | -0.3153158 | 0.0000000 | 0.8965954 | False | False | None | None | participant.rest |
| P1 first vote | A: live v3 | ~0.0969388 | -0.3153158 | 0.0000000 | 0.9905590 | True | False | None | None | proposition.reclassify |
| P1 first vote | B: complete reset | 0.0969388 | -0.3153158 | 0.0000000 | 0.9884322 | True | False | None | component_directions_disagree | proposition.reclassify |
| P1 first vote | C: Cadence Anchor | 0.0969388 | -0.3153158 | 0.0000000 | 0.9884322 | True | False | None | component_directions_disagree | proposition.reclassify |
| P1 -> Q1 | A: live v3 | ~0.0955528 | -0.3700546 | 0.0000000 | 0.9871226 | False | False | None | None | proposition.variant |
| P1 -> Q1 | B: complete reset | 0.0804878 | -0.3700546 | 0.0000000 | 0.9034778 | False | False | None | None | proposition.variant |
| P1 -> Q1 | C: Cadence Anchor | 0.0804878 | -0.3700546 | 0.0000000 | 0.9034778 | False | False | None | None | proposition.variant |
| Q1 -> S1 | A: live v3 | ~0.0653904 | -0.4609498 | 0.0000000 | 0.9055460 | False | False | None | component_directions_disagree | participant.rest |
| Q1 -> S1 | B: complete reset | 0.0800437 | -0.4609498 | 0.0000000 | 0.8730313 | False | False | None | None | participant.rest |
| Q1 -> S1 | C: Cadence Anchor | 0.0800437 | -0.4609498 | 0.0000000 | 0.8730313 | False | False | None | None | participant.rest |
| S1 first vote | A: live v3 | ~0.0653904 | -0.4609498 | 0.0000000 | 0.8994452 | True | False | None | component_directions_disagree | participant.rest |
| S1 first vote | B: complete reset | 0.0800437 | -0.4609498 | 0.0000000 | 0.9299681 | True | False | None | component_directions_disagree | participant.rest |
| S1 first vote | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| P2 -> Q2 | A: live v3 | ~0.0219512 | -0.3700546 | 0.0000000 | 0.8839656 | False | False | None | None | proposition.variant |
| P2 -> Q2 | B: complete reset | 0.0219512 | -0.3700546 | 0.0000000 | 0.8839656 | False | False | None | None | proposition.variant |
| P2 -> Q2 | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| Mediate S2 | A: live v3 | ~0.0480167 | -0.4609498 | 0.0000000 | 0.9192924 | False | False | None | component_directions_disagree | proposition.reconcile |
| Mediate S2 | B: complete reset | 0.0480167 | -0.4609498 | 0.0000000 | 0.8623556 | False | False | None | None | proposition.reconcile |
| Mediate S2 | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| Reconcile / first Bias | A: live v3 | ~0.0973101 | -0.5343177 | 0.0000000 | 0.8788030 | False | True | False | component_directions_disagree | participant.rest |
| Reconcile / first Bias | B: complete reset | 0.0846121 | -0.5343177 | 0.0000000 | 0.8500982 | False | True | False | None | participant.rest |
| Reconcile / first Bias | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
| R grounded Bias | A: live v3 | ~0.0734980 | -0.5343177 | 0.0000000 | 0.8596498 | True | True | False | component_directions_disagree | participant.rest |
| R grounded Bias | B: complete reset | 0.1216965 | -0.5343177 | 0.0000000 | 0.9050854 | True | True | False | component_directions_disagree | participant.rest |
| R grounded Bias | C: Cadence Anchor | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 | UNDEFINED FROM c8 |
