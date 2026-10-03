# Boundary-beat propagation

Only the endpoint is carried; parent interval lists below are audit provenance, never samples. Endpoint is frozen at child birth.

| Parent | Own acts at birth | Parent intervals (audit only) | Child | Endpoint event order | First child own act order | First interval belonging to child | First local samples |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Origin | 1 | [] | D1 | 2 | [6] | 3 | [3] |
| D1 | 1 | [3] | P1 | 6 | [19] | 10 | [10] |
| P1 | 1 | [10] | Q1 | 19 | [20] | 0 | [0] |

## Every birth in the preserved archive

Later entries are read-only archival findings, not candidate lifecycle results. The first immediate predecessor with no own beat in the primary archive is D2→P2 at c11, after this run stops at c5.

| Birth | Parent | Child | Parent own act orders | Beat availability | Scope |
| --- | --- | --- | --- | --- | --- |
| prefix:0 | Origin | D1 | [2] | own beat exists | runtime reached |
| prefix:3 | D1 | P1 | [6] | own beat exists | runtime reached |
| continuation:1 | P1 | Q1 | [19] | own beat exists | runtime reached |
| continuation:6 | Q1 | S1 | [20,22] | own beat exists | baseline audit only |
| continuation:10 | Origin | D2 | [2] | own beat exists | baseline audit only |
| continuation:11 | D2 | P2 | [] | MISSING OWN BEAT | baseline audit only |
| continuation:15 | P2 | Q2 | [26] | own beat exists | baseline audit only |
| continuation:17 | Q2 | S2 | [27] | own beat exists | baseline audit only |
| continuation:18 | S2 | R | [] | MISSING OWN BEAT | baseline audit only |
| continuation:18 | S1 | R | [23] | own beat exists | baseline audit only |
| continuation:35 | Origin | D3 | [2] | own beat exists | baseline audit only |
| continuation:36 | D3 | P3 | [] | MISSING OWN BEAT | baseline audit only |
| continuation:40 | P3 | Q3 | [39] | own beat exists | baseline audit only |
| continuation:42 | Q3 | S3 | [40] | own beat exists | baseline audit only |
| continuation:43 | S3 | R2 | [] | MISSING OWN BEAT | baseline audit only |
| continuation:43 | S1 | R2 | [23] | own beat exists | baseline audit only |
| continuation:51 | S3 | R3 | [46] | own beat exists | baseline audit only |
| continuation:51 | S2 | R3 | [34] | own beat exists | baseline audit only |
| continuation:77 | Origin | D4 | [2] | own beat exists | baseline audit only |
| continuation:78 | D4 | P4 | [] | MISSING OWN BEAT | baseline audit only |
