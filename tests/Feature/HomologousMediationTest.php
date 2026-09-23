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

    public function test_foreign_reads_and_immediate_gates_are_discovered_without_changing_public_options(): void
    {
        $receipt = $this->loadedReceiptFixture($this->gateB);
        $context = new ParticipationContext($this->mediate);
        $this->assertFalse($context->propositions->has($this->gateB->id));
        $this->assertFalse($context->receipts->contains('id', $receipt->id));
        $service = app(HomologousMediationService::class);
        foreach ([null, $context] as $readModel) {
            $pool = $service->poolFor($this->mediate, $readModel);
            $this->assertSame([$this->readB->id], $pool['reads']->pluck('id')->all());
            $this->assertSame([$this->gateB->id], $pool['gates']->pluck('id')->all());
        }
        $this->assertFalse($context->propositions->has($this->gateB->id));
        $this->assertFalse($context->receipts->contains('id', $receipt->id));
        $this->assertNotSame($this->gateA->phase->cycle->context_horizon_id, $this->gateB->phase->cycle->context_horizon_id);
        // This checkpoint deliberately does not publish the discovery pool yet.
        $this->assertSame([$this->readA->id], app(MediationReadService::class)->visibleReadsFor($this->mediate, $context)->pluck('id')->all());
        $this->assertTrue(app(ReconciliationOptionService::class)->forPhase($this->mediate, $context)->isEmpty());
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
