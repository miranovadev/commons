# Stage 3: local exploration epochs and current spentness

This checkpoint follows Stage 2 at `ee464ef7f7c96566b3138c96027cc21ff64b2551`.
It corrects epoch identity and current spentness only. It does not change Variant
evidence/Potential prerequisites, recovery selection, Bias traversal visibility,
the Tragedy recovery override, or suite/Actions selection.

## Exact position identity

`ExplorationPositionService` owns the tuple:

`(context_horizon_id, producing phase kind, structural_source_id)`

No participant, Cycle, Phase occurrence, classification label, or global
Committal counter is added. The source is an immutable proposition ID, not an
inferred chronological predecessor.

| Provenance | Structural source |
| --- | --- |
| Variant | Actual incoming source edge; the generating Phase's Gate |
| Single-input output, including selected Reclassify | Actual incoming edge, even when the selected source differs from the Phase's incidental Gate |
| Revise, including nested revisions | Original output's position, recursively; Horizon and producing kind must remain unchanged |
| Multi-input Reconcile | Recorded producing Phase Gate, which must be one of its reconciliation inputs |
| Committal | No expressive exploration position |
| Missing/ambiguous expressive provenance | Unknown, not a guessed source; cannot publish a failure |

A revised output inherits its own original slot. When that revised proposition
is subsequently traversed as a structural Gate, its ID is the downstream slot's
actual source. Different source versions are not silently collapsed. Revision
cycles, mixed-parent revisions, and cross-position revision chains are rejected.
Cold database reads and hydrated participation reads use the same resolver.

## One current-spentness predicate

`ExplorationEpochService::currentFailures` resolves each failed proposition's
actual position, validates its Horizon and producing kind, then requires its
failure epoch to equal that exact slot's current counter. All spent annotations,
write/traverse/attestation exclusions, generation checks, and failure propagation
consume this authority rather than applying divergent partial predicates.

`continuation_failures` is unchanged. The immutable subject proposition already
determines its source; no duplicate source column or rewrite of historical rows
is necessary. A failure remains evidence after a local epoch advance, but is no
longer current eligibility suppression. Unknown or mismatched provenance cannot
be treated as current failure proof.

Only a successful Variant advances a counter, inside its existing contribution
transaction under the substrate lock. Rejected/rolled-back writes advance
nothing. Rest inspection captures each exact source and epoch; publication
revalidates them with the existing Phase/field-version checks under the lock.
Source metadata in that transient proof is not inserted into historical rows.

Full participation propositions and epoch-advanced events expose
`structural_source_id`. Compact inference packets retain their existing bounded
shape; their spentness and epoch values use the same authority. Memoization is
limited to one participation calculation. Batched candidate-scoped queries load
revision predecessors when needed, not the whole historical substrate.

## HTTP acceptance and honest red boundaries

The HTTP harness retains its database-write guard. Every participant, phase
transition, failure, Variant, and Commit in these journeys comes from production
HTTP actions. Only external evidence transport/DNS is substituted.

| Regression | Stage 2 baseline | Stage 3 acceptance |
| --- | --- | --- |
| Successful-route revisit reaches Bias but lacks forward Traverse | Red: only Read/Rest offered | Intentionally still red; later lifecycle work |
| Later Committal's Distinguish Variant advances/reopens Origin | Red: Origin epoch changes and spent option reopens | Origin phase/Gate, epoch, current spentness, and failure history remain unchanged |
| Variant at another Constrain Gate reopens first Disperse slot | Red: first spent option reopens | Independent slot remains spent at its unchanged epoch; history unchanged |
| Third Distinguish Variant after spending the existing alternatives | Additional Stage 3 acceptance | Three HTTP-created Variants; old options reopen only on their local advance; historical failures and every Disperse counter remain unchanged |

Exact local spentness changes the naturally available route after Commit. The
Bias regression now uses a second participant that entered Origin before the
first Commit to revisit that successful route. It still fails at the missing
Traverse assertion, not an action budget.

The stronger cross-Committal setup parks the original explorer only after its
Origin option is genuinely spent. Two participants already at Persist continue
using advertised actions, with effective Support of the fresh continuation,
to a later real Commit without authoring another Origin Variant. Its first
Distinguish Variant must not reopen the parked Origin option. Both scenarios
were rerun on unmodified Stage 2 application code, alongside the unchanged
Disperse scenario: all three reproduced their intended semantic failures.

The repeated-Variant journey supplies legal Refute attestations and real
Instrument/Receipt grounding for the reconciliation itself before natural Bias
Rest. It never fabricates Closure, Potential, failure records, epochs, or
intermediate participant positions. Before each further Origin Variant, every
existing option must be currently spent. After it, each old option's own epoch
increments and its old failure becomes historical. This tests reopening and
renewed creation beyond two alternatives without relaxing eligibility.

## Migration and rollout limits

`2026_09_19_000100_localize_exploration_positions.php` transactionally replaces
the live counter table with the three-part primary key and a source foreign key.
It derives known slots from actual proposition provenance and clones the old
coarse epoch label into each matching local slot. A missing counter remains zero.
It never updates or deletes a continuation failure.

Preserving labels retains existing failure applicability at cutover. It does
**not** reconstruct lost local chronology or retroactively undo false reopenings
that occurred before this change. Future Variant advances are independent. Any
separate historical-data correction needs an audited, explicitly authorized plan.

Ambiguous failed provenance aborts atomically, retaining the old table and all
evidence. Migration tests cover that rollback, byte-for-byte history retention,
shared initial labels, and subsequent independent advancement. A nonempty local
counter/failure history cannot be collapsed by `down()`; restore a pre-rollout
backup rather than discarding history.

Quiesce contributions/workers for coordinated schema and application rollout,
retain a backup, apply the complete integration, and restart workers so old
writers cannot use the new key. Follow the earlier projection-refresh rollout
requirements in [exploration-epochs.md](exploration-epochs.md) as applicable.
This review checkpoint does not deploy or modify production data.

## Validation and next boundary

Nine provenance/migration cases supplement the existing eligibility, stale
proof, transaction rollback, current-vs-historical failure, packet-budget, and
concurrency coverage. The eight HTTP cases stay in Core; the Bias failure is
neither skipped nor asserted as a success. The expected counts are Core 596,
Full Integration 598, Query 16, Load 22, and All PHP 636, including data providers.

The checkpoint PR records actual local and PostgreSQL Actions results. Core and
Full Integration are expected to be red only for Bias; any additional failure
must be reported and investigated. Query and Load must pass. No workflow budget
or coverage exclusion is changed to hide a failure.

The initial PostgreSQL Core and Full Integration runs reached the original
20-minute job ceiling before completing. Claire subsequently authorized a
30-minute ceiling for those two suites and an unabridged rerun. Query and Load
retain 20 minutes. No test, assertion, suite membership, SQL profiling setting,
or statement timeout changes accompany that budget adjustment. The checkpoint
PR records the completed rerun results and runtimes separately from the initial
incomplete runs.

Stop here for review. Later backward-lineage recovery work must include the
Tragedy `recovery_proposition_id` jump to the Horizon opener; it remains unchanged
and is not endorsed as a valid exception by this stage.
