<?php

namespace Tests\Feature\Api;

use App\Services\Observables\LineageMeasurementService;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('http-participation')]
class ParticipationHttpHarnessTest extends ParticipationHttpTestCase
{
    public function test_real_http_actions_reach_an_operational_commit(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('explorer');
        $origin = $this->enterParticipant($actor, true)['current_proposition_id'];
        $state = $this->reachFirstCommit($actor, $origin);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $snapshot = DB::table('commitment_snapshots')->where('proposition_id', $state['last_commitment']['proposition_id'])->sole();
        $this->assertSame(LineageMeasurementService::VERSION, $snapshot->calculator_version);
        $this->assertSame($snapshot->depth_direction, $snapshot->cadence_direction);
        $this->assertGreaterThan(0, DB::table('continuation_failures')->count());
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
        $input = ['action' => 'proposition.variant', 'statement' => 'One legitimate distinction.'];
        $after = $this->contribute($actor, $input);
        $this->assertSame('persist', $after['plan']['phase']['kind']);
        $this->postContribution($actor, $before['plan']['phase']['id'], $input)->assertConflict();
        $this->assertSame($after['plan']['phase']['id'], $this->readParticipation($actor)['plan']['phase']['id']);
        $this->assertDatabaseCount('propositions', 2);
    }

    public function test_optional_receipt_read_leaves_rest_available_without_advancing_the_phase(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('reader');
        $this->enterParticipant($actor, true);
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'A distinction.']);
        $before = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'A persistent candidate.']);
        $this->assertSame('constrain', $before['plan']['phase']['kind']);
        $this->assertActionAvailable($before, 'participant.rest');
        $this->assertActionAvailable($before, 'receipt.read');
        $receipt = $this->actionDefinitions($before)['receipt.read']['properties']['receipt_id']['enum'][0];
        $after = $this->contribute($actor, ['action' => 'receipt.read', 'receipt_id' => $receipt]);
        $this->assertSame($before['plan']['phase']['id'], $after['plan']['phase']['id']);
        $this->assertActionAvailable($after, 'participant.rest');
        $rested = $this->contribute($actor, ['action' => 'participant.rest']);
        $this->assertGreaterThan($after['cycle']['sequence'], $rested['cycle']['sequence']);
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
}
