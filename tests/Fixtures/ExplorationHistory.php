<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Historical-input fixture for isolated selectors, not a publication shortcut.
 * Production Rest publication is exercised by ParticipantExplorationTest.
 * Raw historical rows avoid replaying unrelated measurement birth/entry events.
 */
final class ExplorationHistory
{
    public static function record(Proposition $head, ?Phase $viewer = null): string
    {
        $owner = $viewer ?? $head->phase;
        $cycle = (string) Str::ulid();
        $arrival = (string) Str::ulid();
        $rest = (string) Str::ulid();
        $kind = PhaseKind::from(min(7, $head->phase->kind->value + 1));
        $source = $head->phase->gate_proposition_id;
        DB::table('cycles')->insert(['id' => $cycle, 'participant_id' => $owner->cycle->participant_id,
            'context_horizon_id' => $head->phase->cycle->context_horizon_id,
            'entry_committal_id' => $head->phase->cycle->entry_committal_id,
            'sequence' => 1 + (int) DB::table('cycles')->where('participant_id', $owner->cycle->participant_id)->max('sequence'),
            'rested_at' => now(), 'completed_at' => now(), 'rest_reason' => 'no_available_move', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('phases')->insert([
            ['id' => $arrival, 'cycle_id' => $cycle, 'gate_proposition_id' => $source, 'kind' => $kind->value - 1,
                'write_register' => $head->register, 'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => $rest, 'cycle_id' => $cycle, 'gate_proposition_id' => $head->id, 'kind' => $kind->value,
                'write_register' => $head->phase->cycle->contextHorizon->domain->registerFor($kind),
                'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('exploration_events')->insert(['phase_id' => $rest, 'proposition_id' => $head->id,
            'arrival_phase_id' => $arrival, 'observed_field_version' => $head->phase->cycle->participant->substrate->field_version,
            'witness' => json_encode(['contract' => 1, 'fixture' => 'historical encounter input', 'head_id' => $head->id,
                'arrival_phase_id' => $arrival, 'arrival_source_id' => $source, 'meaningful_continuation' => false]),
            'recorded_at' => now()]);

        return $rest;
    }
}
