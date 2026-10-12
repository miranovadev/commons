<?php

namespace Tests\Feature;

use App\Models\CanonicalMeasurement;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\PropositionStateService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\Support\RefreshIntegrationDatabase as RefreshDatabase;
use Tests\TestCase;

/** Independent skeptical-development transcript; the historical aligned transcript is unchanged. */
#[Group('integration')]
class CommitSourceHistoryLifecycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_commit_preserves_a_genuine_expressive_source_and_its_evidence_references(): void
    {
        $commit = $this->driveHorizonCycle(realClosure: true, originDirection: 'support',
            actionBudget: 101, expressiveDirection: 'refute');
        $source = $commit->commitSources()->sole();
        $this->assertSame('expressive', $source->tier);
        $this->assertSame($source->id, CanonicalMeasurement::findOrFail($commit->id)->source_proposition_id);
        $this->assertSame($source->statement, $commit->statement);
        $closure = app(ProspectiveClosureService::class);
        $this->assertSame($closure->stored($source), $closure->stored($commit));
        $this->assertEquals($closure->stored($commit), $commit->commitmentSnapshot->closure_observation);
        $state = app(PropositionStateService::class);
        $votes = $state->attestations(collect([$source]));
        $receipts = $state->receipts($source);
        $this->assertNotEmpty($votes);
        $this->assertNotEmpty($receipts);
        $this->assertSame($votes->pluck('id')->all(), $state->attestations(collect([$commit]))->pluck('id')->all());
        $this->assertSame($receipts->pluck('id')->all(), $state->receipts($commit)->pluck('id')->all());
        $this->assertSame(0, $commit->attestations()->count());
        $this->assertSame(0, $commit->instrumentExecutions()->count());
    }
}
