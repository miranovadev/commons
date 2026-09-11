<?php

namespace App\Services;

use App\Services\Participation\ParticipationContext;
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
        private AttestationEvidenceService $evidence,
    ) {}

    /** Active register-equivalent candidates in one Commit Region, counted once. */
    public function populationFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $phase->kind === PhaseKind::Bias ? $this->regions->forPhase($phase, context: $context) : collect();
    }

    /** One receipt-weighted effective lean; no projected sign or extra attenuation. */
    public function leanFor(Phase $phase): float
    {
        return $this->chirality->leanFor($this->populationFor($phase)->pluck('id'), $phase->cycle->participant->substrate);
    }

    /** The retained field direction admits every matching active source. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $population = $this->populationFor($phase, $context);
        $seed = $this->regions->seedFor($context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->firstOrFail(), $context);
        if ($seed === null) {
            return collect();
        }
        $direction = $this->evidence->populationDirection($seed, \App\Enums\RegisterPosition::Second, $context);

        return $population->filter(fn (Proposition $candidate): bool =>
            $this->chirality->directionFor($candidate, $context) === $direction)->values();
    }
}
