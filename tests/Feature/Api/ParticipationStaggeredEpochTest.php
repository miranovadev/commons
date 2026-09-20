<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationStaggeredEpochTest extends ScriptedParticipationTestCase
{
    public function test_one_natural_failure_spends_two_positions_that_reopen_only_on_their_own_variants(): void
    {
        // The fixed script includes all arrivals, observations and failed Rest.
        // It asserts the returned state after each of these 28 API actions.
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/staggered-epochs.json', 28);
        $actor = $journey['participants']['staggered-explorer'];
        $originObserver = $journey['participants']['distinguish-observer'];
        $observer = $journey['participants']['disperse-observer'];
        $witness = $journey['participants']['disperse-witness'];
        $origin = $journey['responses'][0]['current_proposition_id'];
        $a = $journey['responses'][1]['current_proposition_id'];
        $b = $journey['responses'][16]['current_proposition_id'];
        $slot = $journey['responses'][7];
        $parked = $journey['responses'][11];
        $witnessState = $journey['responses'][15];
        $this->assertSame($slot['plan']['gate_id'], $parked['plan']['gate_id']);
        $this->assertSame($slot['plan']['gate_id'], $witnessState['plan']['gate_id']);
        $state = $journey['responses'][27];
        $aState = $this->visibleProposition($this->readParticipation($originObserver), $a);
        $bState = $this->visibleProposition($this->readParticipation($observer), $b);
        $this->assertTrue($aState['spent'], $this->traceMessage($state));
        $this->assertTrue($bState['spent'], $this->traceMessage($state));
        $history = DB::table('continuation_failures')->whereIn('proposition_id', [$a, $b])->orderBy('proposition_id')->get();
        $this->assertCount(2, $history);
        $this->assertCount(1, $history->pluck('phase_id')->unique(), 'Both failures must come from the same real failed continuation.');
        $this->assertSame($origin, $this->readParticipation($originObserver)['plan']['gate_id']);
        $this->continueParticipationScript(1); // Only A's new Variant, with its fixed expected state.
        $afterA = $this->visibleProposition($this->readParticipation($actor), $a);
        $stillB = $this->visibleProposition($this->readParticipation($observer), $b);
        $this->assertFalse($afterA['spent']);
        $this->assertSame($aState['exploration_epoch'] + 1, $afterA['exploration_epoch']);
        $this->assertTrue($stillB['spent']);
        $this->assertSame($bState['exploration_epoch'], $stillB['exploration_epoch']);
        $this->continueParticipationScript(1); // Only B's new Variant.
        $this->assertParticipationScriptComplete();
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
