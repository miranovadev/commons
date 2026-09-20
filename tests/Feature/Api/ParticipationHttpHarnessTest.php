<?php

namespace Tests\Feature\Api;

use App\Services\Observables\LineageMeasurementService;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
class ParticipationHttpHarnessTest extends ScriptedParticipationTestCase
{
    #[Group('integration')]
    public function test_real_http_actions_reach_an_operational_commit(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('explorer');
        $origin = $this->enterParticipant($actor, true)['current_proposition_id'];
        $state = $this->reachFirstCommit($actor, $origin);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $snapshot = DB::table('commitment_snapshots')->where('proposition_id', $state['last_commitment']['proposition_id'])->sole();
        $this->assertSame(LineageMeasurementService::VERSION, $snapshot->calculator_version);
        $this->assertSame($snapshot->depth_direction, $snapshot->cadence_direction);
        $this->assertGreaterThan(0, DB::table('continuation_failures')->count());
        $this->assertSame([3, 6], DB::table('receipt_consumptions')->join('phases', 'phases.id', '=', 'receipt_consumptions.phase_id')
            ->distinct()->orderBy('kind')->pluck('kind')->all());
    }

    public function test_stale_http_contribution_is_rejected_without_creating_a_second_proposition(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('explorer');
        $before = $this->enterParticipant($actor, true);
        $this->postContribution($actor, $before['plan']['phase']['id'], ['action' => 'proposition.variant'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('propositions', 1);
        $this->assertSame($before['plan']['phase']['id'], $this->readParticipation($actor)['plan']['phase']['id']);
        $input = ['action' => 'proposition.variant', 'statement' => 'One legitimate distinction.'];
        $after = $this->contribute($actor, $input);
        $this->assertSame('persist', $after['plan']['phase']['kind']);
        $this->postContribution($actor, $before['plan']['phase']['id'], $input)->assertConflict();
        $this->assertSame($after['plan']['phase']['id'], $this->readParticipation($actor)['plan']['phase']['id']);
        $this->assertDatabaseCount('propositions', 2);
    }

    #[Group('constitutional')]
    public function test_optional_receipt_read_leaves_rest_available_without_advancing_the_phase(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/rest-after-receipt.json', 3);
        $before = $journey['responses'][2];
        $this->assertSame('constrain', $before['plan']['phase']['kind']);
        $this->assertActionAvailable($before, 'participant.rest');
        $this->assertActionAvailable($before, 'receipt.read');
        $after = $this->continueParticipationScript(1)['responses'][3];
        $this->assertSame($before['plan']['phase']['id'], $after['plan']['phase']['id']);
        $this->assertActionAvailable($after, 'participant.rest');
        $rested = $this->continueParticipationScript(1)['responses'][4];
        $this->assertParticipationScriptComplete();
        $this->assertGreaterThan($after['cycle']['sequence'], $rested['cycle']['sequence']);
    }

    public function test_harness_rejects_direct_fixture_writes_before_they_execute(): void
    {
        $this->startSubstrate();
        try {
            DB::table('substrates')->where('slug', $this->substrateSlug)->update(['title' => 'Forbidden fixture write']);
            $this->fail('The direct fixture write was permitted.');
        } catch (LogicException $exception) {
            $this->assertSame('HTTP harness forbids fixture writes outside an application request.', $exception->getMessage());
        }
        $this->getJson('/api/substrates/'.$this->substrateSlug)->assertOk()->assertJsonPath('data.title', 'HTTP lifecycle');
    }

    #[Group('constitutional')]
    public function test_rest_rejects_a_productive_phase_and_accepts_an_unread_informational_receipt(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/rest-unread-receipt.json', 1);
        $actor = $journey['participants']['rest-reader'];
        $initial = $journey['responses'][0];
        $this->assertActionUnavailable($initial, 'participant.rest');
        $this->postContribution($actor, $initial['plan']['phase']['id'], ['action' => 'participant.rest'])
            ->assertUnprocessable();
        $this->assertSame($initial['plan']['phase']['id'], $this->readParticipation($actor)['plan']['phase']['id']);
        $before = $this->continueParticipationScript(2)['responses'][2];
        $this->assertActionAvailable($before, 'receipt.read');
        $this->assertActionAvailable($before, 'participant.rest');
        $loads = DB::table('participant_receipt_contexts')->count();
        $after = $this->continueParticipationScript(1)['responses'][3];
        $this->assertParticipationScriptComplete();
        $this->assertGreaterThan($before['cycle']['sequence'], $after['cycle']['sequence']);
        $this->assertSame($loads, DB::table('participant_receipt_contexts')->count());
        $this->assertDatabaseCount('horizon_tragedies', 0);
        $this->assertSame('no_available_move', DB::table('cycles')->where('id', $before['cycle']['id'])->value('rest_reason'));
    }
}
