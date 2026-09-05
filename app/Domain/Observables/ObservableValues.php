<?php

namespace App\Domain\Observables;

final readonly class ObservableValues
{
    public function __construct(
        public float $correspondence,
        public float $chirality,
        public float $closure,
    ) {}
}
