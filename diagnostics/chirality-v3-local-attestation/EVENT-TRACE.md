# Every proposition Write and Attestation event

All event targets are real service-created propositions. Creating Cycle/Phase belongs to the target proposition; current actor positions after the contribution appear in the field table. Full vote IDs/phase IDs and causal IDs are in candidate-trace.json; authors follow the unchanged actor/action schedule in the preserved history. Numeric values are printed to seven decimals; raw statistics retain full precision in JSON.

## Event identities

| Boundary | Event | Alias | Proposition ID | Parents | Domain | Creating Cycle/Phase | Effective votes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| origin | proposition_write | Origin | 01m41tpc9z27cdj16gmj0s4vp9 |  | emergence | C1/P7 | 1 |
| origin | attestation_create | Origin | 01m41tpc9z27cdj16gmj0s4vp9 |  | emergence | C1/P7 | 1 |
| prefix:0 | proposition_write | D1 | 01m41tpe9163fnkczn2vmz31v4 | Origin | settlement | C2/P1 | 0 |
| prefix:2 | attestation_create | D1 | 01m41tpe9163fnkczn2vmz31v4 | Origin | settlement | C2/P1 | 1 |
| prefix:3 | proposition_write | P1 | 01m41tpj29zx99b3eg8zzfwswt | D1 | settlement | C2/P2 | 0 |
| prefix:15 | attestation_create | D1 | 01m41tpe9163fnkczn2vmz31v4 | Origin | settlement | C2/P1 | 2 |
| prefix:32 | attestation_create | D1 | 01m41tpe9163fnkczn2vmz31v4 | Origin | settlement | C2/P1 | 3 |
| continuation:0 | attestation_create | P1 | 01m41tpj29zx99b3eg8zzfwswt | D1 | settlement | C2/P2 | 1 |
| continuation:1 | proposition_write | Q1 | 01m41tqwr50etgfgejc580v7vv | P1 | settlement | C2/P3 | 0 |
| continuation:3 | attestation_create | Q1 | 01m41tqwr50etgfgejc580v7vv | P1 | settlement | C2/P3 | 1 |
| continuation:4 | attestation_create | P1 | 01m41tpj29zx99b3eg8zzfwswt | D1 | settlement | C2/P2 | 2 |
| continuation:5 | attestation_create | Q1 | 01m41tqwr50etgfgejc580v7vv | P1 | settlement | C2/P3 | 2 |
| continuation:6 | proposition_write | S1 | 01m41tr645fbbz3dnv94a2g61r | Q1 | settlement | C6/P4 | 0 |
| continuation:8 | attestation_create | S1 | 01m41tr645fbbz3dnv94a2g61r | Q1 | settlement | C6/P4 | 1 |
| continuation:10 | proposition_write | D2 | 01m41trgbmb15jgds38h410qfx | Origin | settlement | C7/P1 | 0 |
| continuation:11 | proposition_write | P2 | 01m41trja8zt2hc4dkmjnzy9jv | D2 | settlement | C7/P2 | 0 |
| continuation:14 | attestation_create | P2 | 01m41trja8zt2hc4dkmjnzy9jv | D2 | settlement | C7/P2 | 1 |
| continuation:15 | proposition_write | Q2 | 01m41trvnk2serz6f9j9a822ah | P2 | settlement | C7/P3 | 0 |
| continuation:16 | attestation_create | Q2 | 01m41trvnk2serz6f9j9a822ah | P2 | settlement | C7/P3 | 1 |
| continuation:17 | proposition_write | S2 | 01m41ts0v7rw2wwwet0rk696t7 | Q2 | settlement | C7/P4 | 0 |
| continuation:18 | proposition_write | R | 01m41ts468c0tmvyw00avj4wyd | S2,S1 | settlement | C7/P5 | 0 |
| continuation:19 | attestation_create | R | 01m41ts468c0tmvyw00avj4wyd | S2,S1 | settlement | C7/P5 | 1 |
| continuation:21 | attestation_create | R | 01m41ts468c0tmvyw00avj4wyd | S2,S1 | settlement | C7/P5 | 2 |
| continuation:23 | attestation_create | D2 | 01m41trgbmb15jgds38h410qfx | Origin | settlement | C7/P1 | 1 |
| continuation:24 | attestation_create | P2 | 01m41trja8zt2hc4dkmjnzy9jv | D2 | settlement | C7/P2 | 2 |
| continuation:25 | attestation_create | Q2 | 01m41trvnk2serz6f9j9a822ah | P2 | settlement | C7/P3 | 2 |
| continuation:26 | attestation_create | S2 | 01m41ts0v7rw2wwwet0rk696t7 | Q2 | settlement | C7/P4 | 1 |
| continuation:27 | attestation_create | R | 01m41ts468c0tmvyw00avj4wyd | S2,S1 | settlement | C7/P5 | 3 |
| continuation:35 | proposition_write | D3 | 01m41tv7zt667ah3wbx5qd1vk2 | Origin | settlement | C10/P1 | 0 |
| continuation:36 | proposition_write | P3 | 01m41tvbgk08zy0k1a4txm9cqx | D3 | settlement | C10/P2 | 0 |
| continuation:39 | attestation_create | P3 | 01m41tvbgk08zy0k1a4txm9cqx | D3 | settlement | C10/P2 | 1 |
| continuation:40 | proposition_write | Q3 | 01m41tvtrbz4n2pwt2hjdy134x | P3 | settlement | C10/P3 | 0 |
| continuation:41 | attestation_create | Q3 | 01m41tvtrbz4n2pwt2hjdy134x | P3 | settlement | C10/P3 | 1 |
| continuation:42 | proposition_write | S3 | 01m41tw2xa69f60q80bkjzhag9 | Q3 | settlement | C10/P4 | 0 |
| continuation:43 | proposition_write | R2 | 01m41tw7wb3wr7hb5dm8he5v6b | S3,S1 | settlement | C10/P5 | 0 |
| continuation:45 | attestation_create | R2 | 01m41tw7wb3wr7hb5dm8he5v6b | S3,S1 | settlement | C10/P5 | 1 |
| continuation:47 | attestation_create | D3 | 01m41tv7zt667ah3wbx5qd1vk2 | Origin | settlement | C10/P1 | 1 |
| continuation:48 | attestation_create | P3 | 01m41tvbgk08zy0k1a4txm9cqx | D3 | settlement | C10/P2 | 2 |
| continuation:49 | attestation_create | Q3 | 01m41tvtrbz4n2pwt2hjdy134x | P3 | settlement | C10/P3 | 2 |
| continuation:50 | attestation_create | S3 | 01m41tw2xa69f60q80bkjzhag9 | Q3 | settlement | C10/P4 | 1 |
| continuation:51 | proposition_write | R3 | 01m41txvfgdgnbam56h8a9cc6r | S3,S2 | settlement | C11/P5 | 0 |
| continuation:57 | attestation_create | R2 | 01m41tw7wb3wr7hb5dm8he5v6b | S3,S1 | settlement | C10/P5 | 2 |
| continuation:63 | attestation_create | R3 | 01m41txvfgdgnbam56h8a9cc6r | S3,S2 | settlement | C11/P5 | 1 |
| continuation:77 | proposition_write | D4 | 01m41v3gcn4f3nenyn7xegb9wy | Origin | settlement | C16/P1 | 0 |
| continuation:78 | proposition_write | P4 | 01m41v3qfrtv0nbm5bjaspe5n1 | D4 | settlement | C16/P2 | 0 |

