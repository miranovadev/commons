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
    ) {}

    /** @return array{support_direction: ?string, positive: int, negative: int} */
    public function attestations(Proposition $position, ?ParticipationContext $context = null): array
    {
        $calculate = function () use ($position, $context): array {
            $seed = $this->regions->seedFor($position, $context);
            $summary = ['support_direction' => $seed === null ? null : $this->polarity->forSeed($seed, $context)['support']->value,
                'positive' => 0, 'negative' => 0];
            // Counts describe unweighted effective Attestations. Receipts and
            // hysteresis still belong to the existing evidence calculators.
            foreach ($this->state->attestations(collect([$position]), $context)->groupBy('proposition_id') as $sourceId => $attestations) {
                $source = $context !== null ? $context->propositions->get($sourceId) : $attestations->first()->proposition;
                $originalSeed = $this->regions->seedFor($source, $context);
                $support = $originalSeed === null ? Direction::Positive : $this->polarity->forSeed($originalSeed, $context)['support'];
                foreach ($attestations as $attestation) {
                    $positive = ($attestation->direction === 'support') === ($support === Direction::Positive);
                    $summary[$positive ? 'positive' : 'negative']++;
                }
            }

            return $summary;
        };

        return $context !== null ? $context->remember('presentation:'.$position->id, $calculate) : $calculate();
    }
}
