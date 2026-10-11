<?php

namespace App\Services\Observables;

use App\Domain\Observables\CardinalMorphology;
use App\Domain\Observables\CorrespondenceValues;
use App\Domain\Observables\LineageComparison;
use App\Enums\RegisterPosition;
use App\Enums\SupersessionStrength;
use App\Models\Proposition;

final readonly class CorrespondenceCalculator
{
    private const int ITERATIONS = 2;

    public function __construct(
        private CardinalMorphologyService $morphologies,
        private PhaseProfileService $profiles,
    ) {}

    public function calculate(LineageComparison $comparison): float
    {
        return $this->components($comparison)->average();
    }

    public function components(LineageComparison $comparison): CorrespondenceValues
    {
        if ($comparison->isBootstrap()) {
            // Exact provenance/terminal identity. Bounded components with mean +1
            // uniquely require (+1, +1); no first-Domain bootstrap override.
            return new CorrespondenceValues(1, 1);
        }

        /** @var Proposition $head */
        $head = $comparison->current->propositions->last();
        $current = $this->morphologies->current($head, $comparison->current->horizon, $comparison->current->context);
        $previous = $this->morphologies->reference($comparison->current->horizon, $current->primaryDepth, $comparison->current->context);
        $topology = $previous === null || $previous->propositions->isEmpty()
            ? -1 : $this->topology($current, $previous);
        $profile = round($this->profiles->calculate($head, $comparison->current->context), 7);

        return new CorrespondenceValues($topology, $profile);
    }

    private function topology(CardinalMorphology $current, CardinalMorphology $previous): float
    {
        $left = $this->features($current);
        $right = $this->features($previous);
        $keys = array_unique([...array_keys($left), ...array_keys($right)]);
        $dotProduct = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        foreach ($keys as $key) {
            $a = $left[$key] ?? 0.0;
            $b = $right[$key] ?? 0.0;
            $dotProduct += $a * $b;
            $leftMagnitude += $a ** 2;
            $rightMagnitude += $b ** 2;
        }

        if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
            return -1;
        }

        return $this->normalise(2 * ($dotProduct / (sqrt($leftMagnitude) * sqrt($rightMagnitude))) - 1);
    }

    /**
     * Unit-weight structural WL features. Receipt state must not mutate a
     * completed Domain's topology after it has become a reference.
     *
     * @return array<string, float>
     */
    private function features(CardinalMorphology $morphology): array
    {
        $labels = $morphology->propositions->mapWithKeys(fn (Proposition $proposition) => [
            (string) $proposition->getKey() => RegisterPosition::forRegister($proposition->register)->value,
        ])->all();
        $features = [];

        for ($iteration = 0; $iteration <= self::ITERATIONS; $iteration++) {
            foreach ($labels as $label) {
                $key = $iteration.':'.$label;
                $features[$key] = ($features[$key] ?? 0) + 1;
            }

            if ($iteration === self::ITERATIONS) {
                break;
            }

            $next = [];
            foreach ($labels as $id => $label) {
                $neighbours = [];
                foreach ($morphology->supersessions as $supersession) {
                    $edge = SupersessionStrength::for($supersession->type)->value;
                    if ($supersession->source_proposition_id === $id && isset($labels[$supersession->target_proposition_id])) {
                        $neighbours[] = 'out:'.$edge.':'.$labels[$supersession->target_proposition_id];
                    }
                    if ($supersession->target_proposition_id === $id && isset($labels[$supersession->source_proposition_id])) {
                        $neighbours[] = 'in:'.$edge.':'.$labels[$supersession->source_proposition_id];
                    }
                }
                sort($neighbours);
                $next[$id] = hash('sha256', implode('|', [$label, ...$neighbours]));
            }
            $labels = $next;
        }

        return $features;
    }

    private function normalise(float $value): float
    {
        return round(max(-1, min(1, $value)), 7);
    }
}
