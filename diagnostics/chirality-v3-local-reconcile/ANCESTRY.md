# R: both parents remain in real attenuation

Groups use the production lineage positions/distances and weights. Shared ancestors contribute exactly once at their production distance. Adding the full overlapping parent lineages independently would double-count the shared group.

| Ancestor | Distance | Weight | Local Chirality | Group | Normalized contribution |
| --- | --- | --- | --- | --- | --- |
| S1 | 1 | 0.2500000 | 0.2500000 | other_only | 0.0319693 |
| S2 | 1 | 0.2500000 | 0.0000000 | local_only | 0.0000000 |
| Q1 | 2 | 0.1111111 | 0.7500000 | other_only | 0.0426257 |
| Q2 | 2 | 0.1111111 | 0.2500000 | local_only | 0.0142086 |
| P1 | 3 | 0.0625000 | 0.2500000 | other_only | 0.0079923 |
| P2 | 3 | 0.0625000 | 0.2500000 | local_only | 0.0079923 |
| D1 | 4 | 0.0400000 | 0.5713072 | other_only | 0.0116891 |
| D2 | 4 | 0.0400000 | 0.0000000 | local_only | 0.0000000 |
| Origin | 5 | 0.0277778 | -0.5000000 | shared_once | -0.0071043 |

| Group | Normalized contribution |
| --- | --- |
| local_only | 0.0222009 |
| other_only | 0.0942765 |
| shared_once | -0.0071043 |

Total ancestor contribution: 0.1093732. R local contribution: 0.0000000. Field Chirality: 0.1093732.
