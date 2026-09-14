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
        return $source->tier === 'expressive' && ! $this->committed($source, $context);
    }
}
