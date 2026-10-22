<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\PersistEpochHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationStaggeredEpochTest extends ScriptedParticipationTestCase
{
    use PersistEpochHttpFixture;

    public function test_one_natural_failure_spends_two_positions_that_reopen_only_on_their_own_variants(): void
    {
        ['actor' => $actor, 'originObserver' => $originObserver, 'observer' => $observer,
            'witness' => $witness, 'origin' => $origin, 'a' => $a, 'b' => $b,
            'slot' => $slot, 'parked' => $parked, 'witnessState' => $witnessState,
            'state' => $state] = $this->stage2AEpochJourney();
        $this->assertSame($slot['plan']['gate_id'], $parked['plan']['gate_id']);
        $this->assertSame($slot['plan']['gate_id'], $witnessState['plan']['gate_id']);
        $aState = $this->visibleProposition($this->readParticipation($originObserver), $a);
        $bState = $this->visibleProposition($this->readParticipation($observer), $b);
        $this->assertTrue($aState['spent'], $this->traceMessage($state));
        $this->assertTrue($bState['spent'], $this->traceMessage($state));
        $history = DB::table('continuation_failures')->whereIn('proposition_id', [$a, $b])->orderBy('proposition_id')->get();
        $this->assertCount(2, $history);
        $this->assertCount(1, $history->pluck('phase_id')->unique(), 'Both failures must come from the same real failed continuation.');
        $this->assertSame($origin, $this->readParticipation($originObserver)['plan']['gate_id']);
        $this->contribute($originObserver, ['action' => 'proposition.variant', 'statement' => 'Renew Distinguish only']);
        $afterA = $this->visibleProposition($this->readParticipation($actor), $a);
        $stillB = $this->visibleProposition($this->readParticipation($observer), $b);
        $this->assertFalse($afterA['spent']);
        $this->assertSame($aState['exploration_epoch'] + 1, $afterA['exploration_epoch']);
        $this->assertTrue($stillB['spent']);
        $this->assertSame($bState['exploration_epoch'], $stillB['exploration_epoch']);
        $this->contribute($observer, ['action' => 'proposition.variant', 'statement' => 'Renew Disperse only']);
        $afterBState = $this->readParticipation($witness);
        $afterB = $this->visibleProposition($afterBState, $b);
        $this->assertSame($witnessState['plan']['phase']['id'], $afterBState['plan']['phase']['id']);
        $this->assertSame($parked['plan']['gate_id'], $afterBState['plan']['gate_id']);
        $this->assertFalse($afterB['spent']);
        $this->assertSame($bState['exploration_epoch'] + 1, $afterB['exploration_epoch']);
        $this->assertStringContainsString($b, json_encode($this->actionDefinitions($afterBState)['attestation.create']));
        $this->assertSame($afterA, $this->visibleProposition($this->readParticipation($actor), $a));
        $this->assertEquals($history, DB::table('continuation_failures')->whereIn('proposition_id', [$a, $b])->orderBy('proposition_id')->get());
    }
}
