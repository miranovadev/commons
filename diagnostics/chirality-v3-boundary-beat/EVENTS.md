# Event-oriented primary trace

Producing Phase identifies the proposition; current participant Phases/Cycles and available actions are in TRACE.md. For Attestation events, Cycle position is the number of represented entries before that beat in this proposition’s scope. Event-order endpoints preserve exact temporal ordering; they are not Cycle counts. Non-Attestation rows show unchanged stored observations, not fresh measurements. Stable count covers all existing propositions whose complete local snapshot remains identical. Origin keeps its constitutional calibration.

| Boundary | Event | Action | Acting participant/Cycle/Phase after action | Proposition or current Gate | Parents | Domain | Producing Phase | Previous event order | Current event order | Previous Cycle position | Current Cycle position | New interval | Local intervals | Maturity | Mean | SD | CV | Cadence | Orientation | Directions | Local Chirality | Unchanged snapshots |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| origin | proposition_write | Origin | A0/C2/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 0 |
| origin | attestation_create | Origin | A0/C2/P1 | Origin |  | emergence | 7 | None | 2 | None | 1 | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 0 |
| prefix:0 | proposition_write | proposition.variant | A0/C2/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 1 |
| prefix:1 | structural_action | instrument.execute | A1/C1/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 2 |
| prefix:2 | attestation_create | attestation.create | A2/C1/P2 | D1 | Origin | settlement | 1 | 2 | 6 | 0 | 3 | 3 | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 1 |
| prefix:3 | proposition_write | proposition.variant | A0/C2/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 2 |
| prefix:4 | structural_action | instrument.execute | A1/C1/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:5 | structural_action | instrument.execute | A2/C1/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:6 | cycle_only_progression | participant.rest | A0/C3/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:7 | cycle_only_progression | participant.rest | A1/C2/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:8 | cycle_only_progression | participant.rest | A2/C2/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:9 | structural_action | proposition.traverse | A2/C2/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 3 |
| prefix:10 | structural_action | instrument.execute | A2/C2/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:11 | cycle_only_progression | participant.rest | A2/C3/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:12 | structural_action | proposition.traverse | A2/C3/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3] | formation_immature | 3.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 3 |
| prefix:13 | structural_action | instrument.execute | A2/C3/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:14 | cycle_only_progression | participant.rest | A2/C4/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:15 | attestation_create | attestation.create | A0/C3/P2 | D1 | Origin | settlement | 1 | 6 | 12 | 3 | 8 | 5 | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 | 2 |
| prefix:16 | structural_action | proposition.traverse | A2/C4/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 | 3 |
| prefix:17 | structural_action | instrument.execute | A0/C3/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:18 | structural_action | instrument.execute | A2/C4/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:19 | cycle_only_progression | participant.rest | A0/C4/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:20 | cycle_only_progression | participant.rest | A2/C5/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:21 | structural_action | proposition.traverse | A0/C4/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 | 3 |
| prefix:22 | structural_action | proposition.traverse | A2/C5/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 | 3 |
| prefix:23 | structural_action | instrument.execute | A0/C4/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:24 | structural_action | instrument.execute | A2/C5/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:25 | cycle_only_progression | participant.rest | A0/C5/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:26 | cycle_only_progression | participant.rest | A2/C6/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:27 | structural_action | proposition.traverse | A0/C5/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 | 3 |
| prefix:28 | structural_action | proposition.traverse | A2/C6/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3,5] | formation_cv | 4.0000000 | 1.0000000 | 0.2500000 | 0.6000000 | 0.5000000 | positive/positive | 0.5500000 | 3 |
| prefix:29 | structural_action | instrument.execute | A0/C5/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:30 | structural_action | instrument.execute | A2/C6/P3 | P1 | D1 | settlement | 2 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| prefix:31 | cycle_only_progression | participant.rest | A0/C6/P1 | Origin |  | emergence | 7 | None | None | None | None | None | [] | constitutional_origin | undefined / not recorded | undefined / not recorded | undefined / not recorded | -1.0000000 | 0.0000000 | negative/negative | -0.5000000 | 3 |
| prefix:32 | attestation_create | attestation.create | A1/C2/P2 | D1 | Origin | settlement | 1 | 12 | 18 | 8 | 13 | 5 | [3,5,5] | formation_cv | 4.3333333 | 0.9428090 | 0.2175713 | 0.6426143 | 0.5000000 | positive/positive | 0.5713072 | 2 |
| continuation:0 | attestation_create | attestation.create | A1/C2/P3 | P1 | D1 | settlement | 2 | 6 | 19 | 3 | 13 | 10 | [10] | formation_immature | 10.0000000 | 0.0000000 | 0.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 2 |
| continuation:1 | proposition_write | proposition.reclassify | A1/C2/P4 | Q1 | P1 | settlement | 3 | None | None | None | None | None | [] | formation_immature | undefined / not recorded | undefined / not recorded | undefined / not recorded | 0.0000000 | 0.0000000 | negative/negative | 0.0000000 | 3 |
| continuation:2 | structural_action | proposition.traverse | A0/C6/P2 | D1 | Origin | settlement | 1 | None | None | None | None | None | [3,5,5] | formation_cv | 4.3333333 | 0.9428090 | 0.2175713 | 0.6426143 | 0.5000000 | positive/positive | 0.5713072 | 4 |
| continuation:3 | attestation_create | attestation.create | A2/C6/P4 | Q1 | P1 | settlement | 3 | 19 | 20 | 13 | 13 | 0 | [0] | formation_immature | 0.0000000 | 0.0000000 | undefined / not recorded | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 3 |
| continuation:4 | attestation_create | attestation.create | A0/C6/P3 | P1 | D1 | settlement | 2 | 19 | 21 | 13 | 13 | 0 | [10,0] | formation_cv | 5.0000000 | 5.0000000 | 1.0000000 | 0.0000000 | 0.5000000 | positive/negative | 0.2500000 | 3 |

