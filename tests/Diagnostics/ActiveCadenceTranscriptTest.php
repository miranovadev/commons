<?php

namespace Tests\Diagnostics;

use App\Models\Proposition;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;
use Throwable;

/** Opt-in diagnostic: replay the unchanged fixed transcript, preserving its assertions. */
class ActiveCadenceTranscriptTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;

    public function test_record_the_first_changed_measurement_or_action_in_the_fixed_commit_transcript(): void
    {
        [$actor] = $this->beginPersistJourney('cadence-comparison');
        if ($transcript = getenv('COMMONS_CADENCE_TRANSCRIPT')) {
            $this->persistJourneySteps = json_decode(file_get_contents($transcript), true, flags: JSON_THROW_ON_ERROR)['steps'];
        }
        $trace = [];
        $snapshot = function (): array {
            $aliases = array_flip($this->persistJourneyIds);
            $ids = Proposition::whereKey(array_values($this->persistJourneyIds))->pluck('id');

            return DB::table('local_chirality_states')->whereIn('proposition_id', $ids)->get()
                ->mapWithKeys(fn ($row) => [$aliases[$row->proposition_id] => [
                    'orientation' => (float) $row->orientation,
                    'cadence' => (float) $row->attestation_cadence,
                    'orientation_direction' => $row->orientation_direction,
                    'cadence_direction' => $row->attestation_cadence_direction,
                    'own_intervals' => json_decode($row->intervals, true),
                    'reference' => array_map(fn ($ref) => [
                        'source' => $aliases[$ref['proposition_id']] ?? 'outside-replay',
                        'index' => $ref['interval_index'], 'interval' => $ref['interval'],
                    ], json_decode($row->cadence_reference ?? '[]', true) ?? []),
                ]])->sortKeys()->all();
        };
        try {
            foreach ($this->persistJourneySteps as $index => $step) {
                $before = $this->readParticipation($actor);
                $trace[$index] = ['step' => $index, 'expected' => $step,
                    'actual_phase' => $before['plan']['phase']['number'], 'actual_cycle' => $before['cycle']['sequence'],
                    'available_actions' => array_keys($before['plan']['schema']['$defs']['actions']),
                    'before' => $snapshot()];
                $this->executePersistJourneyStep($actor, $index);
                $trace[$index]['after'] = $snapshot();
            }
        } catch (Throwable $error) {
            $trace[array_key_last($trace)]['failure'] = substr($error->getMessage(), 0, 1000);
            throw $error;
        } finally {
            file_put_contents(getenv('COMMONS_CADENCE_TRACE_FILE') ?: base_path('test-results/cadence-fixed-transcript.json'),
                json_encode($trace, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }
}
