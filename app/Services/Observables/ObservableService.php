<?php

namespace App\Services\Observables;

use App\Domain\Observables\ObservableValues;
use App\Models\Proposition;

final readonly class ObservableService
{
    public function __construct(
        private ContextLineageComparisonService $lineages,
        private CorrespondenceCalculator $correspondence,
        private ChiralityCalculator $chirality,
        private ClosureCalculator $closure,
    ) {}

    public function calculate(Proposition $proposition): ObservableValues
    {
        $comparison = $this->lineages->for($proposition);

        return new ObservableValues(
            correspondence: $this->correspondence->calculate($comparison),
            chirality: $this->chirality->calculate($comparison),
            closure: $this->closure->calculate($comparison),
        );
    }
}
