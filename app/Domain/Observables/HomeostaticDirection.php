<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection;

/** Observation-local aggregate; never a Proposition measurement or persisted field. */
final readonly class HomeostaticDirection
{
    /** @param array<string, float|null> $values Independent values keyed by population member. */
    public function __construct(
        public array $values,
        public ?float $mean,
        public ?ObservableDirection $direction,
    ) {}
}
