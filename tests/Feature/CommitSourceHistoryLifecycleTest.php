<?php

namespace Tests\Feature;

use App\Models\CanonicalMeasurement;
use App\Models\Receipt;
use App\Services\AttestationEvidenceService;
use App\Services\CommitRegionPolarityService;
use App\Services\Observables\ObservableService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\PropositionStateService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\Support\RefreshEvidenceDatabase as RefreshDatabase;
use Tests\TestCase;

/** Skeptical development with supportive synthesis; the historical aligned transcript is unchanged. */
#[Group('integration')]
class CommitSourceHistoryLifecycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_commit_preserves_a_genuine_expressive_source_and_its_evidence_references(): void
    {
        $actions = [];
        $reconciles = 0;
        $commit = $this->driveHorizonCycle(realClosure: true, originDirection: 'support',
            actionBudget: 101, expressiveDirection: 'refute', mediateDirection: 'support',
            recordAction: function ($participant, $state, $input, $result) use (&$actions, &$reconciles): void {
                $actions[] = ['cycle' => $state['cycle']['sequence'], 'phase' => $state['plan']['phase']['number'],
                    'gate_id' => $state['plan']['gate_id'], 'input' => $input,
                    'result_id' => $participant->fresh()->current_proposition_id];
                if ($input['action'] === 'proposition.reconcile') {
                    $head = $participant->fresh()->position;
                    $normal = app(ObservableService::class)->components($head);
                    $actual = $head->fresh()->measurement;
                    $this->assertSame($normal->correspondence, $actual->correspondence);
                    $this->assertSame($normal->correspondenceComponents->shapeTopology, $actual->shape_topology);
                    $this->assertSame($normal->correspondenceComponents->phaseProfile, $actual->phase_profile);
                    $this->assertEqualsCanonicalizing([$state['plan']['gate_id'], $input['other_proposition_id']],
                        $head->parents()->pluck('propositions.id')->all());
                    $reconciles++;
                }
            });
        $this->assertGreaterThan(0, $reconciles, 'Ordinary Reconcile measurement must actually execute.');
        $source = $commit->commitSources()->sole();
        $this->assertSame('expressive', $source->tier);
        $this->assertSame($source->id, CanonicalMeasurement::findOrFail($commit->id)->source_proposition_id);
        $this->assertSame($source->statement, $commit->statement);
        $sourceDirection = app(AttestationEvidenceService::class)->directionFor($source);
        $this->assertSame(['governing' => $sourceDirection, 'support' => $sourceDirection],
            app(CommitRegionPolarityService::class)->forSeed($commit));
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
        $this->assertSame(0, $commit->evidenceExecutions()->count());
        // Retain the actual authorised transcript as CI evidence for future
        // reusable history builders, instead of inferring a cheaper history.
        $directory = base_path('test-results');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/lawful-commit-history.json', json_encode([
            'origin_direction' => 'support', 'expressive_direction_preference' => 'refute',
            'mediate_direction_preference' => 'support',
            'reconciles_verified' => $reconciles, 'actions' => $actions, 'source_id' => $source->id, 'commit_id' => $commit->id,
            'receipts_in_creation_order' => Receipt::orderBy('id')->pluck('id')->all(),
            'source_closure' => $closure->stored($source),
            'snapshot' => $commit->commitmentSnapshot->getAttributes(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
