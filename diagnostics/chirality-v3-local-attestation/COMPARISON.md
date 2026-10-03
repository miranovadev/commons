# Same authored boundaries: current v3 versus candidate

Component columns are raw/local. Chirality is the production attenuated aggregate. `None` in Bias generation means the actor is not at Bias. Positive Tension is not a Bias admission predicate. F is A1 immediately before the preserved c34 turn, at which Rest is selected because Bias Reclassify is unavailable; no forbidden Bias write was attempted.

| Boundary | Label | Model | Actor/Cycle/Phase | Orientation | Intervals | Cadence | O/AC directions | Chirality | Tension | Grounded | Bias generation | Available actions |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A: mature D1 | prefix:32 | v3 | A1/C2/P2 | 0.5000000 | [5,5] | 1.0000000 | positive/positive | 0.5000000 | 0.1628540 | True | None | instrument.execute,attestation.create |
| A: mature D1 | prefix:32 | candidate | A1/C2/P2 | 0.5000000 | [5,5] | 1.0000000 | positive/positive | 0.5000000 | 0.1628540 | True | None | instrument.execute,attestation.create |
| B: P1 grounding | continuation:0 | v3 | A1/C2/P3 | 0.5000000 | [5,5,0] | 0.1715729 | positive/positive | 0.3436391 | 0.0094410 | True | None | proposition.reclassify |
| B: P1 grounding | continuation:0 | candidate | A1/C2/P3 | 0.5000000 | [] | 0.0000000 | positive/negative | 0.2806122 | -0.0115678 | True | None | proposition.reclassify |
| C: Reclassify Q1 | continuation:1 | v3 | A1/C2/P4 | 0.5000000 | [5,5,0] | 0.1715729 | positive/positive | 0.3314223 | -0.0128774 | False | None | proposition.variant |
| C: Reclassify Q1 | continuation:1 | candidate | A1/C2/P4 | 0.0000000 | [] | 0.0000000 | negative/negative | 0.0804878 | -0.0965222 | False | None | proposition.variant |
| D: first Mediate S1 | continuation:6 | v3 | A0/C6/P5 | 0.5000000 | [5,5,0,0,0,0] | -0.1715729 | positive/negative | 0.1775879 | -0.0944540 | False | None | participant.rest |
| D: first Mediate S1 | continuation:6 | candidate | A0/C6/P5 | 0.0000000 | [] | 0.0000000 | negative/negative | 0.0800437 | -0.1269687 | False | None | participant.rest |
| D: ready to Reconcile S2 | continuation:17 | v3 | A0/C7/P5 | 0.5000000 | [0] | 0.0000000 | positive/negative | 0.2188271 | -0.0807076 | False | None | proposition.reconcile |
| D: ready to Reconcile S2 | continuation:17 | candidate | A0/C7/P5 | 0.0000000 | [] | 0.0000000 | negative/negative | 0.0480167 | -0.1376444 | False | None | proposition.reconcile |
| E: first Bias R | continuation:18 | v3 | A0/C7/P6 | 0.5000000 | [5,5,0,0,0,0,0,2,0] | -0.2129404 | positive/negative | 0.1707269 | -0.1211970 | False | False | participant.rest |
| E: first Bias R | continuation:18 | candidate | A0/C7/P6 | 0.0000000 | [] | 0.0000000 | negative/negative | 0.0846121 | -0.1499018 | False | False | participant.rest |
| F: grounded Bias R | continuation:33 | v3 | A1/C3/P6 | 0.5000000 | [5,5,0,0,0,0,0,2,0,0,0,1,0,0,0,0] | -0.3445034 | positive/negative | 0.1132670 | -0.1403502 | True | False | participant.rest |
| F: grounded Bias R | continuation:33 | candidate | A1/C3/P6 | 0.5000000 | [0,1] | 0.0000000 | positive/negative | 0.2495737 | -0.0949146 | True | False | participant.rest |
