<?php

namespace Tests\Feature;

use App\Services\Observables\PhaseProfileObservationService;
use App\Services\Observables\ProspectiveClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\TestCase;

/** Authorised birth events, not the retired Cardinal/Cadence birth-only Closure contract. */
class ClosureBirthTest extends TestCase
{
    use RefreshDatabase;

    public function test_expressive_birth_has_ledger_governor_and_immutable_interval_before_closure(): void
    {
        $fixture = new GovernedExpressionFixture;
        $head = $fixture->expression;
        $this->assertNotNull($head->ledgerEntry);
        $this->assertSame($fixture->origin->id, $head->phase->cycle->entry_committal_id);
        $this->assertSame([$fixture->origin->id], $head->parents()->pluck('propositions.id')->all());
        $this->assertNotNull(app(PhaseProfileObservationService::class)->stored($head->id));
        $state = app(ProspectiveClosureService::class)->stored($head);
        $this->assertSame(['kind' => 'expressive_birth', 'id' => $head->id], $state['trigger']);
        $this->assertNull($state['phase_observations'][$head->id]['interval']);
    }

    public function test_later_structural_birth_does_not_remeasure_older_authorised_closure(): void
    {
        $fixture = new GovernedExpressionFixture;
        $first = $fixture->expression;
        $states = app(ProspectiveClosureService::class);
        // The new Cycle legitimately updates prospective cadence. Capture the
        // baseline after entry, then isolate the subsequent structural birth.
        $fixture->enterPersist();
        $before = $states->stored($first);
        $interval = app(PhaseProfileObservationService::class)->stored($first->id);
        $fixture->writePersist();
        $this->assertSame($before, $states->stored($first));
        $this->assertEquals($interval, app(PhaseProfileObservationService::class)->stored($first->id));
        $this->assertSame(['kind' => 'expressive_birth', 'id' => $fixture->expression->id], $states->stored($fixture->expression)['trigger']);
    }
}
