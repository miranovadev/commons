<?php

namespace Tests\Feature;

use App\Models\CanonicalMeasurement;
use App\Models\Participant;
use App\Services\Observables\CommittalLineageService;
use App\Services\Observables\ProspectiveClosureService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\Support\RefreshIntegrationDatabase as RefreshDatabase;
use Tests\TestCase;

/** Ordinary Commit ancestry must pass through real expressive development. */
#[Group('integration')]
class RepeatedCommitHistoryLifecycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_second_commit_preserves_its_own_expressive_source_and_frozen_predecessor(): void
    {
        $first = $this->driveHorizonCycle(realClosure: true, actionBudget: 101, expressiveDirection: 'refute');
        $actor = Participant::sole();
        $closure = app(ProspectiveClosureService::class);
        $frozen = $closure->stored($first);
        $snapshot = $first->commitmentSnapshot->getAttributes();
        $actions = [];
        $second = $this->driveAuthorisedCycle([$actor], $first->id, actionBudget: 101,
            expressiveDirection: 'refute',
            recordAction: function ($participant, $state, $input, $result) use (&$actions): void {
                $actions[] = ['cycle' => $state['cycle']['sequence'], 'phase' => $state['plan']['phase']['number'],
                    'gate_id' => $state['plan']['gate_id'], 'input' => $input,
                    'result_id' => $participant->fresh()->current_proposition_id];
            });
        $source = $second->commitSources()->sole();
        $this->assertSame('expressive', $source->tier);
        $this->assertNotSame($first->id, $source->id);
        $this->assertSame($source->id, CanonicalMeasurement::findOrFail($second->id)->source_proposition_id);
        $this->assertSame($first->id, $second->phase->cycle->entry_committal_id);
        $this->assertSame($first->id, $source->phase->cycle->entry_committal_id);
        $this->assertSame($closure->stored($source), $closure->stored($second));
        $this->assertSame($frozen, $closure->stored($first->fresh()));
        $this->assertSame($snapshot, $first->fresh()->commitmentSnapshot->getAttributes());
        $ancestors = app(CommittalLineageService::class)->ancestors($second);
        $this->assertSame([$first->id, $first->phase->cycle->entry_committal_id], $ancestors->pluck('proposition_id')->all());
        $this->assertSame('settlement', $first->commitmentSnapshot->direction_after->value);
        $this->assertSame('settlement', $second->phase->cycle->contextHorizon->domain->value);
        $directory = base_path('test-results');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/second-commit-history.json', json_encode([
            'first_id' => $first->id, 'source_id' => $source->id, 'second_id' => $second->id,
            'actions' => $actions, 'source_closure' => $closure->stored($source),
            'snapshot' => $second->commitmentSnapshot->getAttributes(),
            'ancestry' => $ancestors->toArray(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
