<?php

namespace App\Services;

use App\Models\Proposition;
use Illuminate\Support\Collection;

/** Structural measurement population only; membership never authorizes an action. */
final readonly class HomeostaticPopulationService
{
    public function __construct(private HomologousPositionService $homology) {}

    /**
     * Include the acting position exactly once when its coordinate is valid.
     * Activity, exhaustion and operational eligibility are separate consumer policies.
     * Expressive and committed positions are never mixed into one population.
     */
    public function forPosition(Proposition $position): Collection
    {
        if (! $position->exists || $position->phase === null) {
            return collect();
        }

        return $this->homology->candidatesFor($position, $position->phase->kind,
            includeAnchor: true);
    }
}
