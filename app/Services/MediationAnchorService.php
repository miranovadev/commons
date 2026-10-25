<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;

final class MediationAnchorService
{
    /** The previous Disperse Gate is the anchor, regardless of display ordering. */
    public function anchorFor(Phase $phase): ?Proposition
    {
        if ($phase->kind !== PhaseKind::Mediate) {
            return null;
        }

        // A read-only exploration probe carries this Participant's immutable
        // recorded arrival; it must not borrow the current Cycle's unrelated path.
        if (! $phase->exists && $phase->relationLoaded('arrivalAnchor')) {
            return $phase->getRelation('arrivalAnchor');
        }

        $disperse = $phase->cycle->phases()
            ->where('kind', PhaseKind::Disperse->value)
            ->where('id', '<', $phase->id)
            ->whereNotNull('completed_at')->latest('id')->first();
        $anchor = $disperse?->gate;

        return $anchor?->phase->cycle->participant->substrate_id === $phase->cycle->participant->substrate_id
            ? $anchor : null;
    }
}
