<?php

namespace App\Services\Participation;

use App\Models\{Attestation, CommitmentSnapshot, ContextHorizon, ContextHorizonMembership, Phase, Proposition, Receipt, Substrate, SubstrateLedgerEntry, Supersession};
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
            ->whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $this->substrate->id))
            ->with(['phase.cycle.participant', 'measurement', 'ledgerEntry'])->get()->keyBy('id');
        foreach ($this->propositions as $proposition) {
            $proposition->phase->cycle->participant->setRelation('substrate', $this->substrate);
        }
        $ids = $this->propositions->keys();
        // Include outgoing hard edges even when their target is outside this substrate.
        $this->edges = Supersession::query()->where(fn ($q) => $q
            ->whereIn('source_proposition_id', $ids)->orWhereIn('target_proposition_id', $ids))
            ->with('ledgerEntry')->get();
        $this->parents = $this->edges->groupBy('target_proposition_id');
        $this->children = $this->edges->groupBy('source_proposition_id');
        $this->horizons = ContextHorizon::where('substrate_id', $this->substrate->id)->get()->keyBy('id');
        foreach ($this->horizons as $horizon) {
            $horizon->setRelation('substrate', $this->substrate);
        }
        $this->memberships = ContextHorizonMembership::whereIn('context_horizon_id', $this->horizons->keys())->get()->keyBy('proposition_id');
        $this->membershipsByHorizon = $this->memberships->groupBy('context_horizon_id');
        $this->frames = DB::table('commit_region_polarities')->whereIn('proposition_id', $ids)->get()->keyBy('proposition_id');
        $this->attestations = Attestation::whereIn('proposition_id', $ids)
            ->with(['ledgerEntry', 'supersededBy.ledgerEntry', 'phase.cycle'])->get()->keyBy('id');
        $this->attestationsByProposition = $this->attestations->groupBy('proposition_id');
        foreach ($this->attestations as $attestation) {
            $attestation->setRelation('proposition', $this->propositions[$attestation->proposition_id]);
        }
        $this->receipts = Receipt::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('receiptable_type', (new Proposition)->getMorphClass())->whereIn('receiptable_id', $ids))
            ->orWhere(fn ($q) => $q->where('receiptable_type', (new Attestation)->getMorphClass())->whereIn('receiptable_id', $this->attestations->keys())))
            ->with(['ledgerEntry', 'instrumentExecution'])->get();
        $this->receiptsBySubject = $this->receipts->groupBy(fn ($receipt) => $receipt->receiptable_type.':'.$receipt->receiptable_id);
        $this->touches = Phase::query()->whereHas('cycle.participant', fn ($q) => $q->where('substrate_id', $this->substrate->id))
            ->whereNotNull('gate_proposition_id')->selectRaw('gate_proposition_id, count(*) as aggregate')
            ->groupBy('gate_proposition_id')->pluck('aggregate', 'gate_proposition_id');
        $this->snapshots = CommitmentSnapshot::where('substrate_id', $this->substrate->id)
            ->with('ledgerEntry')->get();
        $this->nextSequence = (int) SubstrateLedgerEntry::where('substrate_id', $this->substrate->id)->max('sequence') + 1;
    }

    public function remember(string $key, callable $calculate): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $calculate();
        }
        return $this->memo[$key];
    }

    /** Reuse authoritative hydrated objects, including freshly repaired measurements. */
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
                if (isset($seen[$node->id])) continue;
                $seen[$node->id] = true;
                if ($node->tier === 'committal') {
                    foreach ($this->parentNodes($node, true) as $parent) $queue[] = $parent;
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
                if (isset($seen[$node->id])) continue;
                $seen[$node->id] = true;
                if ($node->tier === 'committal') $seeds[$node->id] = $node;
                elseif ($node->tier === 'expressive') {
                    foreach ($this->parentNodes($node) as $parent) $queue[] = $parent;
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
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                foreach ($this->children->get($id, collect()) as $edge) {
                    $child = $this->propositions->get($edge->target_proposition_id);
                    if ($child?->tier !== 'expressive') continue;
                    $queue[] = $child->id;
                    if ($this->seed($child)?->id === $seed->id) $members->put($child->id, $child);
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

    public function reinforcementReceipts(string $type, iterable $ids): Collection
    {
        return $this->receiptsFor($type, $ids)
            ->where('type', '!=', 'citation')->whereNotNull('signature')->values();
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
                if (isset($seen[$id]) || $this->memberships->get($id)?->context_horizon_id !== $horizonId) continue;
                $seen[$id] = true;
                foreach ($this->parents->get($id, collect()) as $edge) $queue[] = $edge->source_proposition_id;
            }
            return collect(array_keys($seen));
        });
    }

    public function history(Proposition $position): ?CommitmentSnapshot
    {
        return $this->remember('history:'.$position->id, function () use ($position) {
            $seen = [];
            $queue = [$position->id];
            while ($queue !== []) {
                $id = array_shift($queue);
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                foreach ($this->parents->get($id, collect()) as $edge) $queue[] = $edge->source_proposition_id;
            }
            return $this->snapshots->whereIn('proposition_id', array_keys($seen))
                ->whereNotNull('depth_direction')->whereNotNull('cadence_direction')
                ->sortByDesc(fn ($snapshot) => $snapshot->ledgerEntry->sequence)->first();
        });
    }
}
