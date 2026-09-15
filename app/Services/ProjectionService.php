<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;

final class ProjectionService
{
    /** Canonical signed field projection, shared by recovery and human observation. */
    public function field(ObservableValues $values): array
    {
        $projection = $this->from($values);
        $tension = round($projection['prominence'] - $projection['turbulence'], 7);

        return ['tension' => $tension, 'charge' => round(1 - abs($tension), 7)];
    }

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
