<?php

namespace Tests\Feature\Api;

use App\Services\Observables\LineageMeasurementService;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\EvidenceLifecycleAudit;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
class ParticipationHttpHarnessTest extends ScriptedParticipationTestCase
{
    use EvidenceLifecycleAudit, PersistCommitHttpFixture;

    #[Group('integration')]
    public function test_real_http_actions_reach_an_operational_commit(): void
    {
        [$actor, $initial] = $this->beginPersistJourney('explorer');
        $origin = $initial['current_proposition_id'];
        foreach (array_keys($this->persistJourneySteps) as $index) {
            $state = $this->executePersistJourneyStep($actor, $index);
            $packet = $this->auditEvidencePacket($state);
            if ($this->persistJourneySteps[$index]['result'] === 'p15'
                && $this->persistJourneySteps[$index]['input']['action'] === 'proposition.reconcile') {
                foreach (['r0', 'r1', 'r3', 'r4', 'r5'] as $reference) {
                    $this->assertContains($this->persistJourneyIds[$reference], array_column($packet['receipts'], 'id'));
                }
                $this->assertNotContains($this->persistJourneyIds['rp8'], array_column($packet['receipts'], 'id'),
                    'Unattached sibling examination is excluded; r4 qualifies through its actual consumption by p13');
            }
        }
        $this->assertNotSame($origin, $state['last_commitment']['proposition_id']);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $this->assertSame($state['last_commitment']['proposition_id'], $state['plan']['gate_id']);
        $snapshot = DB::table('commitment_snapshots')->where('proposition_id', $state['last_commitment']['proposition_id'])->sole();
        $this->assertSame(LineageMeasurementService::VERSION, $snapshot->calculator_version);
        $this->assertSame($snapshot->depth_direction, $snapshot->cadence_direction);
        $this->assertSame($snapshot->proposition_id, DB::table('cycles')->where('id', $state['cycle']['id'])->value('entry_committal_id'));
        $producingCycle = DB::table('propositions as proposition')->join('phases as phase', 'phase.id', '=', 'proposition.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('proposition.id', $snapshot->proposition_id)->first(['cycle.entry_committal_id']);
        $this->assertSame($origin, $producingCycle->entry_committal_id);
        $this->assertGreaterThan(0, DB::table('exploration_events')->count());
        $this->assertSame([3, 6], DB::table('receipt_consumptions')->join('phases', 'phases.id', '=', 'receipt_consumptions.phase_id')
            ->distinct()->orderBy('kind')->pluck('kind')->all());
    }

    public function test_stale_http_contribution_is_rejected_without_creating_a_second_proposition(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('explorer');
        $before = $this->enterParticipant($actor, true);
        $this->postContribution($actor, $before['plan']['phase']['id'], ['action' => 'proposition.variant'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('propositions', 1);
        $this->assertSame($before['plan']['phase']['id'], $this->readParticipation($actor)['plan']['phase']['id']);
        $this->discoverGate($actor);
        $input = ['action' => 'proposition.variant', 'statement' => 'One legitimate distinction.'];
        $after = $this->contribute($actor, $input);
        $this->assertSame('persist', $after['plan']['phase']['kind']);
        $this->postContribution($actor, $before['plan']['phase']['id'], $input)->assertConflict();
        $this->assertSame($after['plan']['phase']['id'], $this->readParticipation($actor)['plan']['phase']['id']);
        $this->assertDatabaseCount('propositions', 2);
    }

    #[Group('constitutional')]
    public function test_retired_receipt_actions_are_rejected_without_mutating_memory_or_blocking_rest(): void
    {
        ['actor' => $actor, 'state' => $before] = $this->developPersist('rest-reader');
        $this->assertActionAvailable($before, 'participant.rest');
        foreach (['receipt.read', 'receipt.replay'] as $action) {
            $this->assertActionUnavailable($before, $action);
            $this->postContribution($actor, $before['plan']['phase']['id'], ['action' => $action, 'receipt_id' => (string) str()->ulid()])->assertUnprocessable();
        }
        $this->assertDatabaseCount('participant_receipt_contexts', 2);
        $rested = $this->contribute($actor, ['action' => 'participant.rest']);
        $this->assertGreaterThan($before['cycle']['sequence'], $rested['cycle']['sequence']);
    }

    public function test_proposition_search_limit_is_enforced_by_the_http_contract(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('rest-reader');
        $this->enterParticipant($actor, true);
        $this->discoverGate($actor);
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Searchable distinction']);
        $this->assertActionAvailable($state, 'instrument.execute');
        $target = $state['plan']['gate_id'];

        foreach ([0, 11, 1.5, '5'] as $limit) {
            $this->postContribution($actor, $state['plan']['phase']['id'], [
                'action' => 'instrument.execute',
                'proposition_id' => $target,
                'instrument' => ['kind' => 'proposition_search', 'limit' => $limit],
            ])->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('evidence_executions')->where('kind', 'proposition_search')->where('phase_id', $state['plan']['phase']['id'])->count());

        $this->postContribution($actor, $state['plan']['phase']['id'], [
            'action' => 'instrument.execute',
            'proposition_id' => $target,
            'instrument' => ['kind' => 'proposition_search', 'limit' => 10],
        ])->assertOk();
        $this->assertSame(1, DB::table('evidence_executions')->where('kind', 'proposition_search')->where('phase_id', $state['plan']['phase']['id'])->count());
    }

    public function test_harness_rejects_direct_fixture_writes_before_they_execute(): void
    {
        $this->startSubstrate();
        try {
            DB::table('substrates')->where('slug', $this->substrateSlug)->update(['title' => 'Forbidden fixture write']);
            $this->fail('The direct fixture write was permitted.');
        } catch (LogicException $exception) {
            $this->assertSame('HTTP harness forbids fixture writes outside an application request.', $exception->getMessage());
        }
        $this->getJson('/api/substrates/'.$this->substrateSlug)->assertOk()->assertJsonPath('data.title', 'HTTP lifecycle');
    }

    #[Group('constitutional')]
    public function test_rest_rejects_productive_search_generation_and_accepts_a_personally_actionless_phase(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('rest-reader');
        $initial = $this->enterParticipant($actor, true);
        $this->assertActionUnavailable($initial, 'participant.rest');
        $this->postContribution($actor, $initial['plan']['phase']['id'], ['action' => 'participant.rest'])
            ->assertUnprocessable();
        $this->assertSame($initial['plan']['phase']['id'], $this->readParticipation($actor)['plan']['phase']['id']);
        $this->discoverGate($actor);
        $persist = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'An informational encounter']);
        $before = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $persist['plan']['gate_id'],
            'instrument' => ['kind' => 'proposition_search', 'limit' => 3]]);
        $this->assertSame($persist['plan']['phase'], $before['plan']['phase']);
        $this->assertActionUnavailable($before, 'receipt.read');
        $this->assertActionAvailable($before, 'proposition.variant');
        $this->assertActionUnavailable($before, 'participant.rest');
        $before = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Develop the observed distinction']);
        $this->assertActionAvailable($before, 'participant.rest');
        $loads = DB::table('participant_receipt_contexts')->count();
        $after = $this->contribute($actor, ['action' => 'participant.rest']);
        $this->assertGreaterThan($before['cycle']['sequence'], $after['cycle']['sequence']);
        $this->assertSame($loads, DB::table('participant_receipt_contexts')->count());
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertSame('no_available_move', DB::table('cycles')->where('id', $before['cycle']['id'])->value('rest_reason'));
    }
}
