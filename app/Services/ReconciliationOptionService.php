<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
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

        $used = $this->establishedCounterparts($context)->get($phase->gate_proposition_id, collect());

        return $gates->contains('id', $phase->gate_proposition_id)
            ? $gates->reject(fn ($node) => $node->id === $phase->gate_proposition_id || $used->has($node->id))->values()
            : collect();
    }

    /** Experimental pair identity. Both directions refer to the same recorded birth. */
    private function establishedCounterparts(ParticipationContext $context): Collection
    {
        // Read persisted edges even if the child (or its Region) is no longer active.
        // Dispatch obtains a fresh context after locking the substrate for the entire birth.
        return $context->remember('established-reconciliation-pairs', fn () => DB::table('supersessions as operand')
            ->join('supersessions as partner', fn ($join) => $join
                ->on('partner.target_proposition_id', '=', 'operand.target_proposition_id')
                ->on('partner.phase_id', '=', 'operand.phase_id')
                ->on('partner.source_proposition_id', '!=', 'operand.source_proposition_id'))
            ->join('substrate_ledger_entries as birth', 'birth.ledgerable_id', '=', 'operand.target_proposition_id')
            ->where('birth.substrate_id', $context->substrate->id)
            ->where('birth.ledgerable_type', (new Proposition)->getMorphClass())
            ->where('operand.type', 'reconcile')->where('partner.type', 'reconcile')
            ->whereIn('operand.source_proposition_id', $context->propositions->keys())
            ->distinct()->get(['operand.source_proposition_id as operand_id', 'partner.source_proposition_id as partner_id'])
            ->groupBy('operand_id')->map(fn ($pairs) => $pairs->keyBy('partner_id')));
    }
}
