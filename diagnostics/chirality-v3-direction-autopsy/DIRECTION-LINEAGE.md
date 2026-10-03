# Direction persistence lineage

Post-action snapshots from the exact replay. `prefix:n` and `continuation:n` are zero-based. Local component numbers are not separately attenuated. The raw JSON also records every measurement/history SQL write, including intermediate writes within an action. Changes below omit identical repeat writes.

| Boundary | Proposition / producing Phase | Table | Previous O / AC directions | Current local O / direction | Current local AC / direction | Intervals | Maturity | Attenuated Chirality |
|---|---|---|---|---|---|---|---|---|
| origin_and_entry | 01m40kf4jc3vym5cnmfn2hvajh / 7 | chirality_observations | none / none | 0.0000000 / negative | -1.0000000 / negative | [] | constitutional_origin | -0.5000000 |
| origin_and_entry | 01m40kf4jc3vym5cnmfn2hvajh / 7 | proposition_measurements | none / none | 0.0000000 / negative | -1.0000000 / negative | [] | constitutional_origin | -0.5000000 |
| origin_and_entry | 01m40kf4jc3vym5cnmfn2hvajh / 7 | commitment_snapshots | none / none | 0.0000000 / negative | -1.0000000 / negative | [] | constitutional_origin | -0.5000000 |
| prefix:0 | 01m40kf6mah3shn5ajwe987jz3 / 1 | chirality_observations | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | -0.1000000 |
| prefix:0 | 01m40kf6mah3shn5ajwe987jz3 / 1 | proposition_measurements | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | -0.1000000 |
| prefix:2 | 01m40kf6mah3shn5ajwe987jz3 / 1 | chirality_observations | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1000000 |
| prefix:2 | 01m40kf6mah3shn5ajwe987jz3 / 1 | proposition_measurements | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1000000 |
| prefix:3 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | chirality_observations | none / none | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1887755 |
| prefix:3 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | proposition_measurements | none / none | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1887755 |
| prefix:32 | 01m40kf6mah3shn5ajwe987jz3 / 1 | chirality_observations | positive / negative | 0.5000000 / positive | 1.0000000 / positive | [5, 5] | formation_cv | 0.5000000 |
| prefix:32 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | chirality_observations | positive / negative | 0.5000000 / positive | 1.0000000 / positive | [5, 5] | formation_cv | 0.6479592 |
| prefix:32 | 01m40kf6mah3shn5ajwe987jz3 / 1 | proposition_measurements | positive / negative | 0.5000000 / positive | 1.0000000 / positive | [5, 5] | formation_cv | 0.5000000 |
| prefix:32 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | proposition_measurements | positive / negative | 0.5000000 / positive | 1.0000000 / positive | [5, 5] | formation_cv | 0.6479592 |
| continuation:0 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | chirality_observations | positive / positive | 0.5000000 / positive | 0.1715729 / positive | [5, 5, 0] | formation_cv | 0.3436391 |
| continuation:0 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | proposition_measurements | positive / positive | 0.5000000 / positive | 0.1715729 / positive | [5, 5, 0] | formation_cv | 0.3436391 |
| continuation:1 | 01m40kh042nb9gvm89ndr88r86 / 3 | chirality_observations | none / none | 0.5000000 / positive | 0.1715729 / positive | [5, 5, 0] | formation_cv | 0.3314223 |
| continuation:1 | 01m40kh042nb9gvm89ndr88r86 / 3 | proposition_measurements | none / none | 0.5000000 / positive | 0.1715729 / positive | [5, 5, 0] | formation_cv | 0.3314223 |
| continuation:3 | 01m40kh042nb9gvm89ndr88r86 / 3 | chirality_observations | positive / positive | 0.5000000 / positive | 0.0000000 / positive | [5, 5, 0, 0] | formation_cv | 0.2711625 |
| continuation:3 | 01m40kh042nb9gvm89ndr88r86 / 3 | proposition_measurements | positive / positive | 0.5000000 / positive | 0.0000000 / positive | [5, 5, 0, 0] | formation_cv | 0.2711625 |
| continuation:4 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | chirality_observations | positive / positive | 0.5000000 / positive | 0.0000000 / positive | [5, 5, 0, 0] | formation_cv | 0.2806122 |
| continuation:4 | 01m40kh042nb9gvm89ndr88r86 / 3 | chirality_observations | positive / positive | 0.5000000 / positive | -0.1010205 / negative | [5, 5, 0, 0, 0] | formation_cv | 0.2206172 |
| continuation:4 | 01m40kh042nb9gvm89ndr88r86 / 3 | proposition_measurements | positive / positive | 0.5000000 / positive | -0.1010205 / negative | [5, 5, 0, 0, 0] | formation_cv | 0.2206172 |
| continuation:4 | 01m40kfaq9h2q3mct0jk90qe6m / 2 | proposition_measurements | positive / positive | 0.5000000 / positive | 0.0000000 / positive | [5, 5, 0, 0] | formation_cv | 0.2806122 |
| continuation:5 | 01m40kh042nb9gvm89ndr88r86 / 3 | chirality_observations | positive / negative | 0.5000000 / positive | -0.1715729 / negative | [5, 5, 0, 0, 0, 0] | formation_cv | 0.1958378 |
| continuation:5 | 01m40kh042nb9gvm89ndr88r86 / 3 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.1715729 / negative | [5, 5, 0, 0, 0, 0] | formation_cv | 0.1958378 |
| continuation:6 | 01m40khdqzm7r3ba0hanrr2y30 / 4 | chirality_observations | none / none | 0.5000000 / positive | -0.1715729 / negative | [5, 5, 0, 0, 0, 0] | formation_cv | 0.1775879 |
| continuation:6 | 01m40khdqzm7r3ba0hanrr2y30 / 4 | proposition_measurements | none / none | 0.5000000 / positive | -0.1715729 / negative | [5, 5, 0, 0, 0, 0] | formation_cv | 0.1775879 |
| continuation:8 | 01m40khdqzm7r3ba0hanrr2y30 / 4 | chirality_observations | positive / negative | 0.5000000 / positive | -0.2251482 / negative | [5, 5, 0, 0, 0, 0, 0] | formation_cv | 0.1592855 |
| continuation:8 | 01m40khdqzm7r3ba0hanrr2y30 / 4 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.2251482 / negative | [5, 5, 0, 0, 0, 0, 0] | formation_cv | 0.1592855 |
| continuation:10 | 01m40khv4yvhkckzr8nea8cnjg / 1 | chirality_observations | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | -0.1000000 |
| continuation:10 | 01m40khv4yvhkckzr8nea8cnjg / 1 | proposition_measurements | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | -0.1000000 |
| continuation:11 | 01m40khx1m19k4amty9zhefz7m / 2 | chirality_observations | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | -0.0408163 |
| continuation:11 | 01m40khx1m19k4amty9zhefz7m / 2 | proposition_measurements | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | -0.0408163 |
| continuation:14 | 01m40khx1m19k4amty9zhefz7m / 2 | chirality_observations | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1428571 |
| continuation:14 | 01m40khx1m19k4amty9zhefz7m / 2 | proposition_measurements | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1428571 |
| continuation:15 | 01m40kj73xhtabbx5egg24egnz / 3 | chirality_observations | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | 0.0219512 |
| continuation:15 | 01m40kj73xhtabbx5egg24egnz / 3 | proposition_measurements | none / none | 0.0000000 / negative | 0.0000000 / negative | [] | formation_immature | 0.0219512 |
| continuation:16 | 01m40kj73xhtabbx5egg24egnz / 3 | chirality_observations | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [0] | formation_immature | 0.1975610 |
| continuation:16 | 01m40kj73xhtabbx5egg24egnz / 3 | proposition_measurements | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [0] | formation_immature | 0.1975610 |
| continuation:17 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | chirality_observations | none / none | 0.5000000 / positive | 0.0000000 / negative | [0] | formation_immature | 0.2188271 |
| continuation:17 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | proposition_measurements | none / none | 0.5000000 / positive | 0.0000000 / negative | [0] | formation_immature | 0.2188271 |
| continuation:18 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | none / none | 0.5000000 / positive | -0.2129404 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0] | formation_cv | 0.1707269 |
| continuation:18 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | none / none | 0.5000000 / positive | -0.2129404 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0] | formation_cv | 0.1707269 |
| continuation:19 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | positive / negative | 0.5000000 / positive | -0.2476430 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0] | formation_cv | 0.1618516 |
| continuation:19 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.2476430 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0] | formation_cv | 0.1618516 |
| continuation:21 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | positive / negative | 0.5000000 / positive | -0.2773958 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0] | formation_cv | 0.1542422 |
| continuation:21 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.2773958 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0] | formation_cv | 0.1542422 |
| continuation:23 | 01m40khv4yvhkckzr8nea8cnjg / 1 | chirality_observations | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1000000 |
| continuation:23 | 01m40khx1m19k4amty9zhefz7m / 2 | chirality_observations | positive / negative | 0.5000000 / positive | 0.0000000 / negative | [1] | formation_immature | 0.1887755 |
| continuation:23 | 01m40kj73xhtabbx5egg24egnz / 3 | chirality_observations | positive / negative | 0.5000000 / positive | 0.0000000 / negative | [0, 1] | formation_cv | 0.2170732 |
| continuation:23 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | chirality_observations | positive / negative | 0.5000000 / positive | 0.0000000 / negative | [0, 1] | formation_cv | 0.2295028 |
| continuation:23 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | positive / negative | 0.5000000 / positive | -0.2604922 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1] | formation_cv | 0.1636804 |
| continuation:23 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | proposition_measurements | positive / negative | 0.5000000 / positive | 0.0000000 / negative | [0, 1] | formation_cv | 0.2295028 |
| continuation:23 | 01m40khv4yvhkckzr8nea8cnjg / 1 | proposition_measurements | negative / negative | 0.5000000 / positive | 0.0000000 / negative | [] | formation_immature | 0.1000000 |
| continuation:23 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.2604922 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1] | formation_cv | 0.1636804 |
| continuation:23 | 01m40kj73xhtabbx5egg24egnz / 3 | proposition_measurements | positive / negative | 0.5000000 / positive | 0.0000000 / negative | [0, 1] | formation_cv | 0.2170732 |
| continuation:23 | 01m40khx1m19k4amty9zhefz7m / 2 | proposition_measurements | positive / negative | 0.5000000 / positive | 0.0000000 / negative | [1] | formation_immature | 0.1887755 |
| continuation:24 | 01m40kj73xhtabbx5egg24egnz / 3 | chirality_observations | positive / negative | 0.5000000 / positive | -0.1715729 / negative | [0, 1, 0] | formation_cv | 0.1568134 |
| continuation:24 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | chirality_observations | positive / negative | 0.5000000 / positive | -0.1715729 / negative | [0, 1, 0] | formation_cv | 0.1562366 |
| continuation:24 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | positive / negative | 0.5000000 / positive | -0.2850591 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0] | formation_cv | 0.1415516 |
| continuation:24 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.1715729 / negative | [0, 1, 0] | formation_cv | 0.1562366 |
| continuation:24 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.2850591 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0] | formation_cv | 0.1415516 |
| continuation:24 | 01m40kj73xhtabbx5egg24egnz / 3 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.1715729 / negative | [0, 1, 0] | formation_cv | 0.1568134 |
| continuation:25 | 01m40kj73xhtabbx5egg24egnz / 3 | chirality_observations | positive / negative | 0.5000000 / positive | -0.2679492 / negative | [0, 1, 0, 0] | formation_cv | 0.1229642 |
| continuation:25 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | chirality_observations | positive / negative | 0.5000000 / positive | -0.2679492 / negative | [0, 1, 0, 0] | formation_cv | 0.1150815 |
| continuation:25 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | positive / negative | 0.5000000 / positive | -0.3069495 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0, 0] | formation_cv | 0.1270521 |
| continuation:25 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.2679492 / negative | [0, 1, 0, 0] | formation_cv | 0.1150815 |
| continuation:25 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.3069495 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0, 0] | formation_cv | 0.1270521 |
| continuation:25 | 01m40kj73xhtabbx5egg24egnz / 3 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.2679492 / negative | [0, 1, 0, 0] | formation_cv | 0.1229642 |
| continuation:26 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | chirality_observations | positive / negative | 0.5000000 / positive | -0.3333333 / negative | [0, 1, 0, 0, 0] | formation_cv | 0.0927449 |
| continuation:26 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | positive / negative | 0.5000000 / positive | -0.3266429 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0, 0, 0] | formation_cv | 0.1178349 |
| continuation:26 | 01m40kjd8ws6gtn57pk7ve7y9s / 4 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.3333333 / negative | [0, 1, 0, 0, 0] | formation_cv | 0.0927449 |
| continuation:26 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.3266429 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0, 0, 0] | formation_cv | 0.1178349 |
| continuation:27 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | chirality_observations | positive / negative | 0.5000000 / positive | -0.3445034 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0, 0, 0, 0] | formation_cv | 0.1132670 |
| continuation:27 | 01m40kjgsebr7tx5fqer62sbg0 / 5 | proposition_measurements | positive / negative | 0.5000000 / positive | -0.3445034 / negative | [5, 5, 0, 0, 0, 0, 0, 2, 0, 0, 0, 1, 0, 0, 0, 0] | formation_cv | 0.1132670 |
