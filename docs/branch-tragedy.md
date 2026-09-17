# Exhaustion, persistent Committals, and Origin Tragedy

Pruning is an observer concern. Exhaustion is a structural state of the substrate.
No proposition, supersession, receipt, attestation, snapshot, or ledger entry is
removed to make recovery possible. `origin_branches` remains a causal membership
index, not an active/history switch.

## Explicit state and exploration

`exhaustion_states` contains one immutable transition per proposition identity:

* `excursion`: the expressive path has exhausted all its continuation routes.
* `basin`: the **outgoing possibilities** of a Committal have exhausted. The
  Committal itself remains a persistent historical and recovery boundary.

The transition records the exact terminal Phase, Participant via that Phase,
observed field version, timestamp, child identities, and reason. New propositions
and new Horizons start without any exhaustion state. Zero measured Potential
never creates a transition.

A validated terminal Rest supplies an exploration witness. An expressive leaf
must have completed evidence and an effective Attestation before that witness can
close it. A fresh Write arriving at Order before another Participant grounds or
attests it is still unexplored; its author's local Rest cannot close it. An
internal node closes only when **all** its direct children have closed. A
Committal child counts only when its outgoing basin closes. These local rules
propagate through every parent of a reconciliation, and stop at an open sibling.
This is an explicit structural criterion, not a claim that hypothetical future
Participants could never imagine new statements. New work uses remaining open
routes or a new Origin-rooted lineage.

The Domain Horizon opened by a Committal receives `exhausted_at` only when that
Committal's outgoing basin closes. A non-boundary Commit still has its own basin
state, without inventing a Domain Horizon. The original Origin never exhausts.

## Recovery and Potential

Recovery starts in the completed Cycle's Horizon and reads the maintained local
terrain. Its reachable descendant frontiers retain their existing precedence;
peer entries rank by observer-attenuated Potential, Charge, distance, and ID.
Fresh open basins remain eligible at their honest numeric Potential, including
zero. An empty numeric frontier cannot authorize an upward step. Explicit basin
closure permits moving through its opening Commit to the immediate parent
Horizon, where remaining sibling routes compete. Recovery stops at the first
available frontier. It never assembles a substrate-wide candidate pool.

Potential remains the maximum available reduction in absolute Tension before the
next Committal. Exhausting the strongest route exposes the next strongest route;
values are never added as fuel. Exhausted routes contribute no operational
Potential. Full historical inspection reads the same maintained field as the live
view, rather than recomputing a competing historical score.

## Origin-only Tragedy

Only exhaustion propagated to a direct expressive child of the genuine Origin
records Tragedy. The event is unique per root and records `semantics: exhaustion-v1`,
the terminal witness, opening time, field version, transition identities, and
exhausted Horizons. Memberships and immutable ledger history support later runtime,
participation, Commit, receipt, depth, and Domain-crossing analyses.

A local Rest, an exhausted leaf, and an exhausted child Horizon are not Tragedy.
The obsolete “at least one Commit plus zero Potential” shortcut is removed.
A fully explored uncommitted lineage can exhaust; mere freshness cannot.

## Concurrency, publication, and cost

The proof runs before acquiring the shared write lock. It queries direct children
and parents along the affected ancestry; open siblings stop propagation. Under
the existing Substrate lock, publication validates field version, Phase, position,
Participant, and completed Rest. Unique transition and Tragedy keys make repeat
publication harmless. A concurrent sibling write changes the field version and
invalidates the whole proof before any exhaustion is published.

The guarded write only inserts the prepared transitions and updates affected
Horizon/lineage metadata. The existing incremental projection worklist rebuilds
affected regions and propagates changed summaries afterward. Ordinary state reads
never run an exhaustion proof or repair topology. Cost grows with affected local
ancestry and sibling fanout, rather than unrelated substrate history.

Other Participants retain their exact saved Cycle, Phase, and position. On their
next normal action boundary, exhausted structure offers Rest; their own lifecycle
performs recovery. No bulk participant updates occur.

## Participant context and observers

