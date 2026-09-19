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
#[Group('constitutional')]
class ParticipationTragedyLifecycleTest extends ScriptedParticipationTestCase
{
    public function test_natural_tragedy_supports_another_participants_final_attempt_and_lineage_exhaustion(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/tragedy-lifecycle.json', 72);
        $actor = $journey['participants']['tragic-explorer'];
        $outside = $journey['participants']['previous-horizon-observer'];
        $initial = $journey['responses'][0];
        $state = $journey['responses'][71];
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
        $before = $journey['responses'][79];
        $this->assertSame($tragedy->context_horizon_id, $before['context_horizon_id']);
        $visible = collect($before['plan']['receipts'])->firstWhere('id', $receipt->id);
        $this->assertSame('available', $visible['availability']);
        $this->assertSame($tragedy->id, $visible['evidence']['tragedy_id']);
        $this->assertSame($tragedy->terminal_proposition_id, $visible['evidence']['terminal_proposition_id']);
        $history = DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson();
        $competing = $this->readParticipation($actor);
        $this->assertActionAvailable($competing, 'proposition.variant');
        $afterVariant = $this->continueParticipationScript(1)['responses'][80];
        $attempt = DB::table('tragedy_final_attempts')->sole();
        $this->assertSame($tragedy->id, $attempt->tragedy_id);
        $this->assertSame($afterVariant['current_proposition_id'], $attempt->proposition_id);
        $this->postContribution($actor, $competing['plan']['phase']['id'], ['action' => 'proposition.variant', 'statement' => 'A duplicate final attempt.'])->assertUnprocessable();
        $this->assertDatabaseCount('tragedy_final_attempts', 1);
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson());
        $read = $this->continueParticipationScript(1)['responses'][81];
        $this->assertSame($afterVariant['plan']['phase']['id'], $read['plan']['phase']['id']);
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $receipt->id]);
        // These exact 24 contributions consume the receipt at step 88 and
        // exhaust the Horizon at step 105. No eligibility-driven action picker.
        for ($step = 82; $step <= 105; $step++) {
            $after = $this->continueParticipationScript(1)['responses'][$step];
            if ($step < 105) {
                $this->assertDatabaseCount('horizon_exhaustions', 0);
                $this->assertSame($tragedy->context_horizon_id, $after['context_horizon_id'],
                    'Every live final-attempt step stays in its current Horizon until actual Exhaustion.');
            }
            if ($step === 88) {
                $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id]);
                $this->assertSame(0, DB::table('horizon_exhaustions')->count(), 'Consumption alone does not exhaust a Horizon.');
                $shared = collect($this->readParticipation($actor)['plan']['receipts'])->firstWhere('id', $receipt->id);
                $this->assertSame('consumed', $shared['availability']);
                $this->assertSame($after['current_proposition_id'], $shared['attached_proposition_id']);
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
        $continuation = $this->continueParticipationScript(1)['responses'][106];
        $this->assertParticipationScriptComplete();
        $this->assertSame('persist', $continuation['plan']['phase']['kind']);
        $this->assertNotContains($continuation['current_proposition_id'], $closed->pluck('id')->all());
    }
}
