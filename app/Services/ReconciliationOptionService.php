<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class ReconciliationOptionService
{
    public function __construct(private MediationReadService $mediation, private PropositionAvailabilityService $availability) {}

    /** Only Mediate performs binary reconciliation. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->write_register === null || $phase->kind !== PhaseKind::Mediate) {
            return collect();
        }
        $context ??= new ParticipationContext($phase);
        $exhausted = app(ExhaustedContinuationService::class)->ids($phase, $context);
        $gates = $this->mediation->gatesFor($phase, $context)->map(fn ($node) => $context->proposition($node))
            ->filter(fn ($node) => $this->availability->mutable($node, $context)
            && ! $exhausted->has($node->id));

        // Foreign Horizon continuations need complete Horizon data, not merely the
        // operands hydrated for this comparison. Resolve once per distinct Horizon.
        foreach ($gates->groupBy(fn ($node) => $node->phase->cycle->context_horizon_id) as $horizon => $nodes) {
            if ($horizon === $phase->cycle->context_horizon_id) {
                continue;
            }
            $foreignExhausted = $context->remember('mediate-foreign-exhausted:'.$horizon,
                fn () => app(ExhaustedContinuationService::class)->ids($nodes->first()->phase));
            $gates = $gates->reject(fn ($node) => $foreignExhausted->has($node->id));
        }

        // The Phase Gate records dominance; parent ordering is not direction.
        $used = Supersession::query()->where('type', 'reconcile')
            ->whereHas('phase', fn ($query) => $query->where('kind', PhaseKind::Mediate->value)
                ->where('gate_proposition_id', $phase->gate_proposition_id))
            ->whereHas('target.parents', fn ($query) => $query->whereKey($phase->gate_proposition_id)
                ->where('supersessions.type', 'reconcile'))
            ->pluck('source_proposition_id');

        return $gates->contains('id', $phase->gate_proposition_id)
            ? $gates->where('id', '!=', $phase->gate_proposition_id)->whereNotIn('id', $used)->values()
            : collect();
    }
}
