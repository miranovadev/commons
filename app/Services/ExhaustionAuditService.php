<?php

namespace App\Services;

use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

/** Diagnostic only: audit persisted final-attempt proofs, never revive the retired closure algorithm. */
final class ExhaustionAuditService
{
    public function inspect(string $substrateId): array
    {
        $horizons = DB::table('context_horizons')->where('substrate_id', $substrateId)->get()->keyBy('id');
        $tragedies = DB::table('tragedies')->whereNotNull('governing_committal_id')->whereIn('context_horizon_id', $horizons->keys())->get()->keyBy('id');
        $exhaustions = DB::table('commit_region_exhaustions')->whereIn('context_horizon_id', $horizons->keys())->get()->keyBy('governing_committal_id');
        $failures = DB::table('continuation_failures')->whereIn('context_horizon_id', $horizons->keys())->get();
        $phaseIds = $tragedies->pluck('phase_id')->merge($exhaustions->pluck('phase_id'))->merge($failures->pluck('phase_id'))->unique();
        $phases = DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->whereIn('phase.id', $phaseIds)->get(['phase.id', 'phase.gate_proposition_id', 'phase.completed_at',
                'cycle.context_horizon_id', 'cycle.rest_reason', 'cycle.rested_at'])->keyBy('id');
        $witnesses = DB::table('horizon_failure_witnesses')->whereIn('phase_id', $phaseIds)->get()->keyBy('phase_id');
        $receipts = DB::table('receipts')->whereIn('id', $tragedies->pluck('receipt_id'))->get()->keyBy('id');
        $consumptions = DB::table('receipt_consumptions')->whereIn('receipt_id', $tragedies->pluck('receipt_id'))->get()->keyBy('receipt_id');
        $validRest = fn ($id, $horizon, $potentialRequired = true) => ($phase = $phases->get($id)) !== null
            && $phase->context_horizon_id === $horizon && (! $potentialRequired || $phase->rest_reason === 'no_remaining_potential')
            && $phase->completed_at !== null && $phase->rested_at !== null
            && $witnesses->get($id)?->context_horizon_id === $horizon;
        $invalidRest = $invalidTragedy = $invalidConsumption = $horizonMismatch = [];
        foreach ($tragedies as $tragedy) {
            $receipt = $receipts->get($tragedy->receipt_id);
            $metadata = $receipt === null ? [] : json_decode($receipt->metadata, true);
            if (($metadata['trigger'] ?? null) === 'expressive_charge_boundary') {
                // Birth measurements are historical: later evidence can change
                // the terminal's current measurement without erasing catastrophe.
                $terminal = Proposition::find($tragedy->terminal_proposition_id);
                if ($terminal?->tier !== 'expressive' || $terminal->phase_id !== $tragedy->phase_id
                    || $terminal->phase->cycle->context_horizon_id !== $tragedy->context_horizon_id
                    || app(CommitRegionService::class)->seedFor($terminal)?->id !== $tragedy->governing_committal_id
                    || ! isset($metadata['field']['charge']) || $metadata['field']['charge'] != 0
                    || abs($metadata['field']['tension'] ?? 0) != 1) {
                    $invalidTragedy[] = $tragedy->id;
                }
            } elseif (! $validRest($tragedy->phase_id, $tragedy->context_horizon_id)
                || $phases->get($tragedy->phase_id)?->gate_proposition_id !== $tragedy->terminal_proposition_id
                || $witnesses->get($tragedy->phase_id)?->observed_field_version !== $tragedy->observed_field_version) {
                // Historical Rest-born rows remain auditable, never recreated.
                $invalidRest[] = $tragedy->phase_id;
            }
            if ($receipt === null || $receipt->type !== 'tragedy' || $receipt->instrument_execution_id !== null
                || $receipt->context_horizon_id !== $tragedy->context_horizon_id || $receipt->phase_id !== $tragedy->phase_id) {
                $invalidTragedy[] = $tragedy->id;
            }
        }
        foreach ($exhaustions as $seed => $exhaustion) {
            $id = $exhaustion->context_horizon_id;
            $tragedy = $tragedies->get($exhaustion->tragedy_id);
            $consumption = $consumptions->get($exhaustion->receipt_id);
            if (! $validRest($exhaustion->phase_id, $id)
                || $phases->get($exhaustion->phase_id)?->gate_proposition_id !== $exhaustion->terminal_proposition_id
                || $witnesses->get($exhaustion->phase_id)?->observed_field_version !== $exhaustion->observed_field_version) {
                $invalidRest[] = $exhaustion->phase_id;
            }
            if ($tragedy === null || $tragedy->governing_committal_id !== $seed || $tragedy->context_horizon_id !== $id
                || $tragedy->receipt_id !== $exhaustion->receipt_id || $tragedy->phase_id === $exhaustion->phase_id
                || $consumption === null || $consumption->legacy || $consumption->context_horizon_id !== $id
                || $consumption->consumed_at > $exhaustion->exhausted_at) {
                $invalidConsumption[] = $id;
            }
            $terminal = Proposition::find($exhaustion->terminal_proposition_id);
            if ($terminal === null || app(CommitRegionService::class)->seedFor($terminal)?->id !== $seed) {
                $horizonMismatch[] = $id;
            }
        }
        $invalidFailures = [];
        $subjects = DB::table('propositions as node')->join('phases as phase', 'phase.id', '=', 'node.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')->whereIn('node.id', $failures->pluck('proposition_id'))
            ->get(['node.id', 'node.tier', 'phase.kind', 'cycle.context_horizon_id'])->keyBy('id');
        foreach ($failures as $failure) {
            $node = $subjects->get($failure->proposition_id);
            if (! $validRest($failure->phase_id, $failure->context_horizon_id, false) || $node === null
                || (int) $node->kind !== (int) $failure->structural_position || $node->tier !== 'expressive' || $node->context_horizon_id !== $failure->context_horizon_id) {
                $invalidFailures[] = $failure->proposition_id;
            }
        }
        $horizonMismatch = array_values(array_unique($horizonMismatch));
        $invalidRest = array_values(array_unique($invalidRest));

        return ['substrate_id' => $substrateId,
            'archived_horizon_tragedies' => DB::table('tragedies')->whereIn('context_horizon_id', $horizons->keys())->whereNull('governing_committal_id')->count(),
            'archived_origin_tragedies' => DB::table('legacy_origin_tragedies')->where('substrate_id', $substrateId)->count(),
            'archived_horizon_exhaustion_markers' => DB::table('legacy_horizon_exhaustion_markers')->whereIn('context_horizon_id', $horizons->keys())->count(),
            'archived_horizon_exhaustions' => DB::table('horizon_exhaustions')->whereIn('context_horizon_id', $horizons->keys())->count(),
            'matches' => $invalidRest === [] && $invalidTragedy === [] && $invalidConsumption === [] && $horizonMismatch === [] && $invalidFailures === [],
            'missing' => $horizonMismatch, 'unexpected' => $invalidFailures,
            'invalid_rest_witnesses' => $invalidRest, 'invalid_receipt_consumptions' => $invalidConsumption,
            'horizon_mismatches' => $horizonMismatch, 'lineage_mismatches' => [], 'tragedy_mismatches' => $invalidTragedy,
            'derived_states' => $failures->count() + $exhaustions->count() - count($invalidFailures) - count($invalidConsumption),
            'projected_states' => $failures->count() + $exhaustions->count(),
            'legacy_holds' => DB::table('origin_branches')->where('substrate_id', $substrateId)->whereNotNull('legacy_retired_at')->count()];
    }
}
