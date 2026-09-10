<?php

namespace App\Domain\Observables;

final readonly class LocalMeasurements
{
    public function __construct(
        public float $correspondence,
        public float $chirality,
        public float $depth,
        public float $cadence,
    ) {}

    public function closure(): ClosureValues
    {
        return new ClosureValues($this->depth, $this->cadence);
    }

    public function observables(): ObservableValues
    {
        return new ObservableValues($this->correspondence, $this->chirality, $this->closure()->average());
    }
}
