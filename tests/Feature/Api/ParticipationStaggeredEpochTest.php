<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('http-participation')]
#[Group('constitutional')]
class ParticipationStaggeredEpochTest extends ParticipationHttpTestCase
{
    public function test_one_natural_failure_spends_two_positions_that_reopen_only_on_their_own_variants(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('staggered-explorer');
        $origin = $this->enterParticipant($actor, true)['current_proposition_id'];
        $a = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Distinguish opportunity A.'])['current_proposition_id'];
        $originObserver = $this->joinParticipant('distinguish-observer');
        $this->enterParticipant($originObserver);
        $slot = $this->driveUntil($actor, fn ($state) => $state['plan']['phase']['kind'] === 'disperse');
        $observer = $this->joinParticipant('disperse-observer');
        $this->enterParticipant($observer);
        $parked = $this->driveUntil($observer, fn ($state) => $state['plan']['phase']['kind'] === 'disperse', maxActions: 10);
        $this->assertSame($slot['plan']['gate_id'], $parked['plan']['gate_id']);
        $witness = $this->joinParticipant('disperse-witness');
        $this->enterParticipant($witness);
        $witnessState = $this->driveUntil($witness, fn ($state) => $state['plan']['phase']['kind'] === 'disperse', maxActions: 10);
        $this->assertSame($slot['plan']['gate_id'], $witnessState['plan']['gate_id']);
        $b = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Disperse opportunity B.'])['current_proposition_id'];
        for ($step = 0; $step < 100; $step++) {
            $state = $this->readParticipation($actor);
            $aState = $this->visibleProposition($this->readParticipation($originObserver), $a);
            $bState = $this->visibleProposition($this->readParticipation($observer), $b);
            if ($aState['spent'] && $bState['spent']) {
                break;
            }
            $input = $this->chooseContribution($state);
            if ($state['plan']['phase']['kind'] === 'mediate' && $input['action'] === 'attestation.create') {
                $refute = collect($this->actionDefinitions($state)['attestation.create']['anyOf'])
                    ->first(fn ($choice) => $choice['properties']['direction']['enum'][0] === 'refute');
                if ($refute !== null) {
                    $input['direction'] = 'refute';
                    $input['proposition_id'] = $refute['properties']['proposition_id']['enum'][0];
                } elseif (isset($this->actionDefinitions($state)['instrument.execute'])) {
                    $input = ['action' => 'instrument.execute',
                        'proposition_id' => $this->actionDefinitions($state)['instrument.execute']['properties']['proposition_id']['enum'][0],
                        'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/staggered/'.$step]];
                }
            }
            $this->contribute($actor, $input);
        }
        $this->assertTrue($aState['spent'], $this->traceMessage($state));
        $this->assertTrue($bState['spent'], $this->traceMessage($state));
        $history = DB::table('continuation_failures')->whereIn('proposition_id', [$a, $b])->orderBy('proposition_id')->get();
        $this->assertCount(2, $history);
        $this->assertCount(1, $history->pluck('phase_id')->unique(), 'Both failures must come from the same real failed continuation.');
        $this->assertSame($origin, $this->readParticipation($originObserver)['plan']['gate_id']);
        $this->contribute($originObserver, ['action' => 'proposition.variant', 'statement' => 'Advance only A.']);
        $afterA = $this->visibleProposition($this->readParticipation($actor), $a);
        $stillB = $this->visibleProposition($this->readParticipation($observer), $b);
        $this->assertFalse($afterA['spent']);
        $this->assertSame($aState['exploration_epoch'] + 1, $afterA['exploration_epoch']);
        $this->assertTrue($stillB['spent']);
        $this->assertSame($bState['exploration_epoch'], $stillB['exploration_epoch']);
        $this->contribute($observer, ['action' => 'proposition.variant', 'statement' => 'Advance only B.']);
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
