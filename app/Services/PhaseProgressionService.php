<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;

final class PhaseProgressionService
{
    public function advance(
        Phase $phase,
        Proposition $gate,
    ): ?Phase {
        $causality = app(CausalContextService::class);
        $horizon = $causality->assertPhase($phase);
        $homologousOrder = $phase->kind === PhaseKind::Order
            && app(HomologousOrderService::class)->forPhase($phase)->contains('id', $gate->id);
        if (! $homologousOrder) {
            $causality->assertGate($gate, $horizon);
        }
        if (! $homologousOrder && ! app(PhasePositionService::class)->matches($gate, $phase, $phase->kind,
            $phase->kind === PhaseKind::Order ? 'committal' : 'expressive')) {
            throw new \DomainException('Progression requires a Write at the current producing Phase position.');
        }
        $continuations = match ($phase->kind) {
            PhaseKind::Bias => app(CausalWriteService::class)->forPhase($phase),
            PhaseKind::Order => app(PhaseGenerationService::class)->committedWrites($phase),
            default => null,
        };
        if (! $homologousOrder && $continuations !== null && ! $continuations->contains('id', $gate->id)) {
            throw new \DomainException('Progression requires a causal continuation of the current Gate.');
        }
        $participant = $phase->cycle->participant;
        $participant->current_proposition_id = $gate->getKey();
        $participant->save();

        $phase->completed_at = now();
        $phase->save();

        if ($phase->kind === PhaseKind::Order) {
            $phase->cycle->completed_at = now();
            $phase->cycle->save();

            // ParticipationService rolls over after the contribution commits.

            return null;
        }

        $kind = PhaseKind::from($phase->kind->value + 1);
        $next = new Phase;
        $next->forceFill([
            'cycle_id' => $phase->cycle_id,
            'gate_proposition_id' => $gate->getKey(),
            'kind' => $kind,
            'write_register' => $horizon->domain->registerFor($kind),
            'started_at' => now(),
        ]);
        $next->save();

        return $next;
    }
}
