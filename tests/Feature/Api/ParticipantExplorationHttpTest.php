<?php

namespace Tests\Feature\Api;

use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\AcquisitionAttemptService;
use App\Services\ParticipantPromptFactory;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseGenerationService;
use App\Services\PropositionOptionService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\PersistExplorationHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipantExplorationHttpTest extends ScriptedParticipationTestCase
{
    use PersistExplorationHttpFixture;

    public function test_explored_route_to_a_second_disperse_variant(): void
    {
        $journey = $this->personalExplorationJourney();
        $actor = $journey['actor'];
        $state = $this->readParticipation($actor);
        $entry = Phase::findOrFail($state['phase']['id']);
        self::assertSame($journey['origin'], $entry->gate_proposition_id);
        $history = DB::table('exploration_events')->get()->toJson();
        $this->assertDatabaseCount('exploration_events', 1);
        $attempts = DB::table('evidence_executions')->count();
        $votes = DB::table('attestations')->count();
        $p3 = Proposition::findOrFail($journey['constrain']);

        // Independently establish admission at the destination before attempting a hop.
        $probe = (new Phase)->forceFill(['id' => (string) str()->ulid(), 'cycle_id' => $entry->cycle_id,
            'kind' => PhaseKind::Disperse, 'write_register' => $entry->cycle->contextHorizon->domain->registerFor(PhaseKind::Disperse),
            'gate_proposition_id' => $p3->id]);
        $probe->setRelation('gate', $p3)->setRelation('cycle', $entry->cycle);
        $context = new ParticipationContext($probe);
        self::assertSame('required', app(AcquisitionAttemptService::class)->state($probe, $context)['status']);
        self::assertTrue(app(PropositionOptionService::class)->instrumentOptionsFor($probe, $context)->contains('id', $p3->id));
        self::assertFalse(app(PhaseGenerationService::class)->available($probe, 'proposition.variant', $context));
        foreach (['a', 'persist', 'constrain'] as $key) {
            $id = $journey[$key];
            self::assertTrue($this->visibleProposition($state, $id)['explored']);
            $actions = $state['schema']['$defs']['actions'];
            self::assertContains($id, $actions['proposition.traverse']['properties']['proposition_id']['enum']);
            if ($key === 'constrain') {
                self::assertArrayNotHasKey('proposition.reclassify', $actions, 'P2 is already classified.');
            }
            self::assertNotContains($id, $actions['attestation.create']['properties']['proposition_id']['enum'] ?? []);
            self::assertNotContains($id, $actions['proposition.revise']['properties']['proposition_id']['enum'] ?? []);
            self::assertSame($state['schema'], app(ParticipantPromptFactory::class)->make(new Connection, $state)['schema']);
            $state = $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $id], $state);
        }
        self::assertSame($p3->id, $state['context']['gate']['acting_id']);
        self::assertTrue($this->visibleProposition($state, $journey['b'])['explored']);
        $this->assertActionUnavailable($state, 'proposition.variant');
        $this->assertActionUnavailable($state, 'proposition.traverse');
        $this->assertDatabaseCount('evidence_executions', $attempts);
        $this->assertDatabaseCount('attestations', $votes);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $observed = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $p3->id,
            'instrument' => ['kind' => 'citation', 'reference' => $journey['persist']]], $state);
        $next = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'A second Disperse Signal'], $observed);
        self::assertNotSame($journey['b'], $next['current_proposition_id']);
        foreach ([$journey['b'], $next['current_proposition_id']] as $child) {
            $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $p3->id, 'target_proposition_id' => $child, 'type' => 'variant']);
        }
        $this->assertDatabaseCount('evidence_executions', $attempts + 1);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_real_personal_rest_leaves_staggered_observers_free_and_variant_does_not_erase_history(): void
    {
        ['actor' => $actor, 'originObserver' => $originObserver, 'observer' => $observer,
            'witness' => $witness, 'origin' => $origin, 'a' => $a, 'b' => $b] = $this->personalExplorationJourney();
        $nova = $this->readParticipation($actor);
        $luna = $this->readParticipation($originObserver);
        $this->assertTrue($this->visibleProposition($nova, $a)['explored']);
        $this->assertFalse($this->visibleProposition($luna, $a)['explored']);
        $this->assertFalse($this->visibleProposition($this->readParticipation($observer), $b)['explored']);
        $this->assertFalse($this->visibleProposition($this->readParticipation($witness), $b)['explored']);
        $this->assertActionUnavailable($nova, 'proposition.variant');
        $this->assertActionAvailable($nova, 'instrument.execute');
        $this->assertActionUnavailable($luna, 'proposition.variant');
        $this->assertActionAvailable($luna, 'attestation.create');
        $this->assertDatabaseCount('exploration_events', 1);
        $history = DB::table('exploration_events')->get()->toJson();
        $this->discoverGate($actor);
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Nova explores a new alternative']);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $after = $this->readParticipation($originObserver);
        $this->assertSame($luna['phase']['id'], $after['phase']['id']);
        $this->assertSame($origin, $after['context']['gate']['acting_id']);
        $this->assertFalse($this->visibleProposition($after, $a)['explored']);
        $this->assertActionUnavailable($after, 'proposition.variant');
        // The Origin discovery reopens the observer's earlier judgments along this route.
        $this->judge($observer, $b);
        $this->contribute($observer, ['action' => 'participant.rest']);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $returned = $this->readParticipation($observer);
        $this->assertFalse($this->visibleProposition($returned, $a)['explored']);
        $this->assertContains($a, $returned['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum']);
        $this->assertFalse($this->visibleProposition($this->readParticipation($originObserver), $a)['explored']);
    }
}
