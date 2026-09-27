<?php

namespace App\Services\Observables;

use App\Domain\Observables\CardinalMorphology;
use App\Domain\Observables\LineageComparison;
use App\Enums\RegisterPosition;
use App\Enums\SupersessionStrength;
use App\Models\Proposition;

final readonly class CorrespondenceCalculator
{
    private const int ITERATIONS = 2;

    public function __construct(
        private CardinalMorphologyService $morphologies,
        private DomainCardinalLayoutService $layouts,
    ) {}

    public function calculate(LineageComparison $comparison): float
    {
        if ($comparison->isBootstrap()) {
            return -1;
        }

        /** @var Proposition $head */
        $head = $comparison->current->propositions->last();
        $current = $this->morphologies->current($head, $comparison->current->horizon, $comparison->current->context);
        $previous = $this->morphologies->reference($comparison->current->horizon, $current->primaryDepth, $comparison->current->context);
        if ($previous === null || $previous->propositions->isEmpty()) {
            return -1;
        }

        $topology = $this->topology($current, $previous);
        $lateral = $this->lateral($head, $comparison, $current);

        return $this->normalise(($topology + $lateral) / 2);
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

    /**
     * Lateral Position is one head-local coordinate comparison, not an average
     * over the ancestry graph. Compare the measured proposition's ordinal with
     * the previous completed Domain's terminal placement-lineage proposition at
     * the same canonical Phase/register position.
     */
    private function lateral(Proposition $head, LineageComparison $comparison, CardinalMorphology $current): float
    {
        $currentPosition = $current->positions[$head->id] ?? null;
        $previousHorizon = $comparison->previous?->horizon;
        if ($currentPosition === null || $previousHorizon === null) {
            return 0;
        }

        $previousLayout = $this->layouts->for($previousHorizon, $comparison->current->context);
        $terminalId = $comparison->current->horizon->opened_by_proposition_id;
        $node = $terminalId === null ? null : $previousLayout->propositions->get($terminalId);
        $targetPhase = $head->phase->kind;
        $targetRegister = RegisterPosition::forRegister($head->register);

        while ($node !== null) {
            if ($node->phase?->kind === $targetPhase
                && RegisterPosition::forRegister($node->register) === $targetRegister) {
                $previousPosition = $previousLayout->position($node->id);

                return $previousPosition === null
                    ? 0
                    : self::positionSimilarity($previousPosition->lateral, $currentPosition->lateral);
            }

            $gateId = $node->phase?->gate_proposition_id;
            if ($gateId === null || $gateId === $node->id) {
                break;
            }
            $node = $previousLayout->propositions->get($gateId);
        }

        // The active Domain may extend to a canonical position absent from the
        // completed previous terminal lineage. Absence is neutral positional
        // evidence, never a wrapped/synthetic analogue.
        return 0;
    }

    public static function positionSimilarity(int $a, int $b): float
    {
        if ($a < 1 || $b < 1) {
            throw new \InvalidArgumentException('Lateral positions are one-based.');
        }

        return max(-1, min(1, 1 - (2 * abs($a - $b) / ($a + $b))));
    }

    private function normalise(float $value): float
    {
        return round(max(-1, min(1, $value)), 7);
    }
}
