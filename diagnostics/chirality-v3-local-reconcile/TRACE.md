# Runtime primary trace through the last defined boundary

Stored boundary beats do not create an interval before an own Attestation. Existing local snapshots remain cached between own-Attestation changes. Full effective vote IDs, measurements, attenuation terms and schemas are in the lossless JSON.

| Boundary | Actor | Cycle | Phase | Source | Boundary event order | Used | Own completed intervals | Cadence samples | Maturity | Mean | SD | CV | AC | O | O/AC directions | Local Chirality |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| origin | 0 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| origin | 1 | 1 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| origin | 2 | 1 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:0 | 0 | 2 | 2 | D1 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:0 | 1 | 1 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:0 | 2 | 1 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:1 | 0 | 2 | 2 | D1 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:1 | 1 | 1 | 2 | D1 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:1 | 2 | 1 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:2 | 0 | 2 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:2 | 1 | 1 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:2 | 2 | 1 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:3 | 0 | 2 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:3 | 1 | 1 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:3 | 2 | 1 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:4 | 0 | 2 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:4 | 1 | 1 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:4 | 2 | 1 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:5 | 0 | 2 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:5 | 1 | 1 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:5 | 2 | 1 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:6 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:6 | 1 | 1 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:6 | 2 | 1 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:7 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:7 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:7 | 2 | 1 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:8 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:8 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:8 | 2 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:9 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:9 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:9 | 2 | 2 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:10 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:10 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:10 | 2 | 2 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:11 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:11 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:11 | 2 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:12 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:12 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:12 | 2 | 3 | 2 | D1 | 2 | True | [3] | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| prefix:13 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:13 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:13 | 2 | 3 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:14 | 0 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:14 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:14 | 2 | 4 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:15 | 0 | 3 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:15 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:15 | 2 | 4 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:16 | 0 | 3 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:16 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:16 | 2 | 4 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:17 | 0 | 3 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:17 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:17 | 2 | 4 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:18 | 0 | 3 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:18 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:18 | 2 | 4 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:19 | 0 | 4 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:19 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:19 | 2 | 4 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:20 | 0 | 4 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:20 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:20 | 2 | 5 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:21 | 0 | 4 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:21 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:21 | 2 | 5 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:22 | 0 | 4 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:22 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:22 | 2 | 5 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:23 | 0 | 4 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:23 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:23 | 2 | 5 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:24 | 0 | 4 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:24 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:24 | 2 | 5 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:25 | 0 | 5 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:25 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:25 | 2 | 5 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:26 | 0 | 5 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:26 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:26 | 2 | 6 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:27 | 0 | 5 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:27 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:27 | 2 | 6 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:28 | 0 | 5 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:28 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:28 | 2 | 6 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:29 | 0 | 5 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:29 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:29 | 2 | 6 | 2 | D1 | 2 | True | [3,5] | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 |
| prefix:30 | 0 | 5 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:30 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:30 | 2 | 6 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:31 | 0 | 6 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:31 | 1 | 2 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:31 | 2 | 6 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| prefix:32 | 0 | 6 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| prefix:32 | 1 | 2 | 2 | D1 | 2 | True | [3,5,5] | [3,5,5] | formation_cv | 4.3333333 | 0.9428090 | 0.2175713 | 0.6426143 | 0.5000000 | positive/positive | 0.5713072 |
| prefix:32 | 2 | 6 | 3 | P1 | 6 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:0 | 0 | 6 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| continuation:0 | 1 | 2 | 3 | P1 | 6 | True | [10] | [10] | formation_immature | 10.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:0 | 2 | 6 | 3 | P1 | 6 | True | [10] | [10] | formation_immature | 10.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:1 | 0 | 6 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| continuation:1 | 1 | 2 | 4 | Q1 | 19 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:1 | 2 | 6 | 3 | P1 | 6 | True | [10] | [10] | formation_immature | 10.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:2 | 0 | 6 | 2 | D1 | 2 | True | [3,5,5] | [3,5,5] | formation_cv | 4.3333333 | 0.9428090 | 0.2175713 | 0.6426143 | 0.5000000 | positive/positive | 0.5713072 |
| continuation:2 | 1 | 2 | 4 | Q1 | 19 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:2 | 2 | 6 | 3 | P1 | 6 | True | [10] | [10] | formation_immature | 10.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:3 | 0 | 6 | 2 | D1 | 2 | True | [3,5,5] | [3,5,5] | formation_cv | 4.3333333 | 0.9428090 | 0.2175713 | 0.6426143 | 0.5000000 | positive/positive | 0.5713072 |
| continuation:3 | 1 | 2 | 4 | Q1 | 19 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:3 | 2 | 6 | 4 | Q1 | 19 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:4 | 0 | 6 | 3 | P1 | 6 | True | [10,0] | [10,0] | formation_cv | 5.0000000 | 5.0000000 | 1.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:4 | 1 | 2 | 4 | Q1 | 19 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:4 | 2 | 6 | 4 | Q1 | 19 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:5 | 0 | 6 | 4 | Q1 | 19 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:5 | 1 | 2 | 4 | Q1 | 19 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:5 | 2 | 6 | 4 | Q1 | 19 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:6 | 0 | 6 | 5 | S1 | 22 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:6 | 1 | 2 | 4 | Q1 | 19 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:6 | 2 | 6 | 4 | Q1 | 19 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:7 | 0 | 6 | 5 | S1 | 22 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:7 | 1 | 2 | 5 | S1 | 22 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:7 | 2 | 6 | 4 | Q1 | 19 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:8 | 0 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:8 | 1 | 2 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:8 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:9 | 0 | 7 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| continuation:9 | 1 | 2 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:9 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:10 | 0 | 7 | 2 | D2 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:10 | 1 | 2 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:10 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:11 | 0 | 7 | 3 | P2 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:11 | 1 | 2 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:11 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:12 | 0 | 7 | 3 | P2 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:12 | 1 | 3 | 1 | Origin | None | False | [] | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 |
| continuation:12 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:13 | 0 | 7 | 3 | P2 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:13 | 1 | 3 | 2 | D2 | 2 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:13 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:14 | 0 | 7 | 3 | P2 | 2 | True | [15] | [15] | formation_immature | 15.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:14 | 1 | 3 | 3 | P2 | 2 | True | [15] | [15] | formation_immature | 15.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:14 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:15 | 0 | 7 | 4 | Q2 | 26 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:15 | 1 | 3 | 3 | P2 | 2 | True | [15] | [15] | formation_immature | 15.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:15 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:16 | 0 | 7 | 4 | Q2 | 26 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:16 | 1 | 3 | 4 | Q2 | 26 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:16 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:17 | 0 | 7 | 5 | S2 | 27 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:17 | 1 | 3 | 4 | Q2 | 26 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:17 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:18 | 0 | 7 | 6 | R | 27 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:18 | 1 | 3 | 4 | Q2 | 26 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:18 | 2 | 6 | 5 | S1 | 22 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:19 | 0 | 7 | 6 | R | 27 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:19 | 1 | 3 | 4 | Q2 | 26 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:19 | 2 | 6 | 6 | R | 27 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:20 | 0 | 7 | 6 | R | 27 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:20 | 1 | 3 | 5 | S2 | 27 | False | [] | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 |
| continuation:20 | 2 | 6 | 6 | R | 27 | True | [0] | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 |
| continuation:21 | 0 | 7 | 6 | R | 27 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:21 | 1 | 3 | 6 | R | 27 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |
| continuation:21 | 2 | 6 | 6 | R | 27 | True | [0,0] | [0,0] | formation_zero_mean_regular | 0.0000000 | 0.0000000 | 0.0000000 | 1.0000000 | 0.5000000 | positive/positive | 0.7500000 |

