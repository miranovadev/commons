<?php

namespace App\Services\Observables;

use App\Domain\Observables\ObservableValues;
use App\Models\Proposition;

final readonly class ObservableService
{
    public function __construct(
        private LineageMeasurementService $measurements,
    ) {}

    public function calculate(Proposition $proposition): ObservableValues
    {
        return $this->measurements->calculate($proposition)->observables();
    }
}
