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
        $participant = $phase->cycle->participant;
        $participant->current_proposition_id = $gate->getKey();
        $participant->save();

        $phase->completed_at = now();
        $phase->save();

        if ($phase->kind === PhaseKind::Order) {
            $phase->cycle->completed_at = now();
            $phase->cycle->save();

            return null;
        }

        $kind = PhaseKind::from($phase->kind->value + 1);
        $next = new Phase;
        $next->forceFill([
            'cycle_id' => $phase->cycle_id,
            'gate_proposition_id' => $gate->getKey(),
            'kind' => $kind,
            'write_register' => $this->registerFor($kind, $gate),
            'started_at' => now(),
        ]);
        $next->save();

        return $next;
    }

    private function registerFor(
        PhaseKind $kind,
        Proposition $gate,
    ): string {
        $emergence = in_array(
            $gate->register,
            ['activity', 'frontier', 'canon'],
            true,
        );

        return match ($kind) {
            PhaseKind::Distinguish,
            PhaseKind::Disperse => $emergence ? 'activity' : 'signal',
            PhaseKind::Persist,
            PhaseKind::Mediate => $emergence ? 'frontier' : 'stance',
            PhaseKind::Constrain,
            PhaseKind::Bias,
            PhaseKind::Order => $emergence ? 'canon' : 'concord',
        };
    }
}

