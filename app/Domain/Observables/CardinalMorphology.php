<?php

namespace App\Domain\Observables;

use App\Models\Proposition;
use App\Models\Supersession;
use Illuminate\Support\Collection;

final readonly class CardinalMorphology
{
    /**
     * @param Collection<string, Proposition> $propositions
     * @param Collection<int, Supersession> $supersessions
     * @param array<string, CardinalPosition> $positions
     */
    public function __construct(
        public Collection $propositions,
        public Collection $supersessions,
        public array $positions,
        public string $primaryHorizonId,
        public int $primaryDepth,
    ) {}
}
