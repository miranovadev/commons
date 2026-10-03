# Boundary-beat propagation

Only the endpoint is carried. Cursor provenance lists propositions traversed, never inherited Attestations or interval samples. Endpoint is captured at child birth; own events then advance the local cursor.

| Parent | Own acts at birth | Cursor provenance | Child | Endpoint event order | First child own act order | First interval belonging to child | First local samples |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Origin | 1 | immediate_predecessor via Origin | D1 | 2 | [6] | 3 | [3] |
| D1 | 1 | immediate_predecessor via D1 | P1 | 6 | [19] | 10 | [10] |
| P1 | 1 | immediate_predecessor via P1 | Q1 | 19 | [20] | 0 | [0] |
| Q1 | 2 | immediate_predecessor via Q1 | S1 | 22 | [23] | 0 | [0] |
| Origin | 1 | immediate_predecessor via Origin | D2 | 2 | none | none | empty |
| D2 | 0 | unattested_pass_through via Origin,D2 | P2 | 2 | [26] | 15 | [15] |
| P2 | 1 | immediate_predecessor via P2 | Q2 | 26 | [27] | 0 | [0] |
| Q2 | 1 | immediate_predecessor via Q2 | S2 | 27 | none | none | empty |
| S2 | 0 | unattested_pass_through via Q2,S2 | 01m41zxeb0jhz3d2h1myb689m6 | 27 | none | none | empty |

## Every birth in the preserved archive

Later entries are read-only archival findings, not candidate lifecycle results. The first immediate predecessor with no own beat in the primary archive is D2→P2 at c11, and this run now passes it by carrying the recorded cursor through unattested D2.

| Birth | Parent | Child | Parent own act orders | Beat availability | Scope |
| --- | --- | --- | --- | --- | --- |
| prefix:0 | Origin | D1 | [2] | own beat exists | runtime reached |
| prefix:3 | D1 | P1 | [6] | own beat exists | runtime reached |
| continuation:1 | P1 | Q1 | [19] | own beat exists | runtime reached |
| continuation:6 | Q1 | S1 | [20,22] | own beat exists | runtime reached |
| continuation:10 | Origin | D2 | [2] | own beat exists | runtime reached |
| continuation:11 | D2 | P2 | [] | no own beat: cursor propagation needed | runtime reached |
| continuation:15 | P2 | Q2 | [26] | own beat exists | runtime reached |
| continuation:17 | Q2 | S2 | [27] | own beat exists | runtime reached |
| continuation:18 | S2 | R | [] | no own beat: cursor propagation needed | baseline audit only |
| continuation:18 | S1 | R | [23] | own beat exists | baseline audit only |
| continuation:35 | Origin | D3 | [2] | own beat exists | baseline audit only |
| continuation:36 | D3 | P3 | [] | no own beat: cursor propagation needed | baseline audit only |
| continuation:40 | P3 | Q3 | [39] | own beat exists | baseline audit only |
| continuation:42 | Q3 | S3 | [40] | own beat exists | baseline audit only |
| continuation:43 | S3 | R2 | [] | no own beat: cursor propagation needed | baseline audit only |
| continuation:43 | S1 | R2 | [23] | own beat exists | baseline audit only |
| continuation:51 | S3 | R3 | [46] | own beat exists | baseline audit only |
| continuation:51 | S2 | R3 | [34] | own beat exists | baseline audit only |
| continuation:77 | Origin | D4 | [2] | own beat exists | baseline audit only |
| continuation:78 | D4 | P4 | [] | no own beat: cursor propagation needed | baseline audit only |
