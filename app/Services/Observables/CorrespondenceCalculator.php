<?php

namespace App\Services\Observables;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\RegisterPosition;
use App\Enums\SupersessionStrength;
use App\Models\Proposition;

final readonly class CorrespondenceCalculator
{
    private const int ITERATIONS = 2;

    public function __construct(
        private ReinforcementService $reinforcement,
    ) {}

    public function calculate(LineageComparison $comparison): float
    {
        if ($comparison->isBootstrap()) {
            return -1;
        }

        $current = $this->features($comparison->current);
        $previous = $this->features($comparison->previous);
        $keys = array_unique([
            ...array_keys($current),
            ...array_keys($previous),
        ]);
        $dotProduct = 0.0;
        $currentMagnitude = 0.0;
        $previousMagnitude = 0.0;

        foreach ($keys as $key) {
            $left = $current[$key] ?? 0.0;
            $right = $previous[$key] ?? 0.0;
            $dotProduct += $left * $right;
            $currentMagnitude += $left ** 2;
            $previousMagnitude += $right ** 2;
        }

        if ($currentMagnitude === 0.0 || $previousMagnitude === 0.0) {
            return -1;
        }

        $similarity = $dotProduct
            / (sqrt($currentMagnitude) * sqrt($previousMagnitude));

        return $this->normalise(2 * $similarity - 1);
    }

    /**
     * Build a continuously receipt-weighted Weisfeiler-Leman feature vector.
     *
     * @return array<string, float>
     */
    private function features(DomainLineage $lineage): array
    {
        $labels = $lineage->propositions->mapWithKeys(
            fn (Proposition $proposition) => [
                (string) $proposition->getKey() => $this->nodeLabel(
                    $proposition->register
                ),
            ],
        )->all();
        $weights = $this->reinforcement->weights(
            $lineage->propositions,
            $lineage->horizon->substrate,
            $lineage->context,
        );
        $features = [];

        for ($iteration = 0; $iteration <= self::ITERATIONS; $iteration++) {
            foreach ($labels as $id => $label) {
                $key = $iteration.':'.$label;
                $features[$key] = ($features[$key] ?? 0)
                    + $weights->get($id, 1);
            }

            if ($iteration === self::ITERATIONS) {
                break;
            }

            $next = [];

            foreach ($labels as $id => $label) {
                $neighbours = [];

                foreach ($lineage->supersessions as $supersession) {
                    $edge = SupersessionStrength::for(
                        $supersession->type
                    )->value;

                    if ($supersession->source_proposition_id === $id) {
                        $neighbours[] = 'out:'.$edge.':'
                            .$labels[$supersession->target_proposition_id];
                    }

                    if ($supersession->target_proposition_id === $id) {
                        $neighbours[] = 'in:'.$edge.':'
                            .$labels[$supersession->source_proposition_id];
                    }
                }

                sort($neighbours);
                $next[$id] = hash(
                    'sha256',
                    implode('|', [$label, ...$neighbours]),
                );
            }

            $labels = $next;
        }

        return $features;
    }

    private function nodeLabel(string $register): string
    {
        return RegisterPosition::forRegister($register)->value;
    }

    private function normalise(float $value): float
    {
        return round(max(-1, min(1, $value)), 7);
    }
}
