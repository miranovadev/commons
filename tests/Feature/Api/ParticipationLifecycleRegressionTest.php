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
        $revisitor = $this->joinParticipant('route-revisitor');
        $this->enterParticipant($revisitor);
        $committed = $this->reachFirstCommit($actor, $origin);
        $commit = $committed['last_commitment']['proposition_id'];
        $source = DB::table('supersessions')->where('type', 'commit')->where('target_proposition_id', $commit)->sole()->source_proposition_id;
        $biasGate = DB::table('supersessions')->where('type', 'reclassify')->where('target_proposition_id', $source)->sole()->source_proposition_id;
        $revisit = $this->driveUntil($revisitor, fn ($state) => ($state['plan']['phase']['kind'] ?? null) === 'bias'
            && $state['plan']['gate_id'] === $biasGate, maxActions: 60);
        $this->assertActionAvailable($revisit, 'proposition.traverse');
        $order = $this->contribute($revisitor, ['action' => 'proposition.traverse', 'proposition_id' => $source]);
        $this->assertSame('order', $order['plan']['phase']['kind']);
        $next = $this->contribute($revisitor, ['action' => 'proposition.traverse', 'proposition_id' => $commit]);
        $this->assertSame('distinguish', $next['plan']['phase']['kind']);
        $this->assertSame($commit, $next['plan']['gate_id']);
    }

    public function test_distinguish_under_another_committal_does_not_advance_origins_epoch(): void
    {
        $this->startSubstrate();
        $explorer = $this->joinParticipant('failure-explorer');
        $origin = $this->enterParticipant($explorer, true)['current_proposition_id'];
        $signal = $this->contribute($explorer, ['action' => 'proposition.variant', 'statement' => 'An original distinction.'])['current_proposition_id'];
        $relays = [];
        // Enter before the failure; these participants can continue from Persist without
        // creating another Origin Variant or changing the parked explorer's position.
        foreach (['relay-a', 'relay-b'] as $callsign) {
            $relay = $this->joinParticipant($callsign);
            $this->enterParticipant($relay);
            $this->driveUntil($relay, fn ($state) => $state['plan']['phase']['kind'] === 'persist', maxActions: 5);
            $relays[] = $relay;
        }
        $spent = $this->driveUntil($explorer, fn ($state) => $state['plan']['phase']['kind'] === 'distinguish'
            && (collect($state['plan']['write_options'])->firstWhere('id', $signal)['spent'] ?? false), maxActions: 50);
        $before = $this->visibleProposition($spent, $signal);
        $this->assertTrue($before['spent']);
        $this->assertSame($origin, $spent['plan']['gate_id']);
        $history = $this->continuationFailures($signal);
        $committer = null;
        for ($step = 0; $step < 40 && $committer === null; $step++) {
            $progressed = false;
            foreach ($relays as $relay) {
                $state = $this->readParticipation($relay);
                if (($state['last_commitment']['proposition_id'] ?? $origin) !== $origin) {
                    $committer = $relay;
                    break;
                }
                if (array_diff(array_keys($this->actionDefinitions($state)), ['participant.rest', 'receipt.read']) !== []) {
                    $input = $this->chooseContribution($state);
                    if ($input['action'] === 'attestation.create') {
                        // Ground the fresh continuation rather than switching an older judgment.
                        $choice = collect($this->actionDefinitions($state)['attestation.create']['anyOf'])
                            ->first(fn ($choice) => $choice['properties']['direction']['enum'][0] === $input['direction']);
                        $input['proposition_id'] = collect($choice['properties']['proposition_id']['enum'])->last();
                    }
                    $this->contribute($relay, $input);
                    $progressed = true;
                    break;
                }
            }
            $this->assertTrue($progressed || $committer !== null, 'The HTTP relay has no forward contribution. '.$this->traceMessage($state));
        }
        $this->assertNotNull($committer, $this->traceMessage());
        $committed = $this->readParticipation($committer);
        $this->assertSame($spent['context_horizon_id'], $committed['context_horizon_id']);
        $this->assertSame('distinguish', $committed['plan']['phase']['kind']);
        $this->assertNotSame($origin, $committed['plan']['gate_id']);
        $this->assertTrue($this->visibleProposition($this->readParticipation($explorer), $signal)['spent']);
        $this->contribute($committer, ['action' => 'proposition.variant', 'statement' => 'A distinct Committal slot.']);
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
