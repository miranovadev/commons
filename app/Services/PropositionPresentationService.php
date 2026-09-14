<?php

namespace App\Services;

use App\Enums\ObservableDirection as Direction;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;

/** Read-only UI metadata; neither a measurement nor an eligibility calculation. */
final readonly class PropositionPresentationService
{
    public function __construct(
        private PropositionStateService $state,
        private CommitRegionService $regions,
        private CommitRegionPolarityService $polarity,
        private AttestationEvidenceService $evidence,
    ) {}

    /** Stable display source only; never changes ancestry or calculation order. */
    public function transition(Proposition $position, ?ParticipationContext $context = null): ?array
    {
        if ($context === null) {
            $parent = $position->parents()->orderBy('supersessions.created_at')->orderBy('supersessions.id')->first();

            return $parent === null ? null : [
                'type' => $parent->pivot->type, 'from' => $parent->register,
                'to' => $position->register, 'source_id' => $parent->id,
            ];
        }

        return $context->remember('presentation-transition:'.$position->id, function () use ($position, $context): ?array {
            $edge = $context->parents->get($position->id, collect())
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])->first();
            $parent = $edge === null ? null : ($context->propositions->get($edge->source_proposition_id)
                ?? ($position->relationLoaded('parents') ? $position->parents->firstWhere('id', $edge->source_proposition_id) : null));

            return $parent === null ? null : [
                'type' => $edge->type, 'from' => $parent->register,
                'to' => $position->register, 'source_id' => $parent->id,
            ];
        });
    }

    /** @return array{positive_direction: ?string, retained_direction: string, positive: int, negative: int} */
    public function attestations(Proposition $position, ?ParticipationContext $context = null): array
    {
        $calculate = function () use ($position, $context): array {
            $seed = $this->regions->seedFor($position, $context);
            $summary = ['positive_direction' => $seed === null ? null : $this->polarity->forSeed($seed, $context)['positive']->value,
                'positive' => 0, 'negative' => 0,
                'retained_direction' => $this->evidence->directionFor($position, context: $context)->value];
            // Counts describe unweighted effective Attestations. Receipts and
            // hysteresis still belong to the existing evidence calculators.
            foreach ($this->state->attestations(collect([$position]), $context)->groupBy('proposition_id') as $sourceId => $attestations) {
                $source = $context !== null ? $context->propositions->get($sourceId) : $attestations->first()->proposition;
                $originalSeed = $this->regions->seedFor($source, $context);
                $support = $originalSeed === null ? Direction::Positive : $this->polarity->forSeed($originalSeed, $context)['positive'];
                foreach ($attestations as $attestation) {
                    $positive = ($attestation->direction === 'positive') === ($support === Direction::Positive);
                    $summary[$positive ? 'positive' : 'negative']++;
                }
            }

            return $summary;
        };

        return $context !== null ? $context->remember('presentation:'.$position->id, $calculate) : $calculate();
    }
}
