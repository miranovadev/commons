<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Receipt;
use App\Services\Participation\ParticipationContext;

/** Discovery identity is the observed Proposition's Cycle-governed region. */
final class ReceiptRegionService
{
    public function seeds(ParticipationContext $context): array
    {
        return $context->seedIds();
    }

    public function current(Phase $phase, ParticipationContext $context): ?string
    {
        return $this->seeds($context)[$phase->gate_proposition_id] ?? null;
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
            && ($this->seeds($context)[$receipt->instrumentExecution?->proposition_id] ?? null) === $seed;
    }
}

