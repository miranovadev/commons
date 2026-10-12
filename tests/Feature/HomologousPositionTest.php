<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ContextHorizonProjector;
use App\Services\HomologousPositionService;
use App\Services\LedgerService;
use App\Services\PhasePositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class HomologousPositionTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    use \Tests\Fixtures\CreatesHomologousPositions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
        $this->createHomologyActor();
    }

    public function test_equal_committed_depth_defines_homology_with_nearest_ancestor_as_diagnostics(): void
    {
        $c0 = $this->committedCoordinate(null, 'emergence');
        $c1 = $this->committedCoordinate($c0, 'emergence');
        $a2 = $this->committedCoordinate($c1, 'emergence');
        $a3 = $this->committedCoordinate($a2, 'emergence');
        $b2 = $this->committedCoordinate($c1, 'emergence');
        $b3 = $this->committedCoordinate($b2, 'emergence');
        $service = app(HomologousPositionService::class);
        $this->assertSame([$b3->id => ['ancestor_id' => $c1->id, 'distance' => 2]],
            $service->committedCoordinatesFor($a3, collect([$b2, $b3])));
        $this->assertFalse($service->areCommittedHomologous($a3, $b2));
        $this->assertTrue($service->areCommittedHomologous($b3, $a3));
        $this->assertSame([$a3->id => ['ancestor_id' => $a3->id, 'distance' => 0]],
            $service->committedCoordinatesFor($a3, collect([$a3])));
        // These are committed-coordinate read-model inputs only. No edge count
        // or direct Committal-to-Committal lifecycle is asserted.
    }

    public function test_same_horizon_phase_homology_preserves_canonical_phase_and_register(): void
    {
        $origin = $this->committedCoordinate(null, 'emergence');
        $a = $this->committedCoordinate($origin, 'emergence');
        $b = $this->committedCoordinate($origin, 'emergence');
        $readA = $this->position($a);
        $readB = $this->position($b);
        $wrongPhase = $this->position($b, PhaseKind::Bias); // Same canon label, different phase.
        $wrongRegister = $this->position($b, register: 'frontier');
        $service = app(HomologousPositionService::class);
        $this->assertSame($readA->phase->cycle->context_horizon_id, $readB->phase->cycle->context_horizon_id);
        $this->assertTrue($service->arePositionsHomologous($readA, $readB));
        $this->assertFalse($service->arePositionsHomologous($readA, $wrongPhase));
        $this->assertFalse($service->arePositionsHomologous($readA, $wrongRegister));
        $candidates = collect([$readB, $wrongPhase, $wrongRegister]);
        $this->assertSame([$readB->id], $service->homologousPositionsFor($readA, $candidates)->pluck('id')->all());
        $this->assertCount(3, $candidates); // Comparison must not append the anchor to its caller's pool.
        $this->assertSame([$readB->id], $service->candidatesFor($readA, PhaseKind::Constrain)->pluck('id')->all());
    }

    public function test_sibling_horizons_are_structurally_homologous_but_still_operationally_foreign(): void
    {
        $origin = $this->committedCoordinate(null, 'emergence');
        $a = $this->committedCoordinate($origin, 'settlement');
        $b = $this->committedCoordinate($origin, 'settlement');
        $readA = $this->position($a);
        $readB = $this->position($b);
        $this->assertNotSame($readA->phase->cycle->context_horizon_id, $readB->phase->cycle->context_horizon_id);
        $this->assertTrue(app(HomologousPositionService::class)->arePositionsHomologous($readA, $readB));
        $this->assertFalse(app(PhasePositionService::class)->matches($readB, $readA->phase, PhaseKind::Constrain));
        $this->assertSame([$readB->id], app(HomologousPositionService::class)->candidatesFor($readA, PhaseKind::Constrain)->pluck('id')->all());
    }

    public function test_domain_disagreement_is_rejected_even_with_equal_distance_or_misleading_registers(): void
    {
        $origin = $this->committedCoordinate(null, 'emergence');
        $a1 = $this->committedCoordinate($origin, 'emergence');
        $b1 = $this->committedCoordinate($origin, 'settlement');
        $a2 = $this->committedCoordinate($a1, 'emergence');
        $b2 = $this->committedCoordinate($b1, 'settlement');
        $service = app(HomologousPositionService::class);
        $this->assertFalse($service->areCommittedHomologous($a2, $b2));
        $readA = $this->position($a1);
        $readB = $this->position($b1);
        $this->assertFalse($service->arePositionsHomologous($readA, $readB));
        // Persistence already rejects cross-Domain labels. Also reject a malformed
        // hydrated model without weakening those production validation rules.
        $readB->register = 'canon';
        $readB->phase->write_register = 'canon';
        $this->assertFalse($service->arePositionsHomologous($readA, $readB));
        $this->assertTrue($service->candidatesFor($readA, PhaseKind::Constrain)->isEmpty());
    }

    public function test_disconnected_and_missing_committed_history_fail_closed(): void
    {
        $origin = $this->committedCoordinate(null, 'emergence');
        $otherRoot = $this->committedCoordinate(null, 'emergence');
        $a = $this->committedCoordinate($origin, 'emergence');
        $b = $this->committedCoordinate($otherRoot, 'emergence');
        $service = app(HomologousPositionService::class);
        $this->assertFalse($service->areCommittedHomologous($a, $b));
        $uncommitted = new Proposition;
        $uncommitted->forceFill(['phase_id' => $a->phase_id, 'tier' => 'committal', 'register' => 'canon', 'statement' => 'No frozen proof'])->save();
        $this->assertFalse($service->areCommittedHomologous($a, $uncommitted));
        $ungoverned = $this->position(null);
        $this->assertFalse($service->arePositionsHomologous($this->position($a), $ungoverned));
    }

    public function test_malformed_ancestral_horizon_link_fails_closed_even_below_a_shared_ancestor(): void
    {
        $origin = $this->committedCoordinate(null, 'emergence');
        $boundary = $this->committedCoordinate($origin, 'settlement');
        $otherBoundary = $this->committedCoordinate($origin, 'settlement');
        $a = $this->committedCoordinate($boundary, 'settlement');
        $b = $this->committedCoordinate($boundary, 'settlement');
        $service = app(HomologousPositionService::class);
        $this->assertTrue($service->areCommittedHomologous($a, $b));
        // Database guards reject rewriting Horizon parentage. Exercise malformed
        // historical read data without disabling those production protections.
        $records = app(\App\Services\Observables\CommittalLineageService::class)
            ->recordsForIds($this->actor->substrate_id, collect([$a->id, $b->id]));
        $records[$boundary->id]->opened_parent_horizon_id = app(ContextHorizonProjector::class)->forEntry($otherBoundary)->id;
        $rows = $records->map(fn ($record) => (object) $record->getAttributes())->values()->all();
        $database = DB::getFacadeRoot();
        $readBoundary = \Mockery::mock($database);
        $readBoundary->shouldReceive('select')->once()->andReturn($rows);
        DB::swap($readBoundary);
        try {
            $this->assertFalse($service->areCommittedHomologous($a, $b));
        } finally {
            DB::swap($database);
        }
    }

    public function test_foreign_substrate_is_never_homologous(): void
    {
        $a = $this->committedCoordinate(null, 'emergence');
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'elsewhere', 'title' => 'Elsewhere', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'other'])->save();
        $b = $this->committedCoordinate(null, 'emergence');
        $this->assertFalse(app(HomologousPositionService::class)->areCommittedHomologous($a, $b));
    }

    public function test_cyclic_committed_read_history_fails_closed_without_modifying_immutable_storage(): void
    {
        $origin = $this->committedCoordinate(null, 'emergence');
        $a = $this->committedCoordinate($origin, 'emergence');
        $b = $this->committedCoordinate($origin, 'emergence');
        $a->load('phase.cycle.participant');
        $records = app(\App\Services\Observables\CommittalLineageService::class)
            ->recordsForIds($this->actor->substrate_id, collect([$a->id, $b->id]));
        $records[$origin->id]->entry_committal_id = $a->id;
        $database = DB::getFacadeRoot();
        $readBoundary = \Mockery::mock($database);
        $readBoundary->shouldReceive('select')->once()
            ->andReturn($records->map(fn ($record) => (object) $record->getAttributes())->values()->all());
        DB::swap($readBoundary);
        try {
            $this->assertFalse(app(HomologousPositionService::class)->areCommittedHomologous($a, $b));
        } finally {
            DB::swap($database);
        }
    }

    public function test_batch_expansion_uses_one_query_independent_of_candidate_count_and_chain_length(): void
    {
        $origin = $this->committedCoordinate(null, 'emergence');
        $a = $origin;
        $b = $origin;
        $candidates = collect();
        foreach (range(1, 12) as $depth) {
            $a = $this->committedCoordinate($a, 'emergence');
            $b = $this->committedCoordinate($b, 'emergence');
            $candidates->push($b);
            if (! in_array($depth, [2, 12], true)) {
                continue;
            }
            $a->load('phase.cycle.participant');
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $coordinates = app(HomologousPositionService::class)->committedCoordinatesFor($a, $candidates);
                $queries = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount(1, $queries);
            $this->assertSame([$b->id => ['ancestor_id' => $origin->id, 'distance' => $depth]], $coordinates);
        }
    }

}
