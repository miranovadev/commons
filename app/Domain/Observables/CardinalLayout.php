<?php

namespace App\Domain\Observables;

use App\Models\ContextHorizon;
use App\Models\Proposition;
use App\Models\Supersession;
use Illuminate\Support\Collection;

final readonly class CardinalLayout
{
    /**
     * @param Collection<string, Proposition> $propositions
     * @param Collection<int, Supersession> $supersessions
     * @param array<string, CardinalPosition> $positions
     */
    public function __construct(
        public ContextHorizon $horizon,
        public string $entryId,
        public Collection $propositions,
        public Collection $supersessions,
        public array $positions,
    ) {}

    public function position(string $id): ?CardinalPosition
    {
        return $this->positions[$id] ?? null;
    }

    public function maxDepth(): int
    {
        return $this->positions === [] ? 0 : max(array_map(fn (CardinalPosition $position) => $position->depth, $this->positions));
    }
}
