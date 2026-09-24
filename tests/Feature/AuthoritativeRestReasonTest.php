<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\RestReason;
use App\Services\ContributionService;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\RestReasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $proof = app(ExhaustionService::class)->inspect($terminal);
        $this->assertSame('no_available_move', $proof['rest_reason']);
        $this->assertSame([], $proof['transitions']);
        $this->assertSame([], $proof['branches']);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_evidenced_actionless_position_with_zero_projected_potential_has_distinct_reason(): void
    {
        [, $terminal] = $this->exhaustedBranch();
        $this->assertSame(RestReason::NoRemainingPotential, app(RestReasonService::class)->derive($terminal));
        $this->assertSame('no_remaining_potential', app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal)['rest_reason']);
    }

    public function test_existing_positive_potential_prevents_a_no_potential_reason(): void
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

    public function test_zero_regional_potential_is_only_a_rest_diagnostic_not_a_tragedy_trigger(): void
    {
        [$root, $terminal] = $this->exhaustedBranch();
        $this->measure($root, 0);
        $region = app(PotentialProjectionService::class)->region($terminal->cycle->context_horizon_id);
        $this->assertSame(0.0, $region['potential']);
        $this->assertTrue(app(PotentialChargeService::class)->hasRemainingInHorizon($terminal));
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($terminal));
        $this->assertSame(RestReason::NoRemainingPotential, app(RestReasonService::class)->derive($terminal));
        $this->rest($terminal);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_legacy_rest_without_an_authoritative_reason_cannot_be_reinterpreted(): void
    {
        [, $terminal] = $this->exhaustedBranch();
        $terminal->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        $this->assertSame(RestReason::NoAvailableMove, app(RestReasonService::class)->derive($terminal));
    }
}
