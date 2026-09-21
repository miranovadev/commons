<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Receipt;
use App\Services\Participation\ParticipationContext;

/** Discovery identity is the observation's immutable ancestry, never its attachment. */
final class ReceiptRegionService
{
    public function seeds(ParticipationContext $context): array
    {
        return $context->remember('receipt-region-seeds', function () use ($context): array {
            $seeds = app(CommitRegionService::class)->seedIdsFor($context->propositions, $context->propositions, $context->parents);
            // Discovery additionally fails closed on incomplete paths and cycles.
            // This does not change the motor's canonical region resolver.
            $children = $pending = $valid = [];
            foreach ($context->propositions as $node) {
                $parents = $node->tier === 'committal' ? collect() : $context->parents->get($node->id, collect());
                $pending[$node->id] = $parents->count();
                $valid[$node->id] = $node->tier === 'committal' || $parents->isNotEmpty();
                foreach ($parents as $edge) {
                    $children[$edge->source_proposition_id][] = $node->id;
                }
            }
            $ready = array_keys(array_filter($pending, fn ($count) => $count === 0));
            $resolved = [];
            while ($ready !== []) {
                $id = array_pop($ready);
                $resolved[$id] = $valid[$id] ? $seeds[$id] : null;
                foreach ($children[$id] ?? [] as $child) {
                    $valid[$child] = $valid[$child] && $resolved[$id] !== null;
                    if (--$pending[$child] === 0) {
                        $ready[] = $child;
                    }
                }
            }

            return array_replace(array_fill_keys(array_keys($seeds), null), $resolved);
        });
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
