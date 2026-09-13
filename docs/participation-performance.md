# Participation-plan performance

`InputPlanService` builds one explicit `ParticipationContext` per calculation.
The context is passed through option selection, region resolution, evidence,
lineage comparison, local measurements, Closure previews and serialization.
It is never stored in the container, a static property, Redis or application
cache. The existing static JSON-schema cache is unchanged.

## What caused the multiplication

Both participation endpoints and machine contribution validation reach the same
plan builder. Previously, candidate filtering, schema construction and rendering
repeatedly discovered overlapping data:

- Commit Region ancestry and membership CTEs, followed by repeated seed hydration
  and fixed-polarity lookups;
- ledger-prefix Attestation replay, superseding Attestations and reinforcing
  Receipts;
- current/previous Horizon membership and recursive lineage queries;
- attenuation over ancestor positions, each repeating local calculations;
- Phase touch counts and Proposition → Phase → Cycle → Participant loads;
- repeated Closure previews and Write/Attestation option calculations;
- separate ORM instances of the same Proposition during serialization.

The supplied log contains **14,283 statements, 5,824.23 ms cumulative SQL time,
and a 0.32 ms median**. It has overlapping request activity and no request IDs or
bindings, so it cannot establish a precise isolated-request count. The reported
~13,130-query request is the production symptom, not an after-change benchmark.

## Calculation scope and semantics

The context bulk-loads the current substrate's graph, Horizon memberships,
region frames, evidence, relevant ledger entries, snapshots and grouped Phase
touch counts. Parent/child adjacency, Attestations by Proposition and Receipts
by subject are indexed in memory. Region resolution, history sources, timelines,
raw local measurements and Closure previews are reused within that calculation.

The existing calculators, attenuation weights, register equivalence, hard/soft
edge rules and ledger-prefix arithmetic remain authoritative. In particular,
Commit-only source IDs are distinct from structural ancestry; region traversal
stops at Commits; original Attestation IDs and polarity survive inheritance;
and numeric zero does not reset retained direction. Scoped and unscoped service
paths share the same formulas. Unscoped calls remain available to mutation and
projection workflows without retaining state between operations.

Rendering uses shared Proposition objects. Missing/obsolete projections are
still repaired and persisted; their locks, direction updates and upserts are
preserved. Those necessary cold-projection writes explain most of the remaining
cold/warm query difference.

Standalone PostgreSQL plans can repair projections, so they acquire the Substrate
lock before loading graph, evidence and rendering state under READ COMMITTED.
Cooperating mutations cannot interleave with that context. Serialization/deadlock conflicts
are retried up to three times by Laravel. An existing contribution transaction
retains its own isolation and locks, and every new plan calculation inside it
receives a fresh context after writes.

## Reproducing the benchmark

```bash
php artisan test --filter ParticipationPerformanceTest
php artisan test --filter ParticipationSnapshotTest
```

The fixture has 43 Propositions, three participants, multiple Cycles/Phases,
two Horizons, multiple Commit Regions (including negative polarity), branching,
Revise/Reconcile/Commit edges, Attestations, reinforcing Receipts and committed
Closure history. Each late phase runs the unbatched calculation in a savepoint,
rolls back its projection repairs, then runs the optimized calculation against
the same rows. Frozen time and distinct creation timestamps make exact payload
comparison deterministic. The unbatched query counts were also verified against
the unchanged `5b9b5ff3dda172340939a59a4dc7b88e877a78a0` source tree.

Local PHP 8.5.10 / SQLite measurements (cold projections):

| Phase | Before queries | After queries | Reduction | Before wall ms | After wall ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| Mediate | 13,855 | 210 | 98.48% | 2,311.96 | 131.98 |
| Bias | 13,992 | 196 | 98.60% | 2,236.74 | 122.77 |
| Order / Closure | 44,536 | 194 | 99.56% | 7,811.89 | 189.73 |

SQL execution time fell from 482.06/471.53/1,626.00 ms to
9.20/8.26/8.96 ms respectively. These are fixture measurements, not predicted
production latency. PostgreSQL CI emits its own counts and timings.

Growing the fixture from 27 to 51 Propositions leaves warm counts unchanged:
54 (Mediate), 48 (Bias), 46 (Order). Cold growth is bounded by newly repaired
projections. Regression limits are <500 cold queries, <200 warm queries,
<=125 additional cold queries and <=20 additional warm queries for that growth.

The tests compare the entire serialized plan and schema, covering Gate, Read,
Gate/Write/Revision options, actions, Attestation choices, commitment eligibility,
measurements and provenance. Additional comparisons cover retained directions,
region membership, history source IDs and touch counts. A fresh-context test
replaces evidence between calculations. The PostgreSQL-only test interleaves
a committed evidence write from a second connection with plan construction.

## Index review and limits

No indexes are added. Existing indexes cover Supersession source/target access,
Attestation subjects and replacements, Receipt polymorphic subjects and
signatures, ledger subjects and substrate sequence, phase gates/cycles, and
participant/cycle ownership. Local `EXPLAIN QUERY PLAN` confirmed indexed
Supersession and Receipt lookups and grouped gate-count access. Horizon
membership loading currently scans its table: it has a Proposition primary key
but no Horizon-leading index. This small bulk-read fixture does not justify a
new PostgreSQL index without representative larger-table plans.

Hydration is bounded to one substrate, not to the whole installation, but its
memory footprint grows with that substrate's graph and evidence history. This
change removes query multiplication; it does not claim constant memory or CPU
for indefinitely growing substrates. A future narrower hydration strategy must
retain all ancestry, historical population removals and original evidence
needed by the same calculations.
