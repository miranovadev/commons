<?php

namespace App\Services;

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
    public function forPhase(Phase $phase): Collection
    {
        if ($phase->kind !== PhaseKind::Order) {
            return collect();
        }

        return $this->regions->forPhase($phase)->filter(fn (Proposition $candidate): bool =>
            $this->closure->preview($candidate, $phase)['admissible'])->values();
    }

    /** Evidence remains a separate prerequisite for the Commit action. */
    public function committableFor(Phase $phase): Collection
    {
        return $this->forPhase($phase)->filter(fn (Proposition $candidate): bool =>
            $this->admissibility->grounding($candidate)['admissible'])->values();
    }
}
