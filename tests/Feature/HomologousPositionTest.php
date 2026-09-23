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
use Tests\TestCase;

class HomologousPositionTest extends TestCase
{
    use RefreshDatabase;

    private Participant $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'homology', 'title' => 'Homology', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
    }

    public function test_nearest_shared_commit_and_equal_edge_distance_define_the_coordinate(): void
    {
        $c0 = $this->commit(null, 'emergence');
        $c1 = $this->commit($c0, 'emergence');
        $a2 = $this->commit($c1, 'emergence');
        $a3 = $this->commit($a2, 'emergence');
        $b2 = $this->commit($c1, 'emergence');
        $b3 = $this->commit($b2, 'emergence');
        $service = app(HomologousPositionService::class);
        $this->assertSame([$b3->id => ['ancestor_id' => $c1->id, 'distance' => 2]],
            $service->committedCoordinatesFor($a3, collect([$b2, $b3])));
        $this->assertFalse($service->areCommittedHomologous($a3, $b2));
        $this->assertTrue($service->areCommittedHomologous($b3, $a3));
        $this->assertSame([$a3->id => ['ancestor_id' => $a3->id, 'distance' => 0]],
            $service->committedCoordinatesFor($a3, collect([$a3])));
        // These fixtures deliberately have no Supersession edges at all.
        $this->assertSame(0, DB::table('supersessions')->count());
    }

    public function test_same_horizon_phase_homology_preserves_canonical_phase_and_register(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'emergence');
        $b = $this->commit($origin, 'emergence');
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
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'settlement');
        $b = $this->commit($origin, 'settlement');
        $readA = $this->position($a);
        $readB = $this->position($b);
        $this->assertNotSame($readA->phase->cycle->context_horizon_id, $readB->phase->cycle->context_horizon_id);
        $this->assertTrue(app(HomologousPositionService::class)->arePositionsHomologous($readA, $readB));
        $this->assertFalse(app(PhasePositionService::class)->matches($readB, $readA->phase, PhaseKind::Constrain));
        $this->assertSame([$readB->id], app(HomologousPositionService::class)->candidatesFor($readA, PhaseKind::Constrain)->pluck('id')->all());
    }

    public function test_domain_disagreement_is_rejected_even_with_equal_distance_or_misleading_registers(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a1 = $this->commit($origin, 'emergence');
        $b1 = $this->commit($origin, 'settlement');
        $a2 = $this->commit($a1, 'emergence');
        $b2 = $this->commit($b1, 'settlement');
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
        $origin = $this->commit(null, 'emergence');
        $otherRoot = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'emergence');
        $b = $this->commit($otherRoot, 'emergence');
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
        $origin = $this->commit(null, 'emergence');
        $boundary = $this->commit($origin, 'settlement');
        $otherBoundary = $this->commit($origin, 'settlement');
        $a = $this->commit($boundary, 'settlement');
        $b = $this->commit($boundary, 'settlement');
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
        $a = $this->commit(null, 'emergence');
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'elsewhere', 'title' => 'Elsewhere', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'other'])->save();
        $b = $this->commit(null, 'emergence');
        $this->assertFalse(app(HomologousPositionService::class)->areCommittedHomologous($a, $b));
    }

    public function test_batch_expansion_uses_one_query_independent_of_candidate_count_and_chain_length(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $origin;
        $b = $origin;
        $candidates = collect();
        foreach (range(1, 12) as $depth) {
            $a = $this->commit($a, 'emergence');
            $b = $this->commit($b, 'emergence');
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

    private function position(?Proposition $governor, PhaseKind $kind = PhaseKind::Constrain, ?string $register = null): Proposition
    {
        $phase = $this->phase($governor, $governor, $kind);
        if ($register !== null) {
            $phase->forceFill(['write_register' => $register])->save();
        }
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $register ?? $phase->write_register, 'statement' => 'Structural position'])->save();
        app(LedgerService::class)->record($node);

        return $node;
    }

    private function commit(?Proposition $governor, string $after): Proposition
    {
        $phase = $this->phase($governor, $governor, PhaseKind::Order);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'committal', 'register' => $phase->write_register, 'statement' => 'Region seed'])->save();
        $entry = app(LedgerService::class)->record($node);
        $horizon = $phase->cycle->contextHorizon;
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $entry->id, 'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
            'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $horizon->domain, 'direction_after' => $after,
            'calculator_version' => $governor === null ? 'origin-bootstrap-v1' : 'ownership-fixture', 'evaluated_at' => now()])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);

        return $node;
    }

    private function phase(?Proposition $governor, ?Proposition $gate, PhaseKind $kind, bool $malformed = false): Phase
    {
        $horizon = $governor === null ? app(ContextHorizonProjector::class)->currentFor($this->actor->substrate)
            : ($malformed ? $gate->phase->cycle->contextHorizon : app(ContextHorizonProjector::class)->forEntry($governor));
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->actor->id, 'sequence' => (int) $this->actor->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id, 'governing_committal_id' => $governor?->id]);
        if ($malformed) {
            Cycle::withoutEvents(fn () => $cycle->save());
        } else {
            $cycle->save();
        }
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $gate?->id,
            'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()])->save();

        return $phase;
    }

}
