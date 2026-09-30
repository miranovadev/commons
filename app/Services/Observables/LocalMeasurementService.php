<?php

namespace App\Services\Observables;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\ClosureValues;
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

    public function chiralityComponents(Proposition $proposition, ?LineageComparison $comparison = null): ChiralityValues
    {
        return $this->chirality->components($comparison ?? $this->lineages->for($proposition));
    }

    public function calculate(Proposition $proposition, ?LineageComparison $comparison = null, bool $includeClosure = true): LocalMeasurements
    {
        $comparison ??= $this->lineages->for($proposition);
        $closure = $includeClosure ? $this->closure->localComponents($proposition, $comparison)
            : new ClosureValues(0, 0);

        $chirality = $this->chirality->components($comparison);
        $correspondence = $this->correspondence->components($comparison);

        return new LocalMeasurements(
            $correspondence->average(),
            $chirality->average(),
            $closure->depth,
            $closure->cadence,
            $closure->average(),
            $chirality,
            $correspondence,
        );
    }
}
