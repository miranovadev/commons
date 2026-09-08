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

    public function test_new_cycles_keep_position_even_when_a_distant_region_has_higher_potential(): void
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
        app(CycleEntryService::class)->enter($participant);

        $this->assertSame($position->id, $this->state($participant)['plan']['gate']['id']);
        $this->assertSame(2, $participant->cycles()->count());
    }

    public function test_normal_expressive_participation_updates_persisted_position_without_traversal(): void
    {
        $position = $this->node('Current', 0.0);
        $participant = $this->participant('writer', $position);
        app(CycleEntryService::class)->enter($participant);
        $state = $this->state($participant);
        $this->assertNull($state['navigation']);
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

    public function test_exhausted_leaf_moves_back_one_step_without_teleporting_to_the_best_region(): void
    {
        [$branch, $leaf, $region] = $this->branchedGraph();
        $participant = $this->participant('leaf-observer', $leaf);
        $phase = $this->phase($participant, $leaf);
        $state = $this->state($participant);
        $this->assertSame([], $state['plan']['schema']['oneOf']);
        $this->assertSame($branch->id, $state['navigation']['next_proposition_id']);
        $this->assertSame($leaf->id, $participant->fresh()->current_proposition_id, 'GET must not move.');

        $after = $this->traverse($participant, $phase);

        $this->assertSame($branch->id, $participant->fresh()->current_proposition_id);
        $this->assertNotSame($region->id, $participant->fresh()->current_proposition_id);
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->assertSame('position', $after['entry']['mode']);
        $this->assertTrue($after['entry']['available']);
        $this->assertDatabaseCount('cycles', 2);
    }

    public function test_a_later_cycle_can_continue_from_the_branch_and_move_into_another_branch(): void
    {
        [$branch, $leaf, $region] = $this->branchedGraph();
        $participant = $this->participant('branch-observer', $leaf);
        $this->traverse($participant, $this->phase($participant, $leaf));
        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($branch->id, $this->state($participant)['plan']['gate']['id']);

        // Exercise a later exhausted phase at the retained branch position.
        $cycle = $participant->cycles()->latest('sequence')->first();
        $cycle->phases()->update(['completed_at' => now()]);
        $phase = $this->phase($participant, $branch, $cycle);
        $this->traverse($participant, $phase);

        $this->assertSame($region->id, $participant->fresh()->current_proposition_id);
    }

    public function test_attenuation_prefers_nearer_potential_over_a_larger_distant_signal(): void
    {
        $position = $this->node('Observer', 0.0);
        $near = $this->region('Near', 0.6);
        $step = $this->node('Distant route', 0.0);
        $middle = $this->node('Further step', 0.0);
        $far = $this->region('Far', 0.9);
        $this->edge($position, $near);
        $this->edge($position, $step);
        $this->edge($step, $middle);
        $this->edge($middle, $far);

        $selection = app(PotentialChargeService::class)->nextFrom($position);

        $this->assertSame($near->id, $selection['proposition']->id);
        // Near: .6 / (1 + 1 step to source + 1 to continuation) = .2.
        // Far: .9 / (1 + 3 steps to source + 1 to continuation) = .18.
        $this->assertEqualsWithDelta(0.2, $selection['potential'], 0.0000001);
    }

    public function test_participants_at_different_positions_follow_different_trajectories(): void
    {
        $left = $this->region('Left', 0.6);
        $right = $this->region('Right', 0.6);
        $a = $this->node('A', 0.0);
        $b = $this->node('B', 0.0);
        $this->edge($left, $a);
        $this->edge($a, $b);
        $this->edge($b, $right);
        $first = $this->participant('first', $a);
        $second = $this->participant('second', $b);

        $this->traverse($first, $this->phase($first, $a));
        $this->traverse($second, $this->phase($second, $b));

        $this->assertSame($left->id, $first->fresh()->current_proposition_id);
        $this->assertSame($right->id, $second->fresh()->current_proposition_id);
    }

    public function test_disconnected_global_potential_does_not_make_local_movement_available(): void
    {
        $position = $this->node('Isolated', 0.0);
        $this->region('Elsewhere', 1.0);
        $participant = $this->participant('isolated', $position);
        $phase = $this->phase($participant, $position);
        $this->assertFalse($this->state($participant)['navigation']['available']);

        try {
            $this->traverse($participant, $phase);
            $this->fail('An isolated Participant cannot traverse.');
        } catch (DomainException) {
            $this->assertNull($phase->fresh()->completed_at);
            $this->assertSame($position->id, $participant->fresh()->current_proposition_id);
        }
    }

    public function test_traversal_is_rejected_while_a_variant_is_admissible(): void
    {
        [$branch, $leaf] = $this->branchedGraph();
        $participant = $this->participant('writer', $leaf);
        app(CycleEntryService::class)->enter($participant);
        $phase = $participant->cycles()->first()->phases()->first();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Traversal requires an exhausted Write.');
        $this->traverse($participant, $phase);
    }

    public function test_replaying_a_traversal_cannot_take_a_second_step(): void
    {
        [$branch, $leaf] = $this->branchedGraph();
        $participant = $this->participant('replay', $leaf);
        $phase = $this->phase($participant, $leaf);
        $this->traverse($participant, $phase);
        app(CycleEntryService::class)->enter($participant);
        try {
            $this->traverse($participant, $phase);
            $this->fail('A stale phase token cannot traverse again.');
        } catch (ConflictHttpException) {
            $this->assertSame($branch->id, $participant->fresh()->current_proposition_id);
        }
    }

    public function test_existing_write_targets_cannot_select_a_distant_proposition(): void
    {
        $position = $this->node('Current', 0.0);
        $near = $this->node('Local expressive head', 0.0, 'expressive');
        $distant = $this->node('Disconnected expressive head', 0.0, 'expressive');
        $this->edge($position, $near);
        $participant = $this->participant('selector', $position);
        $phase = $this->phase($participant, $position);
        $phase->kind = PhaseKind::Distinguish;
        $phase->write_register = 'activity';
        $phase->save();
        $plan = app(InputPlanService::class)->forParticipant($participant);
        $this->assertSame([$near->id], array_column($plan['write_options'], 'id'));
        $this->assertNotContains($distant->id, array_column($plan['write_options'], 'id'));
    }

    public function test_cyclic_edges_terminate_and_equal_scores_use_stable_id_order(): void
    {
        $position = $this->node('Current', 0.0);
        $first = $this->region('First', 0.6);
        $second = $this->region('Second', 0.6);
        $this->edge($position, $first);
        $this->edge($first, $second);
        $this->edge($second, $position);
        $this->assertSame(
            min($first->id, $second->id),
            app(PotentialChargeService::class)->nextFrom($position)['proposition']->id,
        );
    }

    public function test_an_ungrounded_order_reports_exhaustion_instead_of_advertising_an_invalid_commit(): void
    {
        [$branch, $leaf] = $this->branchedGraph();
        $participant = $this->participant('ungrounded', $leaf);
        $phase = $this->phase($participant, $leaf);
        $phase->kind = PhaseKind::Order;
        $phase->save();

        $state = $this->state($participant);

        $this->assertSame([], $state['plan']['schema']['oneOf']);
        $this->assertSame('no_admissible_write', $state['reason']['code']);
        $this->assertSame($branch->id, $state['navigation']['next_proposition_id']);
    }

    public function test_automated_wakes_traverse_without_calling_inference_and_resume_later(): void
    {
        [$branch, $leaf] = $this->branchedGraph();
        $participant = $this->participant('automated', $leaf);
        $this->phase($participant, $leaf);
        $connection = new \App\Models\Connection;
        $connection->forceFill([
            'provider' => 'openai', 'name' => 'Navigation test',
            'configuration' => ['model' => 'test'], 'credentials' => ['api_key' => 'test'],
        ])->save();
        $link = new \App\Models\ParticipantConnection;
        $link->forceFill([
            'participant_id' => $participant->id, 'connection_id' => $connection->id,
            'is_enabled' => true, 'configuration' => [], 'last_error' => 'Earlier exhaustion',
        ])->save();
        $inference = \Mockery::mock(\App\Contracts\ParticipantInference::class);
        $inference->shouldNotReceive('contribute');
        $job = new \App\Jobs\WakeParticipant($link->id);
        $job->handle($inference, app(ParticipationService::class),
            app(\App\Services\ParticipantConnectionTriggerService::class));
        $this->assertSame($branch->id, $participant->fresh()->current_proposition_id);
        $this->assertNull($link->fresh()->last_error);

        $laterInference = \Mockery::mock(\App\Contracts\ParticipantInference::class);
        $laterInference->shouldReceive('contribute')->once()->withArgs(
            fn ($connection, array $state): bool => $state['plan']['gate']['id'] === $branch->id,
        )->andReturn(['action' => 'proposition.variant', 'statement' => 'Continue at C.']);
        $job->handle($laterInference, app(ParticipationService::class),
            app(\App\Services\ParticipantConnectionTriggerService::class));
        $this->assertSame('persist', $this->state($participant)['plan']['phase']['kind']);
    }

    public function test_navigation_api_uses_the_phase_token_and_ignores_no_admissible_write_as_a_terminal_state(): void
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'System', 'email' => 'navigation@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();
        [$branch, $leaf] = $this->branchedGraph();
        $participant = $this->participant('api', $leaf);
        $phase = $this->phase($participant, $leaf);
        $endpoint = '/api/substrates/'.$this->substrate->slug
            .'/participants/'.$participant->id.'/participate';
        $this->postJson($endpoint, [
            'phase_id' => $phase->id, 'contribution' => ['action' => 'participant.traverse'],
        ])->assertOk()->assertJsonPath('data.entry.mode', 'position');
        $this->assertSame($branch->id, $participant->fresh()->current_proposition_id);
        $this->postJson($endpoint.'/enter', [])->assertCreated()
            ->assertJsonPath('data.plan.gate.id', $branch->id);
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

    /** @return array{Proposition, Proposition, Proposition} */
    private function branchedGraph(): array
    {
        $branch = $this->node('C', 0.0);
        $leaf = $this->node('D', 0.0);
        $region = $this->region('B', 0.9);
        $this->edge($branch, $leaf);
        $this->edge($branch, $region);
        $this->region('Disconnected global maximum', 1.0);

        return [$branch, $leaf, $region];
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

    private function traverse(Participant $participant, Phase $phase): array
    {
        return app(ParticipationService::class)->contribute(
            $participant, $phase->id, ['action' => 'participant.traverse'],
        );
    }
}
