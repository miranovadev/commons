# Non-invasive Proposition observation

The graph index (up to 500 nodes) and individual Proposition GET only serialize
persisted projections. Neither calls `repairMissing`, calculates observables,
updates retained Correspondence direction, queues repair, nor takes write locks.
Reverb-driven graph/detail refreshes use these same paths.

`observables.status` describes the loaded projection:

- `missing`: no projection exists; values and calculated_at are null.
- `stale`: calculator_version is null (invalidated) or obsolete; return the last
  persisted values and calculated_at, without presenting them as current.
- `current`: calculator_version matches the current calculator.

Graph observation is eventually consistent, not an atomic snapshot across its
relationship queries. This is display data, not commitment admissibility input.
Missing/stale values can remain until semantic evaluation or explicit maintenance.

## Repair and authoritative evaluation

Run `php artisan commons:measurements-repair SUBSTRATE_SLUG --limit=100` after a
calculator upgrade or when backfilling projections. Each invocation inspects at
most 100 candidates by default (1–500 accepted); repeat as needed. Only measurable
missing/obsolete projections are selected. Unmeasurable nodes remain unmeasured.
Current projections and immutable commitment snapshots are untouched.

Each candidate uses its own transaction: lock Substrate, reload projection state,
recheck freshness, calculate, then project. The existing Substrate → Proposition
locks and atomic retained-direction/projection write remain in force. Another
repair/contribution can finish while a candidate waits; freshness is rechecked
after acquiring the root. Repairs publish the usual minimal Proposition update
after commit. This command is deliberately not scheduled or invoked from GET.

Contributions, cycle entry, instruments, Phase-touch invalidation, and calculator
versioning retain their existing semantics. Input plans and correspondence/potential
selection still repair the projections needed for authoritative decisions. All
remaining production repairMissing callers belong to these paths or maintenance.
Standalone input plans now acquire the Substrate root before building their
calculation context under READ COMMITTED; a waiting plan sees the preceding
writer's committed state. Existing contribution transactions retain ownership.
The root serializes plans with cooperating mutations, so a plan cannot mix their
intermediate states. Plan requests can still wait behind participants; graph and
Proposition detail requests do not join that serialization queue.

## Failure mechanism and performance

Previously each graph GET could run repairMissing → recalculateIfMeasurable →
recalculate → project for up to 500 nodes. Each repair could traverse observable
lineage and perform a locking transaction, retained-direction update and upsert.
Phase touches invalidate substrate projections, so a busy graph repeatedly
triggered this expensive path. Committal attestation-count history traversal also
issued queries per node; history loading is now batched by Commit-chain depth.

The reported Proposition FOR UPDATE matches project(). PostgreSQL can reject that
lock with SQLSTATE 40001 if a REPEATABLE READ snapshot predates a concurrent row
update. InputPlanService explicitly used that isolation while permitting repair;
ordinary graph GET did not itself set isolation. Without a production request
trace/session isolation, the log alone cannot prove which caller raised it.
This change removes the graph/detail write path and fixes the known standalone
plan snapshot/write conflict. It does not claim every possible 40001 is eliminated.

Regression coverage checks projection/direction immutability and SQL purity on
both GETs for missing, invalidated, obsolete and current projections; bounded
maintenance; 1 versus 500 Committal-node query counts; independent PostgreSQL GETs
while a measurement writer holds both root and node locks; a plan waiting behind
a measurement writer; and coherent plans while another writer attempts the root.
Existing contribution, reinforcement, invalidation and hysteresis tests remain.
