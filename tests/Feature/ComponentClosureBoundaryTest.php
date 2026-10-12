<?php

namespace Tests\Feature;

use App\Services\Observables\ProspectiveClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

#[Group('component-contract')]
class ComponentClosureBoundaryTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    public function test_component_output_does_not_persist_a_fabricated_expressive_history(): void
    {
        $this->isolateClosureOutput();
        $fixture = new GovernedExpressionFixture;
        $this->assertDatabaseMissing('prospective_closure_states', ['proposition_id' => $fixture->expression->id]);
        $state = app(ProspectiveClosureService::class)->stored($fixture->expression);
        $this->assertSame('isolated_closure_output', $state['fixture']);
        $this->assertArrayNotHasKey('phase_observations', $state);
        $this->assertArrayNotHasKey('orientation_observations', $state);
    }

    public function test_component_output_cannot_be_preserved_as_an_authorised_commit(): void
    {
        $this->isolateClosureOutput();
        $fixture = new GovernedExpressionFixture;
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('component Closure double cannot establish canonical Commit preservation');
        app(ProspectiveClosureService::class)->preserve($fixture->origin, $fixture->expression, []);
    }

    public function test_nested_numerical_double_cannot_bypass_component_preservation_boundary(): void
    {
        $this->isolateClosureOutput();
        $fixture = new GovernedExpressionFixture;
        $this->controlledBirthClosureFixture(1);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Component fixtures cannot establish canonical Commit preservation');
        app(ProspectiveClosureService::class)->preserve($fixture->origin, $fixture->expression, []);
    }
}
