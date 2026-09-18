<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Diagnostic only: audit persisted final-attempt proofs, never revive the retired closure algorithm. */
final class ExhaustionAuditService
{
    public function inspect(string $substrateId): array
    {
        $horizons = DB::table('context_horizons')->where('substrate_id', $substrateId)->get()->keyBy('id');
        $tragedies = DB::table('horizon_tragedies')->whereIn('context_horizon_id', $horizons->keys())->get()->keyBy('id');
        $exhaustions = DB::table('horizon_exhaustions')->whereIn('context_horizon_id', $horizons->keys())->get()->keyBy('context_horizon_id');
        $failures = DB::table('continuation_failures')->whereIn('context_horizon_id', $horizons->keys())->get();
        $phaseIds = $tragedies->pluck('phase_id')->merge($exhaustions->pluck('phase_id'))->merge($failures->pluck('phase_id'))->unique();
        $phases = DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->whereIn('phase.id', $phaseIds)->get(['phase.id', 'phase.gate_proposition_id', 'phase.completed_at',
                'cycle.context_horizon_id', 'cycle.rest_reason', 'cycle.rested_at'])->keyBy('id');
        $witnesses = DB::table('horizon_failure_witnesses')->whereIn('phase_id', $phaseIds)->get()->keyBy('phase_id');
        $receipts = DB::table('receipts')->whereIn('id', $tragedies->pluck('receipt_id'))->get()->keyBy('id');
        $consumptions = DB::table('receipt_consumptions')->whereIn('receipt_id', $tragedies->pluck('receipt_id'))->get()->keyBy('receipt_id');
        $validRest = fn ($id, $horizon) => ($phase = $phases->get($id)) !== null
            && $phase->context_horizon_id === $horizon && $phase->rest_reason === 'no_remaining_potential'
            && $phase->completed_at !== null && $phase->rested_at !== null
            && $witnesses->get($id)?->context_horizon_id === $horizon;
        $invalidRest = $invalidTragedy = $invalidConsumption = $horizonMismatch = [];
        foreach ($tragedies as $tragedy) {
            $receipt = $receipts->get($tragedy->receipt_id);
            if (! $validRest($tragedy->phase_id, $tragedy->context_horizon_id)
                || $phases->get($tragedy->phase_id)?->gate_proposition_id !== $tragedy->terminal_proposition_id
                || $witnesses->get($tragedy->phase_id)?->observed_field_version !== $tragedy->observed_field_version) {
                $invalidRest[] = $tragedy->phase_id;
            }
            if ($receipt === null || $receipt->type !== 'tragedy' || $receipt->instrument_execution_id !== null
                || $receipt->context_horizon_id !== $tragedy->context_horizon_id || $receipt->phase_id !== $tragedy->phase_id) {
                $invalidTragedy[] = $tragedy->id;
            }
        }
        foreach ($exhaustions as $id => $exhaustion) {
            $tragedy = $tragedies->get($exhaustion->tragedy_id);
            $consumption = $consumptions->get($exhaustion->receipt_id);
            if (! $validRest($exhaustion->phase_id, $id)
                || $phases->get($exhaustion->phase_id)?->gate_proposition_id !== $exhaustion->terminal_proposition_id
                || $witnesses->get($exhaustion->phase_id)?->observed_field_version !== $exhaustion->observed_field_version) {
                $invalidRest[] = $exhaustion->phase_id;
            }
            if ($tragedy === null || $tragedy->context_horizon_id !== $id
                || $tragedy->receipt_id !== $exhaustion->receipt_id || $tragedy->phase_id === $exhaustion->phase_id
                || $consumption === null || $consumption->legacy || $consumption->context_horizon_id !== $id
                || $consumption->consumed_at > $exhaustion->exhausted_at) {
                $invalidConsumption[] = $id;
            }
            if ($horizons[$id]->opened_by_proposition_id !== $exhaustion->opening_proposition_id) {
                $horizonMismatch[] = $id;
            }
        }
        foreach ($horizons as $id => $horizon) {
            if (($horizon->exhausted_at !== null) !== $exhaustions->has($id)) {
                $horizonMismatch[] = $id;
            }
        }
        $invalidFailures = [];
        $subjects = DB::table('propositions as node')->join('phases as phase', 'phase.id', '=', 'node.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')->whereIn('node.id', $failures->pluck('proposition_id'))
            ->get(['node.id', 'node.tier', 'cycle.context_horizon_id'])->keyBy('id');
        foreach ($failures as $failure) {
            $node = $subjects->get($failure->proposition_id);
            if (! $validRest($failure->phase_id, $failure->context_horizon_id) || $node === null
                || $node->tier !== 'expressive' || $node->context_horizon_id !== $failure->context_horizon_id) {
                $invalidFailures[] = $failure->proposition_id;
            }
        }
        $horizonMismatch = array_values(array_unique($horizonMismatch));
        $invalidRest = array_values(array_unique($invalidRest));

        return ['substrate_id' => $substrateId,
            'matches' => $invalidRest === [] && $invalidTragedy === [] && $invalidConsumption === [] && $horizonMismatch === [] && $invalidFailures === [],
            'missing' => $horizonMismatch, 'unexpected' => $invalidFailures,
            'invalid_rest_witnesses' => $invalidRest, 'invalid_receipt_consumptions' => $invalidConsumption,
            'horizon_mismatches' => $horizonMismatch, 'lineage_mismatches' => [], 'tragedy_mismatches' => $invalidTragedy,
            'derived_states' => $failures->count() + $exhaustions->count() - count($invalidFailures) - count($invalidConsumption),
            'projected_states' => $failures->count() + $exhaustions->count(),
            'legacy_holds' => DB::table('origin_branches')->where('substrate_id', $substrateId)->whereNotNull('legacy_retired_at')->count()];
    }
}
