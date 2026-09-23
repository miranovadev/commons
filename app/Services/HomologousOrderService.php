<?php

namespace App\Services;

use App\Domain\Observables\HomeostaticDirection;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Additional committed continuations only; local Commit and its measurements are independent. */
final readonly class HomologousOrderService
{
    public function __construct(
        private HomeostaticPopulationService $populations,
        private HomeostaticDirectionService $directions,
    ) {}

    public function populationFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $discover = function () use ($phase): Collection {
            $gate = $phase->gate()->first();
            if ($phase->kind !== PhaseKind::Order || $gate === null
                || ! app(PhasePositionService::class)->acceptsGate($phase, $gate)) {
                return collect();
            }

            return $this->populations->committedWritesFor($phase);
        };

        return $context === null ? $discover() : $context->remember('homologous-order-structure:'.$phase->id, $discover);
    }

    /** Frozen component agreement resolves direction, including component hysteresis at zero. */
    public function directionFor(Collection $population, ?ObservableDirection $previous = null): HomeostaticDirection
    {
        $values = [];
        foreach ($population->unique('id') as $node) {
            $snapshot = $node->commitmentSnapshot;
            $direction = $snapshot?->depth_direction;
            $values[$node->id] = $direction !== null && $direction === $snapshot->cadence_direction
                ? ($direction === ObservableDirection::Positive ? 1.0 : -1.0) : null;
        }

        // Order aggregates resolved directions, never rewrites frozen numeric Closure.
        return $this->directions->aggregate($values, 'closure', $previous);
    }

    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Order) {
            return collect();
        }
        $resolve = function () use ($phase, $context): Collection {
            $population = $this->populationFor($phase, $context);
            $field = $this->directionFor($population);
            if ($field->direction === null) {
                return collect();
            }
            $active = Proposition::active()->whereKey($population->pluck('id'))->pluck('id')->flip();

            return $population->filter(fn ($node) => $active->has($node->id)
                && $node->commitmentSnapshot->depth_direction === $field->direction
                && $node->commitmentSnapshot->cadence_direction === $field->direction)->values();
        };

        return $context === null ? $resolve() : $context->remember('homologous-order:'.$phase->id, $resolve);
    }
}
