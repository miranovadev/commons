# Actual birth-time anchor propagation

The final row includes an attempted real Attestation that rolled back when CV was undefined. No downstream field value was produced.

| Parent | Completed intervals at birth | Parent own acts | Child | Anchor | Endpoint order | Child own act orders at first vote | First successor | Samples | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Origin | [] | 1 | D1 | None | None | [6] | None | [] | formation_immature |
| D1 | [] | 1 | P1 | None | None | [19] | None | [] | formation_immature |
| P1 | [] | 1 | Q1 | None | None | [20] | None | [] | formation_immature |
| Q1 | [0] | 2 | S1 | 0 | 22 | [23] | 0 | [0,0] | undefined_zero_mean |

## Full preserved history: parent observations at each birth

These are complete-reset archive observations, not an operational anchor continuation. They establish that most births precede any completed own interval. Multi-parent Reconcile requires an explicit predecessor-selection rule. No missing anchor is retroactively filled from later parent observations.

| Birth | Parent | Child | Parent own intervals then | Parent own act orders then | Evidence scope |
| --- | --- | --- | --- | --- | --- |
| prefix:0 | Origin | D1 | [] | [2] | runtime candidate reached |
| prefix:3 | D1 | P1 | [] | [6] | runtime candidate reached |
| continuation:1 | P1 | Q1 | [] | [19] | runtime candidate reached |
| continuation:6 | Q1 | S1 | [0] | [20,22] | runtime candidate reached |
| continuation:10 | Origin | D2 | [] | [2] | baseline audit only; candidate stopped |
| continuation:11 | D2 | P2 | [] | [] | baseline audit only; candidate stopped |
| continuation:15 | P2 | Q2 | [] | [26] | baseline audit only; candidate stopped |
| continuation:17 | Q2 | S2 | [] | [27] | baseline audit only; candidate stopped |
| continuation:18 | S2 | R | [] | [] | baseline audit only; candidate stopped |
| continuation:18 | S1 | R | [] | [23] | baseline audit only; candidate stopped |
| continuation:35 | Origin | D3 | [] | [2] | baseline audit only; candidate stopped |
| continuation:36 | D3 | P3 | [] | [] | baseline audit only; candidate stopped |
| continuation:40 | P3 | Q3 | [] | [39] | baseline audit only; candidate stopped |
| continuation:42 | Q3 | S3 | [] | [40] | baseline audit only; candidate stopped |
| continuation:43 | S3 | R2 | [] | [] | baseline audit only; candidate stopped |
| continuation:43 | S1 | R2 | [] | [23] | baseline audit only; candidate stopped |
| continuation:51 | S3 | R3 | [] | [46] | baseline audit only; candidate stopped |
| continuation:51 | S2 | R3 | [] | [34] | baseline audit only; candidate stopped |
| continuation:77 | Origin | D4 | [] | [2] | baseline audit only; candidate stopped |
| continuation:78 | D4 | P4 | [] | [] | baseline audit only; candidate stopped |
