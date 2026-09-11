<?php

namespace App\Services;

use App\Services\Participation\ParticipationContext;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Support\Collection;

final readonly class ClosureOptionService
{
    public function __construct(
        private CommitRegionService $regions,
        private ClosureGateService $closure,
        private CommitmentAdmissibilityService $admissibility,
    ) {}

    /** Measurement comparison stays in ClosureGateService; scope stays in CommitRegionService. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Order) {
            return collect();
        }

        return $this->regions->forPhase($phase, context: $context)->filter(fn (Proposition $candidate): bool =>
            $this->closure->preview($candidate, $phase, $context)['admissible'])->values();
    }

    /** Evidence remains a separate prerequisite for the Commit action. */
    public function committableFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $this->forPhase($phase, $context)->filter(fn (Proposition $candidate): bool =>
            $this->admissibility->grounding($candidate, $context)['admissible'])->values();
    }
}
