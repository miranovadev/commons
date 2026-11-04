<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\RestReason;
use App\Services\ContributionService;
use App\Services\ExplorationEventService;
use App\Services\InputPlanService;
use App\Services\ParticipantContextSerializer;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\RestReasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class AuthoritativeRestReasonTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_unobserved_actionless_position_is_no_available_move_and_never_tragedy(): void
    {
        $head = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $terminal = $this->phase($head);
        $this->measure($head, 1);
        $proof = app(ExplorationEventService::class)->inspect($terminal);
        $this->assertSame('no_available_move', $proof['rest_reason']);
        $this->assertNull($proof['witness']);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_evidenced_actionless_position_classifies_rest_without_a_query(): void
    {
        [, $terminal] = $this->exhaustedBranch();
        $terminal->load('cycle');
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertSame(RestReason::NoAvailableMove, app(RestReasonService::class)->derive($terminal));
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame('no_available_move', app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal)['rest_reason']);
    }

    public function test_actionless_rest_with_positive_potential_has_the_same_reason(): void
    {
        [, $terminal, , , $head] = $this->exhaustedBranch();
        $this->measure($head, 0);
        $this->assertSame(RestReason::NoAvailableMove, app(RestReasonService::class)->derive($terminal));
    }

    public function test_participant_cannot_supply_the_rest_reason(): void
    {
        [, $terminal] = $this->exhaustedBranch();
        $plan = app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal);
        $this->expectException(InvalidArgumentException::class);
        app(ContributionService::class)->validateSchema($plan['schema'],
            ['action' => 'participant.rest', 'reason' => 'no_remaining_potential']);
    }

    public function test_zero_regional_potential_stays_observable_across_a_lawful_rest(): void
    {
        [$root, $terminal] = $this->exhaustedBranch();
        $this->measure($root, 0);
        $region = app(PotentialProjectionService::class)->region($terminal->cycle->context_horizon_id);
        $this->assertSame(0.0, $region['potential']);
        $this->assertTrue(app(PotentialChargeService::class)->hasRemainingInHorizon($terminal));
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($terminal));
        $this->assertSame(RestReason::NoAvailableMove, app(RestReasonService::class)->derive($terminal));
        $this->rest($terminal);
        $this->assertSame(RestReason::NoAvailableMove, $terminal->cycle->fresh()->rest_reason);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_recorded_legacy_reason_survives_model_and_plan_serialization(): void
    {
        [, $terminal] = $this->exhaustedBranch();
        $terminal->cycle->forceFill(['rested_at' => now(), 'completed_at' => now(),
            'rest_reason' => 'no_remaining_potential'])->save();
        $terminal->refresh();
        $this->assertSame(RestReason::NoRemainingPotential, $terminal->cycle->rest_reason);
        $plan = app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal);
        $this->assertSame('no_remaining_potential', app(ParticipantContextSerializer::class)->plan($plan)['rest_reason']);
    }

    public function test_legacy_rest_without_an_authoritative_reason_cannot_be_reinterpreted(): void
    {
        [, $terminal] = $this->exhaustedBranch();
        $terminal->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        $this->assertSame(RestReason::NoAvailableMove, app(RestReasonService::class)->derive($terminal));
    }
}
