# Causal Context Horizons

Context Domain belongs to a causal Horizon lineage. A Substrate can contain concurrent Cycles in different historical Horizons, and multiple open branches. Advancing a branch never aborts another Participant's Cycle.

Horizon ancestry is not a Participant movement map. A Participant progresses
through explicit causal continuations of its current Gate or recovers along
its recorded ancestry backward. Same-Horizon or same-Commit-Region membership,
visibility and Homeostatic calculation scope never create traversal edges.
No edge means no movement.

## Ownership and vocabulary

`cycles.context_horizon_id` is a required foreign key. Cycle creation resolves a Horizon in the same Substrate and saves that association once. Eloquent guards and PostgreSQL/SQLite triggers reject ownership changes, including bulk SQL updates. Horizon parent, Domain and opening boundary are also immutable through the model.

Potential selects a Committal; that Committal determines entry context. `forEntry()` returns the direct child explicitly opened by a boundary Committal, or the creating Cycle's own Horizon for a non-boundary Committal. It validates the immutable snapshot direction and direct parent/opener relationship. Missing boundary projection is an error. It never searches open descendants or uses Horizon sequence to reinterpret an old Gate.

For recovery, that Committal must first be found on the Participant's actual
backward lineage. Ordinary completion retains its exact causal Committal;
initial entry without a position uses its separate selection contract. Resolving
a selected Committal's Horizon never authorises a jump to another Proposition.

Causal backtracking may enter a historical closed Horizon. `closed_at` remains a record of its first boundary; entry does not clear it, rewrite ownership, or reopen a global Domain. `currentFor()` supplies only the initial/default Horizon when there is no selected Gate. Operational entry always supplies the selected Committal's explicit Horizon.

The Cycle's Horizon supplies initial and subsequent Phase/register vocabulary, participant Domain/workspace state, prospective Commit context and actual Commitment snapshot Domain. Every Phase Gate must belong to that Horizon or be the Committal that directly opened it. Model and direct contribution guards reject incompatible Gates and Domain vocabulary; new Propositions validate their creating Phase and register. Phase-to-Cycle and Proposition-to-Phase ownership are immutable through Eloquent and PostgreSQL/SQLite triggers, alongside the existing Cycle guard. A completed Cycle still reports its own Horizon; the next Cycle resolves its own entry region.

Proposition membership follows:

`Proposition → creating Phase → Cycle → Context Horizon`

The membership table is a disposable projection of that relation. A late Stance/Concord write by an H2 Settlement Cycle belongs to H2 even after H3 Emergence opens. Graph queries and realtime notifications use that membership, and graph/workspace Horizon metadata exposes parent and opening Commit IDs without changing presentation.

## Boundaries and branches

`context_horizons.parent_context_horizon_id` identifies the immediate causal parent. There is one root Emergence Horizon per Substrate. Each child flips Domain relative to its parent and has a unique `opened_by_proposition_id` referencing its boundary snapshot.

The boundary-producing Commit belongs to the closing parent. The child references that Commit as its opener. The parent's `closed_at` / `closed_by_proposition_id` record the first boundary. Later boundary Commits from continuing or backtracked parent Cycles can open additional children, each with its own opening Commit. They do not overwrite the first closing marker or alter an existing child.

For example, H2 Settlement can have H3a and H3b Emergence children. H4a Settlement points to H3a, and H4b Settlement points to H3b. `sequence` remains a unique, Substrate-local display/order number; it is never used to infer ancestry or Domain parity. Applying the same boundary twice returns the same child.

`commons:horizons-rebuild` rebuilds memberships from durable Cycle ownership and replays missing boundary projections idempotently. It preserves Horizon IDs, Cycle ownership and immutable snapshots, and marks disposable measurements stale. It no longer deletes Horizon records and invents replacement identities.

## Calculations and history

Both database-backed and hydrated participant calculations resolve the previous Horizon by the explicit parent ID. H4a compares only with H3a, even when H3b is newer or a cross-branch Proposition edge exists. Existing Proposition-lineage extraction within those two regions remains unchanged; the opening Commit supplies parent provenance when there is no direct local edge into the parent.

Prior Tension is the opening boundary snapshot's Tension in the immediate parent, never the newest Substrate snapshot. Closure hysteresis first retains the newest evaluated Proposition ancestor inside the current Horizon; otherwise it uses that Horizon's own opening boundary snapshot in its direct parent. This preserves within-region hysteresis while excluding sibling and more distant Domain regions from transition history. Nullable legacy boundary components retain the existing negative-direction defaults, with that specific boundary still identified in history metadata.

Only the root Horizon uses the established Origin bootstrap measurements and zero prior Tension. A non-root missing its parent or parent provenance is an error, not a reason to search other Domains or fall back to bootstrap. Following each parent's own relation preserves the same rule recursively to Origin. Measurement attenuation, Reinforcement and raw Potential calculations are unchanged. Rest recovery derives Domain scope from this topology and selects the nearest eligible non-exhausted ancestral Committal; see [participant continuity](participant-continuity-and-relocation.md).

Rest recovery follows recorded backward edges, not shortest paths through the
Horizon tree. Only exhaustion permits crossing the current Horizon's opening
boundary, which must lie on that same path. Recovery retains the original
distance origin across previous Horizons and never resets to a provenance node
or searches siblings or descendants.

## Upgrade from the former global projection

Previously, membership, snapshot Domain, prospective Commit context and participant/workspace Domain used the open Horizon; comparisons used `sequence - 1`; prior Tension used the latest Substrate Commit; progression inferred vocabulary from the Gate. These assumptions failed as soon as older Cycles continued after a transition.

The additive migration preserves existing Horizon IDs and snapshots. Since old Cycles have no stored owner, it uses a one-time legacy inference from the first Phase's entry time/vocabulary and its Gate's opening boundary, with the Origin Cycle identified by the first ledger Proposition. Later contribution timestamps never choose the owner. It repairs memberships from the inferred Cycle owner, derives each parent from its opening Commit's creating Cycle, and rebuilds the first-closing summaries from child relationships. Disposable measurements become stale under the new calculator version.

Ambiguous entry history or incompatible causal boundaries abort the PostgreSQL transactional migration with the affected Cycle/Horizon ID. The migration deliberately does not guess a newest Horizon or rewrite immutable snapshot directions/Tension to make inconsistent history fit. Such data needs inspection before retrying. Deploy with participant workers paused during the schema/backfill change, then restart workers with the new code. The existing measurement repair command can refresh stale projections; ordinary graph reads remain read-only.

## Auditing previously persisted invalid entries

`php artisan commons:causal-audit [substrate-slug]` is read-only. It reports Phase/Cycle/Gate IDs for incompatible Horizon relationships and propositions or phases with incompatible Domain vocabulary, returning a failure exit code when any are found. The new identity migration adds guards without reassigning existing Cycles or rewriting ledger history.

The 15 September production dump contains 13 invalid Phase 1 entries: Origin directly opens H2 Settlement, but those Cycles selected H3 Emergence and wrote Activity. A membership rebuild cannot repair that history because membership already agrees with each persisted Cycle. Review affected historical Cycles separately; do not relabel their Domains or change immutable snapshots to conceal the bad entry. Direct contribution checks reject an invalid current Phase. Existing valid later Phases retain their recorded Cycle ownership; subsequent entry uses the corrected causal resolver.
