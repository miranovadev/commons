<?php

namespace Tests\Feature;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\CorrespondenceValues;
use App\Domain\Observables\LocalMeasurements;
use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ClosureGateService;
use App\Services\CommitmentSnapshotService;
use App\Services\ContextHorizonProjector;
use App\Services\LedgerService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class CausalHorizonLineageTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    private Substrate $substrate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'causal', 'title' => 'Causal Horizons', 'visibility' => 'public', 'configuration' => []])->save();
    }

    public function test_component_boundary_projection_does_not_reassign_open_cycle_governance(): void
    {
        $this->isolateCorrespondenceOutput();
        $fixture = new GovernedExpressionFixture;
        $fixture->enterPersist();
        $cycle = $fixture->phase->cycle;
        $horizon = $cycle->contextHorizon;
        $governor = $cycle->entry_committal_id;
        // This supplies a later boundary projection, not an authorised Commit.
        $later = $this->laterHorizonFixture($horizon, 1);
        $fixture->writePersist();
        $head = $fixture->expression;
        $this->assertSame($horizon->id, $cycle->fresh()->context_horizon_id);
        $this->assertSame($governor, $cycle->fresh()->entry_committal_id);
        $this->assertSame($cycle->id, $head->phase->cycle_id);
        $this->assertSame('stance', $head->register);
        $this->assertSame($horizon->id, $head->contextHorizonMembership->context_horizon_id);
        $this->assertFalse($later->propositions()->whereKey($head->id)->exists());
        $this->assertNotNull($horizon->fresh()->closed_at);
        $this->assertSame($horizon->id, $later->parent_context_horizon_id);
        $this->publishPotentialFixture($fixture->actor);
        $state = app(ParticipationService::class)->stateFor($fixture->actor);
        $this->assertSame('settlement', $state['domain']);
        $this->assertSame($horizon->id, $state['cycle']['context_horizon_id']);
    }

    public function test_branching_alternates_and_comparisons_use_only_the_direct_parent_in_both_calculation_paths(): void
    {
        [$root, $h2, $h3a, $h3b, $h4a, $h4b, $pa, $pb] = $this->branches();
        $compare = app(ContextLineageComparisonService::class);
        foreach ([[$h2, $root], [$h3a, $h2], [$h3b, $h2], [$h4a, $h3a], [$h4b, $h3b]] as [$child, $parent]) {
            $this->assertSame($parent->domain->opposite(), $child->domain);
            $this->assertSame($parent->id, $child->parent->id);
            $boundary = $child->openedBy->proposition;
            $this->assertSame($parent->id, $boundary->contextHorizonMembership->context_horizon_id);
        }
        foreach ([[$h4a, $h3a, $pa, $pb], [$h4b, $h3b, $pb, $pa]] as [$horizon, $parent, $opener, $sibling]) {
            $phase = $this->phase($horizon, $opener);
            $node = $this->node($phase, $opener);
            // A cross-branch proposition edge must not change the Horizon comparison.
            $this->edge($phase, $sibling, $node);
            $context = new ParticipationContext($phase);
            foreach ([null, $context] as $readModel) {
                $comparison = $compare->for($node, $readModel);
                $preview = $compare->prospectiveCommit($node, $phase, $readModel);
                foreach ([$comparison, $preview] as $result) {
                    $this->assertSame($horizon->id, $result->current->horizon->id);
                    $this->assertSame($parent->id, $result->previous->horizon->id);
                    $this->assertContains($opener->id, $result->previous->propositions->pluck('id'));
                    $this->assertNotContains($sibling->id, $result->previous->propositions->pluck('id'));
                    $this->assertFalse($result->isBootstrap());
                }
                $history = app(ClosureGateService::class)->historyFor($node, $readModel);
                $this->assertSame($opener->id, $history->proposition_id);
            }
        }
        $this->assertSame($h3a->id, app(ContextHorizonProjector::class)->apply($h3a->openedBy)->id);
        $this->assertSame($h3a->opened_by_proposition_id, $h2->fresh()->closed_by_proposition_id);
        $this->assertCount(2, $h2->children);
        $this->assertSame($h4a->id, app(ContextHorizonProjector::class)->forEntry($pa)->id);
        $this->assertSame($h4b->id, app(ContextHorizonProjector::class)->forEntry($pb)->id);
    }

    public function test_snapshot_projection_uses_parent_tension_even_after_a_newer_sibling(): void
    {
        [, , , , $h4a, , $pa] = $this->branches();
        $phase = $this->phase($h4a, $pa);
        $node = $this->node($phase, $pa, 'committal');
        $this->groundFixture($node);
        $this->positiveMeasurements();
        app(PropositionMeasurementService::class)->recalculate($node);
        // Explicit snapshot-assembly inputs, not an observed committed interval.
        $this->componentClosureInput($node, ['cadence_interval' => 1, 'history_proposition_id' => $pa->id]);
        $snapshot = app(CommitmentSnapshotService::class)->record($node, .5, .5, 'causal-test');
        $this->assertSame($pa->commitmentSnapshot->tension, $snapshot->tension_before);
        $this->assertSame(-.4, $snapshot->tension_before);
        $this->assertSame(ContextDomain::Settlement, $snapshot->direction_before);
        $this->assertSame($pa->id, $snapshot->closure_history_proposition_id);
    }

    public function test_only_origin_uses_bootstrap_and_missing_non_root_provenance_fails_closed(): void
    {
        $root = app(ContextHorizonProjector::class)->currentFor($this->substrate);
        $phase = $this->phase($root);
        $origin = $this->node($phase, null, 'committal');
        $compare = app(ContextLineageComparisonService::class);
        $this->assertTrue($compare->for($origin)->isBootstrap());
        $values = app(LocalMeasurementService::class)->calculate($origin);
        $this->assertSame(1.0, $values->correspondence);
        $this->assertSame(-1.0, $values->depth);
        $this->assertSame(-1.0, $values->cadence);
        $h2 = $this->boundary($origin, -.8);
        $node = $this->node($this->phase($h2));
        $this->assertFalse($compare->for($node)->isBootstrap());
        $this->assertSame($root->id, $compare->for($node)->previous->horizon->id);
        ContextHorizonMembership::where('proposition_id', $origin->id)->delete();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('requires provenance in its immediate parent');
        $compare->for($node);
    }

    public function test_cycle_ownership_is_immutable_in_eloquent(): void
    {
        [$root, $h2] = $this->branches();
        $cycle = $root->cycles()->firstOrFail();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot change its Context Horizon');
        $cycle->forceFill(['context_horizon_id' => $h2->id])->save();
    }

    public function test_cycle_ownership_is_immutable_in_bulk_sql(): void
    {
        [$root, $h2] = $this->branches();
        $this->expectException(QueryException::class);
        DB::table('cycles')->where('context_horizon_id', $root->id)->update(['context_horizon_id' => $h2->id]);
    }

    public function test_closed_horizons_retain_ownership_when_accepting_causal_reentry(): void
    {
        [, $h2] = $this->branches();
        $phase = $this->phase($h2);
        $this->assertSame($h2->id, $phase->cycle->context_horizon_id);
        $this->assertNotNull($h2->fresh()->closed_at);
    }

    public function test_cycle_creation_rejects_a_horizon_from_another_substrate(): void
    {
        $horizon = app(ContextHorizonProjector::class)->currentFor($this->substrate);
        $other = $this->substrate->replicate();
        $other->slug = 'elsewhere';
        $other->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $other->id, 'callsign' => 'foreign'])->save();
        $this->expectException(DomainException::class);
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1, 'context_horizon_id' => $horizon->id])->save();
    }

    public function test_a_horizon_cannot_be_reparented_after_cycles_begin(): void
    {
        [, , $h3a, $h3b, $h4a] = $this->branches();
        $this->phase($h4a);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot change its causal lineage');
        $h4a->forceFill(['parent_context_horizon_id' => $h3b->id])->save();
    }

    private function branches(): array
    {
        $root = app(ContextHorizonProjector::class)->currentFor($this->substrate);
        $origin = $this->node($this->phase($root), null, 'committal');
        $h2 = $this->boundary($origin, -.8);
        $a = $this->phase($h2, $origin);
        $b = $this->phase($h2, $origin); // Starts before A closes H2.
        $pa = $this->node($a, $origin, 'committal');
        $h3a = $this->boundary($pa, .7);
        $this->travel(2)->seconds();
        $pb = $this->node($b, $origin, 'committal');
        $h3b = $this->boundary($pb, .3);
        $pa = $this->node($this->phase($h3a, $pa), $pa, 'committal');
        $h4a = $this->boundary($pa, -.4);
        $pb = $this->node($this->phase($h3b, $pb), $pb, 'committal');
        $h4b = $this->boundary($pb, -.9); // Newest opposite-domain region is on B.

        return [$root, $h2, $h3a, $h3b, $h4a, $h4b, $pa, $pb];
    }

    private function participant(string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => $callsign])->save();

        return $participant;
    }

    private function phase(ContextHorizon $horizon, ?Proposition $gate = null): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->participant('fixture-'.str()->ulid())->id,
            'sequence' => 1, 'context_horizon_id' => $horizon->id,
            'entry_committal_id' => $gate?->tier === 'committal' ? $gate->id : ($gate?->phase->cycle->entry_committal_id ?? $horizon->opened_by_proposition_id)])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
            'write_register' => $horizon->domain->registerFor(PhaseKind::Order),
            'gate_proposition_id' => $gate?->id, 'started_at' => now()])->save();

        return $phase;
    }

    private function node(Phase $phase, ?Proposition $parent = null, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => $tier, 'statement' => 'Causal lineage fixture'])->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $this->edge($phase, $parent, $node);
        }

        return $node;
    }

    private function edge(Phase $phase, Proposition $source, Proposition $target): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $source->id, 'target_proposition_id' => $target->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
    }

    private function boundary(Proposition $node, float $tension): ContextHorizon
    {
        $snapshot = new CommitmentSnapshot;
        $domain = $node->phase->cycle->contextHorizon->domain;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
            'ledger_entry_id' => $node->ledgerEntry->id, 'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
            'depth' => 0, 'cadence' => 0, 'cadence_interval' => 1, 'depth_direction' => 'negative', 'cadence_direction' => 'negative',
            'prominence' => (1 + $tension) / 2, 'turbulence' => (1 - $tension) / 2, 'tension' => $tension,
            'tension_before' => 0, 'direction_before' => $domain, 'direction_after' => $domain->opposite(),
            'calculator_version' => 'topology-fixture', 'evaluated_at' => now()])->save();

        return app(ContextHorizonProjector::class)->apply($snapshot);
    }

    private function positiveMeasurements(): void
    {
        $this->mock(LocalMeasurementService::class, function ($mock): void {
            $mock->shouldReceive('chiralityComponents')->andReturn(new ChiralityValues(.6, .6));
            $mock->shouldReceive('calculate')->andReturn(new LocalMeasurements(.6, .6, .6, .6, chiralityComponents: new ChiralityValues(.6, .6), correspondenceComponents: new CorrespondenceValues(.6, .6)));
        });
    }
}
