# Constitutional trace preservation and runtime investigation

This records the completed lightweight investigation, not an aggregate suite pass.
Acceptance was subsequently revised: full Domain-round-trip minimization is stopped,
and the long journeys are manual replay/reference/performance artifacts. Runtime
growth is deferred to a separate performance effort. Core proves focused API
invariants; fresh natural substrates will validate the emergent whole lifecycle.

## Golden sources

`tests/Replay/Golden/` retains the complete natural HTTP captures, losslessly
compressed with XZ, including every input and full before/after response. Its
manifest records compressed and uncompressed SHA-256 hashes. The separate fixed
scripts in `tests/Replay/Fixtures/` retain deterministic replay expectations.
Neither natural capture has been edited during minimization.

The solo capture has 600 contributions; the coordinated capture has three entries
and 630 contributions. Their operational round trip is **Settlement → Emergence →
Settlement**. Origin bootstrap precedes it; that bootstrap is not substituted for
a complete working Horizon. Local fixed replay results are 728.51 seconds and
820.69 seconds respectively. These are richer replay/soak sources. Both tests and their unchanged fixtures now
live under `tests/Replay/`, outside mandatory discovery, by explicit acceptance
change. No minimal replacement or further minimization is required.

## Measured runtime shape

At application/test commit `dafc167d66406e8dfbac02db1aa0a2e0a4411174`, replayed
only the first 205 fixed solo steps (entry plus 204 contributions), using PHP 8.5.10
and in-memory SQLite. There were 412 HTTP requests including bootstrap, substrate,
participant creation and entry. DB listeners counted SQL and sampled application
query stacks every 100 queries. Temporary `hrtime`/`finally` wrappers measured seven
service methods; the application files were restored byte-for-byte afterward.
There were no calculator replacements, state shortcuts or performance changes.

| Consecutive HTTP requests | Request wall time | SQL queries | SQL time | Lineage calculation | Context construction | Input-plan build | Potential maintenance | Closure local components |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 1–100 | 10.10s | 30,612 | 1.53s | 1.45s | 1.84s | 3.28s | 1.23s | 0.24s |
| 101–200 | 15.07s | 43,090 | 2.46s | 4.78s | 2.98s | 3.70s | 2.58s | 0.77s |
| 201–300 | 20.33s | 52,034 | 3.07s | 8.98s | 3.41s | 4.38s | 4.51s | 1.44s |

Service times are **inclusive and overlap**; do not add them or interpret them as
independent percentages. Action mixes differ between windows, so this demonstrates
observed journey growth, not an asymptotic complexity proof. All 412 requests total
75.52s wall, 189,795 queries and 11.14s SQL execution. The test passed 20,820
assertions in 76.14s. The same prefix with SQL sampling but without method wrappers
passed in 75.25s, indicating the wrappers did not cause the observed growth.

The dominant growing measured operation is repeated **lineage/observable
recalculation**, shared by direct proposition measurement and Potential projection.
It rose 6.2× between the first and third windows, while overall request time rose
2.0×. Lineage calls rose from 327 to 847; recalculation of an individual proposition
was invoked 29 times in each of those windows, but its inclusive time rose from
1.14s to 5.02s. Attestation timeline reconstruction within these calculations rose
from 0.57s/932 calls to 3.26s/2,046 calls. This is growing repeated historical work,
not just an increasing count of contributions or test assertions.

Code paths explain the shape: `PropositionMeasurementService::recalculate` invokes
observable measurement; `LineageMeasurementService::calculate` visits ancestral
positions and computes local observables. Without a shared participation context,
those calculations query lineage, evidence timelines, reinforcement and phase
history repeatedly. Potential maintenance also recalculates active nodes in dirty
Horizons, with a context-local cache. Context and plan construction grow too, but
Closure alone is not the dominant measured cost. The scalar `ProjectionService`
field calculation is only arithmetic; it should not be confused with the broader
Potential projection maintenance.

These measurements are **not PostgreSQL profiling**. Higher per-query database
cost is a plausible amplifier there, but the relative PostgreSQL CPU/SQL split has
not been measured. No broad performance refactor is proposed in this checkpoint.
The exact window measurements and source hashes are in
`docs/evidence/constitutional-runtime.json`.

## Historical causal reduction investigation (stopped)

Whole excursions cannot be deleted solely because their new proposition IDs are
unused later. Attestations, receipt loads, spentness and actual phase touches affect
later eligibility and Closure. One coarse deletion candidate failed the published
action prerequisite at original step 26. A second candidate that removed only
original steps 29–31 replayed through original step 148, then failed the next
published action prerequisite. Both are rejected investigative candidates; neither
changed the canonical script or production semantics.

The golden trace also explains why simply truncating after an earlier Bias is not
a round trip: at original Order step 243, effective attestation and receipt exist,
but Depth is -0.2573768 and Cadence is +0.4825139. Commit is absent from the public
action schema. The eventual step-600 Commit selected an earlier Bias advertised
as eligible by that historical implementation, while the current Gate's own
commitment summary was still inadmissible. This records what the trace did; it
does not establish causal authority for that selection. Under
[Invariant 17](core-invariants.md#17-lineage-constrained-participant-movement),
regional Homeostatic eligibility cannot authorise a sideways move. Each
Phase-advancing operation must be structurally authorised from the current Gate;
Order Traverse requires its direct Commit edge. The trace's conformity to that
restriction is not certified by these runtime measurements. Preserve the raw
historical capture without treating an advertised action as semantic authority.

No shortened sequence was accepted. The subsequent acceptance decision explicitly
stopped this work. The full natural captures and fixed scripts remain unchanged;
focused production-path invariants replace the full round trip as mandatory
constitutional acceptance. The runtime measurements above remain a separate
performance backlog item. No performance refactor or timeout increase is included.
