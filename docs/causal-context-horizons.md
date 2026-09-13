# Causal Context Horizons

Context Domain belongs to a causal Horizon lineage. A Substrate can contain concurrent Cycles in different historical Horizons, and multiple open branches. Advancing a branch never aborts another Participant's Cycle.

## Ownership and vocabulary

`cycles.context_horizon_id` is a required foreign key. Cycle creation resolves an open Horizon in the same Substrate and saves that association once. Eloquent guards and PostgreSQL/SQLite triggers reject ownership changes, including bulk SQL updates. Horizon parent, Domain and opening boundary are also immutable through the model.

Operational entry keeps the existing Potential/Gate selection. It starts from the Horizon opened by the selected Gate, or the Gate's owning Horizon when it did not open one. It chooses an open descendant within that causal subtree (latest sequence as a deterministic entry tie-break). Closed regions cannot accept new Cycles. `currentFor()` remains only the default for initial/unpositioned Cycle creation; it is not a global Substrate Domain.

The Cycle's Horizon supplies initial and subsequent Phase/register vocabulary, participant Domain/workspace state, prospective Commit context and actual Commitment snapshot Domain. Selecting an older Gate cannot change the Cycle's vocabulary. A completed Cycle still reports its own Horizon; the next Cycle resolves its own entry region.

Proposition membership follows:

`Proposition → creating Phase → Cycle → Context Horizon`

The membership table is a disposable projection of that relation. A late Stance/Concord write by an H2 Settlement Cycle belongs to H2 even after H3 Emergence opens. Graph queries and realtime notifications use that membership, and graph/workspace Horizon metadata exposes parent and opening Commit IDs without changing presentation.

## Boundaries and branches

`context_horizons.parent_context_horizon_id` identifies the immediate causal parent. There is one root Emergence Horizon per Substrate. Each child flips Domain relative to its parent and has a unique `opened_by_proposition_id` referencing its boundary snapshot.

The boundary-producing Commit belongs to the closing parent. The child references that Commit as its opener. The parent's `closed_at` / `closed_by_proposition_id` record the first boundary that stopped new entry. Later boundary Commits from already-running parent Cycles can open additional children, each with its own opening Commit. They do not overwrite the first closing marker or alter an existing child.

For example, H2 Settlement can have H3a and H3b Emergence children. H4a Settlement points to H3a, and H4b Settlement points to H3b. `sequence` remains a unique, Substrate-local display/order number; it is never used to infer ancestry or Domain parity. Applying the same boundary twice returns the same child.

`commons:horizons-rebuild` rebuilds memberships from durable Cycle ownership and replays missing boundary projections idempotently. It preserves Horizon IDs, Cycle ownership and immutable snapshots, and marks disposable measurements stale. It no longer deletes Horizon records and invents replacement identities.

## Calculations and history

Both database-backed and hydrated participant calculations resolve the previous Horizon by the explicit parent ID. H4a compares only with H3a, even when H3b is newer or a cross-branch Proposition edge exists. Existing Proposition-lineage extraction within those two regions remains unchanged; the opening Commit supplies parent provenance when there is no direct local edge into the parent.

Prior Tension is the opening boundary snapshot's Tension in the immediate parent, never the newest Substrate snapshot. Closure hysteresis first retains the newest evaluated Proposition ancestor inside the current Horizon; otherwise it uses that Horizon's own opening boundary snapshot in its direct parent. This preserves within-region hysteresis while excluding sibling and more distant Domain regions from transition history. Nullable legacy boundary components retain the existing negative-direction defaults, with that specific boundary still identified in history metadata.

Only the root Horizon uses the established Origin bootstrap measurements and zero prior Tension. A non-root missing its parent or parent provenance is an error, not a reason to search other Domains or fall back to bootstrap. Following each parent's own relation preserves the same rule recursively to Origin. Attenuation, Reinforcement, Potential scoring and Read/Gate candidate selection are unchanged.

## Upgrade from the former global projection

Previously, membership, snapshot Domain, prospective Commit context and participant/workspace Domain used the open Horizon; comparisons used `sequence - 1`; prior Tension used the latest Substrate Commit; progression inferred vocabulary from the Gate. These assumptions failed as soon as older Cycles continued after a transition.

The additive migration preserves existing Horizon IDs and snapshots. Since old Cycles have no stored owner, it uses a one-time legacy inference from the first Phase's entry time/vocabulary and its Gate's opening boundary, with the Origin Cycle identified by the first ledger Proposition. Later contribution timestamps never choose the owner. It repairs memberships from the inferred Cycle owner, derives each parent from its opening Commit's creating Cycle, and rebuilds the first-closing summaries from child relationships. Disposable measurements become stale under the new calculator version.

Ambiguous entry history or incompatible causal boundaries abort the PostgreSQL transactional migration with the affected Cycle/Horizon ID. The migration deliberately does not guess a newest Horizon or rewrite immutable snapshot directions/Tension to make inconsistent history fit. Such data needs inspection before retrying. Deploy with participant workers paused during the schema/backfill change, then restart workers with the new code. The existing measurement repair command can refresh stale projections; ordinary graph reads remain read-only.
