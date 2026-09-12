<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CycleEntryService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame($best->id, $this->state($participant)['plan']['gate']['id']);
    }

    public function test_ordinary_reentry_never_selects_a_disconnected_global_maximum(): void
    {
        $position = $this->node('Current', 0.0);
        $participant = $this->participant('returning', $position);
        $this->region('Unconnected global maximum', 1.0);

        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($position->id, $this->state($participant)['plan']['gate']['id']);
        $phase = $participant->cycles()->latest('sequence')->first()->phases()->first();
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();
        $this->refreshFixtureMeasurements();
        app(CycleEntryService::class)->enter($participant);

        $this->assertSame($position->id, $this->state($participant)['plan']['gate']['id']);
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
        $this->assertSame('committal', $this->state($left)['plan']['gate']['tier']);
        $this->refreshFixtureMeasurements();
        app(CycleEntryService::class)->enter($right);
        $this->assertSame($far->id, $right->fresh()->current_proposition_id);
        $this->assertSame('committal', $this->state($right)['plan']['gate']['tier']);
    }

    public function test_ordinary_entry_can_select_beyond_the_nearest_viable_shell(): void
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
        $this->assertSame(0.05, $recovery['potential_charge']);
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
            $this->assertStringContainsString('structurally reachable', $exception->getMessage());
            $this->assertSame($orphan->id, $participant->fresh()->current_proposition_id);
            $this->assertSame(0, $participant->cycles()->count());
        }
    }

    public function test_zero_potential_ties_never_reuse_the_latest_expressive_position(): void
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
        $this->assertNotSame('position', $waiting['entry']['selection'] ?? null);
        $this->assertSame($expressive->id, $participant->fresh()->current_proposition_id);
        app(CycleEntryService::class)->enter($participant);

        $gate = $this->state($participant)['plan']['gate'];
        $this->assertSame('committal', $gate['tier']);
        $this->assertSame(min($first->id, $second->id), $gate['id']);
    }

    public function test_a_new_commitment_can_win_reentry_and_seed_a_fresh_variant(): void
    {
        $origin = $this->node('Established commitment', 0.8);
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
        $participant = $this->participant('returning', $concord);
        $this->refreshFixtureMeasurements();

        app(CycleEntryService::class)->enter($participant);
        $state = $this->state($participant);
        $this->assertSame($committed->id, $state['plan']['gate']['id']);
        $next = app(ParticipationService::class)->contribute(
            $participant, $state['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A new expressive tier one.'],
        );

        $this->assertSame('expressive', $next['plan']['gate']['tier']);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $committed->id,
            'target_proposition_id' => $next['plan']['gate']['id'],
            'type' => 'variant',
        ]);
        $this->assertDatabaseMissing('supersessions', [
            'source_proposition_id' => $concord->id,
            'target_proposition_id' => $next['plan']['gate']['id'],
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
        $this->assertSame($next['plan']['gate']['id'], $participant->fresh()->current_proposition_id);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $position->id,
            'target_proposition_id' => $next['plan']['gate']['id'],
        ]);
    }

    public function test_traverse_is_an_explicit_local_choice_alongside_creation(): void
    {
        $position = $this->node('Current', 0.8);
        $target = $this->node('Existing distinction', 0.1, 'expressive');
        $this->edge($position, $target);
        $participant = $this->participant('walker', $position);
        $this->establishPath($participant, $target);
        app(CycleEntryService::class)->enter($participant);
        $state = $this->state($participant);
        $actions = $state['plan']['schema']['$defs']['actions'];
        $this->assertArrayHasKey('proposition.variant', $actions);
        $this->assertArrayHasKey('proposition.traverse', $actions);
        $this->assertArrayNotHasKey('participant.rest', $actions);
        $this->assertFalse($state['plan']['tragedy']);
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

    public function test_complete_exhaustion_allows_rest_and_preserves_position(): void
    {
        $position = $this->node('Isolated', 0.0);
        $participant = $this->participant('resting', $position);
        $this->phase($participant, $position);
        $state = $this->state($participant);
        $this->assertTrue($state['plan']['tragedy']);
        $this->assertSame(['participant.rest'], array_keys($state['plan']['schema']['$defs']['actions']));
        $count = Proposition::count();
        $after = app(ParticipationService::class)->contribute($participant, $state['plan']['phase']['id'], ['action' => 'participant.rest']);
        $this->assertSame($count, Proposition::count());
        $this->assertSame($position->id, $participant->fresh()->current_proposition_id);
        $phase = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertNotNull($phase->completed_at);
        $this->assertNotNull($phase->cycle->rested_at);
        $this->assertNotNull($phase->cycle->completed_at);
        $this->assertSame('recovery', $after['entry']['selection']);
        $this->assertFalse($after['entry']['available']);
        $this->expectException(ConflictHttpException::class);
        app(CycleEntryService::class)->enter($participant);
    }

    public function test_recovery_uses_nearest_viable_distance_without_discounting_measurements_again(): void
    {
        $position = $this->node('Observer', 0.0);
        $low = $this->region('Near low', 0.03);
        $near = $this->region('Near winner', 0.06);
        $bridge = $this->node('Empty bridge', 0.0);
        $far = $this->region('Distant maximum', 1.0);
        $this->edge($position, $low);
        $this->edge($position, $near);
        $this->edge($position, $bridge);
        $this->edge($bridge, $far);
        $participant = $this->participant('observer', $position);
        $phase = $this->phase($participant, $position);
        $this->refreshFixtureMeasurements();
        $selection = app(PotentialChargeService::class)->recoverFrom($position);
        $this->assertSame($near->id, $selection['proposition']->id);
        $this->assertSame(1, $selection['distance']);
        $this->assertEqualsWithDelta(0.06, $selection['potential_charge'], 0.0000001);
        app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($near->id, $this->state($participant)['plan']['gate']['id']);
    }

    public function test_equal_distance_ranking_uses_upstream_potential_and_id_ties(): void
    {
        $position = $this->node('Observer', 0.0);
        $near = $this->region('Short continuation', 0.7);
        $long = $this->node('Long continuation', 0.7);
        $middle = $this->node('Middle', 0.7, 'expressive');
        $end = $this->node('End', 0.0, 'expressive');
        $this->edge($position, $near);
        $this->edge($position, $long);
        $this->edge($long, $middle);
        $this->edge($middle, $end);
        $this->assertSame($near->id, app(PotentialChargeService::class)->recoverFrom($position)['proposition']->id);
        $tie = $this->region('Equal short continuation', 0.7);
        $this->edge($tie, $position);
        $this->edge($long, $position); // A graph cycle must terminate.
        $this->assertSame(min($near->id, $tie->id), app(PotentialChargeService::class)->recoverFrom($position)['proposition']->id);
    }

    public function test_repeated_rest_moves_outward_after_a_region_is_exhausted(): void
    {
        $position = $this->node('Empty region', 0.0);
        $first = $this->region('First viable', 0.3);
        $second = $this->region('Second viable', 0.9);
        $this->edge($position, $first);
        $this->edge($first, $second);
        $participant = $this->participant('explorer', $position);
        $target = $first->children()->where('tier', 'expressive')->firstOrFail();
        $target->register = 'canon';
        $target->save();
        $this->establishPath($participant, $target);
        $phase = $this->phase($participant, $position);
        $this->refreshFixtureMeasurements();
        app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        app(CycleEntryService::class)->enter($participant);
        $state = $this->state($participant);
        $this->assertSame($first->id, $state['plan']['gate']['id']);
        // Exercise recovery from an exhausted Order, not Persist (which has Variant).
        $order = Phase::findOrFail($state['plan']['phase']['id']);
        $order->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'canon'])->save();
        $visited = $order->replicate();
        $visited->forceFill(['gate_proposition_id' => $target->id, 'completed_at' => now()])->save();
        // Grounding remains available in Order unless the local target is no longer an active head.
        $this->edge($target, $second);
        // A prior visit no longer exhausts a Commit Region. Divergent Closure
        // makes its Write pool empty for this recovery-only regression.
        $this->mock(\App\Services\ClosureGateService::class, function ($mock): void {
            $mock->shouldReceive('preview')->andReturn((new \App\Domain\Observables\ClosureValues(1, -1))->resolve());
        });
        $this->refreshFixtureMeasurements();
        $state = $this->state($participant);
        $this->assertTrue($state['plan']['tragedy']);
        $state = app(ParticipationService::class)->contribute($participant, $state['plan']['phase']['id'], ['action' => 'participant.rest']);
        $this->assertSame(1, $state['entry']['distance']);
        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($second->id, $this->state($participant)['plan']['gate']['id']);
    }

    public function test_disconnected_and_zero_potential_regions_cannot_supply_recovery(): void
    {
        $position = $this->node('Observer', 0.0);
        $empty = $this->node('Connected zero', 0.0);
        $this->edge($position, $empty);
        $this->region('Disconnected high', 1.0);
        $this->assertNull(app(PotentialChargeService::class)->recoverFrom($position));
    }

    public function test_order_keeps_attestation_but_rejects_traverse_without_weakening_commit(): void
    {
        $parent = $this->node('Forward descendant', 0.0, 'expressive');
        $gate = $this->node('Current', 0.0, 'expressive');
        foreach ([$parent, $gate] as $node) {
            $node->register = 'canon';
            $node->save();
        }
        $this->edge($this->node('Commit Region seed', 0.0), $gate);
        $this->edge($gate, $parent);
        $participant = $this->participant('order', $gate);
        $this->establishPath($participant, $parent);
        $phase = $this->phase($participant, $gate);
        $phase->kind = PhaseKind::Order;
        $phase->write_register = 'canon';
        $phase->save();
        $state = $this->state($participant);
        $this->assertFalse($state['plan']['tragedy']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertSame('commitment_preconditions_unsatisfied', $state['reason']['code']);
        $actions = $state['plan']['schema']['$defs']['actions'];
        $this->assertArrayHasKey('attestation.create', $actions);
        $this->assertArrayNotHasKey('proposition.traverse', $actions);
        $this->assertSame([], $state['plan']['traversal_options']);
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
        $gate = $this->node('Current', 0.8);
        $target = $this->node('Local', 0.0, 'expressive');
        $this->edge($gate, $target);
        $participant = $this->participant('automated', $gate);
        $this->establishPath($participant, $target);
        app(CycleEntryService::class)->enter($participant);
        $connection = new \App\Models\Connection;
        $connection->forceFill([
            'provider' => 'openai', 'name' => 'Navigation test',
            'configuration' => ['model' => 'test'], 'credentials' => ['api_key' => 'test'],
        ])->save();
        $link = new \App\Models\ParticipantConnection;
        $link->forceFill([
            'participant_id' => $participant->id, 'connection_id' => $connection->id,
            'is_enabled' => true, 'configuration' => [],
        ])->save();
        $inference = \Mockery::mock(\App\Contracts\ParticipantInference::class);
        $inference->shouldReceive('contribute')->once()->andReturn([
            'action' => 'proposition.traverse', 'proposition_id' => $target->id,
        ]);
        (new \App\Jobs\WakeParticipant($link->id))->handle($inference, app(ParticipationService::class),
            app(\App\Services\ParticipantConnectionTriggerService::class));
        $this->assertSame($target->id, $participant->fresh()->current_proposition_id);
    }

    public function test_migration_recovers_active_gates_and_completed_commitments_without_reentry(): void
    {
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
        $phase = $this->phase($participant);
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();
        $attestation = new \App\Models\Attestation;
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

    private function node(string $name, float $tension, string $tier = 'committal'): Proposition
    {
        $node = new Proposition;
        $node->forceFill([
            'phase_id' => $this->authorPhase->id, 'statement' => $name,
            'register' => $tier === 'committal' ? 'canon' : 'activity', 'tier' => $tier,
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

        return $node;
    }

    /** Navigation rankings use controlled upstream values, refreshed after Phase observations. */
    private function refreshFixtureMeasurements(): void
    {
        foreach ($this->fixtureTensions as $id => $tension) {
            app(PropositionMeasurementService::class)->project(Proposition::findOrFail($id), $tension, $tension, $tension);
        }
    }

    private function edge(Proposition $source, Proposition $target): void
    {
        $edge = new Supersession;
        $edge->forceFill([
            'phase_id' => $this->authorPhase->id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => 'variant',
        ])->save();
    }

    private function state(Participant $participant): array
    {
        return app(ParticipationService::class)->stateFor($participant);
    }

}

