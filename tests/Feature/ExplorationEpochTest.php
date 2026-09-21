<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\ExplorationEpochService;
use App\Services\HorizonFailureService;
use App\Services\InstrumentExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\SpentContinuationFixture;
use Tests\TestCase;

class ExplorationEpochTest extends TestCase
{
    use RefreshDatabase, SpentContinuationFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_downstream_failure_records_each_positions_own_epoch_and_stops_at_committal(): void
    {
        [$signal, $activity, $terminal, $return] = $this->testedLineage();
        $horizon = $terminal->cycle->context_horizon_id;
        foreach ([[$signal, 3], [$activity, 7]] as [$node, $epoch]) {
            DB::table('horizon_exploration_states')->insert(['context_horizon_id' => $horizon,
                'structural_position' => $node->phase->kind->value, 'structural_source_id' => $node->phase->gate_proposition_id, 'epoch' => $epoch]);
        }
        $proof = app(HorizonFailureService::class)->inspect($terminal);
        $this->assertSame('continuation_failed', $proof['reason']);
        $this->rest($terminal);
        foreach ([[$signal, 1, 3], [$activity, 4, 7]] as [$node, $position, $epoch]) {
            $this->assertDatabaseHas('continuation_failures', ['proposition_id' => $node->id,
                'context_horizon_id' => $horizon, 'structural_position' => $position, 'exploration_epoch' => $epoch]);
            $this->assertTrue(app(ExplorationEpochService::class)->spentNow($node, $return));
        }
        $this->assertDatabaseMissing('continuation_failures', ['proposition_id' => $this->origin->id]);
        $before = DB::table('continuation_failures')->orderBy('proposition_id')->get()->toJson();
        app(HorizonFailureService::class)->publish($terminal, $proof);
        $this->assertSame($before, DB::table('continuation_failures')->orderBy('proposition_id')->get()->toJson());
        $this->assertSame(0, Tragedy::count());
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($terminal->cycle->contextHorizon));
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);

        $this->measure($activity, 0);
        app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
            ['action' => 'proposition.variant', 'statement' => 'A changed Distinguish field']);
        $epochs = app(ExplorationEpochService::class);
        $this->assertSame(4, $epochs->forProposition($signal));
        $this->assertSame(7, $epochs->forProposition($activity));
        $this->assertFalse($epochs->spentNow($signal, $return));
        $this->assertTrue($epochs->spentNow($activity, $return));
        $this->assertSame($before, DB::table('continuation_failures')->orderBy('proposition_id')->get()->toJson());
    }

    public function test_variant_advances_once_and_rejected_or_rolled_back_writes_do_not_advance(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $epochs = app(ExplorationEpochService::class);
        $horizon = $phase->cycle->context_horizon_id;
        $dispatch = fn () => app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'One successful probe']);
        $this->assertSame(0, $epochs->forPhase($phase));
        DB::beginTransaction();
        $dispatch();
        $this->assertSame(1, $epochs->forPhase($phase));
        DB::rollBack();
        $this->assertSame(0, $epochs->forPhase($phase));
        $node = $dispatch();
        try {
            $dispatch();
            $this->fail('An untested existing Variant must reject the second write');
        } catch (\DomainException) {
            $this->assertSame(1, $epochs->forPhase($phase));
        }
        $instruments = app(InstrumentExecutionService::class);
        $receipt = $instruments->complete($instruments->start($phase, $node, 'citation', $this->origin->id), $node);
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'attestation.create', 'proposition_id' => $node->id, 'direction' => 'support']);
        $this->assertSame(1, $epochs->forPhase($phase), 'Instrument, Receipt and Attestation do not advance an epoch');
        $this->assertNotNull($receipt->id);
        $this->assertSame(0, $epochs->current(['context_horizon_id' => $horizon,
            'structural_position' => PhaseKind::Disperse->value, 'structural_source_id' => $node->id]));
    }

    public function test_unobserved_actionless_mediate_is_not_spent_even_at_zero_potential(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Disperse), $this->origin);
        $phase = $this->phase($source, PhaseKind::Mediate);
        $this->measure($source, 1);
        $this->rest($phase);
        $this->assertDatabaseCount('continuation_failures', 0);
        $this->assertSame(0, Tragedy::count());
    }

    public function test_a_live_sibling_prevents_failure_from_propagating_to_its_parent(): void
    {
        [$signal, $activity, $terminal] = $this->testedLineage();
        $sibling = $this->node($signal->phase, $signal);
        $this->measure($activity, 0);
        $proof = app(HorizonFailureService::class)->inspect($terminal);
        $this->assertContains($activity->id, $proof['transitions']);
        $this->assertNotContains($signal->id, $proof['transitions']);
        $this->assertNotContains($sibling->id, $proof['transitions']);
    }

    public function test_a_captured_failure_cannot_spend_a_new_epoch_even_if_field_version_matches(): void
    {
        [, $activity, $terminal] = $this->testedLineage();
        $proof = app(HorizonFailureService::class)->inspect($terminal);
        $terminal->forceFill(['completed_at' => now()])->save();
        $terminal->cycle->forceFill(['completed_at' => now(), 'rested_at' => now(), 'rest_reason' => $proof['rest_reason']])->save();
        // Isolate epoch validation from the additional field-version defense.
        DB::table('horizon_exploration_states')->insert(['context_horizon_id' => $terminal->cycle->context_horizon_id,
            'structural_position' => 4, 'structural_source_id' => $activity->phase->gate_proposition_id, 'epoch' => 1]);
        app(HorizonFailureService::class)->publish($terminal, $proof);
        $this->assertDatabaseCount('continuation_failures', 0);
        $this->assertDatabaseCount('horizon_failure_witnesses', 0);
        $this->assertSame(0, Tragedy::count());
    }
}
