<?php

namespace App\Services\Participation;

use App\Enums\PhaseKind;
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
use App\Services\CommitRegionService;
use App\Services\ExhaustionService;
use App\Services\HomologousBiasService;
use App\Services\HomologousMediationService;
use App\Services\HomologousOrderService;
use App\Services\MediatedIncomingBiasService;
use App\Services\Observables\CommittalLineageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One calculation's read model. Never container-bound, static or persisted.
 * Graph/evidence hydration is batched; calculators retain ownership of semantics.
 * A new plan (including the response after a write) always gets a new instance.
 * This is the operational/evidence working set, not all participant cognition.
 * ParticipantHistoricalContext separately projects local exhausted alternatives
 * without hydrating their descendants, evidence, or action eligibility here.
 */
final class ParticipationContext
{
    public readonly Collection $propositions;

    public readonly Collection $activeIds;

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

    public function __construct(public readonly Phase $phase, ?Collection $additionalPropositionIds = null, bool $includeUnavailableAdditional = false)
    {
        $phase->loadMissing('cycle.participant.substrate');
        $this->substrate = $phase->cycle->participant->substrate;
        $this->propositions = Proposition::query()
            ->whereIn('id', $this->workingSetIds($additionalPropositionIds, $includeUnavailableAdditional))
            ->with(['phase.cycle.participant', 'measurement', 'ledgerEntry'])->get()->keyBy('id');
        foreach ($this->propositions as $proposition) {
            $proposition->phase->cycle->participant->setRelation('substrate', $this->substrate);
        }
        $ids = $this->propositions->keys();
        $this->activeIds = Proposition::active()->whereKey($ids)->pluck('id')->flip();
        // Include outgoing hard edges even when their target is outside this substrate.
        $this->edges = Supersession::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->whereIn('source_proposition_id', $ids)->where('type', '!=', 'variant'))
            ->orWhereIn('target_proposition_id', $ids))
            ->with('ledgerEntry')->get();
        $this->parents = $this->edges->groupBy('target_proposition_id');
        $this->children = $this->edges->groupBy('source_proposition_id');
        $horizonIds = $this->propositions->toBase()->map(fn ($node) => $node->phase->cycle->context_horizon_id)
            ->push($phase->cycle->context_horizon_id)->unique();
        $this->horizons = ContextHorizon::where('substrate_id', $this->substrate->id)->whereKey($horizonIds)->get()->keyBy('id');
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
            ->orWhere(fn ($q) => $q->where('receiptable_type', (new Attestation)->getMorphClass())->whereIn('receiptable_id', $this->attestations->keys()))
            ->orWhereIn('id', $this->receiptLinks->pluck('receipt_id')))
            ->with(['ledgerEntry', 'instrumentExecution'])->get();
        // Historical load subjects need not match their Receipt's observed subject.
        // Resolve type in the existing batch even when that Receipt lies outside this graph.
        $this->receiptContexts = ParticipantReceiptContext::whereIn('proposition_id', $ids)
            ->withExists(['receipt as ordinary_evidence' => fn ($q) => $q->whereNotIn('type', Receipt::MEMORY_TYPES)])->get();
        $this->reinforcements = $this->receiptContexts->whereNull('cleared_at')->where('ordinary_evidence', true);
        $this->receiptContextEvents = ReceiptContextEvent::whereIn('participant_receipt_context_id', $this->receiptContexts->pluck('id'))->with('ledgerEntry')->get();
        $this->receiptsBySubject = $this->receipts->groupBy(fn ($receipt) => $receipt->receiptable_type.':'.$receipt->receiptable_id);
        $this->touches = Phase::query()->whereIn('gate_proposition_id', $ids)
            ->whereNotNull('gate_proposition_id')->selectRaw('gate_proposition_id, count(*) as aggregate')
            ->groupBy('gate_proposition_id')->pluck('aggregate', 'gate_proposition_id');
        $this->snapshots = CommitmentSnapshot::whereIn('proposition_id', $ids)
            ->with('ledgerEntry')->get();
        // Resolve ownership during hydration; contextual reads remain query-free.
        $this->seedIds();
        $this->nextSequence = (int) SubstrateLedgerEntry::where('substrate_id', $this->substrate->id)->max('sequence') + 1;
    }

    /**
     * The live Horizon plus its causal ancestors and comparison boundaries.
     * Ownership seeds also retain legacy/unledgered candidates; membership still
     * separately decides whether a proposition can be measured.
     * Parent traversal is complete (including Commit-only inherited history).
     * Outgoing edges are loaded separately so excluded hard successors still retire sources.
     */
    private function workingSetIds(?Collection $additionalPropositionIds = null, bool $includeUnavailableAdditional = false): Collection
    {
        if ($this->phase->kind === PhaseKind::Mediate) {
            // Comparison operands are hydrated, not granted evidence or movement scope.
            $pool = app(HomologousMediationService::class)->structuralPoolFor($this->phase, $this);
            $additionalPropositionIds = ($additionalPropositionIds ?? collect())
                ->merge($pool['reads']->pluck('id'))->merge($pool['gates']->pluck('id'));
        }
        if ($this->phase->kind === PhaseKind::Bias) {
            $population = app(HomologousBiasService::class)->populationFor($this->phase, $this);
            $additionalPropositionIds = ($additionalPropositionIds ?? collect())->merge($population->pluck('id'))
                ->merge(app(MediatedIncomingBiasService::class)->populationFor($this->phase, $this)->pluck('id'));
        }
        if ($this->phase->kind === PhaseKind::Order) {
            $population = app(HomologousOrderService::class)->populationFor($this->phase, $this);
            $additionalPropositionIds = ($additionalPropositionIds ?? collect())->merge($population->pluck('id'));
        }
        // Walk only newly discovered IDs. A recursive UNION over the full dependency
        // relation can produce pathological PostgreSQL plans as table statistics grow.
        $owned = DB::table('propositions')
            ->join('phases', 'phases.id', '=', 'propositions.phase_id')
            ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')
            ->join('participants', 'participants.id', '=', 'cycles.participant_id')
            ->where('participants.substrate_id', $this->substrate->id);
        // Opt-in measurement hydration can include historical population members.
        // Existing operational callers retain their actionable working set.
        $allOwned = clone $owned;
        app(ExhaustionService::class)->scopeActionable($owned, 'cycles.context_horizon_id');
        $gate = (clone $owned)->where('propositions.id', $this->phase->gate_proposition_id)->select('propositions.id');
        if ($additionalPropositionIds !== null) {
            // Branch exhaustion observes multiple causal Domains with one batched
            // read model. Ownership and active membership still constrain the seeds.
            $gate->union((clone ($includeUnavailableAdditional ? $allOwned : $owned))->whereIn('propositions.id', $additionalPropositionIds)->select('propositions.id'));
        }
        $ids = $owned->where('cycles.context_horizon_id', $this->phase->cycle->context_horizon_id)
            ->selectRaw('propositions.id /* participation working set */')->union($gate)->pluck('id');
        $seen = array_fill_keys($ids->all(), true);
        $frontier = $ids;
        while ($frontier->isNotEmpty()) {
            $boundaries = DB::table('propositions')
                ->join('phases', 'phases.id', '=', 'propositions.phase_id')
                ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')
                ->join('context_horizons', 'context_horizons.id', '=', 'cycles.context_horizon_id')
                ->whereIn('propositions.id', $frontier)
                ->whereNotNull('context_horizons.opened_by_proposition_id')
                ->select('context_horizons.opened_by_proposition_id as id');
            $entries = DB::table('propositions')
                ->join('phases', 'phases.id', '=', 'propositions.phase_id')
                ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')
                ->whereIn('propositions.id', $frontier)->whereNotNull('cycles.entry_committal_id')
                ->select('cycles.entry_committal_id as id');
            $dependencies = DB::table('supersessions')->whereIn('target_proposition_id', $frontier)
                ->select('source_proposition_id as id')->union($boundaries)->union($entries);
            $frontier = DB::query()->fromSub($dependencies, 'dependencies')
                ->join('propositions', 'propositions.id', '=', 'dependencies.id')
                ->join('phases', 'phases.id', '=', 'propositions.phase_id')
                ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')
                ->join('participants', 'participants.id', '=', 'cycles.participant_id')
                ->where('participants.substrate_id', $this->substrate->id)
                ->distinct()->pluck('propositions.id')
                ->reject(fn ($id) => isset($seen[$id]));
            foreach ($frontier as $id) {
                $seen[$id] = true;
            }
        }

        return collect(array_keys($seen));
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

    /** One batched ownership map; Supersession parents never choose a Cycle entry. */
    public function seedIds(): array
    {
        return $this->remember('entry-seeds', fn () => app(CommitRegionService::class)
            ->seedIdsFor($this->propositions, $this->propositions));
    }

    public function seed(Proposition $position): ?Proposition
    {
        if (! $position->exists) {
            return null;
        }
        $node = $this->proposition($position);
        if ($node->tier === 'committal') {
            return $node;
        }
        if (! $this->propositions->has($node->id)) {
            return app(CommitRegionService::class)->seedFor($node);
        }
        $id = $this->seedIds()[$node->id] ?? null;

        return $id === null ? null : $this->propositions->get($id);
    }

    public function members(Proposition $seed): Collection
    {
        return $this->remember('members:'.$seed->id, function () use ($seed) {
            $seeds = $this->seedIds();

            return $this->propositions->filter(fn ($node) => $node->tier === 'expressive'
                && ($seeds[$node->id] ?? null) === $seed->id && $this->activeIds->has($node->id))
                ->sortBy('id')->values();
        });
    }

    public function active(Collection $nodes): Collection
    {
        return $nodes->filter(fn ($node) => $this->activeIds->has($node->id) && $this->children->get($node->id, collect())->where('type', '!=', 'variant')->isEmpty())->values();
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
        return app(CommittalLineageService::class)->ancestors($position, $this)->first();
    }
}
