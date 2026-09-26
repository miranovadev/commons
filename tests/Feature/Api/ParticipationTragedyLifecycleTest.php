<?php

namespace Tests\Feature\Api;

use App\Models\Participant;
use App\Models\Proposition;
use App\Services\BackwardRecoveryLineage;
use App\Services\CrossoverMarginService;
use App\Services\ExhaustionService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationTragedyLifecycleTest extends ScriptedParticipationTestCase
{
    public function test_natural_closed_crossover_rest_recovers_outward_without_creating_catastrophe(): void
    {
        // Keep the original authored prefix. Step 51's Rest is asserted below
        // by its causal/semantic contract, not regenerated downstream snapshots.
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/tragedy-lifecycle.json');
        $actor = $journey['participants']['tragic-explorer'];

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
        $beforeRest = $journey['responses'][50];
        $participant = Participant::findOrFail($actor);
        $cycle = $participant->cycles()->latest('sequence')->firstOrFail();
        $origin = $journey['responses'][0]['current_proposition_id'];
        $seed = Proposition::findOrFail($boundary['proposition_id']);
        $diagnostic = app(CrossoverMarginService::class)->inspect($cycle);
        $this->assertSame($seed->id, $diagnostic['governing_committal_id']);
        $this->assertSame('emergence', $diagnostic['domain']);
        foreach (['correspondence' => -0.4740799, 'chirality' => 0.9543377,
            'prospective_depth' => 1, 'admissible_cadence_bound' => 0,
            'reinforcement_mass' => 1 / 3, 'tension_bound' => 0.3505622,
            'crossover_margin' => -0.3505622] as $name => $value) {
            $this->assertEqualsWithDelta($value, $diagnostic[$name], 1e-7, $name);
        }
        $this->assertTrue($diagnostic['closed']);
        $path = app(BackwardRecoveryLineage::class)->distances($participant->position, $participant,
            fn ($id, $horizon) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
        $committals = Proposition::whereIn('id', array_keys($path))->where('tier', 'committal')->pluck('id')->all();
        $this->assertSame([$seed->id, $origin], array_values(array_intersect(array_keys($path), $committals)));
        $this->assertSame(2, $path[$seed->id]);
        $this->assertSame(9, $path[$origin]);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($origin));
        $this->assertSame('no_available_move', $beforeRest['plan']['rest_reason']);
        $this->assertSame(['participant.rest'], array_keys($beforeRest['plan']['schema']['$defs']['actions']));

        $history = fn () => collect(['tragedies' => 'id', 'receipts' => 'id', 'receipt_consumptions' => 'receipt_id',
            'commit_region_exhaustions' => 'governing_committal_id', 'exhausted_region_memberships' => 'proposition_id',
            'commitment_snapshots' => 'proposition_id', 'context_horizons' => 'id'])
            ->map(fn ($key, $table) => DB::table($table)->orderBy($key)->get()->toJson())->all();
        $beforeHistory = $history();
        $state = $this->contribute($actor, ['action' => 'participant.rest'], $beforeRest);
        $this->assertSame($beforeHistory, $history());
        $this->assertNotNull($cycle->fresh()->rested_at);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertSame(0, DB::table('receipts')->where('type', 'tragedy')->count());
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $this->assertSame($origin, $state['current_proposition_id']);
        $this->assertSame('settlement', $state['domain']);
        $this->assertSame(2, $state['horizon_sequence']);
        $this->assertSame($journey['responses'][0]['context_horizon_id'], $state['context_horizon_id']);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($seed));
        // Boundary events/recurrence and Receipt memory remain independently
        // exercised by NaturalChargeBoundaryLifecycleTest's real contributions.
        $this->assertParticipationScriptComplete();
    }
}
