<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\PersistExplorationHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipantExplorationHttpTest extends ScriptedParticipationTestCase
{
    use PersistExplorationHttpFixture;

    public function test_real_personal_rest_leaves_staggered_observers_free_and_variant_does_not_erase_history(): void
    {
        ['actor' => $actor, 'originObserver' => $originObserver, 'observer' => $observer,
            'witness' => $witness, 'origin' => $origin, 'a' => $a, 'b' => $b] = $this->personalExplorationJourney();
        $nova = $this->readParticipation($actor);
        $luna = $this->readParticipation($originObserver);
        $this->assertTrue($this->visibleProposition($nova, $a)['explored']);
        $this->assertFalse($this->visibleProposition($luna, $a)['explored']);
        $this->assertFalse($this->visibleProposition($this->readParticipation($observer), $b)['explored']);
        $this->assertFalse($this->visibleProposition($this->readParticipation($witness), $b)['explored']);
        $this->assertActionAvailable($nova, 'proposition.variant');
        $this->assertActionUnavailable($luna, 'proposition.variant');
        $this->assertActionAvailable($luna, 'attestation.create');
        $this->assertDatabaseCount('exploration_events', 1);
        $history = DB::table('exploration_events')->get()->toJson();
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Nova explores a new alternative']);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $after = $this->readParticipation($originObserver);
        $this->assertSame($luna['plan']['phase']['id'], $after['plan']['phase']['id']);
        $this->assertSame($origin, $after['plan']['gate_id']);
        $this->assertFalse($this->visibleProposition($after, $a)['explored']);
        $this->assertActionUnavailable($after, 'proposition.variant');
        // Luna encounters the existing head through her own recorded arrivals.
        $this->judge($observer, $b);
        $this->contribute($observer, ['action' => 'participant.rest']);
        $this->assertDatabaseCount('exploration_events', 2);
        $this->assertTrue($this->visibleProposition($this->readParticipation($observer), $a)['explored']);
        $this->assertFalse($this->visibleProposition($this->readParticipation($originObserver), $a)['explored']);
    }
}
