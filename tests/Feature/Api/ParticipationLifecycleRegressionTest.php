<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationLifecycleRegressionTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;

    public function test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit(): void
    {
        [, $revisitor, , $committed] = $this->persistCommitWithObserver('explorer', 'route-revisitor');
        $commit = $committed['last_commitment']['proposition_id'];
        $source = DB::table('supersessions')->where('type', 'commit')->where('target_proposition_id', $commit)->sole()->source_proposition_id;
        $biasGate = DB::table('supersessions')->where('type', 'reclassify')->where('target_proposition_id', $source)->sole()->source_proposition_id;
        $revisit = $this->revisitPersistCommit($revisitor);
        $this->assertSame($biasGate, $revisit['plan']['gate_id']);
        $history = DB::table('exploration_events')->orderBy('proposition_id')->orderBy('phase_id')->get()->toJson();
        $snapshot = DB::table('commitment_snapshots')->where('proposition_id', $commit)->sole();
        $propositions = DB::table('propositions')->count();
        $attestations = DB::table('attestations')->count();
        $this->assertActionAvailable($revisit, 'proposition.traverse');
        $this->assertActionUnavailable($revisit, 'participant.rest');
        foreach (['attestation.create', 'instrument.execute', 'proposition.revise'] as $action) {
            $this->assertStringNotContainsString($source, json_encode($this->actionDefinitions($revisit)[$action] ?? []));
        }
        $order = $this->contribute($revisitor, ['action' => 'proposition.traverse', 'proposition_id' => $source]);
        $this->assertSame('order', $order['plan']['phase']['kind']);
        $next = $this->contribute($revisitor, ['action' => 'proposition.traverse', 'proposition_id' => $commit]);
        $this->assertSame('distinguish', $next['plan']['phase']['kind']);
        $this->assertSame($commit, $next['plan']['gate_id']);
        $this->assertEquals($snapshot, DB::table('commitment_snapshots')->where('proposition_id', $commit)->sole());
        $this->assertSame($propositions, DB::table('propositions')->count());
        $this->assertSame($attestations, DB::table('attestations')->count());
        $this->assertSame($history, DB::table('exploration_events')->orderBy('proposition_id')->orderBy('phase_id')->get()->toJson());
    }
}
