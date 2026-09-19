<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Services\ExhaustedContinuationService;
use App\Services\ExhaustionService;
use App\Services\ExplorationPositionService;
use App\Services\InputPlanService;
use App\Services\Participation\ParticipationContext;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\PotentialRegionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HorizonProofFixture;
use Tests\TestCase;

class ExhaustedContinuationTest extends TestCase
{
    use HorizonProofFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_successful_closed_history_remains_visible_without_a_new_failure(): void
    {
        [$opening, , $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $this->rest($terminal);
        $source = $opening->parents()->wherePivot('type', 'commit')->sole();
        $phase = $this->phase($source, PhaseKind::Order);
        $this->measure($source, 0);
        $local = app(PotentialProjectionService::class)->region($phase->cycle->context_horizon_id)['local'];
        $historical = app(PotentialRegionCalculator::class)->candidates($local, shadowCompletedRegions: false);
        $this->assertGreaterThan(0, collect($historical)->max('raw_potential_charge'));
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInHorizon($phase));
        $before = DB::table('continuation_failures')->count();
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $history = collect($plan['exhausted_alternatives'])->firstWhere('id', $source->id);
        $this->assertTrue($history['continuation_exhausted']);
        $this->assertFalse($history['exhausted']);
        $this->assertFalse($history['basin_exhausted']);
        $this->assertFalse($history['actionable']);
        $proof = app(ExhaustionService::class)->inspect($phase);
        $this->assertSame([], $proof['failures']);
        $this->rest($phase);
        $this->assertSame($before, DB::table('continuation_failures')->count());
        $this->assertDatabaseHas('propositions', ['id' => $source->id]);
        $this->assertDatabaseHas('commitment_snapshots', ['proposition_id' => $opening->id]);
    }

    public function test_live_alternatives_and_exact_current_epochs_control_backward_completion(): void
    {
        [$opening, , $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $this->rest($terminal);
        $source = $opening->parents()->wherePivot('type', 'commit')->sole();
        $parent = $source->parents()->sole();
        $phase = $this->phase($parent, PhaseKind::Persist);
        $live = $this->node($phase, $parent);
        $service = app(ExhaustedContinuationService::class);
        $this->assertTrue($service->ids($phase)->has($source->id));
        $this->assertFalse($service->ids($phase)->has($parent->id));
        $this->assertFalse($service->ids($phase)->has($live->id));
        $position = app(ExplorationPositionService::class)->forProposition($live);
        DB::table('continuation_failures')->insert([
            'proposition_id' => $live->id, 'context_horizon_id' => $position['context_horizon_id'],
            'structural_position' => $position['structural_position'], 'exploration_epoch' => 0,
            'phase_id' => $phase->id, 'failed_at' => now(),
        ]);
        $this->assertTrue($service->ids($phase)->has($parent->id));
        $this->assertEquals($service->ids($phase), $service->ids($phase, new ParticipationContext($phase)));
        // This isolated epoch rule deliberately constructs a later epoch; the
        // HTTP lifecycle test establishes actual Variant-driven advancement.
        DB::table('horizon_exploration_states')->insert([...$position, 'epoch' => 1]);
        $this->assertFalse($service->ids($phase)->has($parent->id));
        $this->assertTrue($service->ids($phase)->has($source->id));
        $this->assertFalse($service->ids($phase)->has($this->origin->id));
        $this->assertDatabaseHas('continuation_failures', ['proposition_id' => $live->id, 'exploration_epoch' => 0]);
    }
}
