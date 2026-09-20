<?php

namespace Tests\Feature;

use App\Services\Observables\LineageMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\TestCase;

#[Group('integration')]
class OperationalClosurePathTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_receipt_backed_full_cycle_commits_with_real_hysteretic_closure_calculations(): void
    {
        $commit = $this->driveHorizonCycle(realClosure: true);
        $snapshot = $commit->commitmentSnapshot;
        $this->assertSame(LineageMeasurementService::VERSION, $snapshot->calculator_version);
        $this->assertSame($snapshot->depth_direction, $snapshot->cadence_direction);
        $this->assertSame(6, $commit->commitSources()->sole()->phase->kind->value);
        $this->assertNotNull($snapshot->closure_history_proposition_id);
    }
}
