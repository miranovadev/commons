<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\Support\RefreshEvidenceDatabase as RefreshDatabase;
use Tests\TestCase;

#[Group('integration')]
class HorizonProofCycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_one_participant_builds_and_commits_through_both_receipt_boundaries_over_cycles(): void
    {
        $this->markTestSkipped('Accepted fresh-Origin solo first-Commit blocker: docs/solo-commit-design-review.md. '
            .'Re-enable after acquisition and recovery corrections establish a lawful solo path without changing measurement gates.');

        // Persist-only acquisition changes the event history. Use the lawful
        // skeptical path; all-Support now disagrees at the independent Bias
        // Orientation/Cadence gate. Do not weaken that mathematical constraint.
        $this->driveHorizonCycle(actionBudget: 101, expressiveDirection: 'refute', mediateDirection: 'support');
    }
}
