<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Models\User;
use App\Services\ContextHorizonProjector;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantHistoricalContext;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class ExhaustedParticipantContextTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public static function domains(): array
    {
        return [[ContextDomain::Emergence], [ContextDomain::Settlement]];
    }

    #[DataProvider('domains')]
    public function test_origin_remembers_exhausted_writes_without_offering_them_as_targets(ContextDomain $domain): void
    {
        $this->setDomain($domain);
        $a = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $b = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $c = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->exhaust($a, $b);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $plan = app(InputPlanService::class)->forWorkspace($phase->cycle->participant);
        $history = collect($plan['exhausted_alternatives'])->where('role', 'write');
        $this->assertSame([$a->id, $b->id], $history->pluck('id')->all());
        $this->assertSame([$c->id], array_column($plan['write_options'], 'id'));
        $this->assertArrayHasKey('proposition.variant', $plan['schema']['$defs']['actions']);
        foreach ([$a, $b] as $node) {
            $this->assertStringNotContainsString($node->id, json_encode($plan['schema']));
            $this->assertFalse($history->firstWhere('id', $node->id)['actionable']);
        }
        $compact = app(ParticipantContextSerializer::class)->plan($plan);
        $inference = app(ParticipantContextSerializer::class)->inference(['status' => 'ready', 'plan' => $compact]);
        $this->assertSame($plan['exhausted_alternatives'], $inference['plan']['exhausted_alternatives']);
        $this->assertSame([$domain->registerFor(PhaseKind::Distinguish)], $history->pluck('register')->unique()->all());
        $this->assertSame([$c->id], $plan['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
    }

    public function test_all_exhausted_origin_choices_inform_but_do_not_block_fresh_generation(): void
    {
        $a = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->exhaust($a);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $participant = $phase->cycle->participant;
        $plan = app(InputPlanService::class)->forParticipant($participant);
        $this->assertSame([$a->id], array_column($plan['exhausted_alternatives'], 'id'));
        $this->assertSame([], $plan['write_options']);
        $this->assertArrayHasKey('proposition.variant', $plan['schema']['$defs']['actions']);
        $this->publishPotentialFixture($participant);
        $state = app(ParticipationService::class)->contribute($participant, $phase->id,
            ['action' => 'proposition.variant', 'statement' => 'A different direction informed by the exhausted path']);
        $this->assertNotSame($a->id, $state['current_proposition_id']);
        $this->assertDatabaseHas('propositions', ['id' => $state['current_proposition_id'],
            'statement' => 'A different direction informed by the exhausted path']);
    }

    public function test_progressing_to_persist_replaces_origin_scars_with_local_phase_two_scars(): void
    {
        $a = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $live = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $b = $this->node($this->phase($live, PhaseKind::Persist), $live);
        $this->exhaust($a, $b);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $before = app(InputPlanService::class)->forWorkspace($phase->cycle->participant);
        $this->assertSame([$a->id], array_column($before['exhausted_alternatives'], 'id'));
        $next = app(PhaseProgressionService::class)->advance($phase, $live);
        $after = app(InputPlanService::class)->forPhase($next->cycle->participant, $next, true);
        $this->assertSame([$b->id], array_column($after['exhausted_alternatives'], 'id'));
        $this->assertSame('write', $after['exhausted_alternatives'][0]['role']);
        $this->assertStringNotContainsString($b->id, json_encode($after['schema']));
    }

    public static function writePositions(): array
    {
        $cases = [];
        foreach (ContextDomain::cases() as $domain) {
            foreach ([PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse, PhaseKind::Mediate, PhaseKind::Bias] as $kind) {
                $cases[$domain->value.'-'.$kind->name] = [$domain, $kind];
            }
        }

        return $cases;
    }

    #[DataProvider('writePositions')]
    public function test_exhausted_writes_are_selected_by_producing_position_in_both_domains(ContextDomain $domain, PhaseKind $kind): void
    {
        $this->setDomain($domain);
        $gate = $this->origin;
        for ($number = 1; $number < $kind->value; $number++) {
            $gate = $this->node($this->phase($gate, PhaseKind::from($number)), $gate);
        }
        $right = $this->node($this->phase($gate, $kind), $gate);
        $wrongKind = match ($kind) {
            PhaseKind::Distinguish => PhaseKind::Disperse,
            PhaseKind::Persist => PhaseKind::Mediate,
            PhaseKind::Constrain => PhaseKind::Bias,
            PhaseKind::Disperse => PhaseKind::Distinguish,
            PhaseKind::Mediate => PhaseKind::Persist,
            PhaseKind::Bias => PhaseKind::Constrain,
        };
        $wrong = $this->node($this->phase($gate, $wrongKind), $gate);
        $this->assertSame($right->register, $wrong->register);
        $this->exhaust($right, $wrong);
        $phase = $this->phase($gate, $kind);
        $history = app(ParticipantHistoricalContext::class)->forPhase($phase, new ParticipationContext($phase));
        $writes = collect($history)->where('role', 'write');
        $this->assertSame([$right->id], $writes->pluck('id')->all());
        $this->assertSame([$kind->value], $writes->pluck('phase_number')->all());
    }

    public function test_revisions_remain_local_but_do_not_open_other_gates_or_horizons(): void
    {
        $local = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $revised = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $local);
        Supersession::where('target_proposition_id', $revised->id)->update(['type' => 'revise']);
        $otherCommit = $this->commitFrom($local);
        $other = $this->node($this->phase($otherCommit, PhaseKind::Distinguish), $otherCommit);
        $foreignHorizon = $this->laterHorizonFixture($local->phase->cycle->contextHorizon);
        $foreignGate = Proposition::findOrFail($foreignHorizon->opened_by_proposition_id);
        $foreign = $this->node($this->phase($foreignGate, PhaseKind::Distinguish), $foreignGate);
        $wrongTier = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin, 'committal');
        $wrongRegister = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $wrongRegister->forceFill(['register' => 'canon'])->save();
        $this->exhaust($local, $revised, $other, $foreign, $wrongTier, $wrongRegister);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $history = app(ParticipantHistoricalContext::class)->forPhase($phase);
        $this->assertSame([$local->id, $revised->id], array_column($history, 'id'));
    }

    public function test_late_context_obeys_commit_boundaries_even_within_one_horizon(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $gate = $this->node($this->phase($root, PhaseKind::Mediate), $root);
        $sibling = $this->node($this->phase($root, PhaseKind::Mediate), $root);
        $local = $this->node($this->phase($sibling, PhaseKind::Bias), $sibling);
        $commit = $this->commitFrom($root);
        $beyond = $this->node($this->phase($commit, PhaseKind::Bias), $commit);
        $ambiguous = $this->node($this->phase($root, PhaseKind::Bias), $root);
        (new Supersession)->forceFill(['phase_id' => $ambiguous->phase_id, 'source_proposition_id' => $commit->id,
            'target_proposition_id' => $ambiguous->id, 'type' => 'reconcile'])->save();
        $this->exhaust($sibling, $local, $beyond, $ambiguous);
        $phase = $this->phase($gate, PhaseKind::Bias);
        $history = app(ParticipantHistoricalContext::class)->forPhase($phase, new ParticipationContext($phase));
        $this->assertSame([$sibling->id], collect($history)->where('role', 'gate')->pluck('id')->all());
        $this->assertSame([$local->id], collect($history)->where('role', 'write')->pluck('id')->all());
    }

    public function test_mediate_context_retains_local_exhausted_reads_and_their_immediate_gates(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $read = $this->node($this->phase($root, PhaseKind::Constrain), $root);
        $gate = $this->node($this->phase($read, PhaseKind::Disperse), $read);
        $write = $this->node($this->phase($gate, PhaseKind::Mediate), $gate);
        $otherGate = $this->node($this->phase($root, PhaseKind::Disperse), $root);
        $this->exhaust($read, $gate, $write);
        $phase = $this->phase($otherGate, PhaseKind::Mediate);
        $history = app(ParticipantHistoricalContext::class)->forPhase($phase);
        $this->assertSame([$read->id], collect($history)->where('role', 'read')->pluck('id')->all());
        $this->assertSame([$gate->id], collect($history)->where('role', 'gate')->pluck('id')->all());
        $this->assertSame([], collect($history)->where('role', 'write')->all());
    }

    public function test_order_context_marks_exhausted_basins_without_calling_commits_exhausted(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root);
        $source = $commit->parents()->firstOrFail();
        $gate = $this->node($this->phase($root, PhaseKind::Bias), $root);
        $this->exhaust($source, $commit);
        $phase = $this->phase($gate, PhaseKind::Order);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $write = collect($plan['exhausted_alternatives'])->firstWhere('role', 'write');
        $this->assertSame($commit->id, $write['id']);
        $this->assertFalse($write['exhausted']);
        $this->assertTrue($write['basin_exhausted']);
        $this->assertStringNotContainsString($commit->id, json_encode($plan['schema']));
        $this->assertStringNotContainsString($source->id, json_encode($plan['schema']));
    }

    public function test_personal_evidence_and_ownership_cannot_reactivate_exhausted_targets(): void
    {
        $old = $this->phase($this->origin, PhaseKind::Distinguish);
        $node = $this->node($old, $this->origin);
        $this->receiptFixture($node);
        $vote = (new Attestation)->forceFill(['phase_id' => $old->id, 'proposition_id' => $node->id, 'direction' => 'refute']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $old->forceFill(['completed_at' => now()])->save();
        $old->cycle->forceFill(['completed_at' => now()])->save();
        $next = $this->phase($this->origin, PhaseKind::Distinguish);
        $next->cycle->forceFill(['participant_id' => $old->cycle->participant_id, 'sequence' => 2])->save();
        $participant = $old->cycle->participant;
        $before = app(InputPlanService::class)->forParticipant($participant);
        foreach (['traversal_options', 'attestation_options', 'instrument_options', 'revision_options', 'write_options'] as $key) {
            $this->assertContains($node->id, array_column($before[$key], 'id'));
        }
        $this->exhaust($node);
        $after = app(InputPlanService::class)->forParticipant($participant);
        foreach (['traversal_options', 'attestation_options', 'instrument_options', 'revision_options', 'write_options', 'gate_options'] as $key) {
            $this->assertNotContains($node->id, array_column($after[$key], 'id'));
        }
        $this->assertStringNotContainsString($node->id, json_encode($after['schema']));
        $this->assertSame([$node->id], array_column($after['exhausted_alternatives'], 'id'));
        $this->publishPotentialFixture($participant);
        $this->expectException(\InvalidArgumentException::class);
        app(ParticipationService::class)->contribute($participant, $next->id,
            ['action' => 'proposition.traverse', 'proposition_id' => $node->id]);
    }

    public function test_context_is_not_cut_off_at_read_limit_and_never_hydrates_historical_subtrees(): void
    {
        $creating = $this->phase($this->origin, PhaseKind::Distinguish);
        $roots = collect(range(1, 30))->map(fn () => $this->node($creating, $this->origin));
        $this->exhaust(...$roots);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $phase->cycle->participant->forceFill(['max_read_count' => 1])->save();
        $before = app(InputPlanService::class)->forParticipant($phase->cycle->participant);
        $this->assertSame($roots->pluck('id')->all(), array_column($before['exhausted_alternatives'], 'id'));
        $childPhase = $this->phase($roots->first(), PhaseKind::Disperse);
        $children = collect(range(1, 120))->map(fn () => $this->node($childPhase, $roots->first()));
        $this->exhaust(...$children);
        $context = new ParticipationContext($phase->fresh());
        $this->assertSame([], $context->propositions->keys()->intersect($roots->concat($children)->pluck('id'))->values()->all());
        $after = app(InputPlanService::class)->forParticipant($phase->cycle->participant);
        $this->assertSame($before['exhausted_alternatives'], $after['exhausted_alternatives']);
        $this->assertSame($before['schema'], $after['schema']);
        foreach ($children as $child) {
            $this->assertStringNotContainsString($child->id, json_encode(app(ParticipantContextSerializer::class)->plan($after)));
        }
    }

    public function test_actual_rest_returns_to_origin_with_memory_independent_of_graph_observer_mode(): void
    {
        [$root, $terminal] = $this->exhaustedBranch();
        $state = $this->rest($terminal);
        $participant = $terminal->cycle->participant->fresh();
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
        $this->assertSame([$root->id], collect($state['plan']['exhausted_alternatives'])->where('role', 'write')->pluck('id')->all());
        $workspace = app(ParticipationService::class)->workspaceFor($participant);
        $this->assertSame($state['plan']['exhausted_alternatives'], $workspace['plan']['exhausted_alternatives']);
        $this->assertSame($state['plan']['schema'], $workspace['plan']['schema']);
        User::factory()->create();
        $prefix = '/api/substrates/'.$this->substrate->slug.'/propositions';
        foreach (['active', 'exhausted', 'history'] as $view) {
            $ids = array_column($this->getJson($prefix.'?view='.$view)->assertOk()->json('data'), 'id');
            $this->assertSame($view !== 'active', in_array($root->id, $ids, true));
            $refreshed = app(ParticipationService::class)->stateFor($participant);
            $this->assertSame($state['plan']['schema'], $refreshed['plan']['schema']);
            $this->assertSame($state['plan']['exhausted_alternatives'], $refreshed['plan']['exhausted_alternatives']);
            $this->assertSame($this->origin->id, app(PotentialChargeService::class)->recoverFrom($terminal->gate)['proposition_id']);
        }
    }

    private function setDomain(ContextDomain $domain): void
    {
        if ($domain === ContextDomain::Settlement) {
            // Origin is created in Emergence and can open Settlement, just like
            // production bootstrap. Never rewrite a Horizon's causal Domain.
            $snapshot = $this->origin->commitmentSnapshot;
            $snapshot->forceFill(['direction_after' => $domain])->save();
            app(ContextHorizonProjector::class)->apply($snapshot);
        }
    }

    private function exhaust(Proposition ...$nodes): void
    {
        foreach ($nodes as $node) {
            DB::table('exhaustion_states')->insert([
                'proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
                'context_horizon_id' => $node->phase->cycle->context_horizon_id,
                'kind' => $node->tier === 'committal' ? 'basin' : 'excursion',
                'phase_id' => $node->phase_id, 'terminal_proposition_id' => $node->id,
                'observed_field_version' => $this->substrate->fresh()->field_version,
                'exhausted_at' => now(), 'evidence' => '{}',
            ]);
        }
    }
}
