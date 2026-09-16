<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Tragedy;
use Illuminate\Support\Facades\DB;

/** Tragedy is a unique Origin-lineage event, never local Rest or numeric zero. */
final class TragedyService
{
    public function record(Phase $phase, array $proof): void
    {
        foreach ($proof['branches'] as $branch) {
            $changed = DB::table('origin_branches')->where('root_proposition_id', $branch['root_proposition_id'])
                ->whereNull('exhausted_at')->whereNull('legacy_retired_at')->update(['exhausted_at' => now()]);
            if (! $changed) {
                continue;
            }
            (new Tragedy)->forceFill([
                'root_proposition_id' => $branch['root_proposition_id'], 'substrate_id' => $proof['substrate_id'],
                'participant_id' => $phase->cycle->participant_id, 'phase_id' => $phase->id,
                'terminal_proposition_id' => $phase->gate_proposition_id, 'observed_field_version' => $proof['field_version'],
                'occurred_at' => now(), 'evidence' => [...$branch, 'semantics' => 'exhaustion-v1',
                    'transition_ids' => array_column($proof['transitions'], 'proposition_id'),
                    'exhausted_horizon_ids' => $proof['horizons']],
            ])->save();
        }
    }
}
