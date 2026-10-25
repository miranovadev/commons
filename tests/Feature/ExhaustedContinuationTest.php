<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Services\ExhaustedContinuationService;
use App\Services\ExplorationEventService;
use App\Services\InputPlanService;
use App\Services\Participation\ParticipationContext;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\PotentialRegionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExplorationHistory;
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
        $before = DB::table('exploration_events')->count();
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $history = collect($plan['exhausted_alternatives'])->firstWhere('id', $source->id);
        $this->assertTrue($history['continuation_exhausted']);
        $this->assertFalse($history['exhausted']);
        $this->assertFalse($history['basin_exhausted']);
        $this->assertFalse($history['actionable']);
        $proof = app(ExplorationEventService::class)->inspect($phase);
        $this->assertNull($proof['witness']);
        $this->rest($phase);
        $this->assertSame($before, DB::table('exploration_events')->count());
        $this->assertDatabaseHas('propositions', ['id' => $source->id]);
        $this->assertDatabaseHas('commitment_snapshots', ['proposition_id' => $opening->id]);
    }

    public function test_personal_exploration_does_not_terminate_a_live_alternative(): void
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
        ExplorationHistory::record($live);
        $this->assertFalse($service->ids($phase)->has($parent->id), 'Personal Rest is not terminal authority');
        $this->assertEquals($service->ids($phase), $service->ids($phase, new ParticipationContext($phase)));
        $this->assertTrue($service->ids($phase)->has($source->id));
        $this->assertFalse($service->ids($phase)->has($this->origin->id));
        $this->assertDatabaseHas('exploration_events', ['proposition_id' => $live->id]);
    }
}
