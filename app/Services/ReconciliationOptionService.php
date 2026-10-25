<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $coherence = app(CorrespondenceGateService::class);
        $current = $coherence->evaluate($context->proposition($phase->gate), $context);
        if (! $current['admissible']) {
            return collect();
        }
        $exhausted = app(ExhaustedContinuationService::class)->ids($phase, $context);
        $gates = $this->mediation->gatesFor($phase, $context)->map(fn ($node) => $context->proposition($node))
            ->filter(fn ($node) => $this->availability->mutable($node, $context)
            && ! $exhausted->has($node->id));

        // Population dominance has already selected Reads and their existing Gates.
        // Apply transition policy afterwards, without changing that population.
        $gates = $gates->filter(function ($node) use ($coherence, $current, $context): bool {
            $candidate = $coherence->evaluate($node, $context);

            return $candidate['admissible']
                && $candidate['correspondence_direction'] === $current['correspondence_direction'];
        });

        // Complete foreign Horizon data is queried in one batch; the comparison
        // working set is not an authoritative inventory of foreign continuations.
        $horizons = $gates->map(fn ($node) => $node->phase->cycle->context_horizon_id)
            ->unique()->reject(fn ($horizon) => $horizon === $phase->cycle->context_horizon_id)->sort()->values();
        $foreignExhausted = $context->remember('mediate-foreign-exhausted:'.$horizons->implode(','),
            fn () => app(ExhaustedContinuationService::class)->idsForHorizons($horizons));
        $gates = $gates->reject(fn ($node) => $foreignExhausted->has($node->id));

        // The Phase Gate records dominance; parent ordering is not direction.
        $pairs = $context->remember('used-directed-reconciliations', fn () => DB::table('supersessions as edge')
            ->join('phases as producer', 'producer.id', '=', 'edge.phase_id')
            ->join('supersessions as dominant', fn ($join) => $join
                ->on('dominant.target_proposition_id', '=', 'edge.target_proposition_id')
                ->on('dominant.source_proposition_id', '=', 'producer.gate_proposition_id'))
            ->where('edge.type', 'reconcile')->where('dominant.type', 'reconcile')
            ->where('producer.kind', PhaseKind::Mediate->value)
            ->whereIn('producer.gate_proposition_id', $context->propositions->keys())
            ->get(['producer.gate_proposition_id', 'edge.source_proposition_id'])->groupBy('gate_proposition_id'));
        $used = $pairs->get($phase->gate_proposition_id, collect())->pluck('source_proposition_id');

        return $gates->contains('id', $phase->gate_proposition_id)
            ? $gates->where('id', '!=', $phase->gate_proposition_id)->whereNotIn('id', $used)->values()
            : collect();
    }
}
