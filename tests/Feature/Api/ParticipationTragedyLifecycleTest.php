<?php

namespace Tests\Feature\Api;

use App\Models\ContextHorizon;
use App\Models\Participant;
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
    public function test_natural_tragedy_supports_another_participants_final_attempt_and_lineage_exhaustion(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/tragedy-lifecycle.json', 67);
        $actor = $journey['participants']['tragic-explorer'];
        $outside = $journey['participants']['previous-horizon-observer'];
        $initial = $journey['responses'][0];
        $state = $journey['responses'][66];

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
        $this->assertEqualsWithDelta(-0.3436754, $boundary['depth'], 0.0000001);
        $this->assertEqualsWithDelta(-0.9753777, $boundary['cadence'], 0.0000001);
        $this->assertSame(1, DB::table('horizon_tragedies')->count(), $this->traceMessage($state));
        $this->assertDatabaseCount('horizon_exhaustions', 0);
        $tragedy = DB::table('horizon_tragedies')->sole();
        $receipt = DB::table('receipts')->where('id', $tragedy->receipt_id)->sole();
        $this->assertNotSame($initial['context_horizon_id'], $tragedy->context_horizon_id);
        $this->assertSame($actor, $tragedy->participant_id);
        $this->assertNull($receipt->instrument_execution_id);
        $this->assertArrayNotHasKey('statement', json_decode($receipt->metadata, true));
        $this->assertSame('no_remaining_potential', DB::table('cycles')->join('phases', 'phases.cycle_id', '=', 'cycles.id')
            ->where('phases.id', $tragedy->phase_id)->value('cycles.rest_reason'));
        $this->assertStringNotContainsString($receipt->id, json_encode($this->readParticipation($outside)));
        $this->assertStringNotContainsString($tragedy->id, json_encode($this->readParticipation($outside)));
        $outsideState = $this->readParticipation($outside);
        $this->assertActionUnavailable($outsideState, 'proposition.variant');
        $this->postContribution($outside, $outsideState['plan']['phase']['id'], ['action' => 'receipt.read', 'receipt_id' => $receipt->id])->assertUnprocessable();

        $journey = $this->continueParticipationScript(8); // Enter and traverse the actual historical route.
        $consumer = $journey['participants']['final-attempt-consumer'];
        $before = $journey['responses'][74];
        $this->assertSame($tragedy->context_horizon_id, $before['context_horizon_id']);
        $visible = collect($before['plan']['receipts'])->firstWhere('id', $receipt->id);
        $this->assertSame('available', $visible['availability']);
        $this->assertSame('tragedy', $visible['type']);
        $this->assertArrayNotHasKey('evidence', $visible);
        $history = DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson();
        $competing = $this->readParticipation($actor);
        $this->assertActionAvailable($competing, 'proposition.variant');
        $afterVariant = $this->continueParticipationScript(1)['responses'][75];
        $attempt = DB::table('tragedy_final_attempts')->sole();
        $this->assertSame($tragedy->id, $attempt->tragedy_id);
        $this->assertSame($afterVariant['current_proposition_id'], $attempt->proposition_id);
        $this->postContribution($actor, $competing['plan']['phase']['id'], ['action' => 'proposition.variant', 'statement' => 'A duplicate final attempt.'])->assertUnprocessable();
        $this->assertDatabaseCount('tragedy_final_attempts', 1);
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson());
        $this->assertDatabaseMissing('participant_receipt_contexts', ['receipt_id' => $receipt->id]);
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $receipt->id]);
        // These exact 24 contributions consume the receipt at step 82 and
        // exhaust the Horizon at step 99. No eligibility-driven action picker.
        for ($step = 76; $step <= 99; $step++) {
            $after = $this->continueParticipationScript(1)['responses'][$step];
            if ($step < 99) {
                $this->assertDatabaseCount('horizon_exhaustions', 0);
                $this->assertSame($tragedy->context_horizon_id, $after['context_horizon_id'],
                    'Every live final-attempt step stays in its current Horizon until actual Exhaustion.');
            }
            if ($step === 82) {
                $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id]);
                $this->assertSame(0, DB::table('horizon_exhaustions')->count(), 'Consumption alone does not exhaust a Horizon.');
                $shared = collect($this->readParticipation($actor)['plan']['receipts'])->firstWhere('id', $receipt->id);
                $this->assertNull($shared, 'Consumed unread Receipt leaves the other Participant’s shared catalogue.');
                $this->assertNotContains($receipt->id, array_column($after['plan']['receipts'], 'id'));
                $grounded = collect($after['plan']['gate_options'])->firstWhere('id', $after['current_proposition_id']);
                $reference = collect($grounded['receipts'])->firstWhere('id', $receipt->id);
                $this->assertFalse($reference['loaded']);
                $this->assertArrayNotHasKey('evidence', $reference);

            }
        }
        $this->assertSame(1, DB::table('horizon_exhaustions')->count(), $this->traceMessage($state));
        $exhaustion = DB::table('horizon_exhaustions')->sole();
        $consumption = DB::table('receipt_consumptions')->where('receipt_id', $receipt->id)->sole();
        $this->assertSame($tragedy->context_horizon_id, $consumption->context_horizon_id);
        $this->assertSame($consumer, DB::table('phases')->join('cycles', 'cycles.id', '=', 'phases.cycle_id')
            ->where('phases.id', $consumption->phase_id)->value('cycles.participant_id'));
        $this->assertSame($tragedy->id, $exhaustion->tragedy_id);
        $this->assertEquals($receipt, DB::table('receipts')->where('id', $receipt->id)->sole());
        $this->assertDatabaseCount('tragedy_final_attempts', 1);
        $this->assertDatabaseCount('horizon_tragedies', 1);
        $this->assertSame($initial['context_horizon_id'], $after['context_horizon_id']);
        $this->assertSame('distinguish', $after['plan']['phase']['kind']);
        $this->assertSame($initial['current_proposition_id'], $after['current_proposition_id']);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom(
            Proposition::findOrFail($exhaustion->terminal_proposition_id),
            ContextHorizon::findOrFail($tragedy->context_horizon_id),
            Participant::findOrFail($consumer));
        $this->assertSame(1, $inspection['fallback_depth']);
        $this->assertSame([$initial['current_proposition_id']], $inspection['eligible_candidate_ids']);
        $this->assertGreaterThan(7, $inspection['selection']['distance'], 'Distance includes the actual path on both sides of the Horizon boundary.');
        $closed = collect($after['plan']['write_options'])->where('continuation_exhausted', true);
        $this->assertNotEmpty($closed, 'Successful paths into the exhausted Horizon remain contextual history.');
        foreach ($closed as $node) {
            foreach (['proposition.traverse', 'instrument.execute', 'attestation.create', 'proposition.revise'] as $action) {
                $this->assertNotContains($node['id'], $this->actionDefinitions($after)[$action]['properties']['proposition_id']['enum'] ?? []);
            }
            $oldFailures = collect(json_decode($history, true))->where('proposition_id', $node['id'])->values()->all();
            $currentFailures = DB::table('continuation_failures')->where('proposition_id', $node['id'])->orderBy('exploration_epoch')
                ->get()->map(fn ($row) => (array) $row)->all();
            $this->assertSame($oldFailures, $currentFailures, 'Horizon Exhaustion must not rewrite successful history as failure.');
        }
        $continuation = $this->continueParticipationScript(1)['responses'][100];
        $this->assertParticipationScriptComplete();
        $this->assertSame('persist', $continuation['plan']['phase']['kind']);
        $this->assertNotContains($continuation['current_proposition_id'], $closed->pluck('id')->all());
    }
}
