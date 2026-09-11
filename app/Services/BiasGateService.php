<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ChiralityCalculator;
use Illuminate\Support\Collection;

final readonly class BiasGateService
{
    public function __construct(
        private CommitRegionService $regions,
        private ChiralityCalculator $chirality,
    ) {}

    /** Active register-equivalent candidates in one Commit Region, counted once. */
    public function populationFor(Phase $phase): Collection
    {
        return $phase->kind === PhaseKind::Bias ? $this->regions->forPhase($phase) : collect();
    }

    /** One receipt-weighted effective lean; no projected sign or extra attenuation. */
    public function leanFor(Phase $phase): float
    {
        return $this->chirality->leanFor($this->populationFor($phase)->pluck('id'), $phase->cycle->participant->substrate);
    }

    /** The retained field direction admits every matching active source. */
    public function forPhase(Phase $phase): Collection
    {
        $population = $this->populationFor($phase);
        $substrate = $phase->cycle->participant->substrate;
        $gate = $phase->gate()->firstOrFail();
        $direction = ObservableDirection::after(
            $this->chirality->leanFor($population->pluck('id'), $substrate),
            $this->chirality->directionFor($gate),
        );

        return $population->filter(fn (Proposition $candidate): bool =>
            $this->chirality->directionFor($candidate) === $direction)->values();
    }
}

