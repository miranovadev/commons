<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection as Direction;

/** Local components only: their mean, never their separate values, is attenuated. */
final readonly class ChiralityValues
{
    public function __construct(
        public float $orientation,
        public float $cadence,
        public int $currentCycles = 0,
        public int $previousCycles = 0,
        public int $currentActs = 0,
        public int $previousActs = 0,
    ) {}

    public function average(): float
    {
        return round(($this->orientation + $this->cadence) / 2, 7);
    }

    public function resolveDirections(Direction $orientation = Direction::Negative, Direction $cadence = Direction::Positive): array
    {
        return ['orientation_direction' => Direction::after($this->orientation, $orientation)->value,
            'attestation_cadence_direction' => Direction::after($this->cadence, $cadence)->value];
    }

    public static function similarity(int $currentActs, int $currentCycles, int $previousActs, int $previousCycles): float
    {
        if ($currentCycles === 0 && $previousCycles === 0) {
            return 0;
        }
        if ($currentCycles === 0 || $previousCycles === 0) {
            return -1;
        }
        $current = $currentActs / $currentCycles;
        $previous = $previousActs / $previousCycles;
        if ($current == 0 && $previous == 0) {
            return 1;
        }

        return round(max(-1, min(1, 1 - 2 * abs($current - $previous) / max($current, $previous))), 7);
    }
}
