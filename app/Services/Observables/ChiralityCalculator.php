<?php

namespace App\Services\Observables;

use App\Services\Participation\ParticipationContext;
use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\ObservableDirection;
use App\Enums\SupersessionStrength;
use App\Models\Substrate;
use App\Models\Proposition;
use Illuminate\Support\Collection;

final readonly class ChiralityCalculator
{
    public function __construct(
        private \App\Services\AttestationEvidenceService $evidence,
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

        return $this->evidence->valueFor($lineage->propositions->whereIn('id', $ids), $lineage->horizon->substrate, $lineage->context);
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
    public function leanFor(Collection $activeIds, Substrate $substrate, ?ParticipationContext $context = null): float
    {
        return $this->evidence->valueFor($context !== null ? $context->nodes($activeIds) : Proposition::query()->whereIn('id', $activeIds)->get(), $substrate, $context);
    }

    /** Effective Attestation direction reconstructed from evidence in its fixed region frame. */
    public function directionFor(Proposition $proposition, ?ParticipationContext $context = null): ObservableDirection
    {
        return $this->evidence->directionFor($proposition, context: $context);
    }
}
