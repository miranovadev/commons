<?php

namespace App\Domain\Observables;

final readonly class CardinalPosition
{
    public function __construct(
        public string $horizonId,
        public int $depth,
        public int $lateral,
    ) {}
}
