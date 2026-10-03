# Earliest preserved path to Bias

Continuation action numbers are zero-based. c0 is the genuine P1 grounding Attestation; this table begins after it. Each field pair follows the **same acting participant**, whose Gate can change. It is not a continuous measurement of one proposition. Input IDs, full schemas, new record IDs and all other participants are in the raw trace. New-record counts are propositions/Attestations/Receipts/Cycles; consuming or attaching an existing Receipt is not a new Receipt.

The 18-action prefix is the first actual prefix of this preserved schedule reaching Bias, not a proven globally shortest schedule. The structural dependency is Constrain Write → Disperse Write → Mediate Reconcile → Bias; Reconcile additionally needs a distinct eligible Disperse operand. No alternative history was authored or optimized.

## Authored lifecycle

| c | Actor/Cycle | Action | Phase | Selected source/target | Gate after | New P/A/R/C |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | A1 C2→C2 | proposition.reclassify | Constrain→Disperse | P1 | Q1 | 1/0/0/0 |
| 2 | A0 C6→C6 | proposition.traverse | Distinguish→Persist | D1 | D1 | 0/0/0/0 |
| 3 | A2 C6→C6 | attestation.create | Constrain→Disperse | Q1 | Q1 | 0/1/0/0 |
| 4 | A0 C6→C6 | attestation.create | Persist→Constrain | P1 | P1 | 0/1/0/0 |
| 5 | A0 C6→C6 | attestation.create | Constrain→Disperse | Q1 | Q1 | 0/1/0/0 |
| 6 | A0 C6→C6 | proposition.variant | Disperse→Mediate | Q1 | S1 | 1/0/0/0 |
| 7 | A1 C2→C2 | instrument.execute | Disperse→Mediate | S1 | S1 | 0/0/1/0 |
| 8 | A2 C6→C6 | attestation.create | Disperse→Mediate | S1 | S1 | 0/1/0/0 |
| 9 | A0 C6→C7 | participant.rest | Mediate→Distinguish | S1 | Origin | 0/0/0/1 |
| 10 | A0 C7→C7 | proposition.variant | Distinguish→Persist | Origin | D2 | 1/0/0/0 |
| 11 | A0 C7→C7 | proposition.variant | Persist→Constrain | D2 | P2 | 1/0/0/0 |
| 12 | A1 C2→C3 | participant.rest | Mediate→Distinguish | S1 | Origin | 0/0/0/1 |
| 13 | A1 C3→C3 | instrument.execute | Distinguish→Persist | D2 | D2 | 0/0/1/0 |
| 14 | A1 C3→C3 | attestation.create | Persist→Constrain | P2 | P2 | 0/1/0/0 |
| 15 | A0 C7→C7 | proposition.reclassify | Constrain→Disperse | P2 | Q2 | 1/0/0/0 |
| 16 | A1 C3→C3 | attestation.create | Constrain→Disperse | Q2 | Q2 | 0/1/0/0 |
| 17 | A0 C7→C7 | proposition.variant | Disperse→Mediate | Q2 | S2 | 1/0/0/0 |
| 18 | A0 C7→C7 | proposition.reconcile | Mediate→Bias | S2 | R | 1/0/0/0 |

## Measured effects

