<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;

final class ProjectionService
{
    /**
     * @return array{prominence: float, turbulence: float}
     */
    public function from(ObservableValues $values): array
    {
        $measurements = [
            $values->correspondence,
            $values->chirality,
            $values->closure,
        ];
        $prominence = array_sum(array_map(
            fn (float $value): float => (1 + $value) / 2,
            $measurements,
        )) / count($measurements);
        $turbulence = array_sum(array_map(
            fn (float $value): float => (1 - $value) / 2,
            $measurements,
        )) / count($measurements);

        return [
            'prominence' => round($prominence, 7),
            'turbulence' => round($turbulence, 7),
        ];
    }
}