## Local observations

| Boundary | Alias | O | O direction | Intervals | Count | Cadence maturity | Mean | SD | CV | AC | AC direction | Local Chirality |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| origin | Origin | 0.0000000 | negative | [] | 0 | constitutional_origin | — | — | — | -1.0000000 | negative | -0.5000000 |
| origin | Origin | 0.0000000 | negative | [] | 0 | constitutional_origin | — | — | — | -1.0000000 | negative | -0.5000000 |
| prefix:0 | D1 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| prefix:2 | D1 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| prefix:3 | P1 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| prefix:15 | D1 | 0.5000000 | positive | [5] | 1 | formation_immature | 5.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | negative | 0.2500000 |
| prefix:32 | D1 | 0.5000000 | positive | [5,5] | 2 | formation_cv | 5.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | positive | 0.7500000 |
| continuation:0 | P1 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:1 | Q1 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:3 | Q1 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:4 | P1 | 0.5000000 | positive | [0] | 1 | formation_immature | 0.0000000 | 0.0000000 | — | 0.0000000 | negative | 0.2500000 |
| continuation:5 | Q1 | 0.5000000 | positive | [0] | 1 | formation_immature | 0.0000000 | 0.0000000 | — | 0.0000000 | negative | 0.2500000 |
| continuation:6 | S1 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:8 | S1 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:10 | D2 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:11 | P2 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:14 | P2 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:15 | Q2 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:16 | Q2 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:17 | S2 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:18 | R | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:19 | R | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:21 | R | 0.5000000 | positive | [0] | 1 | formation_immature | 0.0000000 | 0.0000000 | — | 0.0000000 | negative | 0.2500000 |
| continuation:23 | D2 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:24 | P2 | 0.5000000 | positive | [1] | 1 | formation_immature | 1.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | negative | 0.2500000 |
| continuation:25 | Q2 | 0.5000000 | positive | [1] | 1 | formation_immature | 1.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | negative | 0.2500000 |
| continuation:26 | S2 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:27 | R | 0.5000000 | positive | [0,1] | 2 | formation_cv | 0.5000000 | 0.5000000 | 1.0000000 | 0.0000000 | negative | 0.2500000 |
| continuation:35 | D3 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:36 | P3 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:39 | P3 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:40 | Q3 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:41 | Q3 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:42 | S3 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:43 | R2 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:45 | R2 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:47 | D3 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:48 | P3 | 0.5000000 | positive | [1] | 1 | formation_immature | 1.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | negative | 0.2500000 |
| continuation:49 | Q3 | 0.5000000 | positive | [1] | 1 | formation_immature | 1.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | negative | 0.2500000 |
| continuation:50 | S3 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:51 | R3 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:57 | R2 | 0.5000000 | positive | [2] | 1 | formation_immature | 2.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | negative | 0.2500000 |
| continuation:63 | R3 | 0.5000000 | positive | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.2500000 |
| continuation:77 | D4 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |
| continuation:78 | P4 | 0.0000000 | negative | [] | 0 | formation_immature | — | — | — | 0.0000000 | negative | 0.0000000 |

