<?php

namespace App\Services;

use App\Domain\Observables\HomeostaticDirection;
use App\Enums\ObservableDirection;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use InvalidArgumentException;

/** Consumes lineage-local measurements; never feeds the aggregate back into them. */
final readonly class HomeostaticDirectionService
{
    public function __construct(
        private HomeostaticPopulationService $populations,
        private PropositionMeasurementService $measurements,
    ) {}

    public function forPosition(Proposition $position, string $measurement, ?ObservableDirection $previous = null): HomeostaticDirection
    {
        if (! in_array($measurement, ['correspondence', 'chirality', 'closure'], true)) {
            throw new InvalidArgumentException('Unknown Homeostatic measurement.');
        }
        $population = $this->populations->forPosition($position);
        if ($population->isEmpty()) {
            return $this->aggregate([], $previous);
        }
        // One dependency hydration for the entire population, including historical
        // members. This context is private to measurement and cannot widen a plan.
        $context = new ParticipationContext($position->phase, $population->pluck('id'), includeUnavailableAdditional: true);
        $members = $context->nodes($population->pluck('id'));
        $this->measurements->observeMissing($members, $context);
        $snapshots = $context->snapshots->keyBy('proposition_id');
        $values = [];
        foreach ($population as $member) {
            $node = $context->propositions->get($member->id);
            // Missing ledger membership is unavailable, never a synthetic zero.
            $values[$member->id] = $node === null || ! $context->memberships->has($member->id) ? null
                : ($node->tier === 'committal' ? $snapshots->get($node->id)?->{$measurement}
                    : $node->measurement?->{$measurement});
        }

        return $this->aggregate($values, $previous);
    }

    /**
     * Equal weight per distinct proposition; magnitude matters, not a vote on signs.
     * Zero retains explicitly supplied field history via the existing direction enum.
     * Without field history a tie is unresolved; the acting member never breaks it.
     *
     * @param array<string, float|null> $values
     */
    public function aggregate(array $values, ?ObservableDirection $previous = null): HomeostaticDirection
    {
        $sum = 0.0;
        $count = 0;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (! is_finite($value) || $value < -1 || $value > 1) {
                throw new InvalidArgumentException('A Homeostatic member measurement must be between -1 and 1, or null.');
            }
            $sum += $value;
            $count++;
        }
        $mean = $count === 0 ? null : round($sum / $count, 7);
        $direction = $mean === null ? null : ($mean === 0.0 && $previous === null ? null
            : ObservableDirection::after($mean, $previous ?? ObservableDirection::Negative));

        return new HomeostaticDirection($values, $mean, $direction);
    }
}
