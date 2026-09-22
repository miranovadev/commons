<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Services\ClosureGateService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class CommitBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_loaded_reinforcement_can_commit_and_the_direct_source_is_consumed_globally(): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $source = $f->node($f->gate, 'canon', position: PhaseKind::Bias);
        $alternative = $f->node($f->gate, 'canon', position: PhaseKind::Bias);
        $this->classificationEvidenceFixture($source);
        $closure = [...(new ClosureValues(1, 1))->resolve(), 'history_proposition_id' => null, 'history_source' => 'negative-default', 'cadence_interval' => 1];
        $this->mock(ClosureGateService::class, fn ($mock) => $mock->shouldReceive('preview', 'forCommitment')->andReturn($closure));
        $f->phase->forceFill(['kind' => PhaseKind::Order, 'gate_proposition_id' => $source->id, 'write_register' => 'canon'])->save();
        $admissibility = app(CommitmentAdmissibilityService::class)->evaluate($source, $f->phase->fresh());
        $this->assertTrue($admissibility['admissible']);
        $this->assertSame(0, $admissibility['reinforcement_count']);
        $this->assertArrayNotHasKey('reinforcement', $admissibility['requirements']);
        $commit = app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase->fresh(), ['action' => 'proposition.commit', 'proposition_id' => $source->id]);
        $this->assertSame('committal', $commit->tier);
        $this->assertDatabaseHas('commit_results', ['source_proposition_id' => $source->id, 'target_proposition_id' => $commit->id]);
        $this->assertNotNull($commit->commitmentSnapshot);
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase->fresh(), ['action' => 'proposition.commit', 'proposition_id' => $source->id]);
            $this->fail('The same source committed twice.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
        }
        $f->phase->forceFill(['completed_at' => now()])->save();
        $f->phase = $f->phase->replicate();
        $f->phase->completed_at = null;
        $f->phase->forceFill(['kind' => PhaseKind::Bias, 'gate_proposition_id' => $f->gate->id])->save();
        $plan = app(InputPlanService::class)->forParticipant($f->viewer);
        foreach (['instrument_options', 'attestation_options', 'revision_options', 'write_options'] as $key) {
            $this->assertNotContains($source->id, array_column($plan[$key], 'id'));
            $this->assertNotContains($commit->id, array_column($plan[$key], 'id'));
        }
        $this->assertContains($alternative->id, array_column($plan['write_options'], 'id'));
        foreach ([$source, $commit] as $frozen) {
            try {
                app(OperationalInstrumentService::class)->execute($f->phase->fresh(), $frozen, ['kind' => 'citation', 'reference' => $f->seed->id]);
                $this->fail('Frozen content received new evidence.');
            } catch (\DomainException $exception) {
                $this->assertStringContainsString('cannot receive instruments', $exception->getMessage());
            }
        }
        $snapshot = $commit->commitmentSnapshot->fresh()->getAttributes();
        $measurement = app(PropositionMeasurementService::class)->project($commit, -.99, -.99, -.99);
        $this->assertSame($snapshot, $commit->commitmentSnapshot->fresh()->getAttributes());
        $this->assertEqualsWithDelta($snapshot['closure'], $measurement->closure, .0000001);
        $this->assertSame(0, app(ReinforcementService::class)->count($source));
    }
}
