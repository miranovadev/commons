<?php

namespace App\Domain\Observables;

final readonly class LocalMeasurements
{
    public function __construct(
        public float $correspondence,
        public float $chirality,
        public float $depth,
        public float $cadence,
        public ?float $closureValue = null,
        public ?ChiralityValues $chiralityComponents = null,
        public ?CorrespondenceValues $correspondenceComponents = null,
        public ?ClosureValues $closureComponents = null,
    ) {}

    public function closure(): ClosureValues
    {
        return $this->closureComponents ?? new ClosureValues($this->depth, $this->cadence, $this->closureValue);
    }

    public function observables(): ObservableValues
    {
        return new ObservableValues($this->correspondence, $this->chirality, $this->closure()->average());
    }
}
