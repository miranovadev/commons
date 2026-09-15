<?php

namespace Tests\Feature;

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
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CausalHorizonLineageTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'causal', 'title' => 'Causal Horizons', 'visibility' => 'public', 'configuration' => []])->save();
    }

    public function test_an_existing_settlement_cycle_contributes_after_another_participant_opens_emergence(): void
    {
        $b = $this->participant('B');
        $entry = app(CycleEntryService::class);
        $entry->enter($b, ['statement' => 'Origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']]);
        $a = $this->participant('A');
        $entry->enter($a);
        $cycleA = $a->cycles()->whereNull('completed_at')->sole();
        $cycleB = $b->cycles()->whereNull('completed_at')->sole();
        $h2 = $cycleA->contextHorizon;
        $this->assertSame($h2->id, $cycleB->context_horizon_id);
        $this->assertSame(ContextDomain::Settlement, $h2->domain);
        $service = app(ParticipationService::class);
        $stateA = $service->contribute($a, $cycleA->currentPhase->id, ['action' => 'proposition.variant', 'statement' => 'Signal in H2']);

        // Isolate transition routing from the numeric threshold. The action,
        // grounding, snapshot, terminal progression and projection paths are real.
        $this->positiveMeasurements();
        $order = $cycleB->currentPhase;
        $order->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'concord'])->save();
        $candidate = $this->node($order, $b->fresh()->position, 'expressive');
        $this->groundFixture($candidate);
        $order->forceFill(['gate_proposition_id' => $candidate->id])->save();
        $b->forceFill(['current_proposition_id' => $candidate->id])->save();
        $service->contribute($b, $order->id, ['action' => 'proposition.commit', 'proposition_id' => $candidate->id]);
        $commit = $b->fresh()->position;
        $h3 = ContextHorizon::where('opened_by_proposition_id', $commit->id)->sole();
        $this->assertSame($h2->id, $h3->parent_context_horizon_id);
        $this->assertSame($h2->id, $commit->contextHorizonMembership->context_horizon_id);
        $this->assertNotNull($cycleB->fresh()->completed_at);
        $this->assertNull($cycleA->fresh()->completed_at);

        $this->classificationEvidenceFixture($a->fresh()->position);
        $this->travel(10)->seconds();
        $stateA = $service->contribute($a, $stateA['plan']['phase']['id'], ['action' => 'proposition.reclassify']);
        $stance = $a->fresh()->position;
        $this->assertGreaterThan($h3->opened_at, $stance->created_at);
        $this->assertSame('stance', $stance->register);
        $this->assertSame('concord', $stateA['plan']['phase']['write_register']);
        $this->assertSame('settlement', $stateA['domain']);
        $this->assertSame($h2->id, $stateA['cycle']['context_horizon_id']);
        $this->assertSame($h2->id, $stance->contextHorizonMembership->context_horizon_id);
        $this->assertFalse($h3->propositions()->whereKey($stance->id)->exists());
        $workspace = $service->workspaceFor($a)['plan']['workspace'];
        $this->assertSame($h2->id, $workspace['horizon']['id']);
        $this->assertSame('settlement', $workspace['domain']);
        $this->assertNotNull($h2->fresh()->closed_at);

        $next = $b->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h3->id, $next->context_horizon_id);
        $this->assertSame('activity', $next->currentPhase->write_register);
        // Even traversal through a parent-domain Gate cannot change the Cycle vocabulary.
        $advanced = app(PhaseProgressionService::class)->advance($next->currentPhase, $commit);
        $this->assertSame('frontier', $advanced->write_register);

        // A finishes its original H2 Cycle after B is already operating in H3.
        $lateOrder = $cycleA->fresh()->currentPhase;
        $lateOrder->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'concord'])->save();
        $lateCandidate = $this->node($lateOrder, $stance);
        $this->groundFixture($lateCandidate);
        $lateOrder->forceFill(['gate_proposition_id' => $lateCandidate->id])->save();
        $a->forceFill(['current_proposition_id' => $lateCandidate->id])->save();
        app(ParticipationService::class)->contribute($a, $lateOrder->id, ['action' => 'proposition.commit', 'proposition_id' => $lateCandidate->id]);
        $lateCommit = $a->fresh()->position;
        $sibling = ContextHorizon::where('opened_by_proposition_id', $lateCommit->id)->sole();
        $this->assertSame($h2->id, $lateCommit->contextHorizonMembership->context_horizon_id);
        $this->assertSame(ContextDomain::Settlement, $lateCommit->commitmentSnapshot->direction_before);
        $this->assertSame($h2->id, $sibling->parent_context_horizon_id);
        $this->assertSame(ContextDomain::Emergence, $sibling->domain);
        $this->assertNotSame($h3->id, $sibling->id);
        $this->assertSame($commit->id, $h2->fresh()->closed_by_proposition_id);
        $this->assertSame($h3->id, $next->fresh()->context_horizon_id);
        $this->assertSame($h3->id, app(ParticipationService::class)->stateFor($b)['context_horizon_id']);

        $ids = ContextHorizon::orderBy('sequence')->pluck('id')->all();
        ContextHorizonMembership::where('proposition_id', $stance->id)->update(['context_horizon_id' => $h3->id]);
        app(ContextHorizonProjector::class)->rebuild($this->substrate);
        $this->assertSame($ids, ContextHorizon::orderBy('sequence')->pluck('id')->all());
        $this->assertSame($h2->id, $stance->contextHorizonMembership()->sole()->context_horizon_id);
        $this->assertSame($h2->id, $cycleA->fresh()->context_horizon_id);
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

    public function test_prior_tension_comes_from_the_parent_boundary_even_after_a_newer_sibling_commit(): void
    {
        [, , , , $h4a, , $pa] = $this->branches();
        $phase = $this->phase($h4a, $pa);
        $node = $this->node($phase, $pa, 'committal');
        $this->groundFixture($node);
        $this->positiveMeasurements();
        app(PropositionMeasurementService::class)->recalculate($node);
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
        $this->assertSame(-1.0, $values->correspondence);
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
            'sequence' => 1, 'context_horizon_id' => $horizon->id])->save();
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
            'depth' => 0, 'cadence' => 0, 'depth_direction' => 'negative', 'cadence_direction' => 'negative',
            'prominence' => (1 + $tension) / 2, 'turbulence' => (1 - $tension) / 2, 'tension' => $tension,
            'tension_before' => 0, 'direction_before' => $domain, 'direction_after' => $domain->opposite(),
            'calculator_version' => 'topology-fixture', 'evaluated_at' => now()])->save();

        return app(ContextHorizonProjector::class)->apply($snapshot);
    }

    private function positiveMeasurements(): void
    {
        $this->mock(LocalMeasurementService::class, function ($mock): void {
            $mock->shouldReceive('calculate')->andReturn(new LocalMeasurements(.6, .6, .6, .6));
        });
    }
}
