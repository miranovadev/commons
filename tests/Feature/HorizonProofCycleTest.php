<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\TestCase;

class HorizonProofCycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_one_participant_builds_and_commits_through_both_receipt_boundaries_over_cycles(): void
    {
        $this->driveHorizonCycle();
    }
}
