<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\Support\RefreshIntegrationDatabase as RefreshDatabase;
use Tests\TestCase;

#[Group('integration')]
class HorizonProofCycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_one_participant_builds_and_commits_through_both_receipt_boundaries_over_cycles(): void
    {
        // C1 advertises Commit after action 100; execute that next lawful action.
        $this->driveHorizonCycle(actionBudget: 101);
    }
}
