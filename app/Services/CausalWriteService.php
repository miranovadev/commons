<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

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
        return $context !== null
            ? $this->positions->filter($context->nodes($ids)->filter(fn ($node) => $context->activeIds->has($node->id)), $phase, $position)
            : $this->positions->scope(Proposition::query()->active()->whereKey($ids), $phase, $position)
                ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id))->get();
    }
}
