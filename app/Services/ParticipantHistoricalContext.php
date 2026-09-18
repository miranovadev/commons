<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Observation only. Never feed these objects into options, evidence or schemas. */
final readonly class ParticipantHistoricalContext
{
    public function __construct(
        private PhasePositionService $positions,
        private CommitRegionService $regions,
        private MediationReadService $mediation,
    ) {}

    public function forPhase(Phase $phase, ?ParticipationContext $context = null, ?Collection $reads = null): array
    {
        $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate;
        if ($gate === null) {
            return [];
        }
        $seed = null;
        $history = collect();
        if (in_array($phase->kind, [PhaseKind::Mediate, PhaseKind::Bias, PhaseKind::Order], true)) {
            $seed = $this->regions->seedFor($gate, $context);
        }

        if ($phase->kind === PhaseKind::Mediate) {
            $reads ??= $this->mediation->readsFor($phase, $context);
            $historicalReads = $this->inRegion($this->exhaustedAt($phase, PhaseKind::Constrain)->get(), $seed);
            $history = $history->concat($this->serialize($historicalReads, 'read', PhaseKind::Constrain));
            $readIds = $reads->pluck('id')->merge($historicalReads->pluck('id'));
            $gates = $this->exhaustedAt($phase, PhaseKind::Disperse)
                ->whereIn('propositions.id', $this->childrenOf($readIds))->get();
            $history = $history->concat($this->serialize($this->inRegion($gates, $seed), 'gate', PhaseKind::Disperse));
        } elseif (in_array($phase->kind, [PhaseKind::Bias, PhaseKind::Order], true)) {
            $position = $this->positions->candidatePosition($phase->kind);
            $gates = $this->inRegion($this->exhaustedAt($phase, $position)->get(), $seed);
            $history = $history->concat($this->serialize($gates, 'gate', $position));
        }

        if ($phase->kind === PhaseKind::Order) {
            // Commits persist; only their outgoing basins can be exhausted.
            $writes = $this->exhaustedAt($phase, PhaseKind::Order, 'committal')
                ->whereHas('commitmentSnapshot')->get();
            $sources = $this->positions->scope(Proposition::query(), $phase, PhaseKind::Bias)
                ->whereIn('id', Supersession::whereIn('target_proposition_id', $writes->pluck('id'))
                    ->where('type', 'commit')->select('source_proposition_id'))->get(['propositions.id', 'propositions.tier']);
            $local = Supersession::whereIn('source_proposition_id', $this->inRegion($sources, $seed)->pluck('id'))
                ->where('type', 'commit')->pluck('target_proposition_id');
            $writes = $writes->whereIn('id', $local);
        } elseif ($phase->kind === PhaseKind::Bias) {
            $writes = $this->inRegion($this->exhaustedAt($phase, $phase->kind)->get(), $seed);
        } else {
            $writes = $this->exhaustedAt($phase, $phase->kind)
                ->whereIn('propositions.id', $this->localWriteIds($phase))->get();
        }

        return $history->concat($this->serialize($writes, 'write', $phase->kind))->values()->all();
    }

    /** An unavailable current position is itself local context while awaiting Rest. */
    public function position(Proposition $gate): array
    {
        $nodes = $this->withExhaustion(Proposition::whereKey($gate->id))->get();

        return $this->serialize($nodes, 'gate', $gate->phase->kind)->all();
    }

    private function exhaustedAt(Phase $phase, PhaseKind $position, string $tier = 'expressive'): Builder
    {
        return $this->withExhaustion($this->positions->scope(Proposition::query(), $phase, $position, $tier))
            ->whereHas('phase.cycle', fn ($query) => $query->where('context_horizon_id', $phase->cycle->context_horizon_id));
    }

    private function withExhaustion(Builder $query): Builder
    {
        return app(ExhaustionService::class)->withHistoricalState($query)
            ->orderByDesc('propositions.created_at')->orderByDesc('propositions.id');
    }

    /** Direct Writes and same-position revisions, never the downstream excursion. */
    private function localWriteIds(Phase $phase): Collection
    {
        $atPosition = fn ($ids) => $this->positions->scope(Proposition::query(), $phase, $phase->kind)
            ->whereIn('id', $ids)->pluck('id');
        $ids = $atPosition($this->childrenOf(collect([$phase->gate_proposition_id])));
        $frontier = $ids;
        while ($frontier->isNotEmpty()) {
            $frontier = $atPosition($this->childrenOf($frontier)->where('type', 'revise'))
                ->diff($ids)->unique()->values();
            $ids = $ids->merge($frontier);
        }

        return $ids;
    }

    private function childrenOf(Collection $ids): Builder
    {
        return Supersession::whereIn('source_proposition_id', $ids)->select('target_proposition_id');
    }

    /** Resolve only candidate ancestry, in batches, stopping at every Commit. */
    private function inRegion(Collection $candidates, ?Proposition $seed): Collection
    {
        if ($seed === null || $candidates->isEmpty()) {
            return collect();
        }
        $nodes = $candidates->keyBy('id');
        $frontier = $nodes->where('tier', 'expressive')->keys();
        $edges = collect();
        while ($frontier->isNotEmpty()) {
            $incoming = DB::table('supersessions')->whereIn('target_proposition_id', $frontier)
                ->get(['source_proposition_id', 'target_proposition_id']);
            $edges = $edges->concat($incoming);
            $parents = Proposition::whereIn('id', $incoming->pluck('source_proposition_id')->diff($nodes->keys()))
                ->get(['id', 'tier'])->keyBy('id');
            $nodes = $nodes->union($parents);
            $frontier = $parents->where('tier', 'expressive')->keys();
        }
        $seeds = $this->regions->seedIdsFor($candidates, $nodes, $edges->groupBy('target_proposition_id'));

        return $candidates->filter(fn ($node) => ($seeds[$node->id] ?? null) === $seed->id)->values();
    }

    private function serialize(Collection $nodes, string $role, PhaseKind $position): Collection
    {
        return $nodes->map(fn ($node) => [
            'id' => $node->id, 'role' => $role, 'phase_number' => $position->value,
            'statement' => str($node->statement)->limit(240)->toString(),
            'statement_truncated' => mb_strwidth($node->statement, 'UTF-8') > 240,
            'tier' => $node->tier, 'register' => $node->register,
            'exhausted' => $node->exhaustion_kind === 'excursion',
            'basin_exhausted' => $node->exhaustion_kind === 'basin',
            'exhausted_at' => $node->exhausted_at, 'actionable' => false,
        ])->values();
    }
}
