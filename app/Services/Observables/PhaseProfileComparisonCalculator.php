<?php

namespace App\Services\Observables;

use App\Domain\Observables\PhaseProfile;

/** Diagnostic only. Production Correspondence continues to use Cardinal Position. */
final readonly class PhaseProfileComparisonCalculator
{
    /** @return list<float> */
    public function contributions(PhaseProfile $current, PhaseProfile $reference): array
    {
        return array_map(function (?float $a, ?float $b): float {
            if ($a === null || $b === null) {
                return 0.0;
            }
            if ($a === 0.0 && $b === 0.0) {
                return 1.0;
            }

            return max(-1.0, min(1.0, 1 - 2 * abs($a - $b) / max($a, $b)));
        }, $current->values, $reference->values);
    }

    public function calculate(PhaseProfile $current, PhaseProfile $reference): float
    {
        return array_sum($this->contributions($current, $reference)) / 7;
    }
}