Plans expose `rest_available` for local action exhaustion. `exhausted_alternatives`
contains alternatives at the current structural Read/Gate/Write position,
with short statements, producing-phase identity and explicit `actionable: false`.
All local scars remain visible independently of the broader browsing limit;
historical descendant subtrees are not serialized. See the
[local context contract](participant-context.md#local-exhausted-context).
These objects never enter action target enums. A Committal can expose
`basin_exhausted: true` while its own `exhausted` remains false.

Graph/API modes are presentation choices:

* `view=active` (default): operationally available structure.
* `view=exhausted`: live structure plus its immediate historical alternatives.
* `view=history` (or legacy `history=1`): full historical visibility, within the
  existing 500-card response limit. Direct fetch by ID always remains available.

Changing modes cannot write or change eligibility, Potential, or recovery.

Realtime keeps the shared transport, reconnect ownership, subscription catch-up,
and fetch-by-ID queue. Exhaustion publishes proposition IDs, Horizon invalidation,
and field invalidation after commit. Participant position updates still use the
existing graph-participant event. Projection publication also refreshes metrics.
The history observer cache is invalidated by the same coalesced stream; no graph,
proof, statement, or historical subtree is broadcast.

The real WebSocket smoke test exposed a pre-existing dependency mismatch:
RFC6455 v0.4.1 passes an integer header to strict Guzzle PSR-7. Composer's
post-autoload hook applies the single-line [upstream fix](https://github.com/ratchetphp/RFC6455/commit/a4c6bb885a87be3dd93e8201c0487752b140ab42)
idempotently, guarded to that version. Remove `scripts/patch-rfc6455.php` and its
hook when upgrading to a release containing the fix. Install with Composer
scripts enabled; no transport/reconnect behavior is replaced.

## Deployment and legacy data

1. Save a database backup and pause participant workers/wakes for the deployment.
2. Deploy and run `php artisan migrate --force`.
3. Run `php artisan commons:potential-rebuild <substrate-id>` for each retained
   substrate before resuming workers. The migration also records durable dirty
   regions for the regular maintenance sweep.
4. Run the diagnostics below, then restart workers with the new code.

Migration preserves `pruned_at` verbatim and copies it to `legacy_retired_at`.
These old decisions remain explicit historical holds; they are **not** asserted
to be proven exhaustion and are not resurrected. Old Tragedy evidence is unchanged;
absence of `semantics: exhaustion-v1` identifies the old record format. Normal
eligibility uses the hold, never `pruned_at`. New transitions never write the old
column. The old schema can be removed in a separate, deliberate migration after
historical consumers are updated. Do not use code-only/migration rollback after
new exhaustion transitions: restore the coordinated pre-deployment backup instead.

For a restored legacy database, the explicit dry-run/import command is:

```
php artisan commons:exhaustion-legacy-import <substrate-id>
php artisan commons:exhaustion-legacy-import <substrate-id> --apply
```

It only preserves missing legacy holds; it never repairs history by inference.

## Diagnostics and the next live run

```
php artisan commons:recovery-inspect <participant-id>
php artisan commons:exhaustion-audit <substrate-id>
php artisan commons:potential-inspect <participant-id> --verify
```

Recovery inspection is read-only and reports position, Horizon, parent, local
historical alternatives, lineage identities, candidate scores, visited basins,
field version, and prospective Origin Tragedy. The slow exhaustion audit rebuilds
closure from recorded terminal Rest witnesses and immutable graph topology,
checks basin/lineage/Tragedy projections, and exits nonzero on disagreement. It is
for diagnostics/tests, never participation, UI, or wake recovery. The numeric
reference compares the same recovery semantics using the full historical graph.

Watch fresh zero-Potential child Horizons stay open, fresh ungrounded Writes
remain available for shared grounding, exhausted alternatives disappear only from
live rendering, siblings preserve their parent basin, and avatars move after each
participant's own Rest. Tragedy should appear only when the whole direct Origin
lineage closes. Retained legacy substrates remain held; start a fresh substrate
to observe clean exhaustion semantics.
