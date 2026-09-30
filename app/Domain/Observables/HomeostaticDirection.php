<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection;

/** Aggregate observation; separate from every Proposition measurement and its direction. */
final readonly class HomeostaticDirection
{
    /** @param array<string, float|null> $values Independent values keyed by population member. */
    public function __construct(
        public array $values,
        public ?float $mean,
        public ?ObservableDirection $direction,
    ) {}
}
