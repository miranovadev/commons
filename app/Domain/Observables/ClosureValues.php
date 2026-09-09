<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection;

final readonly class ClosureValues
{
    public function __construct(public float $depth, public float $cadence) {}

    public function average(): float
    {
        return round(($this->depth + $this->cadence) / 2, 7);
    }

    /** @return array{depth: float, cadence: float, closure: float, depth_direction: string, cadence_direction: string, admissible: bool} */
    public function resolve(
        ObservableDirection $previousDepth = ObservableDirection::Negative,
        ObservableDirection $previousCadence = ObservableDirection::Negative,
    ): array {
        $depth = ObservableDirection::after($this->depth, $previousDepth);
        $cadence = ObservableDirection::after($this->cadence, $previousCadence);

        return [
            'depth' => $this->depth,
            'cadence' => $this->cadence,
            'closure' => $this->average(),
            'depth_direction' => $depth->value,
            'cadence_direction' => $cadence->value,
            'admissible' => $depth === $cadence,
        ];
    }
}