| c | Intervals before→after | Cadence before→after | Orientation before→after | Chirality before→after | Tension before→after | Grounded before→after |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | [5,5,0] → [5,5,0] | +0.1715729 / positive → +0.1715729 / positive | +0.5000000 / positive → +0.5000000 / positive | +0.3436391 → +0.3314223 | +0.0094410 → -0.0128774 | True → False |
| 2 | [] → [5,5] | -1.0000000 / negative → +1.0000000 / positive | +0.0000000 / negative → +0.5000000 / positive | -0.5000000 → +0.5000000 | -0.1666666 → +0.1628540 | False → True |
| 3 | [5,5,0] → [5,5,0,0] | +0.1715729 / positive → +0.0000000 / positive | +0.5000000 / positive → +0.5000000 / positive | +0.3436391 → +0.2711625 | +0.0094410 → -0.0329640 | True → True |
| 4 | [5,5] → [5,5,0,0] | +1.0000000 / positive → +0.0000000 / positive | +0.5000000 / positive → +0.5000000 / positive | +0.5000000 → +0.2806122 | +0.1628540 → -0.0115678 | True → True |
| 5 | [5,5,0,0] → [5,5,0,0,0,0] | +0.0000000 / positive → -0.1715729 / negative | +0.5000000 / positive → +0.5000000 / positive | +0.2806122 → +0.1958378 | -0.0115678 → -0.0580722 | True → True |
| 6 | [5,5,0,0,0,0] → [5,5,0,0,0,0] | -0.1715729 / negative → -0.1715729 / negative | +0.5000000 / positive → +0.5000000 / positive | +0.1958378 → +0.1775879 | -0.0580722 → -0.0944540 | True → False |
| 7 | [5,5,0,0,0,0] → [5,5,0,0,0,0] | -0.1715729 / negative → -0.1715729 / negative | +0.5000000 / positive → +0.5000000 / positive | +0.1958378 → +0.1775879 | -0.0580722 → -0.0944540 | True → False |
| 8 | [5,5,0,0,0,0] → [5,5,0,0,0,0,0] | -0.1715729 / negative → -0.2251482 / negative | +0.5000000 / positive → +0.5000000 / positive | +0.1958378 → +0.1592855 | -0.0580722 → -0.1005548 | True → True |
| 9 | [5,5,0,0,0,0,0] → [] | -0.2251482 / negative → -1.0000000 / negative | +0.5000000 / positive → +0.0000000 / negative | +0.1592855 → -0.5000000 | -0.1005548 → -0.1666666 | True → False |
| 10 | [] → [] | -1.0000000 / negative → +0.0000000 / negative | +0.0000000 / negative → +0.0000000 / negative | -0.5000000 → -0.1000000 | -0.1666666 → -0.0371460 | False → False |
| 11 | [] → [] | +0.0000000 / negative → +0.0000000 / negative | +0.0000000 / negative → +0.0000000 / negative | -0.1000000 → -0.0408163 | -0.0371460 → -0.1187107 | False → False |
| 12 | [5,5,0,0,0,0,0] → [] | -0.2251482 / negative → -1.0000000 / negative | +0.5000000 / positive → +0.0000000 / negative | +0.1592855 → -0.5000000 | -0.1005548 → -0.1666666 | True → False |
| 13 | [] → [] | -1.0000000 / negative → +0.0000000 / negative | +0.0000000 / negative → +0.0000000 / negative | -0.5000000 → -0.1000000 | -0.1666666 → -0.0371460 | False → False |
| 14 | [] → [] | +0.0000000 / negative → +0.0000000 / negative | +0.0000000 / negative → +0.5000000 / positive | -0.1000000 → +0.1428571 | -0.0371460 → -0.0574862 | False → False |
| 15 | [] → [] | +0.0000000 / negative → +0.0000000 / negative | +0.5000000 / positive → +0.0000000 / negative | +0.1428571 → +0.0219512 | -0.0574862 → -0.1160344 | False → False |
| 16 | [] → [0] | +0.0000000 / negative → +0.0000000 / negative | +0.5000000 / positive → +0.5000000 / positive | +0.1428571 → +0.1975610 | -0.0574862 → -0.0574978 | False → True |
| 17 | [0] → [0] | +0.0000000 / negative → +0.0000000 / negative | +0.5000000 / positive → +0.5000000 / positive | +0.1975610 → +0.2188271 | -0.0574978 → -0.0807076 | True → False |
| 18 | [0] → [5,5,0,0,0,0,0,2,0] | +0.0000000 / negative → -0.2129404 / negative | +0.5000000 / positive → +0.5000000 / positive | +0.2188271 → +0.1707269 | -0.0807076 → -0.1211970 | False → False |
