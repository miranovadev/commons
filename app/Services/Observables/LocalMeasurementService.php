<?php

namespace App\Services\Observables;

use App\Domain\Observables\LineageComparison;
use App\Domain\Observables\LocalMeasurements;
use App\Models\Proposition;

/** Independent raw evaluation; never consumes accumulated measurement projections. */
class LocalMeasurementService
{
    public function __construct(
        private readonly ContextLineageComparisonService $lineages,
        private readonly CorrespondenceCalculator $correspondence,
        private readonly ChiralityCalculator $chirality,
        private readonly ClosureCalculator $closure,
    ) {}

    public function calculate(Proposition $proposition, ?LineageComparison $comparison = null, bool $includeClosure = true): LocalMeasurements
    {
        $comparison ??= $this->lineages->for($proposition);
        $closure = $includeClosure ? $this->closure->localComponents($proposition, $comparison)
            : new \App\Domain\Observables\ClosureValues(0, 0);

        return new LocalMeasurements(
            $this->correspondence->calculate($comparison),
            $this->chirality->calculate($comparison),
            $closure->depth,
            $closure->cadence,
            $closure->average(),
        );
    }
}
