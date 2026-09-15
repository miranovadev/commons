<?php

namespace App\Services\Participation;

use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\ReceiptContextEvent;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One calculation's read model. Never container-bound, static or persisted.
 * Graph/evidence hydration is batched; calculators retain ownership of semantics.
 * A new plan (including the response after a write) always gets a new instance.
 */
final class ParticipationContext
{
    public readonly Collection $propositions;

    public readonly Collection $edges;

    public readonly Collection $parents;

    public readonly Collection $children;

    public readonly Collection $horizons;

    public readonly Collection $memberships;

    public readonly Collection $frames;

    public readonly Collection $attestations;

    public readonly Collection $receipts;

    public readonly Collection $receiptLinks;

    public readonly Collection $classifications;

    public readonly Collection $reinforcements;

    public readonly Collection $receiptContexts;

    public readonly Collection $receiptContextEvents;

    public readonly Collection $touches;

    public readonly Collection $snapshots;

    public readonly Substrate $substrate;

    public readonly int $nextSequence;

    private array $memo = [];

    private readonly Collection $attestationsByProposition;

    private readonly Collection $receiptsBySubject;

    private readonly Collection $membershipsByHorizon;

    public function __construct(public readonly Phase $phase)
    {
        $phase->loadMissing('cycle.participant.substrate');
        $this->substrate = $phase->cycle->participant->substrate;
        $this->propositions = Proposition::query()
            ->whereIn('id', $this->workingSetIds())
            ->with(['phase.cycle.participant', 'measurement', 'ledgerEntry'])->get()->keyBy('id');
        foreach ($this->propositions as $proposition) {
            $proposition->phase->cycle->participant->setRelation('substrate', $this->substrate);
        }
        $ids = $this->propositions->keys();
        // Include outgoing hard edges even when their target is outside this substrate.
        $this->edges = Supersession::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->whereIn('source_proposition_id', $ids)->where('type', '!=', 'variant'))
            ->orWhereIn('target_proposition_id', $ids))
            ->with('ledgerEntry')->get();
        $this->parents = $this->edges->groupBy('target_proposition_id');
        $this->children = $this->edges->groupBy('source_proposition_id');
        $this->horizons = ContextHorizon::where('substrate_id', $this->substrate->id)->get()->keyBy('id');
        foreach ($this->horizons as $horizon) {
            $horizon->setRelation('substrate', $this->substrate);
        }
        foreach ($this->horizons as $horizon) {
            $horizon->setRelation('parent', $this->horizons->get($horizon->parent_context_horizon_id));
        }
        $phase->cycle->setRelation('contextHorizon', $this->horizons->get($phase->cycle->context_horizon_id));
        foreach ($this->propositions as $proposition) {
            $proposition->phase->cycle->setRelation('contextHorizon', $this->horizons->get($proposition->phase->cycle->context_horizon_id));
        }
        $this->memberships = ContextHorizonMembership::whereIn('proposition_id', $ids)->get()->keyBy('proposition_id');
        $this->membershipsByHorizon = $this->memberships->groupBy('context_horizon_id');
        $this->frames = DB::table('commit_region_polarities')->whereIn('proposition_id', $ids)->get()->keyBy('proposition_id');
        $this->attestations = Attestation::whereIn('proposition_id', $ids)
            ->with(['ledgerEntry', 'supersededBy.ledgerEntry', 'phase.cycle'])->get()->keyBy('id');
        $this->attestationsByProposition = $this->attestations->groupBy('proposition_id');
        foreach ($this->attestations as $attestation) {
            $attestation->setRelation('proposition', $this->propositions[$attestation->proposition_id]);
        }
        $this->classifications = DB::table('reclassification_results')->whereIn('source_proposition_id', $ids)->get();
        $this->receiptLinks = DB::table('proposition_receipt_links')->whereIn('proposition_id', $ids)->get();
        $this->receipts = Receipt::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('receiptable_type', (new Proposition)->getMorphClass())->whereIn('receiptable_id', $ids))
            ->orWhere(fn ($q) => $q->where('receiptable_type', (new Attestation)->getMorphClass())->whereIn('receiptable_id', $this->attestations->keys())))
            ->with(['ledgerEntry', 'instrumentExecution'])->get();
        $this->receiptContexts = ParticipantReceiptContext::whereIn('proposition_id', $ids)->get();
        $this->reinforcements = $this->receiptContexts->whereNull('cleared_at');
        $this->receiptContextEvents = ReceiptContextEvent::whereIn('participant_receipt_context_id', $this->receiptContexts->pluck('id'))->with('ledgerEntry')->get();
        $this->receiptsBySubject = $this->receipts->groupBy(fn ($receipt) => $receipt->receiptable_type.':'.$receipt->receiptable_id);
        $this->touches = Phase::query()->whereIn('gate_proposition_id', $ids)
            ->whereNotNull('gate_proposition_id')->selectRaw('gate_proposition_id, count(*) as aggregate')
            ->groupBy('gate_proposition_id')->pluck('aggregate', 'gate_proposition_id');
        $this->snapshots = CommitmentSnapshot::whereIn('proposition_id', $ids)
            ->with('ledgerEntry')->get();
        $this->nextSequence = (int) SubstrateLedgerEntry::where('substrate_id', $this->substrate->id)->max('sequence') + 1;
    }

    /**
     * The live Horizon plus its causal ancestors and comparison boundaries.
     * Ownership seeds also retain legacy/unledgered candidates; membership still
     * separately decides whether a proposition can be measured.
     * Parent traversal is complete (including Commit-only inherited history).
     * Outgoing edges are loaded separately so excluded hard successors still retire sources.
     */
    private function workingSetIds(): Collection
    {
        return collect(DB::select(<<<'SQL'
            WITH RECURSIVE dependencies (id, parent_id) AS (
                SELECT target_proposition_id, source_proposition_id FROM supersessions
                UNION ALL
                SELECT propositions.id, context_horizons.opened_by_proposition_id
                FROM propositions
                JOIN phases ON phases.id = propositions.phase_id
                JOIN cycles ON cycles.id = phases.cycle_id
                JOIN context_horizons ON context_horizons.id = cycles.context_horizon_id
                WHERE context_horizons.opened_by_proposition_id IS NOT NULL
            ), relevant (id) AS (
                SELECT propositions.id FROM propositions
                JOIN phases ON phases.id = propositions.phase_id
                JOIN cycles ON cycles.id = phases.cycle_id
                WHERE cycles.context_horizon_id = ?
                UNION
                SELECT CAST(? AS VARCHAR(26))
                UNION
                SELECT dependencies.parent_id FROM dependencies
                JOIN relevant ON relevant.id = dependencies.id
                JOIN propositions ON propositions.id = dependencies.parent_id
                JOIN phases ON phases.id = propositions.phase_id
                JOIN cycles ON cycles.id = phases.cycle_id
                JOIN participants ON participants.id = cycles.participant_id
                WHERE participants.substrate_id = ?
            )
            SELECT propositions.id FROM propositions
            JOIN relevant ON relevant.id = propositions.id
            JOIN phases ON phases.id = propositions.phase_id
            JOIN cycles ON cycles.id = phases.cycle_id
            JOIN participants ON participants.id = cycles.participant_id
            WHERE participants.substrate_id = ?
            SQL, [$this->phase->cycle->context_horizon_id, $this->phase->gate_proposition_id,
            $this->substrate->id, $this->substrate->id]))->pluck('id');
    }

    public function remember(string $key, callable $calculate): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $calculate();
        }

        return $this->memo[$key];
    }

    /** Reuse authoritative hydrated objects, including observation-local measurement fallbacks. */
    public function proposition(Proposition $position): Proposition
    {
        return $position->exists ? ($this->propositions->get($position->id) ?? $position) : $position;
    }

    public function nodes(iterable $ids): Collection
    {
        $result = collect();
        foreach ($ids as $id) {
            if ($node = $this->propositions->get($id)) {
                $result->put($id, $node);
            }
        }

        return $result->values();
    }

    public function parentNodes(Proposition $position, bool $commitOnly = false): Collection
    {
        if (! $position->exists) {
            return $commitOnly ? $position->commitSources : $position->parents;
        }
        $edges = $this->parents->get($position->id, collect());

        return $this->nodes(($commitOnly ? $edges->where('type', 'commit') : $edges)->pluck('source_proposition_id'));
    }

    public function sourceIds(Proposition $position): Collection
    {
        return $this->remember('sources:'.$position->id, function () use ($position) {
            $seen = [];
            $queue = [$position];
            while ($queue !== []) {
                $node = array_shift($queue);
                if (isset($seen[$node->id])) {
                    continue;
                }
                $seen[$node->id] = true;
                if ($node->tier === 'committal') {
                    foreach ($this->parentNodes($node, true) as $parent) {
                        $queue[] = $parent;
                    }
                }
            }

            return collect(array_keys($seen));
        });
    }

    /** Same UNION traversal as the ancestry CTE, stopping on each path's first Commit. */
    public function seed(Proposition $position): ?Proposition
    {
        return $this->remember('seed:'.$position->id, function () use ($position) {
            $seen = $seeds = [];
            $queue = [$this->proposition($position)];
            while ($queue !== []) {
                $node = array_shift($queue);
                if (isset($seen[$node->id])) {
                    continue;
                }
                $seen[$node->id] = true;
                if ($node->tier === 'committal') {
                    $seeds[$node->id] = $node;
                } elseif ($node->tier === 'expressive') {
                    foreach ($this->parentNodes($node) as $parent) {
                        $queue[] = $parent;
                    }
                }
            }

            return count($seeds) === 1 ? reset($seeds) : null;
        });
    }

    public function members(Proposition $seed): Collection
    {
        return $this->remember('members:'.$seed->id, function () use ($seed) {
            $seen = [];
            $queue = [$seed->id];
            $members = collect();
            while ($queue !== []) {
                $id = array_shift($queue);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                foreach ($this->children->get($id, collect()) as $edge) {
                    $child = $this->propositions->get($edge->target_proposition_id);
                    if ($child?->tier !== 'expressive') {
                        continue;
                    }
                    $queue[] = $child->id;
                    if ($this->seed($child)?->id === $seed->id) {
                        $members->put($child->id, $child);
                    }
                }
            }

            return $members->sortBy('id')->values();
        });
    }

    public function active(Collection $nodes): Collection
    {
        return $nodes->filter(fn ($node) => $this->children->get($node->id, collect())->where('type', '!=', 'variant')->isEmpty())->values();
    }

    public function effectiveAttestations(iterable $sourceIds): Collection
    {
        return $this->attestationsFor($sourceIds)
            ->filter(fn ($a) => $a->supersededBy === null)->values();
    }

    public function attestationsFor(iterable $ids): Collection
    {
        return collect($ids)->unique()->flatMap(fn ($id) => $this->attestationsByProposition->get($id, collect()))->values();
    }

    public function receiptsFor(string $type, iterable $ids): Collection
    {
        return collect($ids)->unique()->flatMap(fn ($id) => $this->receiptsBySubject->get($type.':'.$id, collect()))->values();
    }

    public function horizonMemberIds(string $horizonId): Collection
    {
        return $this->membershipsByHorizon->get($horizonId, collect())->pluck('proposition_id');
    }

    /** Horizon membership is a traversal boundary, not a global ancestor union. */
    public function lineageIds(string $horizonId, array $gateIds): Collection
    {
        sort($gateIds);

        return $this->remember('lineage:'.$horizonId.':'.implode(',', $gateIds), function () use ($horizonId, $gateIds) {
            $seen = [];
            $queue = $gateIds;
            while ($queue !== []) {
                $id = array_shift($queue);
                if (isset($seen[$id]) || $this->memberships->get($id)?->context_horizon_id !== $horizonId) {
                    continue;
                }
                $seen[$id] = true;
                foreach ($this->parents->get($id, collect()) as $edge) {
                    $queue[] = $edge->source_proposition_id;
                }
            }

            return collect(array_keys($seen));
        });
    }

    public function history(Proposition $position, ContextHorizon $horizon): ?CommitmentSnapshot
    {
        return $this->remember('history:'.$horizon->id.':'.$position->id, function () use ($position, $horizon) {
            $seen = [];
            $queue = [$position->id];
            while ($queue !== []) {
                $id = array_shift($queue);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                foreach ($this->parents->get($id, collect()) as $edge) {
                    $queue[] = $edge->source_proposition_id;
                }
            }

            $local = $this->snapshots->whereIn('proposition_id', array_keys($seen))
                ->whereIn('proposition_id', $this->horizonMemberIds($horizon->id))
                ->whereNotNull('depth_direction')->whereNotNull('cadence_direction')
                ->sortByDesc(fn ($snapshot) => $snapshot->ledgerEntry->sequence)->first();
            if ($local !== null || $horizon->parent_context_horizon_id === null) {
                return $local;
            }
            $boundary = $this->snapshots->firstWhere('proposition_id', $horizon->opened_by_proposition_id);
            if ($boundary === null || $this->memberships->get($boundary->proposition_id)?->context_horizon_id !== $horizon->parent_context_horizon_id) {
                throw new \DomainException('A Horizon requires its own parent boundary commitment.');
            }

            return $boundary;
        });
    }
}
