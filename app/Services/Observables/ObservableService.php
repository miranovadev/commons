<?php

namespace App\Services\Observables;

use App\Domain\Observables\ObservableValues;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;

final readonly class ObservableService
{
    public function __construct(
        private LineageMeasurementService $measurements,
    ) {}

    public function components(Proposition $proposition, ?ParticipationContext $context = null): \App\Domain\Observables\LocalMeasurements
    {
        return $this->measurements->calculate($proposition, context: $context);
    }

    public function calculate(Proposition $proposition, ?ParticipationContext $context = null): ObservableValues
    {
        return $this->measurements->calculate($proposition, context: $context)->observables();
    }
}
