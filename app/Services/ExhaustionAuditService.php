<?php

namespace App\Services;

use App\Models\ContextHorizon;
use App\Models\Tragedy;
use Illuminate\Support\Facades\DB;

/** Read-only diagnostic of persisted proof, never a derivation from global topology. */
final class ExhaustionAuditService
{
    public function inspect(string $substrateId): array
    {
        $horizons = ContextHorizon::where('substrate_id', $substrateId)->get()->keyBy('id');
        $tragedies = Tragedy::whereIn('context_horizon_id', $horizons->keys())->get()->keyBy('context_horizon_id');
        $closed = DB::table('horizon_exhaustions')->whereIn('context_horizon_id', $horizons->keys())->get()->keyBy('context_horizon_id');
        $invalid = $tragedyMismatch = $horizonMismatch = [];
        foreach ($tragedies as $id => $tragedy) {
            if (! $this->validRest($tragedy->phase_id, $id)
                || ! DB::table('receipts')->where('id', $tragedy->receipt_id)->where('type', 'tragedy')
                    ->where('context_horizon_id', $id)->where('phase_id', $tragedy->phase_id)->exists()) {
                $tragedyMismatch[] = $id;
            }
        }
        foreach ($horizons as $id => $horizon) {
            $proof = $closed->get($id);
            if (($horizon->exhausted_at !== null) !== ($proof !== null)) {
                $horizonMismatch[] = $id;
            }
            if ($proof === null) {
                continue;
            }
            $tragedy = $tragedies->get($id);
            if ($tragedy === null || $proof->tragedy_id !== $tragedy->id || $proof->receipt_id !== $tragedy->receipt_id
                || $proof->phase_id === $tragedy->phase_id || $proof->opening_proposition_id !== $horizon->opened_by_proposition_id
                || ! $this->validRest($proof->phase_id, $id)
                || ! DB::table('receipt_consumptions')->where('receipt_id', $proof->receipt_id)->where('context_horizon_id', $id)
                    ->where('legacy', false)->where('consumed_at', '<=', $proof->exhausted_at)->exists()) {
                $invalid[] = $id;
            }
        }

        return ['substrate_id' => $substrateId, 'matches' => $invalid === [] && $tragedyMismatch === [] && $horizonMismatch === [],
            'invalid_rest_witnesses' => $invalid, 'horizon_mismatches' => $horizonMismatch, 'tragedy_mismatches' => $tragedyMismatch,
            'tragedies' => $tragedies->count(), 'exhausted_horizons' => $closed->count()];
    }

    private function validRest(string $phaseId, string $horizonId): bool
    {
        return DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->join('horizon_failure_witnesses as witness', 'witness.phase_id', '=', 'phase.id')
            ->where('phase.id', $phaseId)->where('cycle.context_horizon_id', $horizonId)
            ->where('cycle.rest_reason', 'no_remaining_potential')->whereNotNull('cycle.rested_at')
            ->whereNotNull('phase.completed_at')->exists();
    }
}
