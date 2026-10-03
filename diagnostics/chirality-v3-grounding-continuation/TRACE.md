# Grounding continuation: complete runtime trace

Archived steps 0–32 are unchanged; continuation step 0 branches immediately before archived step 33. These step numbers are different namespaces. All new actions use ParticipationService. Existing-Gate fields are labelled separately from target fields and prospective/actual Commit measurements.

## Fork: all participant plans

| Actor | Cycle | Phase | Gate | Actions | Attested source | Receipt |
|---|---|---|---|---|---|---|
| 0 | 6 | 1 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | yes | no |
| 1 | 2 | 2 | 01m40h0b9rqz877v4jm15h45kx | ["instrument.execute","attestation.create"] | yes | yes |
| 2 | 6 | 3 | 01m40h0fez4qjtwznrf7d4n5ek | ["participant.rest"] | no | yes |

## First genuine Attestation: same target before and after

### target_before

| Target | Intervals | n | Mean | SD | CV | O | Cadence | Mature | χ | C | Closure | T | Charge | Grounding |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01m40h0fez4qjtwznrf7d4n5ek | [5,5] | 2 | 5 | 0.0000000 | 0.0000000 | 0.5000000 | 1.0000000 | yes | 0.6479592 | -0.3153158 | 0.0000000 | 0.1108812 | 0.8891188 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":true,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation"]} |
### target_after

| Target | Intervals | n | Mean | SD | CV | O | Cadence | Mature | χ | C | Closure | T | Charge | Grounding |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01m40h0fez4qjtwznrf7d4n5ek | [5,5,0] | 3 | 3.3333333 | 2.3570226 | 0.7071068 | 0.5000000 | 0.1715729 | yes | 0.3436391 | -0.3153158 | 0.0000000 | 0.0094410 | 0.9905590 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |

## Participant plans after the first Attestation

