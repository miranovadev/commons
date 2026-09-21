<?php

namespace Tests\Feature;

use App\Contracts\ParticipantInference;
use App\Domain\Observables\ClosureValues;
use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Jobs\WakeParticipant;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ClosureGateService;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\ParticipantActivityService;
use App\Services\ParticipantConnectionTriggerService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class LocalParticipantNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    private Participant $author;

    private Phase $authorPhase;

    private array $fixtureTensions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->substrate = new Substrate;
        $this->substrate->forceFill([
            'slug' => 'local-navigation', 'title' => 'Local navigation',
            'visibility' => 'public', 'configuration' => [],
        ])->save();
        $this->author = $this->participant('author');
        $this->authorPhase = $this->phase($this->author);
        $this->authorPhase->completed_at = now();
        $this->authorPhase->save();
        $this->authorPhase->cycle->completed_at = now();
        $this->authorPhase->cycle->save();
    }

    public function test_initial_entry_selects_potential_and_persists_the_starting_position(): void
    {
        $this->node('Lower potential', 0.2);
        $best = $this->region('Best region', 0.9);
        $participant = $this->participant('newcomer');

        app(CycleEntryService::class)->enter($participant);

        $this->assertSame($best->id, $participant->fresh()->current_proposition_id);
        $this->assertSame($best->id, $this->state($participant)['plan']['gate_id']);
    }

    public function test_ordinary_reentry_never_selects_a_disconnected_global_maximum(): void
    {
        $position = $this->node('Current', 0.0);
        $participant = $this->participant('returning', $position);
        $this->region('Unconnected global maximum', 1.0);

        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($position->id, $this->state($participant)['plan']['gate_id']);
        $phase = $participant->cycles()->latest('sequence')->first()->phases()->first();
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();
        $this->refreshFixtureMeasurements();
        app(CycleEntryService::class)->enter($participant);

        $this->assertSame($position->id, $this->state($participant)['plan']['gate_id']);
        $this->assertSame(2, $participant->cycles()->count());
    }

    public function test_two_expressive_observer_positions_rank_the_same_commitments_differently(): void
    {
        $near = $this->region('Moderate nearby Potential', 0.4);
        $far = $this->region('Higher raw Potential', 0.9);
        $bridge = $this->node('Bridge', 0.9, 'expressive');
        $this->edge($near, $bridge);
        $this->edge($bridge, $far);
        $nearPosition = $near->children()->where('statement', 'Moderate nearby Potential continuation')->sole();
        $farPosition = $far->children()->sole();
        $left = $this->participant('left observer', $nearPosition);
        $right = $this->participant('right observer', $farPosition);
        $this->refreshFixtureMeasurements();
        $potential = app(PotentialChargeService::class);

        $this->assertSame($far->id, $potential->selectFor($this->substrate)['proposition']->id);
        $leftSelection = $potential->selectFrom($nearPosition);
        $rightSelection = $potential->selectFrom($farPosition);
        $this->assertSame($near->id, $leftSelection['proposition']->id);
        $this->assertSame(0.4, $leftSelection['raw_potential_charge']);
        $this->assertSame(0.1, $leftSelection['potential_charge']); // .4 / (1 + 1)^2
        $this->assertSame(1, $leftSelection['distance']);
        $this->assertSame($far->id, $rightSelection['proposition']->id);
        $this->assertSame(0.225, $rightSelection['potential_charge']);

        app(CycleEntryService::class)->enter($left);
        $this->assertSame($near->id, $left->fresh()->current_proposition_id);
        $this->assertSame('committal', $this->state($left)['plan']['gate_options'][0]['tier']);
        $this->refreshFixtureMeasurements();
        app(CycleEntryService::class)->enter($right);
        $this->assertSame($far->id, $right->fresh()->current_proposition_id);
        $this->assertSame('committal', $this->state($right)['plan']['gate_options'][0]['tier']);
    }

    public function test_recovery_excludes_connected_descendants_even_when_the_relative_ranking_diagnostic_prefers_them(): void
    {
        $near = $this->region('Near small Potential', 0.05);
        $far = $this->region('Far large Potential', 0.9);
        $this->edge($near, $far);
        $position = $near->children()->where('tier', 'expressive')->sole();
        $this->refreshFixtureMeasurements();
        $potential = app(PotentialChargeService::class);

        $ordinary = $potential->selectFrom($position);
        $this->assertSame($far->id, $ordinary['proposition']->id);
        $this->assertSame(2, $ordinary['distance']);
        $this->assertSame(0.1, $ordinary['potential_charge']);
        // Recovery excludes the exhausted observer from continuation evidence.
        // Supply a separate viable continuation for the nearer region.
        $other = $this->node('Other near continuation', 0.0, 'expressive');
        $this->edge($near, $other);
        $this->refreshFixtureMeasurements();
        $recovery = $potential->recoverFrom($position);
        $this->assertSame($near->id, $recovery['proposition']->id);
        $this->assertSame(1, $recovery['distance']);
        $this->assertEqualsWithDelta(0.05 / 4, $recovery['potential_charge'], 1e-9);
        $this->assertSame(0.05, $recovery['raw_potential_charge']);
    }

    public function test_charge_fallback_is_attenuated_from_the_expressive_position(): void
    {
        $near = $this->node('Lower raw Charge nearby', 0.8);
        $far = $this->node('Highest raw Charge farther away', 0.0);
        $bridge = $this->node('Bridge', 0.8, 'expressive');
        $middle = $this->node('Middle', 0.8, 'expressive');
        $position = $this->node('Expressive observer', 0.8, 'expressive');
        $this->edge($near, $bridge);
        $this->edge($bridge, $middle);
        $this->edge($middle, $far);
        $this->edge($near, $position);
        $this->refreshFixtureMeasurements();
        $potential = app(PotentialChargeService::class);

        $this->assertSame($far->id, $potential->selectFor($this->substrate)['proposition']->id);
        $selection = $potential->selectFrom($position);
        $this->assertSame($near->id, $selection['proposition']->id);
        $this->assertSame(0.0, $selection['potential_charge']);
        $this->assertSame(0.2, $selection['raw_charge']);
        $this->assertSame(0.05, $selection['charge']); // Nearby .2/4 beats distant 1/25.
    }

    public function test_no_reachable_commitment_leaves_entry_unavailable_without_moving(): void
    {
        $this->region('Disconnected destination', 1.0);
        $orphan = $this->node('Isolated expressive position', 0.0, 'expressive');
        $participant = $this->participant('isolated', $orphan);

        $this->assertFalse($this->state($participant)['entry']['available']);
        try {
            app(CycleEntryService::class)->enter($participant);
            $this->fail('Disconnected candidates cannot supply ordinary entry.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('recovery basin', $exception->getMessage());
            $this->assertSame($orphan->id, $participant->fresh()->current_proposition_id);
            $this->assertSame(0, $participant->cycles()->count());
        }
    }

    public function test_non_tragic_expressive_completion_resumes_its_own_committal_seed(): void
    {
        $first = $this->node('First commitment', 0.0);
        $second = $this->node('Second commitment', 0.0);
        $expressive = $this->node('Latest expressive position', 0.0, 'expressive');
        $this->edge($first, $expressive);
        $this->edge($expressive, $second); // Both commitments are one edge from the observer.
        $participant = $this->participant('returning', $expressive);
        $phase = $this->phase($participant, $expressive);
        $phase->forceFill(['kind' => PhaseKind::Order, 'completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now()])->save();
        $this->refreshFixtureMeasurements();

        $waiting = $this->state($participant);
        $this->assertTrue($waiting['entry']['available']);
        $this->assertSame('continuity', $waiting['entry']['mode']);
        $this->assertSame($expressive->id, $participant->fresh()->current_proposition_id);
        app(CycleEntryService::class)->enter($participant);

        $gate = $this->state($participant)['plan']['gate_options'][0];
        $this->assertSame('committal', $gate['tier']);
        $this->assertSame($first->id, $gate['id']);
    }

    public function test_a_zero_potential_commitment_keeps_continuity_even_when_origin_wins_potential(): void
    {
        $origin = $this->region('Established commitment', 0.8);
        $concord = $this->node('Expressive Concord', 0.8, 'expressive');
        $concord->forceFill(['register' => 'canon'])->save();
        $this->edge($origin, $concord);
        $committed = $this->node('New commitment', 0.0);
        $edge = new Supersession;
        $edge->forceFill([
            'phase_id' => $this->authorPhase->id,
            'source_proposition_id' => $concord->id,
            'target_proposition_id' => $committed->id,
            'type' => 'commit',
        ])->save();
        $participant = $this->participant('returning', $committed);
        $this->refreshFixtureMeasurements();

        $this->assertSame($origin->id, app(PotentialChargeService::class)->selectFrom($committed)['proposition']->id);
        $waiting = $this->state($participant);
        $this->assertSame('continuity', $waiting['entry']['mode']);
        $this->assertSame($committed->id, $waiting['entry']['proposition_id']);
        $this->assertSame($committed->id, $participant->fresh()->current_proposition_id);
        app(CycleEntryService::class)->enter($participant);
        $state = $this->state($participant);
        $this->assertSame($committed->id, $state['plan']['gate_id']);
        $next = app(ParticipationService::class)->contribute(
            $participant, $state['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A new expressive tier one.'],
        );

        $this->assertSame('expressive', $next['plan']['gate_options'][0]['tier']);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $committed->id,
            'target_proposition_id' => $next['plan']['gate_id'],
            'type' => 'variant',
        ]);
        $this->assertDatabaseMissing('supersessions', [
            'source_proposition_id' => $concord->id,
            'target_proposition_id' => $next['plan']['gate_id'],
        ]);
    }

    public function test_normal_expressive_participation_updates_persisted_position_without_traversal(): void
    {
        $position = $this->node('Current', 0.0);
        $participant = $this->participant('writer', $position);
        app(CycleEntryService::class)->enter($participant);
        $state = $this->state($participant);
        $this->assertArrayHasKey('proposition.variant', $state['plan']['schema']['$defs']['actions']);
        $next = app(ParticipationService::class)->contribute(
            $participant, $state['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A local distinction.'],
        );

        $this->assertSame('persist', $next['plan']['phase']['kind']);
        $this->assertSame($next['plan']['gate_id'], $participant->fresh()->current_proposition_id);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $position->id,
            'target_proposition_id' => $next['plan']['gate_id'],
        ]);
    }

    public function test_healthy_evidenced_traverse_prevents_redundant_variant_even_with_positive_potential(): void
    {
        $position = $this->node('Current', 0.8);
        $target = $this->node('Existing distinction', 0.1, 'expressive');
        $this->edge($position, $target);
        $participant = $this->participant('walker', $position);
        $this->establishPath($participant, $target);
        app(CycleEntryService::class)->enter($participant);
        $state = $this->state($participant);
        $actions = $state['plan']['schema']['$defs']['actions'];
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $this->assertArrayHasKey('proposition.traverse', $actions);
        $this->assertArrayNotHasKey('participant.rest', $actions);
        $this->assertFalse($state['plan']['rest_available']);
        $count = Proposition::count();
        $edges = Supersession::count();
        $after = app(ParticipationService::class)->contribute($participant, $state['plan']['phase']['id'], [
            'action' => 'proposition.traverse', 'proposition_id' => $target->id,
        ]);
        $this->assertSame($count, Proposition::count());
        $this->assertSame($edges, Supersession::count());
        $this->assertSame($target->id, $participant->fresh()->current_proposition_id);
        $this->assertSame('persist', $after['plan']['phase']['kind']);
    }

    public function test_invalid_traverse_targets_and_legacy_actions_are_rejected_without_movement(): void
    {
        $position = $this->node('Current', 0.8);
        $target = $this->node('Existing', 0.1, 'expressive');
        $foreign = $this->node('Disconnected', 0.0, 'expressive');
        $this->edge($position, $target);
        $participant = $this->participant('walker', $position);
        $this->establishPath($participant, $target);
        app(CycleEntryService::class)->enter($participant);
        $phaseId = $this->state($participant)['plan']['phase']['id'];
        foreach ([
            ['action' => 'participant.select', 'proposition_id' => $target->id],
            ['action' => 'proposition.select', 'proposition_id' => $target->id],
            ['action' => 'participant.traverse'],
            ['action' => 'participant.rest'],
            ['action' => 'proposition.traverse'],
            ['action' => 'proposition.traverse', 'proposition_id' => $foreign->id],
            ['action' => 'proposition.traverse', 'proposition_id' => $position->id],
            ['action' => 'proposition.traverse', 'proposition_id' => $target->id, 'statement' => 'Invented'],
        ] as $input) {
            try {
                app(ParticipationService::class)->contribute($participant, $phaseId, $input);
                $this->fail('Invalid navigation must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertSame($position->id, $participant->fresh()->current_proposition_id);
                $this->assertNull(Phase::findOrFail($phaseId)->completed_at);
            }
        }
    }

    public function test_local_rest_in_an_unexplored_empty_basin_preserves_its_entry(): void
    {
        $position = $this->node('Isolated', 0.0);
        $participant = $this->participant('resting', $position);
        $this->phase($participant, $position);
        $state = $this->state($participant);
        $this->assertTrue($state['plan']['rest_available']);
        $this->assertSame(['participant.rest'], array_keys($state['plan']['schema']['$defs']['actions']));
        $count = Proposition::count();
        $after = app(ParticipationService::class)->contribute($participant, $state['plan']['phase']['id'], ['action' => 'participant.rest']);
        $this->assertSame($count, Proposition::count());
        $this->assertSame($position->id, $participant->fresh()->current_proposition_id);
        $phase = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertNotNull($phase->completed_at);
        $this->assertNotNull($phase->cycle->rested_at);
        $this->assertNotNull($phase->cycle->completed_at);
        $this->assertSame('ready', $after['status']);
        $this->assertSame('distinguish', $after['plan']['phase']['kind']);
        $this->assertSame($position->id, $after['plan']['gate_id']);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $position->id]);
        $this->expectException(ConflictHttpException::class);
        app(CycleEntryService::class)->enter($participant);
    }

    public function test_recovery_stops_at_the_nearest_sequential_ancestor(): void
    {
        $position = $this->node('Observer', 0.0, 'expressive');
        $low = $this->region('Near low', 0.03);
        $near = $this->region('Near winner', 0.06);
        $bridge = $this->node('Empty bridge', 0.0);
        $far = $this->region('Distant maximum', 1.0);
        $this->edge($low, $position);
        $this->edge($near, $low);
        $this->edge($bridge, $near);
        $this->edge($far, $bridge);
        $participant = $this->participant('observer', $position);
        $phase = $this->phase($participant, $position);
        $this->refreshFixtureMeasurements();
        $selection = app(PotentialChargeService::class)->recoverFrom($position);
        $this->assertSame($low->id, $selection['proposition']->id);
        $this->assertSame(1, $selection['distance']);
        $this->assertEqualsWithDelta(0.03 / 4, $selection['potential_charge'], 0.0000001);
        app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame($low->id, $this->state($participant)['plan']['gate_id']);
    }

    public function test_ambiguous_multi_input_without_recorded_arrival_provenance_fails_closed(): void
    {
        $position = $this->node('Observer', 0.0);
        $near = $this->region('Short continuation', 0.7);
        $long = $this->node('Long continuation', 0.7);
        $middle = $this->node('Middle', 0.7, 'expressive');
        $end = $this->node('End', 0.0, 'expressive');
        $this->edge($near, $position);
        $this->edge($long, $position);
        $this->edge($long, $middle);
        $this->edge($middle, $end);
        $tie = $this->region('Equal short continuation', 0.7);
        $this->edge($tie, $position);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('recorded arrival Gate');
        app(PotentialChargeService::class)->recoverFrom($position);
    }

    public function test_repeated_local_rest_does_not_mark_a_still_open_region_exhausted(): void
    {
        $position = $this->node('Empty region', 0.0, 'expressive');
        $first = $this->region('First viable', 0.6);
        $second = $this->region('Second viable', 0.9);
        $this->edge($first, $position);
        $this->edge($second, $first);
        $participant = $this->participant('explorer', $position);
        $target = $first->children()->where('tier', 'expressive')->firstOrFail();
        $target->register = 'canon';
        $target->save();
        $this->establishPath($participant, $target);
        $phase = $this->phase($participant, $position);
        $this->refreshFixtureMeasurements();
        $state = $this->state($participant);

        $this->refreshFixtureMeasurements();
        app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        $state = $this->state($participant);
        $this->assertSame($first->id, $state['plan']['gate_id']);
        // Exercise recovery from an exhausted Order, not Persist (which has Variant).
        $order = Phase::findOrFail($state['plan']['phase']['id']);
        $order->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'canon'])->save();
        $visited = $order->replicate();
        $visited->forceFill(['gate_proposition_id' => $target->id, 'completed_at' => now()])->save();
        // Grounding remains available in Order unless the local target is no longer an active head.
        $later = $this->node('Historical continuation', 0.0);
        $this->edge($target, $later);
        $target->children()->updateExistingPivot($later->id, ['type' => 'reclassify']);
        // A prior visit no longer exhausts a Commit Region. Divergent Closure
        // makes its Write pool empty for this recovery-only regression.
        $this->mock(ClosureGateService::class, function ($mock): void {
            $mock->shouldReceive('preview')->andReturn((new ClosureValues(1, -1))->resolve());
        });
        $this->refreshFixtureMeasurements();
        $state = $this->state($participant);
        $this->assertTrue($state['plan']['rest_available']);
        $state = app(ParticipationService::class)->contribute($participant, $state['plan']['phase']['id'], ['action' => 'participant.rest']);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $this->assertSame($first->id, $this->state($participant)['plan']['gate_id']);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $first->id]);
    }

    public function test_connected_zero_potential_remains_available_without_borrowing_from_disconnected_regions(): void
    {
        $position = $this->node('Observer', 0.0);
        $empty = $this->node('Connected zero', 0.0);
        $this->edge($position, $empty);
        $this->region('Disconnected high', 1.0);
        $selection = app(PotentialChargeService::class)->recoverFrom($position);
        $this->assertSame($position->id, $selection['proposition']->id);
        $this->assertSame(0.0, $selection['raw_potential_charge']);
    }

    public function test_order_rejects_attestation_and_expressive_traverse_without_weakening_commit(): void
    {
        $parent = $this->node('Forward descendant', 0.0, 'expressive', PhaseKind::Bias);
        $gate = $this->node('Current', 0.0, 'expressive', PhaseKind::Bias);
        foreach ([$parent, $gate] as $node) {
            $node->register = 'canon';
            $node->save();
        }
        $this->edge($this->node('Commit Region seed', 0.0), $gate);
        $this->edge($gate, $parent);
        // A same-position continuation is local only through its revision edge.
        $gate->children()->updateExistingPivot($parent->id, ['type' => 'revise']);
        $participant = $this->participant('order', $gate);
        $this->establishPath($participant, $parent);
        $phase = $this->phase($participant, $gate);
        $phase->kind = PhaseKind::Order;
        $phase->write_register = 'canon';
        $phase->save();
        $state = $this->state($participant);
        $this->assertFalse($state['plan']['rest_available']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertArrayNotHasKey('reinforcement', $state['plan']['commitment']['requirements']);
        $actions = $state['plan']['schema']['$defs']['actions'];
        $this->assertArrayNotHasKey('attestation.create', $actions);
        $this->assertArrayNotHasKey('proposition.traverse', $actions);
        $this->assertSame([], $state['plan']['schema']['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum'] ?? []);
        try {
            app(ParticipationService::class)->contribute($participant, $phase->id, [
                'action' => 'proposition.traverse', 'proposition_id' => $parent->id,
            ]);
            $this->fail('Order must reject explicit traversal.');
        } catch (\InvalidArgumentException) {
            $this->assertSame($gate->id, $participant->fresh()->current_proposition_id);
        }
        $this->assertNull($phase->cycle->fresh()->completed_at);
    }

    public function test_automated_wakes_ask_inference_to_choose_traversal(): void
    {
        config()->set('participation.max_actions_per_wake', 1);
        $gate = $this->node('Current', 0.8);
        $target = $this->node('Local', 0.0, 'expressive');
        $this->edge($gate, $target);
        $participant = $this->participant('automated', $gate);
        $this->establishPath($participant, $target);
        $this->assertSame('continuity', $this->state($participant)['entry']['mode']);
        $connection = new Connection;
        $connection->forceFill([
            'provider' => 'openai', 'name' => 'Navigation test',
            'configuration' => ['model' => 'test'], 'credentials' => ['api_key' => 'test'],
        ])->save();
        $link = new ParticipantConnection;
        $link->forceFill([
            'participant_id' => $participant->id, 'connection_id' => $connection->id,
            'is_enabled' => true, 'configuration' => [],
        ])->save();
        $inference = \Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->once()->andReturn([
            'action' => 'proposition.traverse', 'proposition_id' => $target->id,
        ]);
        (new WakeParticipant($link->id))->handle($inference, app(ParticipationService::class),
            app(ParticipantConnectionTriggerService::class), app(ParticipantActivityService::class));
        $this->assertSame($target->id, $participant->fresh()->current_proposition_id);
    }

    public function test_migration_recovers_active_gates_and_completed_commitments_without_reentry(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Reversing an old table rebuild with newer restrictive foreign keys requires PostgreSQL.');
        }
        $gate = $this->node('Previous Gate', 0.0);
        $active = $this->participant('existing active');
        $this->phase($active, $gate);
        $completed = $this->participant('existing completed');
        $order = $this->phase($completed, $gate);
        $order->kind = PhaseKind::Order;
        $order->completed_at = now();
        $order->save();
        $order->cycle->completed_at = now();
        $order->cycle->save();
        $commitment = new Proposition;
        $commitment->forceFill([
            'phase_id' => $order->id, 'register' => 'canon', 'tier' => 'committal',
            'statement' => 'Final commitment.',
        ])->save();
        $newcomer = $this->participant('unpositioned');
        $migration = require database_path('migrations/2026_09_09_000000_persist_participant_position.php');
        $migration->down();
        $migration->up();

        $this->assertSame($gate->id, $active->fresh()->current_proposition_id);
        $this->assertSame($commitment->id, $completed->fresh()->current_proposition_id);
        $this->assertNull($newcomer->fresh()->current_proposition_id);
    }

    private function establishPath(Participant $participant, Proposition $target): void
    {
        $this->receiptFixture($target);
        $phase = $this->phase($participant);
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();
        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $phase->id, 'proposition_id' => $target->id, 'direction' => 'support',
        ])->save();
    }

    private function participant(string $callsign, ?Proposition $position = null): Participant
    {
        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $this->substrate->id, 'callsign' => $callsign,
            'current_proposition_id' => $position?->id, 'max_read_count' => 25,
        ])->save();

        return $participant;
    }

    private function phase(Participant $participant, ?Proposition $gate = null, ?Cycle $cycle = null): Phase
    {
        if ($cycle === null) {
            $cycle = new Cycle;
            $cycle->forceFill([
                'participant_id' => $participant->id,
                'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            ])->save();
        }
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->id, 'gate_proposition_id' => $gate?->id,
            'kind' => PhaseKind::Constrain, 'write_register' => 'canon', 'started_at' => now(),
        ])->save();

        return $phase;
    }

    private function region(string $name, float $potential): Proposition
    {
        $source = $this->node($name, $potential);
        $target = $this->node($name.' continuation', 0.0, 'expressive');
        $this->edge($source, $target);

        return $source;
    }

    private function node(string $name, float $tension, string $tier = 'committal', PhaseKind $position = PhaseKind::Distinguish): Proposition
    {
        $node = new Proposition;
        $node->forceFill([
            'phase_id' => $this->phaseAtPositionFixture($this->authorPhase, $position)->id, 'statement' => $name,
            'register' => $tier === 'committal' ? 'canon' : ContextDomain::Emergence->registerFor($position), 'tier' => $tier,
        ])->save();
        $entry = app(LedgerService::class)->record($node);
        $this->fixtureTensions[$node->id] = $tension;
        app(PropositionMeasurementService::class)->project($node, $tension, $tension, $tension);
        if ($tier === 'committal') {
            $snapshot = new CommitmentSnapshot;
            $snapshot->forceFill([
                'proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
                'ledger_entry_id' => $entry->id,
                'correspondence' => $tension, 'chirality' => $tension, 'closure' => $tension,
                'prominence' => ($tension + 1) / 2, 'turbulence' => (1 - $tension) / 2,
                'tension' => $tension, 'direction_before' => ContextDomain::Emergence,
                'direction_after' => ContextDomain::Emergence,
                'calculator_version' => 'navigation-test', 'evaluated_at' => now(),
            ])->save();
        }

        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);

        return $node;
    }

    /** Navigation rankings use controlled upstream values, refreshed after Phase observations. */
    private function refreshFixtureMeasurements(): void
    {
        foreach ($this->fixtureTensions as $id => $tension) {
            app(PropositionMeasurementService::class)->project(Proposition::findOrFail($id), $tension, $tension, $tension);
        }
        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);
    }

    private function edge(Proposition $source, Proposition $target): void
    {
        $edge = new Supersession;
        $edge->forceFill([
            'phase_id' => $this->authorPhase->id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => 'variant',
        ])->save();
        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);
    }

    private function state(Participant $participant): array
    {
        $this->refreshFixtureMeasurements();

        return app(ParticipationService::class)->stateFor($participant);
    }
}
