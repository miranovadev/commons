<?php

namespace App\Services\Observables;

use App\Services\Participation\ParticipationContext;
use App\Domain\Observables\ObservableValues;
use App\Models\Proposition;

final readonly class ObservableService
{
    public function __construct(
        private LineageMeasurementService $measurements,
    ) {}

    public function calculate(Proposition $proposition, ?ParticipationContext $context = null): ObservableValues
    {
        return $this->measurements->calculate($proposition, context: $context)->observables();
    }
}
