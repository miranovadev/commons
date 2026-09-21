<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

/** Conservative migration only. Never broaden a historical proof to every region in its Horizon. */
final class RegionalExhaustionBackfill
{
    public function run(): void
    {
        foreach (DB::table('horizon_tragedies')->whereNull('governing_committal_id')->get() as $tragedy) {
            $terminal = Proposition::find($tragedy->terminal_proposition_id);
            $seed = $terminal === null ? null : app(CommitRegionService::class)->seedFor($terminal);
            try {
                $owner = $seed === null ? null : app(ContextHorizonProjector::class)->forEntry($seed)->id;
            } catch (\DomainException) {
                continue;
            }
            if ($seed === null || ! $this->witness($tragedy) || $owner !== $tragedy->context_horizon_id) {
                continue;
            }
            $scope = DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->value('current_committal_id');
            if ($scope !== $seed->id) {
                continue; // Historical transport cannot be silently relabelled as local authority.
            }
            $consumed = DB::table('receipt_consumptions')->where('receipt_id', $tragedy->receipt_id)->first();
            if ($consumed !== null && ($consumed->legacy || $consumed->context_horizon_id !== $tragedy->context_horizon_id
                || app(CommitRegionService::class)->seedFor(Proposition::findOrFail($consumed->proposition_id))?->id !== $seed->id)) {
                continue;
            }
            $receipt = DB::table('receipts')->where('id', $tragedy->receipt_id)->first();
            $attempt = DB::table('tragedy_final_attempts')->where('tragedy_id', $tragedy->id)->first();
            if ($receipt === null || $receipt->type !== 'tragedy' || $receipt->phase_id !== $tragedy->phase_id
                || $receipt->context_horizon_id !== $tragedy->context_horizon_id
                || ($attempt !== null && app(CommitRegionService::class)->seedFor(Proposition::findOrFail($attempt->proposition_id))?->id !== $seed->id)
                || DB::table('horizon_tragedies')->where('governing_committal_id', $seed->id)->exists()) {
                continue;
            }
            $exhaustion = DB::table('horizon_exhaustions')->where('tragedy_id', $tragedy->id)->first();
            if ($exhaustion !== null) {
                $failed = Proposition::find($exhaustion->terminal_proposition_id);
                $phase = Phase::find($exhaustion->phase_id);
                if ($consumed === null || $failed === null || $phase === null || ! $this->witness($exhaustion)
                    || $exhaustion->phase_id === $tragedy->phase_id || $exhaustion->receipt_id !== $tragedy->receipt_id
                    || $exhaustion->context_horizon_id !== $tragedy->context_horizon_id
                    || $consumed->consumed_at > $exhaustion->exhausted_at
                    || app(CommitRegionService::class)->seedFor($failed)?->id !== $seed->id) {
                    continue;
                }
                try {
                    $path = app(BackwardRecoveryLineage::class)->distances($failed, $phase->cycle->participant,
                        fn (string $id) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
                } catch (\DomainException) {
                    continue;
                }
                if (! isset($path[$consumed->proposition_id])) {
                    continue;
                }
            }
            // New scope metadata only; original proof/Receipt fields remain byte-for-byte unchanged.
            DB::table('horizon_tragedies')->where('id', $tragedy->id)->update(['governing_committal_id' => $seed->id]);
            if ($exhaustion !== null) {
                DB::table('commit_region_exhaustions')->insertOrIgnore([
                    'governing_committal_id' => $seed->id, 'context_horizon_id' => $tragedy->context_horizon_id,
                    'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id,
                    'phase_id' => $exhaustion->phase_id, 'terminal_proposition_id' => $exhaustion->terminal_proposition_id,
                    'observed_field_version' => $exhaustion->observed_field_version, 'exhausted_at' => $exhaustion->exhausted_at,
                ]);
            }
        }
    }

    private function witness(object $proof): bool
    {
        return DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->join('horizon_failure_witnesses as witness', 'witness.phase_id', '=', 'phase.id')
            ->where('phase.id', $proof->phase_id)->where('phase.gate_proposition_id', $proof->terminal_proposition_id)
            ->whereNotNull('phase.completed_at')->whereNotNull('cycle.rested_at')
            ->where('cycle.rest_reason', 'no_remaining_potential')->where('cycle.context_horizon_id', $proof->context_horizon_id)
            ->where('witness.context_horizon_id', $proof->context_horizon_id)
            ->where('witness.observed_field_version', $proof->observed_field_version)->exists();
    }
}
