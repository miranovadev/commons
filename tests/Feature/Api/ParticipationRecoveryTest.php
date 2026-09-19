<?php

namespace Tests\Feature\Api;

use App\Models\Participant;
use App\Services\PotentialChargeService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('http-participation')]
class ParticipationRecoveryTest extends ParticipationHttpTestCase
{
    public function test_rest_on_a_revisited_route_cannot_jump_to_its_untraversed_commit(): void
    {
        $this->startSubstrate();
        $author = $this->joinParticipant('route-author');
        $origin = $this->enterParticipant($author, true)['current_proposition_id'];
        $observer = $this->joinParticipant('route-observer');
        $this->enterParticipant($observer);
        $committed = $this->reachFirstCommit($author, $origin);
        $commit = $committed['last_commitment']['proposition_id'];
        $source = DB::table('supersessions')->where('type', 'commit')->where('target_proposition_id', $commit)->sole()->source_proposition_id;
        $biasGate = DB::table('supersessions')->where('type', 'reclassify')->where('target_proposition_id', $source)->sole()->source_proposition_id;
        $before = $this->driveUntil($observer, fn ($state) => $state['plan']['phase']['kind'] === 'bias'
            && $state['plan']['gate_id'] === $biasGate, maxActions: 60);
        $this->assertActionAvailable($before, 'participant.rest');

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
        $after = $this->contribute($observer, ['action' => 'participant.rest']);
        $this->assertSame($origin, $after['plan']['gate_id']);
        $this->assertSame($before['context_horizon_id'], $after['context_horizon_id']);
        $this->assertSame('distinguish', $after['plan']['phase']['kind']);
        $this->assertSame($authorPosition, $this->readParticipation($author)['current_proposition_id']);
        $this->assertSame($owners, DB::table('cycles')->whereIn('id', array_keys($owners))->orderBy('id')->pluck('context_horizon_id', 'id')->all());
    }
}
