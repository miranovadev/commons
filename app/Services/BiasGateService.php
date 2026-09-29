<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class BiasGateService
{
    public function __construct(
        private CommitRegionService $regions,
        private ChiralityCalculator $chirality,
        private AttestationEvidenceService $evidence,
    ) {}

    /** Active candidates in the Cycle's exact Gate register, counted once. */
    public function populationFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $phase->kind === PhaseKind::Bias ? $this->regions->forPhase($phase, context: $context) : collect();
    }

    /** One Receipt-neutral effective lean; no projected sign or extra attenuation. */
    public function leanFor(Phase $phase): float
    {
        return $this->chirality->leanFor($this->populationFor($phase)->pluck('id'));
    }

    /** Regional calculation result; this collection does not authorize operational targets. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $population = $this->populationFor($phase, $context);
        $seed = $this->regions->seedFor($context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->firstOrFail(), $context);
        if ($seed === null) {
            return collect();
        }

        return $population->filter(fn (Proposition $candidate): bool => $this->chirality->directionFor($candidate, $context)
            === $this->evidence->lineageDirectionFor($candidate, $context))->values();
    }

    /** Ordinary local, recorded incoming mediation, and homologous sources retain separate authority. */
    public function operationalFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Bias) {
            return collect();
        }
        $context ??= new ParticipationContext($phase);

        return $context->remember('bias-operational:'.$phase->id, fn () => $this->forPhase($phase, $context)
            ->where('id', $phase->gate_proposition_id)
            ->concat(app(MediatedIncomingBiasService::class)->forPhase($phase, $context))
            ->concat(app(HomologousBiasService::class)->forPhase($phase, $context))
            ->unique('id')
                // Resolve each source class (including full population dominance) first.
            ->filter(fn (Proposition $source): bool => app(ChiralityGateService::class)->evaluate($source, $context)['admissible'])
            ->sortBy('id')->values());
    }
}
