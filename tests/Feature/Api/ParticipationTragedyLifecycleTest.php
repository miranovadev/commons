<?php

namespace Tests\Feature\Api;

use App\Models\Proposition;
use App\Services\PotentialChargeService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationTragedyLifecycleTest extends ScriptedParticipationTestCase
{
    public function test_natural_zero_potential_rest_preserves_recovery_without_creating_tragedy(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/tragedy-lifecycle.json', 103);
        $actor = $journey['participants']['tragic-explorer'];
        $state = $journey['responses'][102];

        // Old Read steps 35/36 and 44 now ride with existing Gate contributions.
        foreach ([['3', 36], ['8', 36], ['40', 39]] as [$observation, $step]) {
            $ordinary = DB::table('receipts')->where('reference', 'https://evidence.example.test/observation/'.$observation)->sole();
            $load = DB::table('participant_receipt_contexts')->where('participant_id', $actor)->where('receipt_id', $ordinary->id)->sole();
            $this->assertNull($load->cleared_at);
            $this->assertDatabaseHas('receipt_context_events', ['participant_receipt_context_id' => $load->id,
                'phase_id' => $journey['responses'][$step - 1]['plan']['phase']['id'], 'action' => 'loaded']);
        }
        // The Bias classification uses the Gate-loaded observation40, before Commit measures it.
        $classification = $journey['responses'][40];
        $grounded = $this->visibleProposition($classification, $classification['current_proposition_id']);
        $this->assertSame(1, $grounded['reinforcement_count']);
        $selected = DB::table('receipts')->where('reference', 'https://evidence.example.test/observation/40')->sole();
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $selected->id, 'proposition_id' => $classification['current_proposition_id']]);
        $boundary = $journey['responses'][48]['last_commitment'];
        $this->assertTrue($boundary['closure_boundary']);
        $this->assertEqualsWithDelta(0, $boundary['depth'], 0.0000001);
        $this->assertEqualsWithDelta(0, $boundary['cadence'], 0.0000001);
        $this->assertSame(10, (int) DB::table('commitment_snapshots')
            ->where('proposition_id', $boundary['proposition_id'])->value('cadence_interval'));
        $this->assertSame(12, DB::table('instrument_executions as execution')
            ->join('phases as phase', 'phase.id', '=', 'execution.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('execution.reference', 'like', 'https://evidence.example.test/observation/rhythm-%')
            ->where('cycle.entry_committal_id', $boundary['proposition_id'])
            ->whereNotNull('cycle.completed_at')->whereNotNull('cycle.rested_at')->distinct()->count('cycle.id'));
        foreach ([89, 95, 98, 101] as $step) {
            $observed = $journey['responses'][$step];
            $judged = $this->visibleProposition($observed, $observed['current_proposition_id']);
            $this->assertSame(1, $judged['attestation_summary']['negative']);
        }
        $beforeRest = $journey['responses'][101];
        $this->assertSame('no_remaining_potential', $beforeRest['plan']['rest_reason']);
        $terminal = Proposition::findOrFail($beforeRest['current_proposition_id']);
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($terminal->phase));
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertSame(0, DB::table('receipts')->where('type', 'tragedy')->count());
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $this->assertSame($boundary['proposition_id'], $state['current_proposition_id']);
        $this->assertActionAvailable($state, 'proposition.variant');
        $this->assertParticipationScriptComplete();
    }
}
