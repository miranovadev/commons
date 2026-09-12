<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class ReconciliationOptionService
{
    public function __construct(private MediationReadService $mediation) {}

    /** Only Mediate performs binary reconciliation. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->write_register === null || $phase->kind !== PhaseKind::Mediate) {
            return collect();
        }
        $gates = $this->mediation->gatesFor($phase, $context);

        return $gates->contains('id', $phase->gate_proposition_id)
            ? $gates->where('id', '!=', $phase->gate_proposition_id)->values()
            : collect();
    }
}
