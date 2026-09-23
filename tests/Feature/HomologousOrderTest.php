<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\ClosureGateService;
use App\Services\ContributionDispatcher;
use App\Services\HomologousOrderService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class HomologousOrderTest extends TestCase
{
    use CreatesHomologousPositions;
    use RefreshDatabase;

    private Proposition $origin;
    private Proposition $a;
    private Proposition $b;
    private Proposition $gate;
    private Proposition $positive;
    private Proposition $negative;
    private Phase $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createHomologyActor();
        $this->origin = $this->commit(null, 'emergence', ['cadence_interval' => 1]);
        $this->a = $this->commit($this->origin, 'settlement', ['cadence_interval' => 1]);
        $this->b = $this->commit($this->origin, 'settlement', ['cadence_interval' => 1]);
        $this->positive = $this->frozen($this->b, 'positive');
        $this->negative = $this->frozen($this->b, 'negative');
        $this->gate = $this->position($this->a, PhaseKind::Bias);
        $this->edge($this->a, $this->gate, 'variant');
        $this->activate();
    }

    public function test_tie_uses_default_or_explicit_history_and_ignores_unresolved_members(): void
    {
        $unresolved = $this->frozen($this->b, 'positive', 'negative');
        $service = app(HomologousOrderService::class);
        $population = $service->populationFor($this->order);
        $this->assertNull($service->directionFor(collect([$unresolved]))->mean);
        $this->assertNull($service->directionFor(collect([$unresolved]))->direction);
        $before = $this->snapshot();
        foreach ([null, ObservableDirection::Positive, ObservableDirection::Negative] as $previous) {
            $field = $service->directionFor($population, $previous);
            $this->assertSame(0.0, $field->mean);
            $this->assertSame($previous ?? ObservableDirection::Negative, $field->direction);
            $this->assertNull($field->values[$unresolved->id]);
        }
        foreach ([null, new ParticipationContext($this->order)] as $context) {
            $this->assertSame([$this->negative->id], $this->traverseIds($context));
        }
        $this->assertSame($before, $this->snapshot());
        $this->reject($this->positive);
        $this->reject($unresolved);
    }

    public function test_frozen_component_directions_control_matching_even_when_numeric_closure_is_zero(): void
    {
        $extra = $this->frozen($this->b, 'positive');
        $population = app(HomologousOrderService::class)->populationFor($this->order);
        $field = app(HomologousOrderService::class)->directionFor($population);
        $this->assertSame(.3333333, $field->mean);
        $this->assertSame(ObservableDirection::Positive, $field->direction);
        $this->assertEqualsCanonicalizing([$extra->id, $this->positive->id], $this->traverseIds());
        $this->assertSame(0.0, $extra->commitmentSnapshot->closure);
        $this->reject($this->negative);
    }

    public function test_wrong_coordinates_and_broken_committed_history_cannot_supply_traverse(): void
    {
        $wrongDepth = $this->frozen($this->negative, 'negative');
        $otherDomainEntry = $this->commit($this->origin, 'emergence');
        $wrongDomain = $this->frozen($otherDomainEntry, 'negative');
        $wrongPosition = $this->frozen($this->b, 'negative');
        // Deliberately corrupt storage to exercise fail-closed legacy/malformed input.
        DB::table('phases')->where('id', $wrongPosition->phase_id)->update(['kind' => PhaseKind::Bias->value]);
        $missing = $this->frozen($this->b, 'negative');
        DB::table('commitment_snapshots')->where('proposition_id', $missing->id)->delete();
        foreach ([$wrongDepth, $wrongDomain, $wrongPosition, $missing] as $node) {
            $this->assertNotContains($node->id, $this->traverseIds());
            $this->reject($node);
        }
    }

    public function test_missing_ancestor_read_data_fails_closed_without_rewriting_immutable_storage(): void
    {
        $lineage = app(\App\Services\Observables\CommittalLineageService::class);
        $records = $lineage->recordsForIds($this->actor->substrate_id,
            collect([$this->a->id, $this->positive->id, $this->negative->id]));
        $records->forget($this->b->id);
        // The database correctly protects boundary snapshots. Inject malformed
        // historical read data, as the shared ancestry tests do, without disabling guards.
        $rows = $records->map(fn ($record) => (object) $record->getAttributes())->values()->all();
        $before = $this->snapshot();
        $database = DB::getFacadeRoot();
        $readBoundary = \Mockery::mock($database);
        $readBoundary->shouldReceive('select')->twice()->andReturn($rows);
        DB::swap($readBoundary);
        try {
            $this->assertEmpty(app(HomologousOrderService::class)->populationFor($this->order));
        } finally {
            DB::swap($database);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_inadmissible_local_gate_keeps_homologous_traverse_without_gaining_commit_or_evidence(): void
    {
        $this->receiptFixture($this->negative);
        $receipt = app(\App\Services\PropositionStateService::class)->receipts($this->negative)->firstOrFail();
        $this->activate();
        $this->publishPotentialFixture($this->actor);
        $before = $this->snapshot();
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->order);
        $this->assertArrayNotHasKey('proposition.commit', $plan['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('participant.rest', $plan['schema']['$defs']['actions']);
        $this->assertSame([$this->negative->id], $plan['schema']['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum']);
        $this->assertNotContains($this->negative->id, array_column($plan['read_options'], 'id'));
        $this->assertNotContains($receipt->id, array_column($plan['receipts'], 'id'));
        $option = collect($plan['traversal_options'])->firstWhere('id', $this->negative->id);
        $this->assertSame([], $option['receipts']);
        $this->assertSame([], $option['instruments']);
        $this->assertSame($before, $this->snapshot());
        $this->expectException(DomainException::class);
        app(ContributionDispatcher::class)->dispatch($this->actor, $this->order,
            ['action' => 'proposition.commit', 'proposition_id' => $this->gate->id]);
    }

    public function test_homologous_traverse_completes_cycle_and_enters_selected_committal_without_edges(): void
    {
        $this->publishPotentialFixture($this->actor);
        $before = [Proposition::count(), Supersession::count(), DB::table('receipt_consumptions')->count(),
            DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->toJson()];
        $old = $this->order->cycle;
        $state = app(ParticipationService::class)->contribute($this->actor, $this->order->id,
            ['action' => 'proposition.traverse', 'proposition_id' => $this->negative->id]);
        $next = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertSame(PhaseKind::Distinguish, $next->kind);
        $this->assertSame($this->negative->id, $next->gate_proposition_id);
        $this->assertSame($this->negative->id, $next->cycle->entry_committal_id);
        $this->assertSame($this->negative->phase->cycle->context_horizon_id, $next->cycle->context_horizon_id);
        $this->assertNotSame($old->context_horizon_id, $next->cycle->context_horizon_id);
        $this->assertNotNull($old->fresh()->completed_at);
        $this->assertSame($this->a->id, $old->fresh()->entry_committal_id);
        $this->assertSame($before, [Proposition::count(), Supersession::count(), DB::table('receipt_consumptions')->count(),
            DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->toJson()]);
    }

    public function test_admissible_local_commit_survives_opposite_population_and_enters_its_new_commit(): void
    {
        $this->mock(ClosureGateService::class, function ($mock): void {
            $closure = [...(new \App\Domain\Observables\ClosureValues(1, 1))->resolve(),
                'history_proposition_id' => $this->a->id, 'history_source' => 'committed-ancestor', 'cadence_interval' => 1];
            $mock->shouldReceive('preview', 'forCommitment')->andReturn($closure);
        });
        $vote = new Attestation;
        $vote->forceFill(['phase_id' => $this->gate->phase_id, 'proposition_id' => $this->gate->id, 'direction' => 'support'])->save();
        app(LedgerService::class)->record($vote);
        $this->loadedReceiptFixture($this->gate);
        $this->activate();
        $this->publishPotentialFixture($this->actor);
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->order);
        $this->assertArrayHasKey('proposition.commit', $plan['schema']['$defs']['actions']);
        $this->assertContains($this->negative->id, array_column($plan['traversal_options'], 'id'));
        $state = app(ParticipationService::class)->contribute($this->actor, $this->order->id,
            ['action' => 'proposition.commit', 'proposition_id' => $this->gate->id]);
        $next = Phase::findOrFail($state['plan']['phase']['id']);
        $committed = Proposition::findOrFail($next->gate_proposition_id);
        $this->assertSame('committal', $committed->tier);
        $this->assertSame($this->gate->statement, $committed->statement);
        $this->assertSame($committed->id, $next->cycle->entry_committal_id);
        $this->assertSame($this->a->id, $this->order->cycle->fresh()->entry_committal_id);
    }

    public function test_direct_local_committal_remains_traversable_when_it_is_not_dominant(): void
    {
        $local = $this->frozen($this->a, 'positive');
        $this->edge($this->gate, $local, 'commit');
        $this->frozen($this->b, 'negative');
        $this->frozen($this->b, 'negative');
        $this->assertContains($local->id, $this->traverseIds());
        $this->assertNotContains($local->id, app(HomologousOrderService::class)->forPhase($this->order)->pluck('id'));
        app(PhaseProgressionService::class)->advance($this->order, $local);
        $this->assertSame($local->id, $this->actor->fresh()->current_proposition_id);
    }

    public function test_other_phases_and_direct_progression_do_not_bypass_authorization(): void
    {
        $bias = $this->phase($this->a, $this->gate, PhaseKind::Bias);
        $this->assertEmpty(app(HomologousOrderService::class)->forPhase($bias));
        $this->assertNotContains($this->negative->id, app(PropositionOptionService::class)->traversalOptionsFor($bias)->pluck('id'));
        $this->expectException(DomainException::class);
        app(PhaseProgressionService::class)->advance($this->order, $this->positive);
    }

    public function test_boundary_traverse_enters_the_selected_committals_opened_horizon(): void
    {
        $boundary = $this->commit($this->b, 'emergence',
            ['depth' => -1, 'cadence' => -1, 'closure' => -1, 'depth_direction' => 'negative', 'cadence_direction' => 'negative']);
        $this->activate();
        $this->publishPotentialFixture($this->actor);
        $state = app(ParticipationService::class)->contribute($this->actor, $this->order->id,
            ['action' => 'proposition.traverse', 'proposition_id' => $boundary->id]);
        $next = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertSame($boundary->id, $next->cycle->entry_committal_id);
        $this->assertSame($boundary->id, $next->cycle->contextHorizon->opened_by_proposition_id);
        $this->assertSame('emergence', $next->cycle->contextHorizon->domain->value);
        $this->assertSame($this->a->id, $this->order->cycle->fresh()->entry_committal_id);
    }

    public function test_same_horizon_homologues_are_distinct_and_included_once(): void
    {
        $sameHorizon = $this->frozen($this->a, 'negative');
        $population = app(HomologousOrderService::class)->populationFor($this->order);
        $this->assertCount(3, $population);
        $this->assertCount(1, $population->where('id', $sameHorizon->id));
        $this->assertContains($sameHorizon->id, $this->traverseIds());
        $this->assertNotSame($sameHorizon->id, $this->order->cycle->entry_committal_id);
    }

    #[Group('query')]
    public function test_order_population_discovery_and_selection_are_batched(): void
    {
        $counts = [];
        foreach ([2, 7] as $count) {
            if ($count === 7) {
                foreach (range(1, 5) as $index) {
                    $this->frozen($this->commit($this->origin, 'settlement'), 'negative');
                }
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $options = app(HomologousOrderService::class)->forPhase($this->order->fresh());
                $counts[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount($count - 1, $options);
        }
        $this->assertSame($counts[0], $counts[1], 'Discovery must not query once per Committal or Horizon.');
    }

    public function test_operational_homologous_continuation_rejects_rest_without_publishing_failure(): void
    {
        $this->publishPotentialFixture($this->actor);
        $before = [$this->snapshot(), DB::table('tragedies')->count(),
            DB::table('commit_region_exhaustions')->count(), $this->order->cycle->fresh()->getAttributes()];
        try {
            app(ParticipationService::class)->contribute($this->actor, $this->order->id,
                ['action' => 'participant.rest']);
            $this->fail('A valid homologous Traverse prevents Rest.');
        } catch (\InvalidArgumentException) {
            $this->assertSame($before, [$this->snapshot(), DB::table('tragedies')->count(),
                DB::table('commit_region_exhaustions')->count(), $this->order->cycle->fresh()->getAttributes()]);
        }
    }

    public function test_structural_population_without_resolved_continuation_allows_legitimate_rest(): void
    {
        // Deliberately unresolved frozen components, not a directionless numeric zero.
        $this->a = $this->positive;
        $this->b = $this->negative;
        $this->frozen($this->b, 'positive', 'negative');
        $this->frozen($this->b, 'negative', 'positive');
        $this->gate = $this->position($this->a, PhaseKind::Bias);
        $this->edge($this->a, $this->gate, 'variant');
        $this->activate();
        $this->publishPotentialFixture($this->actor);
        $population = app(HomologousOrderService::class)->populationFor($this->order);
        $this->assertCount(2, $population);
        $this->assertEmpty($this->traverseIds());
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->order);
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $before = [Proposition::count(), Supersession::count(), DB::table('receipt_consumptions')->count()];
        app(ParticipationService::class)->contribute($this->actor, $this->order->id, ['action' => 'participant.rest']);
        $this->assertNotNull($this->order->cycle->fresh()->rested_at);
        $this->assertSame($this->a->id, $this->order->cycle->fresh()->entry_committal_id);
        $this->assertSame($before, [Proposition::count(), Supersession::count(), DB::table('receipt_consumptions')->count()]);
    }

    private function frozen(Proposition $entry, string $depth, ?string $cadence = null): Proposition
    {
        return $this->commit($entry, $entry->commitmentSnapshot->direction_after->value,
            ['cadence_interval' => 1, 'depth' => 0, 'cadence' => 0, 'closure' => 0, 'depth_direction' => $depth, 'cadence_direction' => $cadence ?? $depth]);
    }

    private function activate(): void
    {
        Phase::query()->update(['completed_at' => now()]);
        Cycle::query()->update(['completed_at' => now()]);
        $this->order = $this->phase($this->a, $this->gate, PhaseKind::Order);
        $this->actor->forceFill(['current_proposition_id' => $this->gate->id])->save();
    }

    private function edge(Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    private function traverseIds(?ParticipationContext $context = null): array
    {
        return app(PropositionOptionService::class)->traversalOptionsFor($this->order, $context)->pluck('id')->all();
    }

    private function reject(Proposition $target): void
    {
        $before = $this->snapshot();
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->order,
                ['action' => 'proposition.traverse', 'proposition_id' => $target->id]);
            $this->fail('An unauthorized Committal must not be traversable.');
        } catch (DomainException) {
            $this->assertSame($before, $this->snapshot());
        }
    }

    private function snapshot(): array
    {
        return collect(['propositions' => 'id', 'participants' => 'id', 'supersessions' => 'id',
            'commitment_snapshots' => 'proposition_id', 'proposition_measurements' => 'proposition_id',
            'receipt_consumptions' => 'receipt_id'])->map(fn ($key, $table) => DB::table($table)->orderBy($key)->get()->toJson())->all();
    }
}
