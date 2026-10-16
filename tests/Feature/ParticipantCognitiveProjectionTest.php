<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Models\Proposition;
use App\Services\InputPlanService;
use App\Services\InputSchemaService;
use App\Services\ParticipantCognitiveProjection;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ParticipantCognitiveProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_origin_and_three_committed_boundaries_follow_entry_provenance_only(): void
    {
        $f = new ParticipantContextFixture(2, 0, false);
        $head = $f->seed;
        $ancestors = [];
        for ($i = 0; $i <= 3; $i++) {
            $plan = $this->plan($head, 'distinguish');
            $read = app(ParticipantCognitiveProjection::class)->registers($plan)['read'];
            $this->assertSame($ancestors, array_column($read['propositions'], 'id'));
            $this->assertArrayNotHasKey('question', $read);
            foreach ($read['propositions'] as $node) {
                $this->assertSame('committal', $node['tier']);
                $this->assertSame('ancestor', $node['relation']);
            }
            $ancestors[] = $head->id;
            if ($i < 3) {
                $source = $f->node($head, 'canon', position: PhaseKind::Bias);
                $head = $f->node($source, 'canon', 'commit', 'committal');
                // A later unrelated Commit in the same Horizon must not enter Read.
                $sibling = $f->node($f->seed, 'canon', position: PhaseKind::Bias);
                $f->node($sibling, 'canon', 'commit', 'committal');
            }
        }
    }

    public function test_local_read_keeps_both_reconcile_parents_even_when_history_selects_one(): void
    {
        $f = new ParticipantContextFixture(2, 0, false);
        // The acting parent is deliberately newer than its counterpart.
        $b = $f->node($f->seed, 'activity', position: PhaseKind::Disperse);
        $a = $f->node($f->seed, 'activity', position: PhaseKind::Disperse);
        $result = $f->node($a, 'frontier', 'reconcile', additionalParent: $b);
        $registers = app(ParticipantCognitiveProjection::class)->registers($this->plan($result, 'bias'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_column($registers['read']['propositions'], 'id'));
        $this->assertEquals([$a->id => 'causal', $b->id => 'counterpart'],
            array_column($registers['read']['propositions'], 'relation', 'id'));
        foreach ([[$a->id, $b->id], [$b->id, $a->id]] as $order) {
            $plan = $this->plan($result, 'bias');
            $plan['gate_options'][0]['parent_ids'] = $order;
            $read = app(ParticipantCognitiveProjection::class)->registers($plan)['read']['propositions'];
            $this->assertSame($order, array_column($read, 'id'));
            $this->assertEquals([$a->id => 'causal', $b->id => 'counterpart'], array_column($read, 'relation', 'id'));
        }
        unset($plan['gate_options'][0]['parent_ids']);
        $read = app(ParticipantCognitiveProjection::class)->registers($plan)['read']['propositions'];
        $this->assertEquals([$a->id => 'causal', $b->id => 'counterpart'], array_column($read, 'relation', 'id'));
        $this->assertSame([$result->id], array_column($registers['gate']['propositions'], 'id'));
        $unary = app(ParticipantCognitiveProjection::class)->registers($this->plan($a, 'persist'));
        $this->assertSame([$f->seed->id], array_column($unary['read']['propositions'], 'id'));
        $this->assertArrayNotHasKey('relation', $unary['read']['propositions'][0]);
    }

    #[DataProvider('unresolvedParentRoles')]
    public function test_unresolved_or_other_multi_parent_history_keeps_honest_causal_visibility(string $case): void
    {
        $f = new ParticipantContextFixture(2, 0, false);
        $a = $f->node($f->seed, 'activity', position: PhaseKind::Disperse);
        $b = $f->node($f->seed, 'activity', position: PhaseKind::Disperse);
        $result = $f->node($a, 'frontier', 'reconcile', additionalParent: $b);
        if ($case === 'missing' || $case === 'non-parent') {
            DB::table('phases')->where('id', $result->phase_id)->update([
                'gate_proposition_id' => $case === 'missing' ? null : $f->seed->id,
            ]);
        } elseif ($case === 'other-join') {
            DB::table('supersessions')->where('target_proposition_id', $result->id)->update(['type' => 'variant']);
        } elseif ($case === 'conflicting-phase') {
            DB::table('supersessions')->where('target_proposition_id', $result->id)
                ->where('source_proposition_id', $b->id)->update(['phase_id' => $f->phase->id]);
        } else {
            $f->edge($f->gate, $result, 'reconcile');
        }
        $plan = $this->plan($result->fresh(), 'bias');
        $read = app(ParticipantCognitiveProjection::class)->registers($plan)['read']['propositions'];
        $this->assertEqualsCanonicalizing($plan['gate_options'][0]['parent_ids'], array_column($read, 'id'));
        $this->assertSame(array_fill(0, count($read), 'causal'), array_column($read, 'relation'));
    }

    public static function unresolvedParentRoles(): array
    {
        return array_combine($cases = ['missing', 'non-parent', 'other-join', 'conflicting-phase', 'extra-input'],
            array_map(fn ($case) => [$case], $cases));
    }

    public function test_reconcile_role_projection_preserves_actions_measurements_and_causal_records(): void
    {
        $this->freezeTime();
        $f = new ParticipantContextFixture(2, 2, false);
        $other = $f->node($f->seed, 'activity', position: PhaseKind::Disperse);
        $result = $f->node($f->gate, 'frontier', 'reconcile', additionalParent: $other);
        $f->phase->forceFill(['kind' => PhaseKind::Bias, 'write_register' => 'canon',
            'gate_proposition_id' => $result->id])->save();
        $f->phase->unsetRelation('gate');
        $raw = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        $state = ['participant' => $f->viewer->only(['callsign', 'designation']),
            'plan' => app(ParticipantContextSerializer::class)->plan($raw)];
        $snapshot = fn () => collect(['propositions', 'phases', 'supersessions', 'proposition_measurements'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query['query']);
        }
        DB::disableQueryLog();
        $this->assertSame($before, $snapshot());
        $this->assertSame($state['plan']['schema'], $prompt['schema']);
        $this->assertSame(json_encode($raw), json_encode(app(InputPlanService::class)->forPhase($f->viewer, $f->phase)));
        $packet = json_decode($prompt['user'], true);
        $this->assertEquals([$f->gate->id => 'causal', $other->id => 'counterpart'],
            array_column($packet['read']['propositions'], 'relation', 'id'));
    }

    public function test_mediate_projects_all_authorized_counterparts_without_changing_the_action_schema(): void
    {
        $f = new ParticipantContextFixture(2, 3, false);
        $raw = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        $plan = app(ParticipantContextSerializer::class)->plan($raw);
        $schema = $plan['schema'];
        $ids = $schema['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum'];
        $this->assertGreaterThan(1, count($ids));
        $state = ['participant' => ['callsign' => 'Nova', 'designation' => 'Analytical explorer'], 'plan' => $plan];
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $this->assertSame(['participant', 'read', 'gate', 'write', 'actions'], array_keys($packet));
        $this->assertSame($state['participant'], $packet['participant']);
        unset($schema['x-commons']['description']);
        $this->assertSame($schema, $packet['actions']);
        $this->assertSame($plan, $state['plan']);
        $this->assertSame('How do these propositions relate?', $packet['read']['question']);
        $this->assertSame($plan['read_options'][0]['id'], $packet['read']['propositions'][0]['id']);
        $this->assertSame('causal', $packet['read']['propositions'][0]['relation']);
        $this->assertEqualsCanonicalizing($ids, array_column(array_slice($packet['read']['propositions'], 1), 'id'));
        $this->assertSame([$f->gate->id], array_column($packet['gate']['propositions'], 'id'));
        $this->assertSame(array_column($plan['write_options'], 'id'), array_column($packet['write']['propositions'], 'id'));
        $this->assertArrayNotHasKey('question', $packet['gate']);
        $this->assertArrayNotHasKey('lineage', $packet);
        unset($state['plan']['schema']['$defs']['actions']['proposition.reconcile']);
        $single = app(ParticipantContextSerializer::class)->inference($state);
        $this->assertCount(1, $single['read']['propositions']);
        $this->assertArrayNotHasKey('question', $single['read']);
    }

    public function test_serialization_does_not_write_any_substrate_state(): void
    {
        $f = new ParticipantContextFixture(2, 2);
        $raw = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        $plan = app(ParticipantContextSerializer::class)->plan($raw);
        $state = ['participant' => $f->viewer->only(['callsign', 'designation']), 'plan' => $plan];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query['query']);
        }
        DB::disableQueryLog();
        $this->assertEquals($packet, json_decode($prompt['user'], true));
        $this->assertSame($plan['schema'], $prompt['schema']);
        $this->assertStringNotContainsString('"lineage"', $prompt['user']);
        $this->assertStringNotContainsString('"question":null', $prompt['user']);
    }

    public function test_multiple_gate_and_write_roles_keep_their_ids_without_granting_read_authority(): void
    {
        $f = new ParticipantContextFixture(2, 0, false);
        $plan = $this->plan($f->gate, 'persist');
        $plan['phase'] += ['number' => 2, 'write_register' => 'frontier'];
        $plan['gate_options'][] = $f->seed->only(['id', 'statement', 'register', 'tier']);
        $plan['write_options'] = Proposition::whereKey($f->writes)->get()->map->only(['id', 'statement', 'register', 'tier'])->all();
        $plan['schema'] = app(InputSchemaService::class)->forPhase(PhaseKind::Persist, ['proposition.traverse']);
        $plan['schema']['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum'] = $f->writes;
        $state = ['participant' => ['callsign' => 'Nova', 'designation' => 'Explorer'], 'plan' => $plan];
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $this->assertSame(array_column($plan['gate_options'], 'id'), array_column($packet['gate']['propositions'], 'id'));
        $this->assertSame('acting', $packet['gate']['propositions'][0]['relation']);
        $this->assertSame(array_column($plan['write_options'], 'id'), array_column($packet['write']['propositions'], 'id'));
        $this->assertSame('What else is possible here?', $packet['write']['question']);
        $this->assertSame($f->writes, $packet['actions']['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum']);
        $state['plan']['write_options'] = [];
        $empty = app(ParticipantContextSerializer::class)->inference($state);
        $this->assertSame([], $empty['write']['propositions']);
        $this->assertSame('What else is possible here?', $empty['write']['question']);
    }

    private function plan(Proposition $gate, string $kind): array
    {
        return ['phase' => ['kind' => $kind], 'gate_id' => $gate->id,
            'gate_options' => [[...$gate->only(['id', 'statement', 'register', 'tier']),
                'parent_ids' => $gate->parents->pluck('id')->all()]],
            'read_options' => [], 'write_options' => [], 'schema' => []];
    }
}
