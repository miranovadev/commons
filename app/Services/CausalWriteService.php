<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Expressive options follow causal edges, never a regional calculation population. */
final readonly class CausalWriteService
{
    public function __construct(private PhasePositionService $positions) {}

    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $position = $phase->kind === PhaseKind::Order ? PhaseKind::Bias : $phase->kind;
        // Order arrives at the selected Bias Write. It cannot reopen that Write's
        // siblings by walking backward to their shared Stance/Frontier parent.
        $ids = $phase->kind === PhaseKind::Order
            ? collect([$phase->gate_proposition_id])
            : $this->childrenOf(collect([$phase->gate_proposition_id]), $context);
        if ($phase->kind === PhaseKind::Bias) {
            // An authorized homologous Reclassify creates a single edge from its
            // selected source, not a fictitious edge from the acting Phase Gate.
            // Only this exact Phase's recorded result extends its forward route.
            $ids = $ids->merge($this->classifiedHere($phase, $context))->unique()->values();
        }
        $nodes = $this->atPosition($ids, $phase, $position, $context);
        $frontier = $nodes;
        while ($frontier->isNotEmpty()) {
            $next = $this->atPosition($this->childrenOf($frontier->pluck('id'), $context, revisionsOnly: true), $phase, $position, $context)
                ->whereNotIn('id', $nodes->pluck('id'))->values();
            $nodes = $nodes->concat($next);
            $frontier = $next;
        }

        return $nodes->unique('id')->sortBy('id')->values();
    }

    private function childrenOf(Collection $ids, ?ParticipationContext $context, bool $revisionsOnly = false): Collection
    {
        return $context !== null
            ? $ids->flatMap(fn ($id) => $context->children->get($id, collect())
                ->when($revisionsOnly, fn ($edges) => $edges->where('type', 'revise'))->pluck('target_proposition_id'))->diff($ids)->unique()->values()
            : Supersession::query()->whereIn('source_proposition_id', $ids)
                ->when($revisionsOnly, fn ($query) => $query->where('type', 'revise'))
                ->whereNotIn('target_proposition_id', $ids)->pluck('target_proposition_id')->unique()->values();
    }

    private function atPosition(Collection $ids, Phase $phase, PhaseKind $position, ?ParticipationContext $context): Collection
    {
        if ($phase->kind === PhaseKind::Bias) {
            // A real foreign source edge does not make another Cycle's result
            // a local Write when the selected source is encountered elsewhere.
            $nodes = $context !== null ? $context->nodes($ids) : Proposition::whereKey($ids)->get();
            $seeds = $context !== null ? $context->seedIds() : app(CommitRegionService::class)->seedIdsFor($nodes, $nodes);
            $ids = $nodes->filter(fn ($node) => ($seeds[$node->id] ?? null) === $phase->cycle->entry_committal_id)->pluck('id');
        }
        return $context !== null
            ? $this->positions->filter($context->nodes($ids)->filter(fn ($node) => $context->activeIds->has($node->id)), $phase, $position)
            : $this->positions->scope(Proposition::query()->active()->whereKey($ids), $phase, $position)
                ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id))->get();
    }

    private function classifiedHere(Phase $phase, ?ParticipationContext $context): Collection
    {
        if ($context !== null) {
            $targets = $context->propositions->where('phase_id', $phase->id)->pluck('id');
            $records = $context->classifications->whereIn('target_proposition_id', $targets);

            return $records->filter(fn ($record) => $context->parents->get($record->target_proposition_id, collect())
                ->contains(fn ($edge) => $edge->type === 'reclassify' && $edge->phase_id === $phase->id
                    && $edge->source_proposition_id === $record->source_proposition_id))->pluck('target_proposition_id');
        }

        return DB::table('reclassification_results as result')
            ->join('propositions as target', 'target.id', '=', 'result.target_proposition_id')
            ->join('supersessions as edge', fn ($join) => $join
                ->on('edge.target_proposition_id', '=', 'target.id')->on('edge.source_proposition_id', '=', 'result.source_proposition_id'))
            ->where('target.phase_id', $phase->id)->where('edge.phase_id', $phase->id)
            ->where('edge.type', 'reclassify')->pluck('target.id');
    }
}
