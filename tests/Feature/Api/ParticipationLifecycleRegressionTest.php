<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Fixtures\PersistEpochHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationLifecycleRegressionTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;
    use PersistEpochHttpFixture;

    public function test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit(): void
    {
        [, $revisitor, , $committed] = $this->persistCommitWithObserver('explorer', 'route-revisitor');
        $commit = $committed['last_commitment']['proposition_id'];
        $source = DB::table('supersessions')->where('type', 'commit')->where('target_proposition_id', $commit)->sole()->source_proposition_id;
        $biasGate = DB::table('supersessions')->where('type', 'reclassify')->where('target_proposition_id', $source)->sole()->source_proposition_id;
        $revisit = $this->revisitPersistCommit($revisitor);
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
        $order = $this->contribute($revisitor, ['action' => 'proposition.traverse', 'proposition_id' => $source]);
        $this->assertSame('order', $order['plan']['phase']['kind']);
        $next = $this->contribute($revisitor, ['action' => 'proposition.traverse', 'proposition_id' => $commit]);
        $this->assertSame('distinguish', $next['plan']['phase']['kind']);
        $this->assertSame($commit, $next['plan']['gate_id']);
        $this->assertEquals($snapshot, DB::table('commitment_snapshots')->where('proposition_id', $commit)->sole());
        $this->assertSame($propositions, DB::table('propositions')->count());
        $this->assertSame($attestations, DB::table('attestations')->count());
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson());
    }

    public function test_distinguish_under_another_committal_does_not_advance_origins_epoch(): void
    {
        [$committer, $explorer, $initial, $committed] = $this->persistCommitWithObserver('relay-a', 'failure-explorer');
        $origin = $initial['current_proposition_id'];
        $signal = $this->persistJourneyIds['p5'];
        // Re-test the independent second branch in the current epoch. Its
        // old Persist result is spent, so a new Persist Variant is lawful;
        // that new epoch must genuinely test both the reopened and new routes.
        $attest = fn ($id) => $this->contribute($explorer, ['action' => 'attestation.create', 'proposition_id' => $id, 'direction' => 'refute']);
        $traverse = fn ($id) => $this->contribute($explorer, ['action' => 'proposition.traverse', 'proposition_id' => $id]);
        $attest($signal);
        $new = $this->contribute($explorer, ['action' => 'proposition.variant', 'statement' => 'Retest this independent branch']);
        $persist = $new['current_proposition_id'];
        $this->contribute($explorer, ['action' => 'participant.rest']);
        $traverse($signal);
        $attest($this->persistJourneyIds['p6']);
        $this->contribute($explorer, ['action' => 'participant.rest']);
        $traverse($signal);
        $this->observePersist($explorer, $signal, 'independent-committal-slot');
        $new = $attest($persist);
        $receipt = $new['plan']['schema']['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum'][0];
        $new = $this->contribute($explorer, ['action' => 'proposition.reclassify', 'proposition_id' => $persist, 'receipt_id' => $receipt]);
        $constrain = $new['current_proposition_id'];
        $new = $this->contribute($explorer, ['action' => 'proposition.variant', 'statement' => 'Test the downstream continuation']);
        $activity = $new['current_proposition_id'];
        $counterpart = $new['plan']['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum'][0];
        $new = $this->contribute($explorer, ['action' => 'proposition.reconcile', 'other_proposition_id' => $counterpart]);
        $mediated = $new['current_proposition_id'];
        $this->contribute($explorer, ['action' => 'participant.rest']);
        $traverse($signal);
        $traverse($persist);
        $attest($constrain);
        $attest($activity);
        $this->contribute($explorer, ['action' => 'attestation.create', 'proposition_id' => $mediated, 'direction' => 'support']);
        $this->contribute($explorer, ['action' => 'participant.rest']);
        $spent = $this->readParticipation($explorer);
        $before = $this->visibleProposition($spent, $signal);
        $this->assertTrue($before['spent']);
        $this->assertSame($origin, $spent['plan']['gate_id']);
        $history = $this->continuationFailures($signal);
        // The lawful skeptical Commit remains in Settlement. Distinct source
        // slots must remain independent even inside the same Horizon.
        $this->assertSame($spent['context_horizon_id'], $committed['context_horizon_id']);
        $this->assertSame('settlement', $committed['domain']);
        $this->assertSame('distinguish', $committed['plan']['phase']['kind']);
        $this->assertNotSame($origin, $committed['plan']['gate_id']);
        $this->assertTrue($this->visibleProposition($this->readParticipation($explorer), $signal)['spent']);
        $this->contribute($committer, ['action' => 'proposition.variant', 'statement' => 'Develop the descendant Committal']);
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
        ['actor' => $actor, 'observer' => $observer, 'slot' => $firstSlot,
            'parked' => $parked, 'b' => $first] = $this->stage2AEpochJourney();
        $firstGate = $firstSlot['plan']['gate_id'];
        $this->assertSame($firstGate, $parked['plan']['gate_id']);
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Independent Distinguish route']);
        $signal = $state['current_proposition_id'];
        $this->observePersist($actor, $signal, 'other-slot-first');
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Independent Persist route']);
        $persist = $state['current_proposition_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        $this->judge($actor, $signal);
        $this->observePersist($actor, $signal, 'other-slot-second');
        $state = $this->judge($actor, $persist);
        $receipt = $state['plan']['schema']['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum'][0];
        $secondSlot = $this->contribute($actor, ['action' => 'proposition.reclassify', 'proposition_id' => $persist, 'receipt_id' => $receipt]);
        $this->assertNotSame($firstGate, $secondSlot['plan']['gate_id']);
        $beforeState = $this->readParticipation($observer);
        $before = $this->visibleProposition($beforeState, $first);
        $this->assertTrue($before['spent'], $this->traceMessage());
        $failures = $this->continuationFailures($first);
        $this->assertNotEmpty($failures);
        $this->assertSame($firstSlot['context_horizon_id'], $secondSlot['context_horizon_id']);
        $this->assertSame($firstSlot['last_commitment']['proposition_id'], $secondSlot['last_commitment']['proposition_id']);
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Other Disperse slot']);
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
