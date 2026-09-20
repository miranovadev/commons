<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class ClosureOptionService
{
    public function __construct(
        private CausalWriteService $writes,
        private CommitmentAdmissibilityService $admissibility,
    ) {}

    /** All local expressive Writes remain visible independently of Closure admissibility. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Order) {
            return collect();
        }

        return $this->writes->forPhase($phase, $context);
    }

    /** Evidence remains a separate prerequisite for the Commit action. */
    public function committableFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $this->forPhase($phase, $context)->filter(fn (Proposition $candidate): bool => $this->admissibility->evaluate($candidate, $phase, $context)['admissible'])->values();
    }
}
