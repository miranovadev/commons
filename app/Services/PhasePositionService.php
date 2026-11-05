<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Labels classify. Producing Phase and causal context determine operational position. */
final class PhasePositionService
{
    public function prospectiveArrival(Phase $from, Proposition $gate, PhaseKind $next, ?Cycle $crossed, ?Proposition $anchor): Phase
    {
        $id = 'traverse:'.$from->cycle_id.':'.$gate->id;
        if ($next === PhaseKind::Mediate) {
            $id .= ':'.$anchor?->id;
        }
        $phase = (new Phase)->forceFill(['id' => $id, 'cycle_id' => $from->cycle_id, 'kind' => $next,
            'crossover_cycle_id' => $crossed?->id,
            'gate_proposition_id' => $gate->id, 'write_register' => ($crossed ?? $from->cycle)->contextHorizon->domain->registerFor($next)]);

        return $phase->setRelation('cycle', $from->cycle)->setRelation('crossoverCycle', $crossed)
            ->setRelation('gate', $gate)->setRelation('arrivalAnchor', $anchor);
    }

    public function candidatePosition(PhaseKind $operation): ?PhaseKind
    {
        return match ($operation) {
            PhaseKind::Mediate => PhaseKind::Constrain, // Read, before Disperse
            PhaseKind::Bias => PhaseKind::Mediate,      // Gate, after Reconcile
            PhaseKind::Order => PhaseKind::Bias,        // Closure candidate
            default => null,
        };
    }

    public function matches(Proposition $node, Phase $phase, PhaseKind $position, string $tier = 'expressive'): bool
    {
        $register = $phase->governance()->contextHorizon->domain->registerFor($position);

        return $phase->write_register === $phase->governance()->contextHorizon->domain->registerFor($phase->kind)
            && $node->phase->kind === $position
            && $node->phase->governance()->context_horizon_id === $phase->governance()->context_horizon_id
            && $node->tier === $tier
            && $node->register === $register
            && $node->phase->write_register === $register;
    }

    /** SQL and hydrated contexts use the same producing Phase, Horizon, tier and register. */
    public function scope(Builder $query, Phase $phase, PhaseKind $position, string $tier = 'expressive'): Builder
    {
        $register = $phase->governance()->contextHorizon->domain->registerFor($position);

        if ($phase->write_register !== $phase->governance()->contextHorizon->domain->registerFor($phase->kind)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tier', $tier)->where('register', $register)
            ->whereHas('phase', fn ($query) => $query->where('kind', $position->value)->where('write_register', $register)
                ->whereHas('operatingCycle', fn ($query) => $query->where('context_horizon_id', $phase->governance()->context_horizon_id)));
    }

    public function filter(Collection $nodes, Phase $phase, PhaseKind $position, string $tier = 'expressive'): Collection
    {
        (new Proposition)->newCollection($nodes->all())->loadMissing('phase.cycle', 'phase.crossoverCycle.contextHorizon');

        return $nodes->filter(fn (Proposition $node) => $this->matches($node, $phase, $position, $tier))->values();
    }

    /** Generative actions consume the previous position; Order selects its source separately. */
    public function acceptsGate(Phase $phase, Proposition $gate): bool
    {
        if ($phase->write_register !== $phase->governance()->contextHorizon->domain->registerFor($phase->kind)) {
            return false;
        }
        if ($phase->kind === PhaseKind::Distinguish) {
            return $gate->tier === 'committal'
                && ($gate->phase->governance()->context_horizon_id === $phase->governance()->context_horizon_id
                    || $phase->governance()->contextHorizon->opened_by_proposition_id === $gate->id);
        }

        return $this->matches($gate, $phase, PhaseKind::from($phase->kind->value - 1));
    }
}