## Field

Ancestral contribution is the normalized sum of the production inverse-square ancestry terms. The head also receives its denominator weight.

| Boundary | Event | Alias | Current actors | Local Chirality | Ancestral contribution | Combined Chirality | Correspondence | Closure | Tension | Charge |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| origin | proposition_write | Origin | A0/C2/P1; A1/C1/P1; A2/C1/P1 | -0.5000000 | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| origin | attestation_create | Origin | A0/C2/P1; A1/C1/P1; A2/C1/P1 | -0.5000000 | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:0 | proposition_write | D1 | A0/C2/P2 | 0.0000000 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 |
| prefix:2 | attestation_create | D1 | A0/C2/P2; A1/C1/P2; A2/C1/P2 | 0.2500000 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 |
| prefix:3 | proposition_write | P1 | A0/C2/P3 | 0.0000000 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 |
| prefix:15 | attestation_create | D1 | A0/C3/P2 | 0.2500000 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 |
| prefix:32 | attestation_create | D1 | A1/C2/P2 | 0.7500000 | -0.1000000 | 0.5000000 | -0.0114382 | 0.0000000 | 0.1628540 | 0.8371460 |
| continuation:0 | attestation_create | P1 | A1/C2/P3; A2/C6/P3 | 0.2500000 | 0.0969388 | 0.2806122 | -0.3153158 | 0.0000000 | -0.0115678 | 0.9884322 |
| continuation:1 | proposition_write | Q1 | A1/C2/P4 | 0.0000000 | 0.0804878 | 0.0804878 | -0.3700546 | 0.0000000 | -0.0965222 | 0.9034778 |
| continuation:3 | attestation_create | Q1 | A1/C2/P4; A2/C6/P4 | 0.2500000 | 0.0804878 | 0.2560976 | -0.3700546 | 0.0000000 | -0.0379856 | 0.9620144 |
| continuation:4 | attestation_create | P1 | A0/C6/P3 | 0.2500000 | 0.0969388 | 0.2806122 | -0.3153158 | 0.0000000 | -0.0115678 | 0.9884322 |
| continuation:5 | attestation_create | Q1 | A0/C6/P4; A1/C2/P4; A2/C6/P4 | 0.2500000 | 0.0804878 | 0.2560976 | -0.3700546 | 0.0000000 | -0.0379856 | 0.9620144 |
| continuation:6 | proposition_write | S1 | A0/C6/P5 | 0.0000000 | 0.0800437 | 0.0800437 | -0.4609498 | 0.0000000 | -0.1269687 | 0.8730313 |
| continuation:8 | attestation_create | S1 | A0/C6/P5; A1/C2/P5; A2/C6/P5 | 0.2500000 | 0.0800437 | 0.2508541 | -0.4609498 | 0.0000000 | -0.0700319 | 0.9299681 |
| continuation:10 | proposition_write | D2 | A0/C7/P2 | 0.0000000 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 |
| continuation:11 | proposition_write | P2 | A0/C7/P3 | 0.0000000 | -0.0408163 | -0.0408163 | -0.3153158 | 0.0000000 | -0.1187107 | 0.8812893 |
| continuation:14 | attestation_create | P2 | A0/C7/P3; A1/C3/P3 | 0.2500000 | -0.0408163 | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 |
| continuation:15 | proposition_write | Q2 | A0/C7/P4 | 0.0000000 | 0.0219512 | 0.0219512 | -0.3700546 | 0.0000000 | -0.1160344 | 0.8839656 |
| continuation:16 | attestation_create | Q2 | A0/C7/P4; A1/C3/P4 | 0.2500000 | 0.0219512 | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 |
| continuation:17 | proposition_write | S2 | A0/C7/P5 | 0.0000000 | 0.0480167 | 0.0480167 | -0.4609498 | 0.0000000 | -0.1376444 | 0.8623556 |
| continuation:18 | proposition_write | R | A0/C7/P6 | 0.0000000 | 0.0846121 | 0.0846121 | -0.5343177 | 0.0000000 | -0.1499018 | 0.8500982 |
| continuation:19 | attestation_create | R | A0/C7/P6; A2/C6/P6 | 0.2500000 | 0.0846121 | 0.2124893 | -0.5343177 | 0.0000000 | -0.1072762 | 0.8927238 |
| continuation:21 | attestation_create | R | A0/C7/P6; A1/C3/P6; A2/C6/P6 | 0.2500000 | 0.0846121 | 0.2124893 | -0.5343177 | 0.0000000 | -0.1072762 | 0.8927238 |
| continuation:23 | attestation_create | D2 | A0/C8/P2 | 0.2500000 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 |
| continuation:24 | attestation_create | P2 | A0/C8/P3 | 0.2500000 | 0.0051020 | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 |
| continuation:25 | attestation_create | Q2 | A0/C8/P4 | 0.2500000 | 0.0414634 | 0.2170732 | -0.3700546 | 0.0000000 | -0.0509938 | 0.9490062 |
| continuation:26 | attestation_create | S2 | A0/C8/P5 | 0.2500000 | 0.0586924 | 0.2295028 | -0.4609498 | 0.0000000 | -0.0771490 | 0.9228510 |
| continuation:27 | attestation_create | R | A0/C8/P6; A1/C3/P6; A2/C6/P6 | 0.2500000 | 0.1216965 | 0.2495737 | -0.5343177 | 0.0000000 | -0.0949146 | 0.9050854 |
| continuation:35 | proposition_write | D3 | A0/C10/P2 | 0.0000000 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 |
| continuation:36 | proposition_write | P3 | A0/C10/P3 | 0.0000000 | -0.0408163 | -0.0408163 | -0.3153158 | 0.0000000 | -0.1187107 | 0.8812893 |
| continuation:39 | attestation_create | P3 | A0/C10/P3; A1/C4/P3 | 0.2500000 | -0.0408163 | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 |
| continuation:40 | proposition_write | Q3 | A0/C10/P4 | 0.0000000 | 0.0219512 | 0.0219512 | -0.3700546 | 0.0000000 | -0.1160344 | 0.8839656 |
| continuation:41 | attestation_create | Q3 | A0/C10/P4; A1/C4/P4 | 0.2500000 | 0.0219512 | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 |
| continuation:42 | proposition_write | S3 | A0/C10/P5 | 0.0000000 | 0.0480167 | 0.0480167 | -0.4609498 | 0.0000000 | -0.1376444 | 0.8623556 |
| continuation:43 | proposition_write | R2 | A0/C10/P6 | 0.0000000 | 0.0846121 | 0.0846121 | -0.5343177 | 0.0000000 | -0.1499018 | 0.8500982 |
| continuation:45 | attestation_create | R2 | A0/C10/P6; A1/C4/P6 | 0.2500000 | 0.0846121 | 0.2124893 | -0.5343177 | 0.0000000 | -0.1072762 | 0.8927238 |
| continuation:47 | attestation_create | D3 | A0/C11/P2 | 0.2500000 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 |
| continuation:48 | attestation_create | P3 | A0/C11/P3 | 0.2500000 | 0.0051020 | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 |
| continuation:49 | attestation_create | Q3 | A0/C11/P4 | 0.2500000 | 0.0414634 | 0.2170732 | -0.3700546 | 0.0000000 | -0.0509938 | 0.9490062 |
| continuation:50 | attestation_create | S3 | A0/C11/P5 | 0.2500000 | 0.0586924 | 0.2295028 | -0.4609498 | 0.0000000 | -0.0771490 | 0.9228510 |
| continuation:51 | proposition_write | R3 | A0/C11/P6 | 0.0000000 | 0.1114663 | 0.1114663 | -0.5343177 | 0.0000000 | -0.1409504 | 0.8590496 |
| continuation:57 | attestation_create | R2 | A0/C12/P6; A1/C4/P6 | 0.2500000 | 0.1216965 | 0.2495737 | -0.5343177 | 0.0000000 | -0.0949146 | 0.9050854 |
| continuation:63 | attestation_create | R3 | A0/C13/P6 | 0.2500000 | 0.1114663 | 0.2393436 | -0.5343177 | 0.0000000 | -0.0983247 | 0.9016753 |
| continuation:77 | proposition_write | D4 | A0/C16/P2 | 0.0000000 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 |
| continuation:78 | proposition_write | P4 | A0/C16/P3 | 0.0000000 | -0.0408163 | -0.0408163 | -0.3153158 | 0.0000000 | -0.1187107 | 0.8812893 |

