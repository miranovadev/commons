<?php

namespace Tests\Feature\Api;

use App\Models\Participant;
use App\Services\PotentialChargeService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationRecoveryTest extends ScriptedParticipationTestCase
{
    public function test_successful_revisit_keeps_its_untraversed_commit_out_of_backward_recovery(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/recovery-revisit.json');
        $this->assertParticipationScriptComplete();
        $author = $journey['participants']['route-author'];
        $observer = $journey['participants']['route-observer'];
        $origin = $journey['responses'][0]['current_proposition_id'];
        $committed = $journey['responses'][48];
        $commit = $committed['last_commitment']['proposition_id'];
        $source = DB::table('supersessions')->where('type', 'commit')->where('target_proposition_id', $commit)->sole()->source_proposition_id;
        $biasGate = DB::table('supersessions')->where('type', 'reclassify')->where('target_proposition_id', $source)->sole()->source_proposition_id;
        $before = $journey['responses'][53];
        $this->assertSame($biasGate, $before['plan']['gate_id']);
        // Stage 5 supersedes the Stage 4 expectation of Rest at this broken
        // Bias position: a successful route now has a valid forward Traverse.
        $this->assertActionUnavailable($before, 'participant.rest');
        $this->assertActionAvailable($before, 'proposition.traverse');

        // Consecutive HTTP-produced Phases independently establish this exact path.
        $gates = DB::table('phases')->where('cycle_id', $before['cycle']['id'])->orderBy('kind')
            ->pluck('gate_proposition_id')->all();
        $this->assertSame($origin, $gates[0]);
        $this->assertSame($biasGate, $gates[count($gates) - 1]);
        for ($i = 1; $i < count($gates); $i++) {
            $this->assertTrue(DB::table('supersessions')->where('source_proposition_id', $gates[$i - 1])
                ->where('target_proposition_id', $gates[$i])->exists());
        }
        $participant = Participant::findOrFail($observer);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($participant->position,
            $participant->cycles()->latest('sequence')->first()->contextHorizon, $participant);
        $this->assertSame([$origin], $inspection['eligible_candidate_ids']);
        $this->assertSame(count($gates) - 1, $inspection['selection']['distance']);
        $this->assertNotContains($commit, $inspection['eligible_candidate_ids']);
        $owners = DB::table('cycles')->orderBy('id')->pluck('context_horizon_id', 'id')->all();
        $authorPosition = $this->readParticipation($author)['current_proposition_id'];
        $this->postContribution($observer, $before['plan']['phase']['id'], ['action' => 'participant.rest'])->assertUnprocessable();
        $after = $this->readParticipation($observer);
        $this->assertSame($biasGate, $after['plan']['gate_id']);
        $this->assertSame($before['context_horizon_id'], $after['context_horizon_id']);
        $this->assertSame('bias', $after['plan']['phase']['kind']);
        $this->assertSame($authorPosition, $this->readParticipation($author)['current_proposition_id']);
        $this->assertSame($owners, DB::table('cycles')->whereIn('id', array_keys($owners))->orderBy('id')->pluck('context_horizon_id', 'id')->all());
    }

    public function test_actual_actionless_http_rest_recovers_by_the_traversed_edge_distance(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/recovery-distance.json', 3);
        $actor = $journey['participants']['actionless-recovery'];
        $origin = $journey['responses'][0]['current_proposition_id'];
        $first = $journey['responses'][1];
        $before = $journey['responses'][2];
        foreach ([[$origin, $first['current_proposition_id']], [$first['current_proposition_id'], $before['current_proposition_id']]] as [$source, $target]) {
            $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $source, 'target_proposition_id' => $target]);
        }
        $participant = Participant::findOrFail($actor);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($participant->position,
            $participant->cycles()->latest('sequence')->first()->contextHorizon, $participant);
        $this->assertSame([$origin], $inspection['eligible_candidate_ids']);
        $this->assertSame(2, $inspection['selection']['distance']);
        $after = $this->continueParticipationScript(1)['responses'][3];
        $this->assertParticipationScriptComplete();
        $this->assertSame('distinguish', $after['plan']['phase']['kind']);
        $this->assertSame($origin, $after['current_proposition_id']);
        $this->assertSame($before['context_horizon_id'], $after['context_horizon_id']);
    }
}
