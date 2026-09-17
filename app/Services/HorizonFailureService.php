<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use Illuminate\Support\Facades\DB;

/** Versioned, Horizon-local failure witnesses. No inference about ungenerated possibility. */
final class HorizonFailureService
{
    public function inspect(Phase $phase): array
    {
        $substrateId = $phase->cycle->participant->substrate_id;
        $version = app(PotentialProjectionService::class)->assertCurrent($substrateId);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $proof = ['substrate_id' => $substrateId, 'context_horizon_id' => $phase->cycle->context_horizon_id,
            'field_version' => $version, 'phase_id' => $phase->id, 'participant_id' => $phase->cycle->participant_id,
            'position_id' => $phase->gate_proposition_id, 'rest_reason' => $plan['rest_reason'] ?? 'no_available_move',
            'transitions' => [], 'horizons' => [], 'branches' => [], 'reason' => 'no_available_move'];
        if (isset($plan['schema']['$defs']['actions']['participant.rest']) && $proof['rest_reason'] === 'no_remaining_potential') {
            $proof['reason'] = 'no_remaining_potential';
            $pending = [$phase->gate_proposition_id];
            $closed = [];
            while ($pending !== []) {
                $id = array_shift($pending);
                if (isset($closed[$id])) {
                    continue;
                }
                $node = Proposition::with('phase.cycle')->findOrFail($id);
                // Failed expressive opportunities can reopen generation locally.
                // A Committal is a hard boundary, never recursively closed here.
                if ($node->tier === 'committal' || $node->phase->cycle->context_horizon_id !== $proof['context_horizon_id']) {
                    continue;
                }
                $children = DB::table('supersessions as edge')->leftJoin('continuation_failures as failure', 'failure.proposition_id', '=', 'edge.target_proposition_id')
                    ->where('edge.source_proposition_id', $id)->get(['edge.target_proposition_id', 'failure.failed_at']);
                if ($children->contains(fn ($child) => $child->failed_at === null && ! isset($closed[$child->target_proposition_id]))) {
                    continue;
                }
                $closed[$id] = true;
                $proof['transitions'][] = $id;
                foreach (DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id') as $parent) {
                    $pending[] = $parent;
                }
            }
        }
        $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

        return $proof;
    }

    public function publish(Phase $phase, array $proof): void
    {
        DB::transaction(function () use ($phase, $proof): void {
            $substrate = Substrate::whereKey($phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
            if (! $proof['stable'] || $proof['field_version'] !== (int) $substrate->field_version
                || $proof['phase_id'] !== $phase->id || $proof['position_id'] !== $phase->gate_proposition_id
                || $proof['participant_id'] !== $phase->cycle->participant_id
                || $proof['substrate_id'] !== $substrate->id || $proof['context_horizon_id'] !== $phase->cycle->context_horizon_id
                || $phase->fresh()->completed_at === null || $phase->cycle->fresh()->rest_reason?->value !== 'no_remaining_potential') {
                return;
            }
            if (! DB::table('horizon_failure_witnesses')->insertOrIgnore([
                'phase_id' => $phase->id, 'context_horizon_id' => $proof['context_horizon_id'],
                'observed_field_version' => $proof['field_version'], 'recorded_at' => now(),
            ])) {
                return;
            }
            foreach ($proof['transitions'] as $id) {
                DB::table('continuation_failures')->insertOrIgnore(['proposition_id' => $id,
                    'context_horizon_id' => $proof['context_horizon_id'], 'phase_id' => $phase->id, 'failed_at' => now()]);
                app(RealtimePublisher::class)->proposition($id);
            }
            $tragedy = app(TragedyService::class)->record($phase, $proof);
            $phase->cycle->forceFill(['recovery_proposition_id' => $tragedy->opening_proposition_id])->save();
            $phase->cycle->participant->forceFill(['current_proposition_id' => $tragedy->opening_proposition_id])->save();
            app(FieldMutationService::class)->mark($substrate->id, [$proof['context_horizon_id']], $proof['transitions'], measurementsDirty: false);
            app(RealtimePublisher::class)->horizons($substrate->id);
            app(RealtimePublisher::class)->graphMetrics($substrate->id);
        });
    }
}
