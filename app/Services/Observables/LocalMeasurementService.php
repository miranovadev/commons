<?php

namespace App\Services\Observables;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\ClosureValues;
use App\Domain\Observables\LineageComparison;
use App\Domain\Observables\LocalMeasurements;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;

/** Independent raw evaluation; never consumes accumulated measurement projections. */
class LocalMeasurementService
{
    public function __construct(
        private readonly ContextLineageComparisonService $lineages,
        private readonly CorrespondenceCalculator $correspondence,
        private readonly ChiralityCalculator $chirality,
        private readonly ClosureCalculator $closure,
        private readonly LocalChiralityStateService $localChirality,
        private readonly CanonicalMeasurementService $canonical,
    ) {}

    public function chiralityComponents(Proposition $proposition, ?LineageComparison $comparison = null, ?ParticipationContext $context = null): ChiralityValues
    {
        $context ??= $comparison?->current->context;
        if ($canonical = $this->canonical->for($proposition, $context)) {
            return $canonical->chiralityComponents();
        }

        return $proposition->tier === 'expressive'
            ? $this->localChirality->components($proposition, $context)
            : $this->chirality->components($comparison ?? $this->lineages->for($proposition, $context));
    }

    public function calculate(Proposition $proposition, ?LineageComparison $comparison = null, bool $includeClosure = true, ?ParticipationContext $context = null): LocalMeasurements
    {
        $context ??= $comparison?->current->context;
        if ($canonical = $this->canonical->for($proposition, $context)) {
            $closure = $includeClosure ? $this->closure->components($proposition, $context) : new ClosureValues(0, 0);

            return new LocalMeasurements($canonical->correspondence, $canonical->chirality,
                $closure->depth, $closure->cadence, $closure->average(), $canonical->chiralityComponents(),
                $canonical->correspondenceComponents(), $closure);
        }
        $comparison ??= $this->lineages->for($proposition, $context);
        $closure = $includeClosure ? $this->closure->localComponents($proposition, $comparison)
            : new ClosureValues(0, 0);

        $chirality = $this->chiralityComponents($proposition, $comparison);
        $correspondence = $this->correspondence->components($comparison);

        return new LocalMeasurements(
            $correspondence->average(),
            $chirality->average(),
            $closure->depth,
            $closure->cadence,
            $closure->average(),
            $chirality,
            $correspondence,
            $closure,
        );
    }
}
