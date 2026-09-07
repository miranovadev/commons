<?php

namespace App\Services\Observables;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\SupersessionStrength;
use App\Models\Attestation;

final readonly class ChiralityCalculator
{
    public function __construct(
        private ReinforcementService $reinforcement,
    ) {}

    public function calculate(LineageComparison $comparison): float
    {
        if ($comparison->isBootstrap()) {
            return -1;
        }

        return round(
            $this->lean($comparison->current)
                * $this->lean($comparison->previous),
            7,
        );
    }

    private function lean(DomainLineage $lineage): float
    {
        $supersededIds = $lineage->supersessions
            ->filter(
                fn ($edge) => SupersessionStrength::for($edge->type)
                    === SupersessionStrength::Hard,
            )
            ->pluck('source_proposition_id')
            ->unique();
        $activeIds = $lineage->propositions
            ->pluck('id')
            ->diff($supersededIds);
        $attestations = Attestation::query()
            ->effective()
            ->whereIn('proposition_id', $activeIds)
            ->get();
        $weights = $this->reinforcement->weights(
            $attestations,
            $lineage->horizon->substrate,
        );
        $total = 0.0;
        $net = 0.0;

        foreach ($attestations as $attestation) {
            $weight = $weights->get($attestation->getKey(), 1);
            $total += $weight;
            $net += $attestation->direction === 'support'
                ? $weight
                : -$weight;
        }

        return $total === 0.0 ? 0 : $net / $total;
    }
}
