<?php

namespace App\Services;

use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;

/** Commitment consumes its direct expressive source globally, preserving other branches. */
final readonly class PropositionAvailabilityService
{
    public function committed(Proposition $source, ?ParticipationContext $context = null): bool
    {
        return $context !== null
            ? $context->children->get($source->id, collect())->where('type', 'commit')->isNotEmpty()
            : $source->children()->where('supersessions.type', 'commit')->exists();
    }

    public function mutable(Proposition $source, ?ParticipationContext $context = null): bool
    {
        return $source->tier === 'expressive'
            && ! ($context !== null ? app(ExhaustedContinuationService::class)->ids($context->phase, $context)->has($source->id)
                : app(TerminalContinuationService::class)->closed($source))
            && ($context !== null ? $context->activeIds->has($source->id) || ! $source->exists : ! app(ExhaustionService::class)->unavailable($source)) && ! $this->committed($source, $context);
    }
}