| Actor | Cycle | Phase | Gate | Actions | Grounding | Chirality gate | Commit |
|---|---|---|---|---|---|---|---|
| 0 | 6 | 1 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | {"gate_id":"01m40h09842vmcg2f9fdhxjavx","admissible":true,"reason":null,"orientation_direction":"negative","attestation_cadence_direction":"negative","chirality_direction":"negative"} | {"admissible":false,"reason":"not_order_phase"} |
| 1 | 2 | 3 | 01m40h0fez4qjtwznrf7d4n5ek | ["proposition.reclassify"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | {"gate_id":"01m40h0fez4qjtwznrf7d4n5ek","admissible":true,"reason":null,"orientation_direction":"positive","attestation_cadence_direction":"positive","chirality_direction":"positive"} | {"admissible":false,"reason":"not_order_phase"} |
| 2 | 6 | 3 | 01m40h0fez4qjtwznrf7d4n5ek | ["proposition.reclassify"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | {"gate_id":"01m40h0fez4qjtwznrf7d4n5ek","admissible":true,"reason":null,"orientation_direction":"positive","attestation_cadence_direction":"positive","chirality_direction":"positive"} | {"admissible":false,"reason":"not_order_phase"} |

## Every continuation action

| Step | Actor | Cycle | Phase | Action | Target/partner | Selection reason | Next actor Phase |
|---|---|---|---|---|---|---|---|
| 0 | 1 | 2 | 2 | attestation.create | 01m40h0fez4qjtwznrf7d4n5ek | attest exact source needed by A2 P3 | 3 |
| 1 | 1 | 2 | 3 | proposition.reclassify | 01m40h0fez4qjtwznrf7d4n5ek | fixed field-blind lifecycle/support priority | 4 |
| 2 | 0 | 6 | 1 | proposition.traverse | 01m40h0b9rqz877v4jm15h45kx | fixed field-blind lifecycle/support priority | 2 |
| 3 | 2 | 6 | 3 | attestation.create | 01m40h25gv291jzcpkv0j3f56n | fixed field-blind lifecycle/support priority | 4 |
| 4 | 0 | 6 | 2 | attestation.create | 01m40h0fez4qjtwznrf7d4n5ek | fixed field-blind lifecycle/support priority | 3 |
| 5 | 0 | 6 | 3 | attestation.create | 01m40h25gv291jzcpkv0j3f56n | fixed field-blind lifecycle/support priority | 4 |
| 6 | 0 | 6 | 4 | proposition.variant | — | fixed field-blind lifecycle/support priority | 5 |
| 7 | 1 | 2 | 4 | instrument.execute | 01m40h2jt5ydcvhw1pcz558m67 | fixed field-blind lifecycle/support priority | 5 |
| 8 | 2 | 6 | 4 | attestation.create | 01m40h2jt5ydcvhw1pcz558m67 | fixed field-blind lifecycle/support priority | 5 |
| 9 | 0 | 6 | 5 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 10 | 0 | 7 | 1 | proposition.variant | — | fixed field-blind lifecycle/support priority | 2 |
| 11 | 0 | 7 | 2 | proposition.variant | — | fixed field-blind lifecycle/support priority | 3 |
| 12 | 1 | 2 | 5 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 13 | 1 | 3 | 1 | instrument.execute | 01m40h305gy8j1bdhbv1xzwg4w | fixed field-blind lifecycle/support priority | 2 |
| 14 | 1 | 3 | 2 | attestation.create | 01m40h321b30nxdq498zps25kw | attest exact source needed by A0 P3 | 3 |
| 15 | 0 | 7 | 3 | proposition.reclassify | 01m40h321b30nxdq498zps25kw | fixed field-blind lifecycle/support priority | 4 |
| 16 | 1 | 3 | 3 | attestation.create | 01m40h3c0yf1q8rdv92jbg223k | fixed field-blind lifecycle/support priority | 4 |
| 17 | 0 | 7 | 4 | proposition.variant | — | fixed field-blind lifecycle/support priority | 5 |
| 18 | 0 | 7 | 5 | proposition.reconcile | 01m40h2jt5ydcvhw1pcz558m67 | fixed field-blind lifecycle/support priority | 6 |
| 19 | 2 | 6 | 5 | attestation.create | 01m40h3nh5vyja2hpnzvkfjbf0 | attest exact source needed by A0 P6 | 6 |
| 20 | 1 | 3 | 4 | instrument.execute | 01m40h3j2br8esxvxnpr505jmj | fixed field-blind lifecycle/support priority | 5 |
| 21 | 1 | 3 | 5 | attestation.create | 01m40h3nh5vyja2hpnzvkfjbf0 | fixed field-blind lifecycle/support priority | 6 |
| 22 | 0 | 7 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 23 | 0 | 8 | 1 | attestation.create | 01m40h305gy8j1bdhbv1xzwg4w | fixed field-blind lifecycle/support priority | 2 |
| 24 | 0 | 8 | 2 | attestation.create | 01m40h321b30nxdq498zps25kw | fixed field-blind lifecycle/support priority | 3 |
| 25 | 0 | 8 | 3 | attestation.create | 01m40h3c0yf1q8rdv92jbg223k | fixed field-blind lifecycle/support priority | 4 |
| 26 | 0 | 8 | 4 | attestation.create | 01m40h3j2br8esxvxnpr505jmj | fixed field-blind lifecycle/support priority | 5 |
| 27 | 0 | 8 | 5 | attestation.create | 01m40h3nh5vyja2hpnzvkfjbf0 | fixed field-blind lifecycle/support priority | 6 |
| 28 | 0 | 8 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 29 | 0 | 9 | 1 | proposition.traverse | 01m40h305gy8j1bdhbv1xzwg4w | fixed field-blind lifecycle/support priority | 2 |
| 30 | 0 | 9 | 2 | proposition.traverse | 01m40h321b30nxdq498zps25kw | fixed field-blind lifecycle/support priority | 3 |
| 31 | 0 | 9 | 3 | proposition.traverse | 01m40h3c0yf1q8rdv92jbg223k | fixed field-blind lifecycle/support priority | 4 |
| 32 | 0 | 9 | 4 | proposition.traverse | 01m40h3j2br8esxvxnpr505jmj | fixed field-blind lifecycle/support priority | 5 |
| 33 | 0 | 9 | 5 | instrument.execute | 01m40h3nh5vyja2hpnzvkfjbf0 | fixed field-blind lifecycle/support priority | 6 |
| 34 | 0 | 9 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 35 | 0 | 10 | 1 | proposition.variant | — | fixed field-blind lifecycle/support priority | 2 |
| 36 | 0 | 10 | 2 | proposition.variant | — | fixed field-blind lifecycle/support priority | 3 |
| 37 | 1 | 3 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 38 | 1 | 4 | 1 | instrument.execute | 01m40h60vk3af2wt74mp9wcp17 | fixed field-blind lifecycle/support priority | 2 |
| 39 | 1 | 4 | 2 | attestation.create | 01m40h6349nayda9b6mn4h7y8r | attest exact source needed by A0 P3 | 3 |
| 40 | 0 | 10 | 3 | proposition.reclassify | 01m40h6349nayda9b6mn4h7y8r | fixed field-blind lifecycle/support priority | 4 |
| 41 | 1 | 4 | 3 | attestation.create | 01m40h6hmefk0shc0w18rdb1nb | fixed field-blind lifecycle/support priority | 4 |
| 42 | 0 | 10 | 4 | proposition.variant | — | fixed field-blind lifecycle/support priority | 5 |
| 43 | 0 | 10 | 5 | proposition.reconcile | 01m40h2jt5ydcvhw1pcz558m67 | fixed field-blind lifecycle/support priority | 6 |
| 44 | 1 | 4 | 4 | instrument.execute | 01m40h6rszb1wb92xsqbcf5538 | fixed field-blind lifecycle/support priority | 5 |
| 45 | 1 | 4 | 5 | attestation.create | 01m40h6wvmbk4hz7xkcqcya07b | attest exact source needed by A0 P6 | 6 |
| 46 | 0 | 10 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 47 | 0 | 11 | 1 | attestation.create | 01m40h60vk3af2wt74mp9wcp17 | fixed field-blind lifecycle/support priority | 2 |
| 48 | 0 | 11 | 2 | attestation.create | 01m40h6349nayda9b6mn4h7y8r | fixed field-blind lifecycle/support priority | 3 |
| 49 | 0 | 11 | 3 | attestation.create | 01m40h6hmefk0shc0w18rdb1nb | fixed field-blind lifecycle/support priority | 4 |
| 50 | 0 | 11 | 4 | attestation.create | 01m40h6rszb1wb92xsqbcf5538 | fixed field-blind lifecycle/support priority | 5 |
| 51 | 0 | 11 | 5 | proposition.reconcile | 01m40h3j2br8esxvxnpr505jmj | fixed field-blind lifecycle/support priority | 6 |
| 52 | 0 | 11 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 53 | 0 | 12 | 1 | proposition.traverse | 01m40h60vk3af2wt74mp9wcp17 | fixed field-blind lifecycle/support priority | 2 |
| 54 | 0 | 12 | 2 | proposition.traverse | 01m40h6349nayda9b6mn4h7y8r | fixed field-blind lifecycle/support priority | 3 |
| 55 | 0 | 12 | 3 | proposition.traverse | 01m40h6hmefk0shc0w18rdb1nb | fixed field-blind lifecycle/support priority | 4 |
| 56 | 0 | 12 | 4 | proposition.traverse | 01m40h6rszb1wb92xsqbcf5538 | fixed field-blind lifecycle/support priority | 5 |
| 57 | 0 | 12 | 5 | attestation.create | 01m40h6wvmbk4hz7xkcqcya07b | fixed field-blind lifecycle/support priority | 6 |
| 58 | 0 | 12 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 59 | 0 | 13 | 1 | proposition.traverse | 01m40h60vk3af2wt74mp9wcp17 | fixed field-blind lifecycle/support priority | 2 |
| 60 | 0 | 13 | 2 | proposition.traverse | 01m40h6349nayda9b6mn4h7y8r | fixed field-blind lifecycle/support priority | 3 |
| 61 | 0 | 13 | 3 | proposition.traverse | 01m40h6hmefk0shc0w18rdb1nb | fixed field-blind lifecycle/support priority | 4 |
| 62 | 0 | 13 | 4 | proposition.traverse | 01m40h6rszb1wb92xsqbcf5538 | fixed field-blind lifecycle/support priority | 5 |
| 63 | 0 | 13 | 5 | attestation.create | 01m40h86becr4m5vth5a9vf31b | fixed field-blind lifecycle/support priority | 6 |
| 64 | 0 | 13 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 65 | 0 | 14 | 1 | proposition.traverse | 01m40h60vk3af2wt74mp9wcp17 | fixed field-blind lifecycle/support priority | 2 |
| 66 | 0 | 14 | 2 | proposition.traverse | 01m40h6349nayda9b6mn4h7y8r | fixed field-blind lifecycle/support priority | 3 |
| 67 | 0 | 14 | 3 | proposition.traverse | 01m40h6hmefk0shc0w18rdb1nb | fixed field-blind lifecycle/support priority | 4 |
| 68 | 0 | 14 | 4 | proposition.traverse | 01m40h6rszb1wb92xsqbcf5538 | fixed field-blind lifecycle/support priority | 5 |
| 69 | 0 | 14 | 5 | instrument.execute | 01m40h6wvmbk4hz7xkcqcya07b | fixed field-blind lifecycle/support priority | 6 |
| 70 | 0 | 14 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 71 | 0 | 15 | 1 | proposition.traverse | 01m40h60vk3af2wt74mp9wcp17 | fixed field-blind lifecycle/support priority | 2 |
| 72 | 0 | 15 | 2 | proposition.traverse | 01m40h6349nayda9b6mn4h7y8r | fixed field-blind lifecycle/support priority | 3 |
| 73 | 0 | 15 | 3 | proposition.traverse | 01m40h6hmefk0shc0w18rdb1nb | fixed field-blind lifecycle/support priority | 4 |
| 74 | 0 | 15 | 4 | proposition.traverse | 01m40h6rszb1wb92xsqbcf5538 | fixed field-blind lifecycle/support priority | 5 |
| 75 | 0 | 15 | 5 | instrument.execute | 01m40h86becr4m5vth5a9vf31b | fixed field-blind lifecycle/support priority | 6 |
| 76 | 0 | 15 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |
| 77 | 0 | 16 | 1 | proposition.variant | — | fixed field-blind lifecycle/support priority | 2 |
| 78 | 0 | 16 | 2 | proposition.variant | — | fixed field-blind lifecycle/support priority | 3 |
| 79 | 1 | 4 | 6 | participant.rest | — | fixed field-blind lifecycle/support priority | 1 |

## Existing-Gate field BEFORE each action

| Step | Phase | Intervals | Mean | SD | CV | O | Cadence | Mature | χ | C | Closure | T | Charge | Current Domain |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 0 | 2 | [5,5] | 5 | 0.0000000 | 0.0000000 | 0.5000000 | 1.0000000 | yes | 0.5000000 | -0.0114382 | 0.0000000 | 0.1628540 | 0.8371460 | settlement |
| 1 | 3 | [5,5,0] | 3.3333333 | 2.3570226 | 0.7071068 | 0.5000000 | 0.1715729 | yes | 0.3436391 | -0.3153158 | 0.0000000 | 0.0094410 | 0.9905590 | settlement |
| 2 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 3 | 3 | [5,5,0] | 3.3333333 | 2.3570226 | 0.7071068 | 0.5000000 | 0.1715729 | yes | 0.3436391 | -0.3153158 | 0.0000000 | 0.0094410 | 0.9905590 | settlement |
| 4 | 2 | [5,5] | 5 | 0.0000000 | 0.0000000 | 0.5000000 | 1.0000000 | yes | 0.5000000 | -0.0114382 | 0.0000000 | 0.1628540 | 0.8371460 | settlement |
| 5 | 3 | [5,5,0,0] | 2.5000000 | 2.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.2806122 | -0.3153158 | 0.0000000 | -0.0115678 | 0.9884322 | settlement |
| 6 | 4 | [5,5,0,0,0,0] | 1.6666667 | 2.3570226 | 1.4142136 | 0.5000000 | -0.1715729 | yes | 0.1958378 | -0.3700546 | 0.0000000 | -0.0580722 | 0.9419278 | settlement |
| 7 | 4 | [5,5,0,0,0,0] | 1.6666667 | 2.3570226 | 1.4142136 | 0.5000000 | -0.1715729 | yes | 0.1958378 | -0.3700546 | 0.0000000 | -0.0580722 | 0.9419278 | settlement |
| 8 | 4 | [5,5,0,0,0,0] | 1.6666667 | 2.3570226 | 1.4142136 | 0.5000000 | -0.1715729 | yes | 0.1958378 | -0.3700546 | 0.0000000 | -0.0580722 | 0.9419278 | settlement |
| 9 | 5 | [5,5,0,0,0,0,0] | 1.4285714 | 2.2587698 | 1.5811388 | 0.5000000 | -0.2251482 | yes | 0.1592855 | -0.4609498 | 0.0000000 | -0.1005548 | 0.8994452 | settlement |
| 10 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 11 | 2 | [] | — | — | — | 0.0000000 | 0.0000000 | no | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | settlement |
| 12 | 5 | [5,5,0,0,0,0,0] | 1.4285714 | 2.2587698 | 1.5811388 | 0.5000000 | -0.2251482 | yes | 0.1592855 | -0.4609498 | 0.0000000 | -0.1005548 | 0.8994452 | settlement |
| 13 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 14 | 2 | [] | — | — | — | 0.0000000 | 0.0000000 | no | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | settlement |
| 15 | 3 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 | settlement |
| 16 | 3 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 | settlement |
| 17 | 4 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | settlement |
| 18 | 5 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.2188271 | -0.4609498 | 0.0000000 | -0.0807076 | 0.9192924 | settlement |
| 19 | 5 | [5,5,0,0,0,0,0] | 1.4285714 | 2.2587698 | 1.5811388 | 0.5000000 | -0.2251482 | yes | 0.1592855 | -0.4609498 | 0.0000000 | -0.1005548 | 0.8994452 | settlement |
| 20 | 4 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | settlement |
| 21 | 5 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.2188271 | -0.4609498 | 0.0000000 | -0.0807076 | 0.9192924 | settlement |
| 22 | 6 | [5,5,0,0,0,0,0,2,0,0,0] | 1.0909091 | 1.9284730 | 1.7677670 | 0.5000000 | -0.2773958 | yes | 0.1542422 | -0.5343177 | 0.0000000 | -0.1266918 | 0.8733082 | settlement |
| 23 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 24 | 2 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | settlement |
| 25 | 3 | [1,0] | 0.5000000 | 0.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 | settlement |
| 26 | 4 | [0,1,0,0] | 0.2500000 | 0.4330127 | 1.7320508 | 0.5000000 | -0.2679492 | yes | 0.1229642 | -0.3700546 | 0.0000000 | -0.0823634 | 0.9176366 | settlement |
| 27 | 5 | [0,1,0,0,0] | 0.2000000 | 0.4000000 | 2.0000000 | 0.5000000 | -0.3333333 | yes | 0.0927449 | -0.4609498 | 0.0000000 | -0.1227350 | 0.8772650 | settlement |
| 28 | 6 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0] | 0.8125000 | 1.6665365 | 2.0511218 | 0.5000000 | -0.3445034 | yes | 0.1132670 | -0.5343177 | 0.0000000 | -0.1403502 | 0.8596498 | settlement |
| 29 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 30 | 2 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | settlement |
| 31 | 3 | [1,0] | 0.5000000 | 0.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 | settlement |
| 32 | 4 | [0,1,0,0] | 0.2500000 | 0.4330127 | 1.7320508 | 0.5000000 | -0.2679492 | yes | 0.1229642 | -0.3700546 | 0.0000000 | -0.0823634 | 0.9176366 | settlement |
| 33 | 5 | [0,1,0,0,0] | 0.2000000 | 0.4000000 | 2.0000000 | 0.5000000 | -0.3333333 | yes | 0.0927449 | -0.4609498 | 0.0000000 | -0.1227350 | 0.8772650 | settlement |
| 34 | 6 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0] | 0.8125000 | 1.6665365 | 2.0511218 | 0.5000000 | -0.3445034 | yes | 0.1132670 | -0.5343177 | 0.0000000 | -0.1403502 | 0.8596498 | settlement |
| 35 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 36 | 2 | [] | — | — | — | 0.0000000 | 0.0000000 | no | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | settlement |
| 37 | 6 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0] | 0.8125000 | 1.6665365 | 2.0511218 | 0.5000000 | -0.3445034 | yes | 0.1132670 | -0.5343177 | 0.0000000 | -0.1403502 | 0.8596498 | settlement |
| 38 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 39 | 2 | [] | — | — | — | 0.0000000 | 0.0000000 | no | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | settlement |
| 40 | 3 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 | settlement |
| 41 | 3 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1428571 | -0.3153158 | 0.0000000 | -0.0574862 | 0.9425138 | settlement |
| 42 | 4 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | settlement |
| 43 | 5 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.2188271 | -0.4609498 | 0.0000000 | -0.0807076 | 0.9192924 | settlement |
| 44 | 4 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.1975610 | -0.3700546 | 0.0000000 | -0.0574978 | 0.9425022 | settlement |
| 45 | 5 | [0] | 0 | 0.0000000 | — | 0.5000000 | 0.0000000 | no | 0.2188271 | -0.4609498 | 0.0000000 | -0.0807076 | 0.9192924 | settlement |
| 46 | 6 | [5,5,0,0,0,0,0,6,0,0] | 1.6000000 | 2.4576411 | 1.5360257 | 0.5000000 | -0.2113645 | yes | 0.1711300 | -0.5343177 | 0.0000000 | -0.1210626 | 0.8789374 | settlement |
| 47 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 48 | 2 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | settlement |
| 49 | 3 | [1,0] | 0.5000000 | 0.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 | settlement |
| 50 | 4 | [0,1,0,0] | 0.2500000 | 0.4330127 | 1.7320508 | 0.5000000 | -0.2679492 | yes | 0.1229642 | -0.3700546 | 0.0000000 | -0.0823634 | 0.9176366 | settlement |
| 51 | 5 | [0,1,0,0,0] | 0.2000000 | 0.4000000 | 2.0000000 | 0.5000000 | -0.3333333 | yes | 0.0927449 | -0.4609498 | 0.0000000 | -0.1227350 | 0.8772650 | settlement |
| 52 | 6 | [0,1,0,0,0,3,0,1,0,0,0] | 0.4545455 | 0.8907235 | 1.9595918 | 0.5000000 | -0.3242311 | yes | 0.0985656 | -0.5343177 | 0.0000000 | -0.1452507 | 0.8547493 | settlement |
| 53 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 54 | 2 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | settlement |
| 55 | 3 | [1,0] | 0.5000000 | 0.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 | settlement |
| 56 | 4 | [0,1,0,0] | 0.2500000 | 0.4330127 | 1.7320508 | 0.5000000 | -0.2679492 | yes | 0.1229642 | -0.3700546 | 0.0000000 | -0.0823634 | 0.9176366 | settlement |
| 57 | 5 | [0,1,0,0,0] | 0.2000000 | 0.4000000 | 2.0000000 | 0.5000000 | -0.3333333 | yes | 0.0927449 | -0.4609498 | 0.0000000 | -0.1227350 | 0.8772650 | settlement |
| 58 | 6 | [5,5,0,0,0,0,0,6,0,0,1,0,0,0,1] | 1.2000000 | 2.1039645 | 1.7533038 | 0.5000000 | -0.2735999 | yes | 0.1314009 | -0.5343177 | 0.0000000 | -0.1343056 | 0.8656944 | settlement |
| 59 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 60 | 2 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | settlement |
| 61 | 3 | [1,0] | 0.5000000 | 0.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 | settlement |
| 62 | 4 | [0,1,0,0] | 0.2500000 | 0.4330127 | 1.7320508 | 0.5000000 | -0.2679492 | yes | 0.1229642 | -0.3700546 | 0.0000000 | -0.0823634 | 0.9176366 | settlement |
| 63 | 5 | [0,1,0,0,0] | 0.2000000 | 0.4000000 | 2.0000000 | 0.5000000 | -0.3333333 | yes | 0.0927449 | -0.4609498 | 0.0000000 | -0.1227350 | 0.8772650 | settlement |
| 64 | 6 | [0,1,0,0,0,3,0,1,0,0,0,2] | 0.5833333 | 0.9537936 | 1.6350747 | 0.5000000 | -0.2410082 | yes | 0.1198502 | -0.5343177 | 0.0000000 | -0.1381558 | 0.8618442 | settlement |
| 65 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 66 | 2 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | settlement |
| 67 | 3 | [1,0] | 0.5000000 | 0.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 | settlement |
| 68 | 4 | [0,1,0,0] | 0.2500000 | 0.4330127 | 1.7320508 | 0.5000000 | -0.2679492 | yes | 0.1229642 | -0.3700546 | 0.0000000 | -0.0823634 | 0.9176366 | settlement |
| 69 | 5 | [0,1,0,0,0] | 0.2000000 | 0.4000000 | 2.0000000 | 0.5000000 | -0.3333333 | yes | 0.0927449 | -0.4609498 | 0.0000000 | -0.1227350 | 0.8772650 | settlement |
| 70 | 6 | [5,5,0,0,0,0,0,6,0,0,1,0,0,0,1] | 1.2000000 | 2.1039645 | 1.7533038 | 0.5000000 | -0.2735999 | yes | 0.1314009 | -0.5343177 | 0.0000000 | -0.1343056 | 0.8656944 | settlement |
| 71 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 72 | 2 | [] | — | — | — | 0.5000000 | 0.0000000 | no | 0.1000000 | -0.0114382 | 0.0000000 | 0.0295206 | 0.9704794 | settlement |
| 73 | 3 | [1,0] | 0.5000000 | 0.5000000 | 1.0000000 | 0.5000000 | 0.0000000 | yes | 0.1887755 | -0.3153158 | 0.0000000 | -0.0421801 | 0.9578199 | settlement |
| 74 | 4 | [0,1,0,0] | 0.2500000 | 0.4330127 | 1.7320508 | 0.5000000 | -0.2679492 | yes | 0.1229642 | -0.3700546 | 0.0000000 | -0.0823634 | 0.9176366 | settlement |
| 75 | 5 | [0,1,0,0,0] | 0.2000000 | 0.4000000 | 2.0000000 | 0.5000000 | -0.3333333 | yes | 0.0927449 | -0.4609498 | 0.0000000 | -0.1227350 | 0.8772650 | settlement |
| 76 | 6 | [0,1,0,0,0,3,0,1,0,0,0,2] | 0.5833333 | 0.9537936 | 1.6350747 | 0.5000000 | -0.2410082 | yes | 0.1198502 | -0.5343177 | 0.0000000 | -0.1381558 | 0.8618442 | settlement |
| 77 | 1 | [] | — | — | — | 0.0000000 | -1.0000000 | yes | -0.5000000 | 1.0000000 | -1.0000000 | -0.1666666 | 0.8333334 | settlement |
| 78 | 2 | [] | — | — | — | 0.0000000 | 0.0000000 | no | -0.1000000 | -0.0114382 | 0.0000000 | -0.0371460 | 0.9628540 | settlement |
| 79 | 6 | [5,5,0,0,0,0,0,6,0,0,1,0,0,0,1] | 1.2000000 | 2.1039645 | 1.7533038 | 0.5000000 | -0.2735999 | yes | 0.1314009 | -0.5343177 | 0.0000000 | -0.1343056 | 0.8656944 | settlement |

