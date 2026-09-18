<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('http-participation')]
class ParticipationLifecycleRegressionTest extends ParticipationHttpTestCase
{
    public function test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('explorer');
        $origin = $this->enterParticipant($actor, true)['current_proposition_id'];
        $committed = $this->reachFirstCommit($actor, $origin);
        $commit = $committed['last_commitment']['proposition_id'];
        $source = DB::table('supersessions')->where('type', 'commit')->where('target_proposition_id', $commit)->sole()->source_proposition_id;
        $biasGate = DB::table('supersessions')->where('type', 'reclassify')->where('target_proposition_id', $source)->sole()->source_proposition_id;
        $revisit = $this->driveUntil($actor, fn ($state) => ($state['plan']['phase']['kind'] ?? null) === 'bias'
            && $state['plan']['gate_id'] === $biasGate, maxActions: 30);
        $this->assertActionAvailable($revisit, 'proposition.traverse');
        $order = $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $source]);
        $this->assertSame('order', $order['plan']['phase']['kind']);
        $next = $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $commit]);
        $this->assertSame('distinguish', $next['plan']['phase']['kind']);
        $this->assertSame($commit, $next['plan']['gate_id']);
    }

    public function test_distinguish_under_another_committal_does_not_advance_origins_epoch(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('explorer');
        $origin = $this->enterParticipant($actor, true)['current_proposition_id'];
        $observer = $this->joinParticipant('origin-observer');
        $this->enterParticipant($observer);
        $committed = $this->reachFirstCommit($actor, $origin);
        $before = $this->readParticipation($observer);
        $this->assertSame($origin, $before['plan']['gate_id']);
        $this->assertSame($before['context_horizon_id'], $committed['context_horizon_id']);
        $this->assertNotSame($origin, $committed['plan']['gate_id']);
        $prior = collect($before['plan']['write_options'])->firstWhere('spent', true);
        $this->assertNotNull($prior, 'Origin must have a naturally spent option. '.$this->traceMessage($before));
        $failures = $this->continuationFailures($prior['id']);
        $this->assertNotEmpty($failures);
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'First distinction below a different Committal.']);
        $after = $this->readParticipation($observer);
        $this->assertSame($before['plan']['phase']['id'], $after['plan']['phase']['id']);
        $this->assertSame($origin, $after['plan']['gate_id']);
        $this->assertSame($failures, $this->continuationFailures($prior['id']));
        $current = $this->visibleProposition($after, $prior['id']);
        $this->assertSame($prior['exploration_epoch'], $current['exploration_epoch'],
            'A Variant under another Committal changed the original Distinguish slot. '
            .json_encode(['before' => $prior, 'after' => $current]).$this->traceMessage($after));
        $this->assertTrue($current['spent']);
    }

    public function test_disperse_under_another_constrain_gate_does_not_reopen_a_spent_slot(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('explorer');
        $this->enterParticipant($actor, true);
        $firstSlot = $this->driveUntil($actor, fn ($state) => $state['plan']['phase']['kind'] === 'disperse');
        $firstGate = $firstSlot['plan']['gate_id'];
        $observer = $this->joinParticipant('disperse-observer');
        $this->enterParticipant($observer);
        $parked = $this->driveUntil($observer, fn ($state) => $state['plan']['phase']['kind'] === 'disperse', maxActions: 10);
        $this->assertSame($firstGate, $parked['plan']['gate_id']);
        $first = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'First Disperse slot.'])['current_proposition_id'];
        $secondSlot = $this->driveUntil($actor, fn ($state) => $state['plan']['phase']['kind'] === 'disperse'
            && $state['plan']['gate_id'] !== $firstGate, maxActions: 70);
        $beforeState = $this->readParticipation($observer);
        $before = $this->visibleProposition($beforeState, $first);
        $this->assertTrue($before['spent'], $this->traceMessage());
        $failures = $this->continuationFailures($first);
        $this->assertNotEmpty($failures);
        $this->assertSame($firstSlot['context_horizon_id'], $secondSlot['context_horizon_id']);
        $this->assertSame($firstSlot['last_commitment']['proposition_id'], $secondSlot['last_commitment']['proposition_id']);
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'First Variant in the independent Disperse slot.']);
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
