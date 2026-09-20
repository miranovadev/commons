<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationLifecycleRegressionTest extends ScriptedParticipationTestCase
{
    public function test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/bias-revisit.json', 49);
        $revisitor = $journey['participants']['route-revisitor'];
        $committed = $journey['responses'][48];
        $commit = $committed['last_commitment']['proposition_id'];
        $source = DB::table('supersessions')->where('type', 'commit')->where('target_proposition_id', $commit)->sole()->source_proposition_id;
        $biasGate = DB::table('supersessions')->where('type', 'reclassify')->where('target_proposition_id', $source)->sole()->source_proposition_id;
        $revisit = $this->continueParticipationScript(5)['responses'][53];
        $this->assertSame($biasGate, $revisit['plan']['gate_id']);
        $history = DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson();
        $snapshot = DB::table('commitment_snapshots')->where('proposition_id', $commit)->sole();
        $propositions = DB::table('propositions')->count();
        $attestations = DB::table('attestations')->count();
        $this->assertActionAvailable($revisit, 'proposition.traverse');
        $this->assertActionUnavailable($revisit, 'participant.rest');
        foreach (['attestation.create', 'instrument.execute', 'proposition.revise'] as $action) {
            $this->assertStringNotContainsString($source, json_encode($this->actionDefinitions($revisit)[$action] ?? []));
        }
        $order = $this->continueParticipationScript(1)['responses'][54];
        $this->assertSame('order', $order['plan']['phase']['kind']);
        $next = $this->continueParticipationScript(1)['responses'][55];
        $this->assertParticipationScriptComplete();
        $this->assertSame('distinguish', $next['plan']['phase']['kind']);
        $this->assertSame($commit, $next['plan']['gate_id']);
        $this->assertEquals($snapshot, DB::table('commitment_snapshots')->where('proposition_id', $commit)->sole());
        $this->assertSame($propositions, DB::table('propositions')->count());
        $this->assertSame($attestations, DB::table('attestations')->count());
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson());
    }

    public function test_distinguish_under_another_committal_does_not_advance_origins_epoch(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/committal-epochs.json', 23);
        $explorer = $journey['participants']['failure-explorer'];
        $origin = $journey['responses'][0]['current_proposition_id'];
        $signal = $journey['responses'][1]['current_proposition_id'];
        $spent = $journey['responses'][22];
        $before = $this->visibleProposition($spent, $signal);
        $this->assertTrue($before['spent']);
        $this->assertSame($origin, $spent['plan']['gate_id']);
        $history = $this->continuationFailures($signal);
        $journey = $this->continueParticipationScript(11);
        $committer = $journey['participants']['relay-a'];
        $committed = $journey['responses'][33];
        $this->assertSame($spent['context_horizon_id'], $committed['context_horizon_id']);
        $this->assertSame('distinguish', $committed['plan']['phase']['kind']);
        $this->assertNotSame($origin, $committed['plan']['gate_id']);
        $this->assertTrue($this->visibleProposition($this->readParticipation($explorer), $signal)['spent']);
        $this->continueParticipationScript(1);
        $this->assertParticipationScriptComplete();
        $afterState = $this->readParticipation($explorer);
        $this->assertSame($spent['plan']['phase']['id'], $afterState['plan']['phase']['id']);
        $this->assertSame($origin, $afterState['plan']['gate_id']);
        $after = $this->visibleProposition($afterState, $signal);
        $this->assertSame($before['exploration_epoch'], $after['exploration_epoch'],
            'A Variant under another Committal changed the original Distinguish slot. '
            .json_encode(['before' => $before, 'after' => $after]).$this->traceMessage($afterState));
        $this->assertTrue($after['spent']);
        $this->assertSame($history, $this->continuationFailures($signal));
    }

    public function test_disperse_under_another_constrain_gate_does_not_reopen_a_spent_slot(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/disperse-epochs.json', 35);
        $observer = $journey['participants']['disperse-observer'];
        $firstSlot = $journey['responses'][6];
        $firstGate = $firstSlot['plan']['gate_id'];
        $parked = $journey['responses'][10];
        $this->assertSame($firstGate, $parked['plan']['gate_id']);
        $first = $journey['responses'][11]['current_proposition_id'];
        $secondSlot = $journey['responses'][34];
        $this->assertNotSame($firstGate, $secondSlot['plan']['gate_id']);
        $beforeState = $this->readParticipation($observer);
        $before = $this->visibleProposition($beforeState, $first);
        $this->assertTrue($before['spent'], $this->traceMessage());
        $failures = $this->continuationFailures($first);
        $this->assertNotEmpty($failures);
        $this->assertSame($firstSlot['context_horizon_id'], $secondSlot['context_horizon_id']);
        $this->assertSame($firstSlot['last_commitment']['proposition_id'], $secondSlot['last_commitment']['proposition_id']);
        $this->continueParticipationScript(1);
        $this->assertParticipationScriptComplete();
        $afterState = $this->readParticipation($observer);
        $this->assertSame($beforeState['plan']['phase']['id'], $afterState['plan']['phase']['id']);
        $this->assertSame($firstGate, $afterState['plan']['gate_id']);
        $after = $this->visibleProposition($afterState, $first);
        $this->assertSame($failures, $this->continuationFailures($first));
        $this->assertTrue($after['spent'], 'Another Disperse slot reopened this spent option. '
            .json_encode(['before' => $before, 'after' => $after]).$this->traceMessage($afterState));
        $this->assertSame($before['exploration_epoch'], $after['exploration_epoch']);
    }

    private function continuationFailures(string $proposition): array
    {
        return DB::table('continuation_failures')->where('proposition_id', $proposition)
            ->orderBy('context_horizon_id')->orderBy('structural_position')->orderBy('exploration_epoch')
            ->get()->map(fn ($row) => (array) $row)->all();
    }
}