## Lifecycle BEFORE each action

| Step | Gate | Available actions | Grounding | O/AC directions | Chirality gate | Commit | Rest reason |
|---|---|---|---|---|---|---|---|
| 0 | 01m40h0b9rqz877v4jm15h45kx | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","positive"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 1 | 01m40h0fez4qjtwznrf7d4n5ek | ["proposition.reclassify"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","positive"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 2 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 3 | 01m40h0fez4qjtwznrf7d4n5ek | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","positive"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 4 | 01m40h0b9rqz877v4jm15h45kx | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","positive"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 5 | 01m40h0fez4qjtwznrf7d4n5ek | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","positive"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 6 | 01m40h25gv291jzcpkv0j3f56n | ["proposition.variant"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 7 | 01m40h25gv291jzcpkv0j3f56n | ["instrument.execute"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 8 | 01m40h25gv291jzcpkv0j3f56n | ["attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 9 | 01m40h2jt5ydcvhw1pcz558m67 | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 10 | 01m40h09842vmcg2f9fdhxjavx | ["proposition.variant"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 11 | 01m40h305gy8j1bdhbv1xzwg4w | ["proposition.variant"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation","missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 12 | 01m40h2jt5ydcvhw1pcz558m67 | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 13 | 01m40h09842vmcg2f9fdhxjavx | ["instrument.execute"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 14 | 01m40h305gy8j1bdhbv1xzwg4w | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":true,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 15 | 01m40h321b30nxdq498zps25kw | ["proposition.reclassify"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 16 | 01m40h321b30nxdq498zps25kw | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 17 | 01m40h3c0yf1q8rdv92jbg223k | ["proposition.variant"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 18 | 01m40h3j2br8esxvxnpr505jmj | ["proposition.reconcile"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation","missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 19 | 01m40h2jt5ydcvhw1pcz558m67 | ["proposition.reconcile","instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 20 | 01m40h3c0yf1q8rdv92jbg223k | ["instrument.execute"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 21 | 01m40h3j2br8esxvxnpr505jmj | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":true,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 22 | 01m40h3nh5vyja2hpnzvkfjbf0 | ["participant.rest"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 23 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 24 | 01m40h305gy8j1bdhbv1xzwg4w | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 25 | 01m40h321b30nxdq498zps25kw | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 26 | 01m40h3c0yf1q8rdv92jbg223k | ["attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 27 | 01m40h3j2br8esxvxnpr505jmj | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 28 | 01m40h3nh5vyja2hpnzvkfjbf0 | ["participant.rest"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 29 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 30 | 01m40h305gy8j1bdhbv1xzwg4w | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 31 | 01m40h321b30nxdq498zps25kw | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 32 | 01m40h3c0yf1q8rdv92jbg223k | ["attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 33 | 01m40h3j2br8esxvxnpr505jmj | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 34 | 01m40h3nh5vyja2hpnzvkfjbf0 | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 35 | 01m40h09842vmcg2f9fdhxjavx | ["proposition.variant"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 36 | 01m40h60vk3af2wt74mp9wcp17 | ["proposition.variant"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation","missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 37 | 01m40h3nh5vyja2hpnzvkfjbf0 | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 38 | 01m40h09842vmcg2f9fdhxjavx | ["instrument.execute"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 39 | 01m40h60vk3af2wt74mp9wcp17 | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":true,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 40 | 01m40h6349nayda9b6mn4h7y8r | ["proposition.reclassify"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 41 | 01m40h6349nayda9b6mn4h7y8r | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 42 | 01m40h6hmefk0shc0w18rdb1nb | ["proposition.variant"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 43 | 01m40h6rszb1wb92xsqbcf5538 | ["proposition.reconcile"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation","missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 44 | 01m40h6hmefk0shc0w18rdb1nb | ["instrument.execute"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 45 | 01m40h6rszb1wb92xsqbcf5538 | ["proposition.reconcile","instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":true,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 46 | 01m40h6wvmbk4hz7xkcqcya07b | ["participant.rest"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 47 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 48 | 01m40h60vk3af2wt74mp9wcp17 | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 49 | 01m40h6349nayda9b6mn4h7y8r | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 50 | 01m40h6hmefk0shc0w18rdb1nb | ["attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 51 | 01m40h6rszb1wb92xsqbcf5538 | ["proposition.reconcile","instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 52 | 01m40h86becr4m5vth5a9vf31b | ["participant.rest"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation","missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 53 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 54 | 01m40h60vk3af2wt74mp9wcp17 | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 55 | 01m40h6349nayda9b6mn4h7y8r | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 56 | 01m40h6hmefk0shc0w18rdb1nb | ["attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 57 | 01m40h6rszb1wb92xsqbcf5538 | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 58 | 01m40h6wvmbk4hz7xkcqcya07b | ["participant.rest"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 59 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 60 | 01m40h60vk3af2wt74mp9wcp17 | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 61 | 01m40h6349nayda9b6mn4h7y8r | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 62 | 01m40h6hmefk0shc0w18rdb1nb | ["attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 63 | 01m40h6rszb1wb92xsqbcf5538 | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 64 | 01m40h86becr4m5vth5a9vf31b | ["participant.rest"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 65 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 66 | 01m40h60vk3af2wt74mp9wcp17 | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 67 | 01m40h6349nayda9b6mn4h7y8r | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 68 | 01m40h6hmefk0shc0w18rdb1nb | ["attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 69 | 01m40h6rszb1wb92xsqbcf5538 | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 70 | 01m40h6wvmbk4hz7xkcqcya07b | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 71 | 01m40h09842vmcg2f9fdhxjavx | ["attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 72 | 01m40h60vk3af2wt74mp9wcp17 | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 73 | 01m40h6349nayda9b6mn4h7y8r | ["instrument.execute","attestation.create","proposition.traverse"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 74 | 01m40h6hmefk0shc0w18rdb1nb | ["attestation.create","proposition.traverse"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 75 | 01m40h6rszb1wb92xsqbcf5538 | ["instrument.execute","attestation.create"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | — |
| 76 | 01m40h86becr4m5vth5a9vf31b | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |
| 77 | 01m40h09842vmcg2f9fdhxjavx | ["proposition.variant"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 78 | 01m40hbqs62tsxe5m807708bg3 | ["proposition.variant"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation","missing_completed_receipt"]} | ["negative","negative"] | yes | {"admissible":false,"reason":"not_order_phase"} | — |
| 79 | 01m40h6wvmbk4hz7xkcqcya07b | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | ["positive","negative"] | no | {"admissible":false,"reason":"not_order_phase"} | no_available_move |

## Every genuine Attestation: measured target change

| Step | Actor | Target | Before intervals | Before χ | Before T | After intervals | After Cadence | After χ | After T | After grounding |
|---|---|---|---|---|---|---|---|---|---|---|
| 0 | 1 | 01m40h0fez4qjtwznrf7d4n5ek | [5,5] | 0.6479592 | 0.1108812 | [5,5,0] | 0.1715729 | 0.3436391 | 0.0094410 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 3 | 2 | 01m40h25gv291jzcpkv0j3f56n | [5,5,0] | 0.3314223 | -0.0128774 | [5,5,0,0] | 0.0000000 | 0.2711625 | -0.0329640 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 4 | 0 | 01m40h0fez4qjtwznrf7d4n5ek | [5,5,0] | 0.3436391 | 0.0094410 | [5,5,0,0] | 0.0000000 | 0.2806122 | -0.0115678 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 5 | 0 | 01m40h25gv291jzcpkv0j3f56n | [5,5,0,0,0] | 0.2206172 | -0.0498124 | [5,5,0,0,0,0] | -0.1715729 | 0.1958378 | -0.0580722 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 8 | 2 | 01m40h2jt5ydcvhw1pcz558m67 | [5,5,0,0,0,0] | 0.1775879 | -0.0944540 | [5,5,0,0,0,0,0] | -0.2251482 | 0.1592855 | -0.1005548 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 14 | 1 | 01m40h321b30nxdq498zps25kw | [] | -0.0408163 | -0.1187107 | [] | 0.0000000 | 0.1428571 | -0.0574862 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 16 | 1 | 01m40h3c0yf1q8rdv92jbg223k | [] | 0.0219512 | -0.1160344 | [0] | 0.0000000 | 0.1975610 | -0.0574978 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 19 | 2 | 01m40h3nh5vyja2hpnzvkfjbf0 | [5,5,0,0,0,0,0,2,0] | 0.1707269 | -0.1211970 | [5,5,0,0,0,0,0,2,0,0] | -0.2476430 | 0.1618516 | -0.1241554 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 21 | 1 | 01m40h3nh5vyja2hpnzvkfjbf0 | [5,5,0,0,0,0,0,2,0,0] | 0.1618516 | -0.1241554 | [5,5,0,0,0,0,0,2,0,0,0] | -0.2773958 | 0.1542422 | -0.1266918 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 23 | 0 | 01m40h305gy8j1bdhbv1xzwg4w | [] | -0.1000000 | -0.0371460 | [] | 0.0000000 | 0.1000000 | 0.0295206 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 24 | 0 | 01m40h321b30nxdq498zps25kw | [1] | 0.1887755 | -0.0421801 | [1,0] | 0.0000000 | 0.1887755 | -0.0421801 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 25 | 0 | 01m40h3c0yf1q8rdv92jbg223k | [0,1,0] | 0.1568134 | -0.0710804 | [0,1,0,0] | -0.2679492 | 0.1229642 | -0.0823634 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 26 | 0 | 01m40h3j2br8esxvxnpr505jmj | [0,1,0,0] | 0.1150815 | -0.1152894 | [0,1,0,0,0] | -0.3333333 | 0.0927449 | -0.1227350 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 27 | 0 | 01m40h3nh5vyja2hpnzvkfjbf0 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0] | 0.1178349 | -0.1388276 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0] | -0.3445034 | 0.1132670 | -0.1403502 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 39 | 1 | 01m40h6349nayda9b6mn4h7y8r | [] | -0.0408163 | -0.1187107 | [] | 0.0000000 | 0.1428571 | -0.0574862 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 41 | 1 | 01m40h6hmefk0shc0w18rdb1nb | [] | 0.0219512 | -0.1160344 | [0] | 0.0000000 | 0.1975610 | -0.0574978 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 45 | 1 | 01m40h6wvmbk4hz7xkcqcya07b | [5,5,0,0,0,0,0,6,0] | 0.1805842 | -0.1179112 | [5,5,0,0,0,0,0,6,0,0] | -0.2113645 | 0.1711300 | -0.1210626 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 47 | 0 | 01m40h60vk3af2wt74mp9wcp17 | [] | -0.1000000 | -0.0371460 | [] | 0.0000000 | 0.1000000 | 0.0295206 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 48 | 0 | 01m40h6349nayda9b6mn4h7y8r | [1] | 0.1887755 | -0.0421801 | [1,0] | 0.0000000 | 0.1887755 | -0.0421801 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 49 | 0 | 01m40h6hmefk0shc0w18rdb1nb | [0,1,0] | 0.1568134 | -0.0710804 | [0,1,0,0] | -0.2679492 | 0.1229642 | -0.0823634 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 50 | 0 | 01m40h6rszb1wb92xsqbcf5538 | [0,1,0,0] | 0.1150815 | -0.1152894 | [0,1,0,0,0] | -0.3333333 | 0.0927449 | -0.1227350 | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} |
| 57 | 0 | 01m40h6wvmbk4hz7xkcqcya07b | [5,5,0,0,0,0,0,6,0,0,1,0,0,0] | 0.1287662 | -0.1351838 | [5,5,0,0,0,0,0,6,0,0,1,0,0,0,1] | -0.2735999 | 0.1314009 | -0.1343056 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |
| 63 | 0 | 01m40h86becr4m5vth5a9vf31b | [0,1,0,0,0,3,0,1,0,0,0] | 0.0985656 | -0.1452507 | [0,1,0,0,0,3,0,1,0,0,0,2] | -0.2410082 | 0.1198502 | -0.1381558 | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} |

## Stop and final readiness

Stop: `continuation_bound`; 80 continuation actions.

| Actor | Status | Cycle | Phase | Actions | Grounding | Chirality gate | Commit |
|---|---|---|---|---|---|---|---|
| 0 | ready | 16 | 3 | ["participant.rest"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":false,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_effective_attestation","missing_completed_receipt"]} | {"gate_id":"01m40hbty1bxzwpnhxaqhgtfrv","admissible":true,"reason":null,"orientation_direction":"negative","attestation_cadence_direction":"negative","chirality_direction":"negative"} | {"admissible":false,"reason":"not_order_phase"} |
| 1 | ready | 5 | 1 | ["instrument.execute","attestation.create"] | {"admissible":false,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":false,"closure":null},"closure_enforced":false,"reasons":["missing_completed_receipt"]} | {"gate_id":"01m40h09842vmcg2f9fdhxjavx","admissible":true,"reason":null,"orientation_direction":"negative","attestation_cadence_direction":"negative","chirality_direction":"negative"} | {"admissible":false,"reason":"not_order_phase"} |
| 2 | ready | 6 | 6 | ["participant.rest"] | {"admissible":true,"reinforcement_count":0,"requirements":{"attestation":true,"receipt":true,"closure":null},"closure_enforced":false,"reasons":[]} | {"gate_id":"01m40h3nh5vyja2hpnzvkfjbf0","admissible":false,"reason":"component_directions_disagree","orientation_direction":"positive","attestation_cadence_direction":"negative","chirality_direction":"positive"} | {"admissible":false,"reason":"not_order_phase"} |

## Actual Commit snapshot

```json
null
```

The raw archive also retains the unchanged prefix, exact timestamps/event ordering, all persisted acts/Cycles, complete schemas, receipt IDs, ancestry weights and stored measurement directions.