## Field and lifecycle

| Boundary | Actor | Source | Normalized ancestors | Field Chirality | Correspondence | Closure | Tension | Charge | Grounded | Bias reached | Bias generation | Overlap | Actions |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| origin | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | proposition.variant |
| origin | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | proposition.variant |
| origin | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | proposition.variant |
| prefix:0 | 0 | D1 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | False | False | None | False | proposition.variant |
| prefix:0 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | instrument.execute |
| prefix:0 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | instrument.execute |
| prefix:1 | 0 | D1 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | False | False | None | False | proposition.variant |
| prefix:1 | 1 | D1 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | False | False | None | False | proposition.variant |
| prefix:1 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:2 | 0 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | proposition.variant |
| prefix:2 | 1 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | proposition.variant |
| prefix:2 | 2 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | proposition.variant |
| prefix:3 | 0 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:3 | 1 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:3 | 2 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:4 | 0 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:4 | 1 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:4 | 2 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:5 | 0 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:5 | 1 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:5 | 2 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:6 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:6 | 1 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:6 | 2 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:7 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:7 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:7 | 2 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:8 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:8 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:8 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:9 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:9 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:9 | 2 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:10 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:10 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:10 | 2 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:11 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:11 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:11 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:12 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:12 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:12 | 2 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:13 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:13 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:13 | 2 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 | False | False | None | False | participant.rest |
| prefix:14 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:14 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:14 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:15 | 0 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:15 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:15 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:16 | 0 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:16 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:16 | 2 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:17 | 0 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:17 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:17 | 2 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:18 | 0 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:18 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:18 | 2 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:19 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:19 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:19 | 2 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:20 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:20 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:20 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:21 | 0 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:21 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:21 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:22 | 0 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:22 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:22 | 2 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:23 | 0 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:23 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:23 | 2 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:24 | 0 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:24 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:24 | 2 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:25 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:25 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:25 | 2 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:26 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:26 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:26 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:27 | 0 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:27 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:27 | 2 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:28 | 0 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:28 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:28 | 2 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:29 | 0 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:29 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:29 | 2 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 | True | False | None | False | instrument.execute,attestation.create |
| prefix:30 | 0 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:30 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:30 | 2 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:31 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:31 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create |
| prefix:31 | 2 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 | False | False | None | False | participant.rest |
| prefix:32 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| prefix:32 | 1 | D1 | -0.1000000 | 0.3570458 | -0.0114382 | 0.0000000 | 0.1152026 | 0.8847974 | True | False | None | False | instrument.execute,attestation.create |
| prefix:32 | 2 | P1 | 0.0641176 | 0.0641176 | -0.3153158 | 0.0000000 | -0.0837328 | 0.9162672 | False | False | None | False | participant.rest |
| continuation:0 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| continuation:0 | 1 | P1 | 0.0641176 | 0.2477911 | -0.3153158 | 0.0000000 | -0.0225082 | 0.9774918 | True | False | None | False | proposition.reclassify |
| continuation:0 | 2 | P1 | 0.0641176 | 0.2477911 | -0.3153158 | 0.0000000 | -0.0225082 | 0.9774918 | True | False | None | False | proposition.reclassify |
| continuation:1 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | attestation.create,proposition.traverse |
| continuation:1 | 1 | Q1 | 0.0665410 | 0.0665410 | -0.3700546 | 0.0000000 | -0.1011712 | 0.8988288 | False | False | None | False | proposition.variant |
| continuation:1 | 2 | P1 | 0.0641176 | 0.2477911 | -0.3153158 | 0.0000000 | -0.0225082 | 0.9774918 | True | False | None | False | instrument.execute,attestation.create |
| continuation:2 | 0 | D1 | -0.1000000 | 0.3570458 | -0.0114382 | 0.0000000 | 0.1152026 | 0.8847974 | True | False | None | False | instrument.execute,attestation.create |
| continuation:2 | 1 | Q1 | 0.0665410 | 0.0665410 | -0.3700546 | 0.0000000 | -0.1011712 | 0.8988288 | False | False | None | False | proposition.variant |
| continuation:2 | 2 | P1 | 0.0641176 | 0.2477911 | -0.3153158 | 0.0000000 | -0.0225082 | 0.9774918 | True | False | None | False | instrument.execute,attestation.create |
| continuation:3 | 0 | D1 | -0.1000000 | 0.3570458 | -0.0114382 | 0.0000000 | 0.1152026 | 0.8847974 | True | False | None | False | instrument.execute,attestation.create |
| continuation:3 | 1 | Q1 | 0.0665410 | 0.2421508 | -0.3700546 | 0.0000000 | -0.0426346 | 0.9573654 | True | False | None | False | proposition.variant |
| continuation:3 | 2 | Q1 | 0.0665410 | 0.2421508 | -0.3700546 | 0.0000000 | -0.0426346 | 0.9573654 | True | False | None | False | proposition.variant |
| continuation:4 | 0 | P1 | 0.0641176 | 0.2477911 | -0.3153158 | 0.0000000 | -0.0225082 | 0.9774918 | True | False | None | False | instrument.execute,attestation.create |
| continuation:4 | 1 | Q1 | 0.0665410 | 0.2421508 | -0.3700546 | 0.0000000 | -0.0426346 | 0.9573654 | True | False | None | False | proposition.variant |
| continuation:4 | 2 | Q1 | 0.0665410 | 0.2421508 | -0.3700546 | 0.0000000 | -0.0426346 | 0.9573654 | True | False | None | False | proposition.variant |
| continuation:5 | 0 | Q1 | 0.0665410 | 0.5933703 | -0.3700546 | 0.0000000 | 0.0744386 | 0.9255614 | True | False | None | False | proposition.variant |
| continuation:5 | 1 | Q1 | 0.0665410 | 0.5933703 | -0.3700546 | 0.0000000 | 0.0744386 | 0.9255614 | True | False | None | False | proposition.variant |
| continuation:5 | 2 | Q1 | 0.0665410 | 0.5933703 | -0.3700546 | 0.0000000 | 0.0744386 | 0.9255614 | True | False | None | False | proposition.variant |
| continuation:6 | 0 | S1 | 0.1578182 | 0.1578182 | -0.4609498 | 0.0000000 | -0.1010438 | 0.8989562 | False | False | None | False | participant.rest |
| continuation:6 | 1 | Q1 | 0.0665410 | 0.5933703 | -0.3700546 | 0.0000000 | 0.0744386 | 0.9255614 | True | False | None | False | instrument.execute |
| continuation:6 | 2 | Q1 | 0.0665410 | 0.5933703 | -0.3700546 | 0.0000000 | 0.0744386 | 0.9255614 | True | False | None | False | instrument.execute |
| continuation:7 | 0 | S1 | 0.1578182 | 0.1578182 | -0.4609498 | 0.0000000 | -0.1010438 | 0.8989562 | False | False | None | False | participant.rest |
| continuation:7 | 1 | S1 | 0.1578182 | 0.1578182 | -0.4609498 | 0.0000000 | -0.1010438 | 0.8989562 | False | False | None | False | participant.rest |
| continuation:7 | 2 | Q1 | 0.0665410 | 0.5933703 | -0.3700546 | 0.0000000 | 0.0744386 | 0.9255614 | True | False | None | False | attestation.create |
| continuation:8 | 0 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:8 | 1 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:8 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:9 | 0 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | proposition.variant |
| continuation:9 | 1 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:9 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:10 | 0 | D2 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | False | False | None | False | proposition.variant |
| continuation:10 | 1 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:10 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:11 | 0 | P2 | -0.0408163 | -0.0408163 | -0.3153158 | 0.0000000 | -0.1187107 | 0.8812893 | False | False | None | False | participant.rest |
| continuation:11 | 1 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:11 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:12 | 0 | P2 | -0.0408163 | -0.0408163 | -0.3153158 | 0.0000000 | -0.1187107 | 0.8812893 | False | False | None | False | participant.rest |
| continuation:12 | 1 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | False | False | None | False | instrument.execute |
| continuation:12 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:13 | 0 | P2 | -0.0408163 | -0.0408163 | -0.3153158 | 0.0000000 | -0.1187107 | 0.8812893 | False | False | None | False | participant.rest |
| continuation:13 | 1 | D2 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | False | False | None | False | instrument.execute,attestation.create |
| continuation:13 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:14 | 0 | P2 | -0.0408163 | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 | False | False | None | False | proposition.reclassify |
| continuation:14 | 1 | P2 | -0.0408163 | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 | False | False | None | False | proposition.reclassify |
| continuation:14 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:15 | 0 | Q2 | 0.0219512 | 0.0219512 | -0.3700546 | 0.0000000 | -0.1160344 | 0.8839656 | False | False | None | False | proposition.variant |
| continuation:15 | 1 | P2 | -0.0408163 | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 | False | False | None | False | instrument.execute,attestation.create |
| continuation:15 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:16 | 0 | Q2 | 0.0219512 | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | True | False | None | False | proposition.variant |
| continuation:16 | 1 | Q2 | 0.0219512 | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | True | False | None | False | proposition.variant |
| continuation:16 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | participant.rest |
| continuation:17 | 0 | S2 | 0.0480167 | 0.0480167 | -0.4609498 | 0.0000000 | -0.1376444 | 0.8623556 | False | False | None | False | proposition.reconcile |
| continuation:17 | 1 | Q2 | 0.0219512 | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | True | False | None | False | instrument.execute |
| continuation:17 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | proposition.reconcile |
| continuation:18 | 0 | R | 0.1093732 | 0.1093732 | -0.5343177 | 0.0000000 | -0.1416482 | 0.8583518 | False | True | False | False | participant.rest |
| continuation:18 | 1 | Q2 | 0.0219512 | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | True | False | None | False | instrument.execute |
| continuation:18 | 2 | S1 | 0.1578182 | 0.3286286 | -0.4609498 | 0.0000000 | -0.0441070 | 0.9558930 | True | False | None | False | proposition.reconcile,instrument.execute,attestation.create |
| continuation:19 | 0 | R | 0.1093732 | 0.2372504 | -0.5343177 | 0.0000000 | -0.0990224 | 0.9009776 | False | True | False | False | participant.rest |
| continuation:19 | 1 | Q2 | 0.0219512 | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | True | False | None | False | instrument.execute |
| continuation:19 | 2 | R | 0.1093732 | 0.2372504 | -0.5343177 | 0.0000000 | -0.0990224 | 0.9009776 | False | True | False | False | participant.rest |
| continuation:20 | 0 | R | 0.1093732 | 0.2372504 | -0.5343177 | 0.0000000 | -0.0990224 | 0.9009776 | False | True | False | False | participant.rest |
| continuation:20 | 1 | S2 | 0.0480167 | 0.0480167 | -0.4609498 | 0.0000000 | -0.1376444 | 0.8623556 | False | False | None | False | instrument.execute,attestation.create |
| continuation:20 | 2 | R | 0.1093732 | 0.2372504 | -0.5343177 | 0.0000000 | -0.0990224 | 0.9009776 | False | True | False | False | participant.rest |
| continuation:21 | 0 | R | 0.1093732 | 0.4930049 | -0.5343177 | 0.0000000 | -0.0137710 | 0.9862290 | False | True | True | False | proposition.reclassify |
| continuation:21 | 1 | R | 0.1093732 | 0.4930049 | -0.5343177 | 0.0000000 | -0.0137710 | 0.9862290 | False | True | True | False | proposition.reclassify |
| continuation:21 | 2 | R | 0.1093732 | 0.4930049 | -0.5343177 | 0.0000000 | -0.0137710 | 0.9862290 | False | True | True | False | proposition.reclassify |
