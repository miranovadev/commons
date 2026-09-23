<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\HomologousMediationService;
use App\Services\LedgerService;
use App\Services\MediationReadService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use App\Services\ReconciliationOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomologousMediationTest extends TestCase
{
    use RefreshDatabase;

    private Participant $actor;
    private Proposition $a;
    private Proposition $b;
    private Proposition $readA;
    private Proposition $readB;
    private Proposition $gateA;
    private Proposition $gateB;
    private Phase $mediate;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'foreign-mediate', 'title' => 'Foreign Mediate', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
        $origin = $this->commit(null, 'emergence');
        $this->a = $this->commit($origin, 'settlement');
        $this->b = $this->commit($origin, 'settlement');
        [$this->readA, $this->gateA] = $this->pair($this->a);
        [$this->readB, $this->gateB] = $this->pair($this->b);
        // Leave exactly the acting Cycle open for the public contribution path.
        Phase::query()->update(['completed_at' => now()]);
        Cycle::query()->update(['completed_at' => now()]);
        $disperse = $this->phase($this->a, $this->readA, PhaseKind::Disperse);
        $disperse->forceFill(['completed_at' => now()])->save();
        $this->mediate = new Phase;
        $this->mediate->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'gate_proposition_id' => $this->gateA->id, 'write_register' => 'stance', 'started_at' => now()])->save();
        $this->actor->forceFill(['current_proposition_id' => $this->gateA->id])->save();
        $this->measure($this->readA, -0.8);
        $this->measure($this->readB, -0.5);
        $this->measure($this->gateA, 0.8);
        $this->measure($this->gateB, 0.9);
    }

    public function test_foreign_operands_are_exposed_without_importing_receipts_or_movement(): void
    {
        $receipt = $this->loadedReceiptFixture($this->gateB);
        $context = new ParticipationContext($this->mediate);
        $this->assertTrue($context->propositions->has($this->gateB->id));
        $this->assertFalse(app(\App\Services\ReceiptContextService::class)->forPhase($this->mediate, $context)->contains('id', $receipt->id));
        $service = app(HomologousMediationService::class);
        foreach ([null, $context] as $readModel) {
            $pool = $service->poolFor($this->mediate, $readModel);
            $this->assertSame([$this->readB->id], $pool['reads']->pluck('id')->all());
            $this->assertSame([$this->gateB->id], $pool['gates']->pluck('id')->all());
        }
        $this->assertFalse(app(\App\Services\ReceiptContextService::class)->gateOptions($this->mediate, $context)->contains('id', $receipt->id));
        $this->assertNotSame($this->gateA->phase->cycle->context_horizon_id, $this->gateB->phase->cycle->context_horizon_id);
        $this->assertSame([$this->readA->id], app(MediationReadService::class)->visibleReadsFor($this->mediate, $context)->pluck('id')->all());
        $this->assertSame([$this->gateB->id], app(ReconciliationOptionService::class)->forPhase($this->mediate, $context)->pluck('id')->all());
        $this->assertFalse(app(\App\Services\PropositionOptionService::class)->traversalOptionsFor($this->mediate, $context)->contains('id', $this->gateB->id));
        $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
    }

    public function test_retained_read_direction_controls_eligibility_including_zero_hysteresis(): void
    {
        $service = app(HomologousMediationService::class);
        $this->measure($this->readB, 0);
        $this->assertSame([$this->gateB->id], $service->poolFor($this->mediate)['gates']->pluck('id')->all());
        $this->measure($this->readB, 0.5);
        $this->measure($this->gateB, -0.8);
        $this->assertTrue($service->poolFor($this->mediate)['gates']->isEmpty());
        $this->measure($this->readB, 0);
        $this->assertTrue($service->poolFor($this->mediate)['gates']->isEmpty());
        $this->measure($this->readA, 0.5);
        $this->assertSame([$this->gateB->id], $service->poolFor($this->mediate)['gates']->pluck('id')->all());
    }

    public function test_distinct_homologous_regions_in_the_same_horizon_are_also_discovered(): void
    {
        $a = $this->commit($this->a, 'settlement');
        $b = $this->commit($this->a, 'settlement');
        [$readA, $gateA] = $this->pair($a);
        [$readB, $gateB] = $this->pair($b);
        $disperse = $this->phase($a, $readA, PhaseKind::Disperse);
        $disperse->forceFill(['completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'gate_proposition_id' => $gateA->id, 'write_register' => 'stance', 'started_at' => now()])->save();
        $this->assertSame($gateA->phase->cycle->context_horizon_id, $gateB->phase->cycle->context_horizon_id);
        $pool = app(HomologousMediationService::class)->poolFor($phase);
        $this->assertSame([$readB->id], $pool['reads']->pluck('id')->all());
        $this->assertSame([$gateB->id], $pool['gates']->pluck('id')->all());
    }

    public function test_wrong_phase_register_and_non_immediate_children_are_excluded(): void
    {
        $this->expression($this->b, $this->readB, PhaseKind::Distinguish); // Signal, but wrong producing phase.
        $this->expression($this->b, $this->readB, PhaseKind::Disperse, 'stance');
        $middle = $this->expression($this->b, $this->readB, PhaseKind::Mediate);
        $this->expression($this->b, $middle, PhaseKind::Disperse);
        $this->assertSame([$this->gateB->id], app(HomologousMediationService::class)->poolFor($this->mediate)['gates']->pluck('id')->all());
    }

    public function test_wrong_read_position_and_non_homologous_regions_are_excluded(): void
    {
        $wrong = $this->expression($this->b, $this->b, PhaseKind::Bias);
        $this->expression($this->b, $wrong, PhaseKind::Disperse);
        $badRegister = $this->expression($this->b, $this->b, PhaseKind::Constrain, 'stance');
        $this->expression($this->b, $badRegister, PhaseKind::Disperse);
        $deeper = $this->commit($this->b, 'settlement');
        $this->pair($deeper);
        $unrelated = $this->commit(null, 'settlement');
        $this->pair($unrelated);
        $otherDomain = $this->commit($this->a, 'emergence');
        $this->pair($otherDomain);
        $pool = app(HomologousMediationService::class)->poolFor($this->mediate);
        $this->assertSame([$this->readB->id], $pool['reads']->pluck('id')->all());
        $this->assertSame([$this->gateB->id], $pool['gates']->pluck('id')->all());
    }

    public function test_commit_consumed_gate_is_unavailable_but_other_hard_replacements_remain_reusable(): void
    {
        $replacement = $this->expression($this->b, $this->gateB, PhaseKind::Mediate, edgeType: 'reconcile');
        $service = app(HomologousMediationService::class);
        $this->assertSame([$this->gateB->id], $service->poolFor($this->mediate)['gates']->pluck('id')->all());
        $commit = $this->commit($this->b, 'settlement');
        $this->edge($this->gateB, $commit, 'commit');
        $this->assertTrue($service->poolFor($this->mediate)['gates']->isEmpty());
        $this->assertNotNull($replacement->fresh());
    }

    public function test_missing_causal_anchor_and_non_mediate_phase_fail_closed(): void
    {
        $service = app(HomologousMediationService::class);
        $this->assertTrue($service->poolFor($this->gateA->phase)['gates']->isEmpty());
        $this->mediate->cycle->phases()->where('kind', PhaseKind::Disperse->value)->update(['completed_at' => null]);
        $this->assertTrue($service->poolFor($this->mediate)['reads']->isEmpty());
    }

    public function test_query_count_does_not_grow_per_foreign_read_or_gate(): void
    {
        $counts = [];
        foreach ([1, 6] as $count) {
            if ($count === 6) {
                foreach (range(1, 5) as $index) {
                    $this->pair($this->b);
                }
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $pool = app(HomologousMediationService::class)->poolFor($this->mediate->fresh());
                $counts[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount($count, $pool['gates']);
        }
        $this->assertSame($counts[0], $counts[1], 'Adding independent foreign candidates must not add per-candidate queries.');
    }

    public function test_public_cross_horizon_reconcile_preserves_ownership_gate_route_and_foreign_input_geometry(): void
    {
        $receipt = $this->loadedReceiptFixture($this->gateB);
        $this->publishPotentialFixture($this->actor);
        $plan = app(\App\Services\InputPlanService::class)->forPhase($this->actor, $this->mediate);
        $this->assertSame([$this->readA->id], array_column($plan['read_options'], 'id'));
        $this->assertSame([$this->gateB->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame([$this->gateB->id], $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
        $this->assertNotContains($receipt->id, array_column($plan['receipts'], 'id'));
        $this->assertSame([], $plan['gate_options'][0]['receipts']);
        $this->assertSame([], $plan['gate_options'][0]['instruments']);
        $this->assertNotContains($this->gateB->id, array_column($plan['traversal_options'], 'id'));
        $horizonA = $this->mediate->cycle->context_horizon_id;
        $horizonB = $this->gateB->phase->cycle->context_horizon_id;
        $foreignBefore = $this->gateB->fresh()->getAttributes();
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id];
        app(\App\Services\ParticipationService::class)->contribute($this->actor, $this->mediate->id, $input);
        $result = Proposition::findOrFail($this->actor->fresh()->current_proposition_id);
        $this->assertSame($this->mediate->id, $result->phase_id);
        $this->assertSame($this->a->id, $result->phase->cycle->governing_committal_id);
        $this->assertSame($this->gateA->id, $result->phase->gate_proposition_id);
        $this->assertSame($horizonA, $result->phase->cycle->context_horizon_id);
        $this->assertSame($this->a->id, app(\App\Services\CommitRegionService::class)->seedFor($result)->id);
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id], $result->parents()->pluck('propositions.id')->all());
        $lineage = app(\App\Services\ActiveParticipantLineage::class)->endingAt($result)->pluck('id');
        $this->assertContains($this->a->id, $lineage);
        $this->assertNotContains($this->b->id, $lineage);
        $this->assertNotContains($this->gateB->id, $lineage);
        $this->assertSame($foreignBefore, $this->gateB->fresh()->getAttributes());
        $this->assertSame($this->b->id, app(\App\Services\CommitRegionService::class)->seedFor($this->gateB)->id);
        app(ContextHorizonProjector::class)->rebuild($this->actor->substrate);
        $this->assertSame($horizonA, $result->fresh()->contextHorizonMembership->context_horizon_id);
        $this->assertSame($horizonB, $this->gateB->fresh()->contextHorizonMembership->context_horizon_id);
        $this->publishPotentialFixture($this->actor);
        $local = app(\App\Services\PotentialProjectionService::class)->region($horizonA)['local'];
        $this->assertContains([$this->gateB->id, $result->id, 'reconcile'], $local['foreign_inputs']);
        $this->assertArrayNotHasKey($this->gateB->id, $local['nodes']);
        $this->assertNotContains([$this->gateB->id, $result->id, 'reconcile'], $local['edges']);
        app(\App\Services\GraphProjectionService::class)->load($this->actor->substrate, collect([$result]));
        $this->assertSame($this->a->id, $result->getAttribute('field')['committal_proposition_id']);
        $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $this->gateB->id, 'target_proposition_id' => $result->id, 'type' => 'reconcile']);
        $this->assertFalse(app(ReconciliationOptionService::class)->forPhase($this->mediate->fresh())->contains('id', $this->gateB->id));
    }

    public function test_local_and_foreign_counterparts_are_combined_and_stale_foreign_direction_is_rechecked(): void
    {
        $local = $this->expression($this->a, $this->readA, PhaseKind::Disperse);
        $this->assertEqualsCanonicalizing([$local->id, $this->gateB->id],
            app(ReconciliationOptionService::class)->forPhase($this->mediate)->pluck('id')->all());
        $this->measure($this->readB, 0.8);
        $this->assertSame([$local->id], app(ReconciliationOptionService::class)->forPhase($this->mediate)->pluck('id')->all());
        $before = Proposition::count();
        try {
            app(\App\Services\ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
                ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
            $this->fail('A stale foreign Read direction must not authorize a write.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
        }
    }

    public function test_same_horizon_foreign_result_does_not_become_the_operand_regions_mediate_write(): void
    {
        $a = $this->commit($this->a, 'settlement');
        $b = $this->commit($this->a, 'settlement');
        [$readA, $gateA] = $this->pair($a);
        [$readB, $gateB] = $this->pair($b);
        // Isolate option scope with a real two-parent representation. Public
        // contribution execution is covered by the cross-Horizon test above.
        $foreignResult = $this->expression($a, $gateA, PhaseKind::Mediate, edgeType: 'reconcile');
        $this->edge($gateB, $foreignResult, 'reconcile');
        $localResult = $this->expression($b, $gateB, PhaseKind::Mediate, edgeType: 'reconcile');
        $disperse = $this->phase($b, $readB, PhaseKind::Disperse);
        $disperse->forceFill(['completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'gate_proposition_id' => $gateB->id, 'write_register' => 'stance', 'started_at' => now()])->save();
        $this->assertSame($foreignResult->phase->cycle->context_horizon_id, $phase->cycle->context_horizon_id);
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $writes = app(\App\Services\PropositionOptionService::class)->writeOptionsFor($phase, $context);
            $this->assertSame([$localResult->id], $writes->pluck('id')->all());
            $this->assertFalse($writes->contains('id', $foreignResult->id));
        }
        $this->assertEqualsCanonicalizing([$gateA->id, $gateB->id], $foreignResult->parents()->pluck('propositions.id')->all());
    }

    private function pair(Proposition $governor): array
    {
        $read = $this->expression($governor, $governor, PhaseKind::Constrain);
        $gate = $this->expression($governor, $read, PhaseKind::Disperse);
        $this->measure($read, -0.5);

        return [$read, $gate];
    }

    private function expression(Proposition $governor, Proposition $parent, PhaseKind $kind, ?string $register = null, string $edgeType = 'variant'): Proposition
    {
        $phase = $this->phase($governor, $parent, $kind);
        if ($register !== null) {
            $phase->forceFill(['write_register' => $register])->save();
        }
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $register ?? $phase->write_register, 'statement' => 'Comparison fixture'])->save();
        app(LedgerService::class)->record($node);
        $this->edge($parent, $node, $edgeType);

        return $node;
    }

    private function edge(Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    private function measure(Proposition $node, float $value): void
    {
        app(PropositionMeasurementService::class)->project($node, $value, 0, 0);
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
