<?php

namespace App\Services\Observables;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\SupersessionStrength;
use App\Models\Substrate;
use App\Models\Proposition;
use App\Services\PropositionStateService;
use Illuminate\Support\Collection;

final readonly class ChiralityCalculator
{
    public function __construct(
        private ReinforcementService $reinforcement,
        private PropositionStateService $state,
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
        $ids = $this->activeIds($lineage->propositions, $lineage->supersessions);

        return $this->attestationLean($this->state->attestations(
            $lineage->propositions->whereIn('id', $ids),
        ), $lineage->horizon->substrate);
    }

    /** Shared hard-supersession semantics for active propositions in a lineage. */
    public function activeIds(Collection $propositions, Collection $supersessions): Collection
    {
        $supersededIds = $supersessions
            ->filter(
                fn ($edge) => SupersessionStrength::for($edge->type)
                    === SupersessionStrength::Hard,
            )
            ->pluck('source_proposition_id')
            ->unique();
        return $propositions->pluck('id')->diff($supersededIds)->values();
    }

    /** Receipt-weighted effective Attestation lean, before cross-domain comparison. */
    public function leanFor(Collection $activeIds, Substrate $substrate): float
    {
        return $this->attestationLean($this->state->attestations(
            Proposition::query()->whereIn('id', $activeIds)->get(),
        ), $substrate);
    }

    private function attestationLean(Collection $attestations, Substrate $substrate): float
    {
        $weights = $this->reinforcement->weights(
            $attestations,
            $substrate,
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