## Field at the same event

| Boundary | Proposition | Normalized ancestry | Field Chirality | Correspondence | Closure | Tension | Charge |
| --- | --- | --- | --- | --- | --- | --- | --- |
| origin | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| origin | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:0 | D1 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 |
| prefix:1 | D1 | -0.1000000 | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 |
| prefix:2 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 |
| prefix:3 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 |
| prefix:4 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 |
| prefix:5 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 |
| prefix:6 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:7 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:8 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:9 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 |
| prefix:10 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 |
| prefix:11 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:12 | D1 | -0.1000000 | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 |
| prefix:13 | P1 | 0.0051020 | 0.0051020 | -0.3153158 | 0.0000000 | -0.1034046 | 0.8965954 |
| prefix:14 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:15 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 |
| prefix:16 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 |
| prefix:17 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 |
| prefix:18 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 |
| prefix:19 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:20 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:21 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 |
| prefix:22 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 |
| prefix:23 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 |
| prefix:24 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 |
| prefix:25 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:26 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:27 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 |
| prefix:28 | D1 | -0.1000000 | 0.3400000 | -0.0114382 | 0.0000000 | 0.1095206 | 0.8904794 |
| prefix:29 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 |
| prefix:30 | P1 | 0.0602041 | 0.0602041 | -0.3153158 | 0.0000000 | -0.0850372 | 0.9149628 |
| prefix:31 | Origin | 0.0000000 | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 |
| prefix:32 | D1 | -0.1000000 | 0.3570458 | -0.0114382 | 0.0000000 | 0.1152026 | 0.8847974 |
| continuation:0 | P1 | 0.0641176 | 0.2477911 | -0.3153158 | 0.0000000 | -0.0225082 | 0.9774918 |
| continuation:1 | Q1 | 0.0665410 | 0.0665410 | -0.3700546 | 0.0000000 | -0.1011712 | 0.8988288 |
| continuation:2 | D1 | -0.1000000 | 0.3570458 | -0.0114382 | 0.0000000 | 0.1152026 | 0.8847974 |
| continuation:3 | Q1 | 0.0665410 | 0.2421508 | -0.3700546 | 0.0000000 | -0.0426346 | 0.9573654 |
| continuation:4 | P1 | 0.0641176 | 0.2477911 | -0.3153158 | 0.0000000 | -0.0225082 | 0.9774918 |

## Terminal attempted event (rolled back)

| Boundary | Target | Previous event order | Current event order | Previous Cycle position | Current Cycle position | New interval | Local intervals | Mean | SD | CV | Orientation | Cadence / local Chirality / field |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| continuation:5 | Q1 | 20 | 22 | 13 | 13 | 0 | [0,0] | 0 | 0.0 | undefined | 0.5 | not assigned |