## Lifecycle

| Boundary | Event | Alias | Current actors | Bias reached | Bias generation | Available actions |
| --- | --- | --- | --- | --- | --- | --- |
| origin | proposition_write | Origin | A0/C2/P1; A1/C1/P1; A2/C1/P1 | False | None; None; None | proposition.variant,proposition.variant,proposition.variant |
| origin | attestation_create | Origin | A0/C2/P1; A1/C1/P1; A2/C1/P1 | False | None; None; None | proposition.variant,proposition.variant,proposition.variant |
| prefix:0 | proposition_write | D1 | A0/C2/P2 | False | None | proposition.variant |
| prefix:2 | attestation_create | D1 | A0/C2/P2; A1/C1/P2; A2/C1/P2 | False | None; None; None | proposition.variant,proposition.variant,proposition.variant |
| prefix:3 | proposition_write | P1 | A0/C2/P3 | False | None | participant.rest |
| prefix:15 | attestation_create | D1 | A0/C3/P2 | False | None | instrument.execute/attestation.create |
| prefix:32 | attestation_create | D1 | A1/C2/P2 | False | None | instrument.execute/attestation.create |
| continuation:0 | attestation_create | P1 | A1/C2/P3; A2/C6/P3 | False | None; None | proposition.reclassify,proposition.reclassify |
| continuation:1 | proposition_write | Q1 | A1/C2/P4 | False | None | proposition.variant |
| continuation:3 | attestation_create | Q1 | A1/C2/P4; A2/C6/P4 | False | None; None | proposition.variant,proposition.variant |
| continuation:4 | attestation_create | P1 | A0/C6/P3 | False | None | instrument.execute/attestation.create |
| continuation:5 | attestation_create | Q1 | A0/C6/P4; A1/C2/P4; A2/C6/P4 | False | None; None; None | proposition.variant,proposition.variant,proposition.variant |
| continuation:6 | proposition_write | S1 | A0/C6/P5 | False | None | participant.rest |
| continuation:8 | attestation_create | S1 | A0/C6/P5; A1/C2/P5; A2/C6/P5 | False | None; None; None | participant.rest,participant.rest,participant.rest |
| continuation:10 | proposition_write | D2 | A0/C7/P2 | False | None | proposition.variant |
| continuation:11 | proposition_write | P2 | A0/C7/P3 | False | None | participant.rest |
| continuation:14 | attestation_create | P2 | A0/C7/P3; A1/C3/P3 | False | None; None | proposition.reclassify,proposition.reclassify |
| continuation:15 | proposition_write | Q2 | A0/C7/P4 | False | None | proposition.variant |
| continuation:16 | attestation_create | Q2 | A0/C7/P4; A1/C3/P4 | False | None; None | proposition.variant,proposition.variant |
| continuation:17 | proposition_write | S2 | A0/C7/P5 | False | None | proposition.reconcile |
| continuation:18 | proposition_write | R | A0/C7/P6 | True | False | participant.rest |
| continuation:19 | attestation_create | R | A0/C7/P6; A2/C6/P6 | True | False; False | participant.rest,participant.rest |
| continuation:21 | attestation_create | R | A0/C7/P6; A1/C3/P6; A2/C6/P6 | True | False; False; False | participant.rest,participant.rest,participant.rest |
| continuation:23 | attestation_create | D2 | A0/C8/P2 | False | None | instrument.execute/attestation.create |
| continuation:24 | attestation_create | P2 | A0/C8/P3 | False | None | instrument.execute/attestation.create |
| continuation:25 | attestation_create | Q2 | A0/C8/P4 | False | None | attestation.create |
| continuation:26 | attestation_create | S2 | A0/C8/P5 | False | None | instrument.execute/attestation.create |
| continuation:27 | attestation_create | R | A0/C8/P6; A1/C3/P6; A2/C6/P6 | True | False; False; False | participant.rest,participant.rest,participant.rest |
| continuation:35 | proposition_write | D3 | A0/C10/P2 | False | None | proposition.variant |
| continuation:36 | proposition_write | P3 | A0/C10/P3 | False | None | participant.rest |
| continuation:39 | attestation_create | P3 | A0/C10/P3; A1/C4/P3 | False | None; None | proposition.reclassify,proposition.reclassify |
| continuation:40 | proposition_write | Q3 | A0/C10/P4 | False | None | proposition.variant |
| continuation:41 | attestation_create | Q3 | A0/C10/P4; A1/C4/P4 | False | None; None | proposition.variant,proposition.variant |
| continuation:42 | proposition_write | S3 | A0/C10/P5 | False | None | proposition.reconcile |
| continuation:43 | proposition_write | R2 | A0/C10/P6 | True | False | participant.rest |
| continuation:45 | attestation_create | R2 | A0/C10/P6; A1/C4/P6 | True | False; False | participant.rest,participant.rest |
| continuation:47 | attestation_create | D3 | A0/C11/P2 | False | None | instrument.execute/attestation.create |
| continuation:48 | attestation_create | P3 | A0/C11/P3 | False | None | instrument.execute/attestation.create |
| continuation:49 | attestation_create | Q3 | A0/C11/P4 | False | None | attestation.create |
| continuation:50 | attestation_create | S3 | A0/C11/P5 | False | None | proposition.reconcile/instrument.execute/attestation.create |
| continuation:51 | proposition_write | R3 | A0/C11/P6 | True | False | participant.rest |
| continuation:57 | attestation_create | R2 | A0/C12/P6; A1/C4/P6 | True | False; False | participant.rest,participant.rest |
| continuation:63 | attestation_create | R3 | A0/C13/P6 | True | False | participant.rest |
| continuation:77 | proposition_write | D4 | A0/C16/P2 | False | None | proposition.variant |
| continuation:78 | proposition_write | P4 | A0/C16/P3 | False | None | participant.rest |
