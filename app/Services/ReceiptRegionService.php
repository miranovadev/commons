<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Receipt;
use App\Services\Participation\ParticipationContext;

/** The acting Cycle's immutable entry owns the catalogue; observations retain their provenance. */
final class ReceiptRegionService
{
    public function seeds(ParticipationContext $context): array
    {
        return $context->seedIds();
    }

    public function current(Phase $phase, ParticipationContext $context): ?string
    {
        return $phase->cycle->entry_committal_id
            ?? ($this->seeds($context)[$phase->gate_proposition_id] ?? null); // Origin bootstrap has no prior entry.
    }

    public function contains(Receipt $receipt, Phase $phase, ParticipationContext $context): bool
    {
        $seed = $this->current($phase, $context);
        if ($seed === null) {
            return false;
        }
        if ($receipt->type === 'tragedy') {
            return (app(TragedyReceiptScopeService::class)->seeds($context)[$receipt->id] ?? null) === $seed;
        }

        return $receipt->context_horizon_id === $phase->cycle->context_horizon_id
            && ($this->seeds($context)[$receipt->evidenceExecution?->proposition_id] ?? null) === $seed;
    }
}
