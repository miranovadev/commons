<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Models\User;
use App\Services\ActiveParticipantLineage;
use App\Services\InputPlanService;
use App\Services\OpenAIResponseSchema;
use App\Services\OpenAIResponsesClient;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ParticipantContextTest extends TestCase
{
    use RefreshDatabase;

    private const POPULATIONS = ['lineage', 'read_options', 'gate_options', 'write_options'];

    // Measured representative dense fixture; see docs/participant-context.md.
    private const MAX_BYTES = 45000;

    private const MAX_PROPOSITIONS = 55;

    public function test_dense_packet_has_one_history_and_disjoint_actionable_populations(): void
    {
        $this->freezeTime();
        $fixture = new ParticipantContextFixture;
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $plan = app(ParticipationService::class)->stateFor($fixture->viewer)['plan'];
        $this->assertSame(self::POPULATIONS, array_values(array_intersect(array_keys($plan), self::POPULATIONS)));
        $objects = $this->objects($plan);
        $ids = array_column($objects, 'id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'Every full Proposition has one owning population.');
        $this->assertSame(count($ids), $this->fullObjectCount($plan));
        $this->assertSame($fixture->seed->id, $plan['provenance_id']);
        $this->assertSame($fixture->gate->id, $plan['gate_id']);
        $this->assertNotEmpty($plan['read_options']);
        $this->assertGreaterThan(1, count($plan['gate_options']));
        $this->assertEqualsCanonicalizing($fixture->writes, array_column($plan['write_options'], 'id'));
        $options = array_merge($plan['read_options'], $plan['gate_options'], $plan['write_options']);
        $expected = array_values(array_diff(array_column($fixture->chain, 'id'), array_column($options, 'id')));
        $this->assertSame($expected, array_column($plan['lineage'], 'id'));
        foreach ($fixture->replaced as $id) {
            $this->assertNotContains($id, array_column($plan['lineage'], 'id'));
            $this->assertDatabaseHas('propositions', ['id' => $id]);
            $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $id, 'type' => 'reconcile']);
        }
        foreach ($plan['schema']['$defs']['actions'] as $action) {
            foreach (['proposition_id', 'other_proposition_id'] as $field) {
                foreach ($action['properties'][$field]['enum'] ?? [] as $id) {
                    $this->assertContains($id, $ids, 'Every schema target resolves to an exposed object.');
                }
            }
        }
        $this->assertResolvedChain($plan['lineage']);
        $this->assertLessThanOrEqual(self::MAX_BYTES, strlen(json_encode($plan, JSON_THROW_ON_ERROR)));
        $this->assertLessThanOrEqual(self::MAX_PROPOSITIONS, count($ids));
        $connection = new Connection;
        $connection->configuration = [];
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $state = app(ParticipationService::class)->stateFor($fixture->viewer);
        $prompt = app(ParticipantPromptFactory::class)->make($connection, $state);
        $this->assertSame(['participant', 'receipts', 'read', 'gate', 'write', 'actions'], array_keys(json_decode($prompt['user'], true)));
        $this->assertLessThanOrEqual(self::MAX_BYTES, strlen($prompt['user']));
        $polluted = $state;
        $polluted['plan']['workspace'] = ['registers' => array_fill(0, 500, $plan['gate_options'])];
        $polluted['plan']['gate_options'][0]['title'] = 'UI only';
        $this->assertSame($prompt['user'], app(ParticipantPromptFactory::class)->make($connection, $polluted)['user']);
        Http::fake(['*' => Http::response([
            'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['contribution' => [
                'action' => 'proposition.reconcile', 'other_proposition_id' => $plan['gate_options'][1]['id'],
            ]])]]]],
        ])]);
        $connection->forceFill(['configuration' => ['model' => 'test-model'], 'credentials' => ['api_key' => 'test-key']]);
        app(OpenAIResponsesClient::class)->contribute($connection, $state);
        Http::assertSent(fn ($request) => $request['input'][1]['content'] === $prompt['user']
            && count($request['input']) === 2);
        foreach (['workspace', 'gate', 'attestation_options', 'instrument_options', 'revision_options', 'traversal_options'] as $key) {
            $this->assertArrayNotHasKey($key, $plan);
        }
        foreach ($objects as $node) {
            foreach (['title', 'transition', 'participant', 'created_at', 'attestation_counts'] as $key) {
                $this->assertArrayNotHasKey($key, $node);
            }
        }
        fwrite(STDERR, sprintf("\nCONTEXT dense: %d bytes; %d propositions; L/R/G/W %s; prompt %d bytes\n",
            strlen(json_encode($plan)), count($ids), implode('/', array_map(fn ($key) => count($plan[$key]), self::POPULATIONS)), strlen($prompt['user'])));
    }

    public static function evidencePhases(): array
    {
        return [[PhaseKind::Constrain], [PhaseKind::Bias], [PhaseKind::Order]];
    }

    public function test_cite_receipt_guidance_does_not_promise_reinforcement_in_the_generated_packet(): void
    {
        $fixture = new ParticipantContextFixture(2, 0);
        $observation = $fixture->node($fixture->seed, 'activity', position: PhaseKind::Distinguish);
        $fixture->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $observation->id, 'write_register' => 'frontier'])->save();
        $receipt = app(OperationalInstrumentService::class)->execute($fixture->phase, $observation, [
            'kind' => 'citation', 'reference' => $fixture->seed->id,
        ]);
        $gate = $fixture->node($observation, 'frontier', position: PhaseKind::Persist);
        $fixture->phase = $fixture->phase->replicate();
        $candidate = $fixture->node($gate, 'canon', position: PhaseKind::Constrain);
        $fixture->phase->forceFill([
            'kind' => PhaseKind::Constrain,
            'gate_proposition_id' => $gate->id,
            'write_register' => 'canon',
        ])->save();

        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $state = app(ParticipationService::class)->stateFor($fixture->viewer);
        $connection = new Connection;
        $connection->configuration = [];
        $prompt = app(ParticipantPromptFactory::class)->make($connection, $state);
        $packet = json_decode($prompt['user'], true, flags: JSON_THROW_ON_ERROR);
        $schema = $packet['actions'];
        $this->assertArrayNotHasKey('instrument.execute', $schema['$defs']['actions']);
        $transportSchema = $prompt['schema'];
        unset($transportSchema['x-commons']['description'], $transportSchema['$comment']);
        $this->assertSame($schema, $transportSchema);

        $guidance = 'Loading does not grant action permission.';
        $this->assertStringNotContainsString($guidance, $prompt['system']);
        $this->assertStringContainsString($guidance, $schema['$defs']['instrument']['description']);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
        $this->assertStringContainsString('memory alone grants no movement, grounding or escape authority', json_encode($transport, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Cite records evidence but never reinforces.', $prompt['system']);
        $this->assertStringNotContainsString('no page fetch or content verification', $prompt['user']);
        $this->assertStringNotContainsString('an unsigned Receipt', $prompt['user']);

        $write = collect($packet['write']['propositions'])->firstWhere('id', $candidate->id);
        $this->assertNotNull($write);
        $this->assertNotContains($receipt->id, $write['receipts'], 'Shared availability does not rewrite observation provenance');
        $catalogue = collect($packet['receipts'])->firstWhere('id', $receipt->id);
        $this->assertSame('citation', $catalogue['type']);
        $this->assertTrue($catalogue['loaded']);
        $this->assertArrayHasKey('loaded_inline_content', $catalogue);
    }

    #[DataProvider('evidencePhases')]
    #[Group('query')]
    public function test_dense_instrument_and_commitment_contexts_fit_the_same_budget(PhaseKind $kind): void
    {
        $this->freezeTime();
        $fixture = new ParticipantContextFixture;
        $gate = Proposition::findOrFail($fixture->writes[0]);
        $candidates = [];
        foreach ($fixture->writes as $id) {
            $candidate = $fixture->node(Proposition::findOrFail($id), 'canon', position: $kind === PhaseKind::Order ? PhaseKind::Bias : $kind);
            $fixture->evidence($candidate);
            $candidates[] = $candidate;
        }
        if ($kind === PhaseKind::Order) {
            $gate = $candidates[0];
        }
        $fixture->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id, 'write_register' => 'canon'])->save();
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $plan = app(ParticipationService::class)->stateFor($fixture->viewer)['plan'];
        if ($kind !== PhaseKind::Order) {
            $this->assertArrayHasKey('instrument.execute', $plan['schema']['$defs']['actions']);
        } else {
            $this->assertArrayNotHasKey('instrument.execute', $plan['schema']['$defs']['actions']);
        }
        if ($kind === PhaseKind::Order) {
            $this->assertArrayHasKey('commitment', $plan);
            $this->assertArrayHasKey('closure', $plan['commitment']);
            $this->assertNotEmpty($plan['commitment_options']);
        }
        $this->assertLessThanOrEqual(self::MAX_BYTES, strlen(json_encode($plan)));
        $this->assertLessThanOrEqual(self::MAX_PROPOSITIONS, $this->fullObjectCount($plan));
        $this->assertSame($this->fullObjectCount($plan), count(array_unique(array_column($this->objects($plan), 'id'))));
        fwrite(STDERR, sprintf("CONTEXT %s: %d bytes, %d objects\n", $kind->name, strlen(json_encode($plan)), $this->fullObjectCount($plan)));
    }

    #[Group('query')]
    public function test_ten_to_five_hundred_unrelated_horizon_nodes_cannot_grow_inference(): void
    {
        $started = hrtime(true);
        $profile = static function (string $stage) use ($started): void {
            fwrite(STDERR, sprintf("\nWORKSPACE growth %s: %.2fms\n", $stage, (hrtime(true) - $started) / 1e6));
        };
        $this->freezeTime();
        $fixture = new ParticipantContextFixture;
        $profile('fixture');
        // The human workspace shows the newest 25 cards. Give the unrelated
        // population a distinct timestamp so SQL tie ordering cannot hide it.
        $this->travel(1)->seconds();
        $fixture->growUnrelatedTo(10);
        // Explicitly persist the projection whose invalidation this regression exercises.
        app(PropositionMeasurementService::class)->recalculate($fixture->gate, context: new ParticipationContext($fixture->phase));
        $profile('projection');
        $service = app(ParticipationService::class);
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $before = $service->stateFor($fixture->viewer)['plan'];
        $profile('initial read');
        $fixture->growUnrelatedTo(500);
        $profile('500 candidates');
        $this->assertDatabaseHas('proposition_measurements', ['proposition_id' => $fixture->gate->id, 'calculator_version' => null]);
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $after = $service->stateFor($fixture->viewer)['plan'];
        $profile('expanded read');
        $this->assertSame($before, $after);
        $this->assertSame(strlen(json_encode($before)), strlen(json_encode($after)));
        $ids = array_column($this->objects($after), 'id');
        $this->assertSame([], array_values(array_intersect($fixture->unrelated, $ids)));
        $this->assertLessThanOrEqual(self::MAX_BYTES, strlen(json_encode($after)));
        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertSame($before['schema'], $after['schema']);
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $workspace = $service->workspaceFor($fixture->viewer)['plan'];
        $profile('workspace');
        $humanIds = collect($workspace['workspace']['registers'])->flatten(1)->pluck('id')->all();
        $this->assertNotEmpty(array_intersect($fixture->unrelated, $humanIds));
        $this->assertSame(array_column($after['lineage'], 'id'), array_column($workspace['lineage'], 'id'));
        $this->assertSame($after['schema'], $workspace['schema']);
    }

    public static function hardOperations(): array
    {
        return [['reclassify'], ['revise'], ['reconcile'], ['commit']];
    }

    #[DataProvider('hardOperations')]
    public function test_hard_inputs_collapse_without_deleting_history(string $type): void
    {
        $fixture = new ParticipantContextFixture(2, 0);
        $a = $fixture->node($fixture->seed, 'canon');
        $b = $fixture->node($fixture->seed, 'canon');
        $c = $fixture->node($a, 'canon', $type, $type === 'commit' ? 'committal' : 'expressive');
        if ($type === 'reconcile') {
            $fixture->edge($b, $c, $type);
        }
        $later = $fixture->node($c, 'activity');
        $gate = $fixture->node($later, 'frontier');
        $count = Supersession::count();
        $path = app(ActiveParticipantLineage::class)->endingAt($gate);
        $expected = $type === 'commit' ? [$c->id, $later->id, $gate->id] : [$fixture->seed->id, $c->id, $later->id, $gate->id];
        $this->assertSame($expected, $path->pluck('id')->all());
        $this->assertSame($count, Supersession::count());
        $this->assertDatabaseHas('propositions', ['id' => $a->id]);
        $this->assertDatabaseHas('propositions', ['id' => $b->id]);
        $this->assertResolvedChain($path->map(fn ($node) => ['id' => $node->id])->all());
        $fixture->phase->forceFill(['gate_proposition_id' => $gate->id, 'kind' => PhaseKind::Persist, 'write_register' => 'canon'])->save();
        $plan = app(ParticipantContextSerializer::class)->plan(app(InputPlanService::class)->forParticipant($fixture->viewer));
        $this->assertContains($c->id, array_column($plan['lineage'], 'id'));
        $this->assertNotContains($a->id, array_column($plan['lineage'], 'id'));
        $this->assertNotContains($b->id, array_column($plan['lineage'], 'id'));
        if ($type === 'commit') {
            $this->assertSame($c->id, $plan['provenance_id']);
            $this->assertNotContains($fixture->seed->id, array_column($plan['lineage'], 'id'));
        }
    }

    public function test_reconcile_uses_the_recorded_branch_instead_of_fanning_out_into_both_histories(): void
    {
        $fixture = new ParticipantContextFixture(2, 0);
        $leftHistory = $fixture->node($fixture->seed, 'canon');
        $left = $fixture->node($leftHistory, 'activity');
        $rightHistory = $fixture->node($fixture->seed, 'canon');
        $right = $fixture->node($rightHistory, 'activity');
        $result = $fixture->node($left, 'frontier', 'reconcile');
        $fixture->edge($right, $result, 'reconcile');
        $gate = $fixture->node($result, 'canon');
        $fixture->phase->forceFill(['kind' => PhaseKind::Persist, 'write_register' => 'frontier', 'gate_proposition_id' => $gate->id])->save();
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $plan = app(ParticipationService::class)->stateFor($fixture->viewer)['plan'];
        $this->assertSame([$fixture->seed->id, $leftHistory->id, $result->id], array_column($plan['lineage'], 'id'));
        $this->assertDatabaseHas('propositions', ['id' => $rightHistory->id]);
        $this->assertResolvedChain($plan['lineage']);
    }

    public function test_off_path_hard_replacement_does_not_appear_or_leave_its_retired_input_in_history(): void
    {
        $fixture = new ParticipantContextFixture(2, 0);
        $a = $fixture->node($fixture->seed, 'activity');
        $head = $fixture->node($a, 'frontier');
        $fixture->node($a, 'canon', 'reclassify');
        $this->assertSame([$fixture->seed->id, $head->id],
            app(ActiveParticipantLineage::class)->endingAt($head)->pluck('id')->all());
    }

    public function test_reconcile_removes_both_inputs_even_when_one_is_an_ancestor_of_the_other(): void
    {
        $fixture = new ParticipantContextFixture(2, 0);
        $a = $fixture->node($fixture->seed, 'activity');
        $b = $fixture->node($a, 'activity');
        $result = $fixture->node($b, 'frontier', 'reconcile');
        $fixture->edge($a, $result, 'reconcile');
        $this->assertSame([$fixture->seed->id, $result->id],
            app(ActiveParticipantLineage::class)->endingAt($result)->pluck('id')->all());
    }

    public function test_workspace_is_separately_authorized_and_plan_endpoint_is_compact(): void
    {
        $fixture = new ParticipantContextFixture(4, 1);
        $this->postJson('/api/bootstrap', ['name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'test-password-1234', 'password_confirmation' => 'test-password-1234'])->assertCreated();
        $user = User::firstOrFail();
        auth()->logout();
        $fixture->viewer->forceFill(['user_id' => $user->id])->save();
        app(PotentialProjectionService::class)->rebuild($fixture->substrate->id, false);
        $base = '/api/substrates/'.$fixture->substrate->slug.'/participants/'.$fixture->viewer->id;
        $this->getJson($base.'/workspace')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($base.'/workspace')->assertForbidden();
        $this->actingAs($user)->getJson($base.'/workspace')->assertOk()->assertJsonStructure(['data' => ['plan' => ['workspace' => ['registers']]]]);
        $this->getJson($base.'/participate')->assertOk()->assertJsonMissingPath('data.plan.workspace')->assertJsonMissingPath('data.plan.gate');
        $this->getJson($base.'/plan')->assertOk()->assertJsonPath('data.gate_id', $fixture->gate->id)->assertJsonMissingPath('data.workspace');
    }

    private function fullObjectCount(mixed $value): int
    {
        if (! is_array($value)) {
            return 0;
        }

        return (isset($value['id'], $value['register'], $value['statement']) ? 1 : 0)
            + array_sum(array_map($this->fullObjectCount(...), $value));
    }

    private function objects(array $plan): array
    {
        return array_merge(...array_map(fn ($key) => $plan[$key], self::POPULATIONS));
    }

    /** Each adjacency contracts a real ancestry path; no sibling can pass this check. */
    private function assertResolvedChain(array $lineage): void
    {
        for ($i = 1; $i < count($lineage); $i++) {
            $seen = [];
            $queue = [$lineage[$i]['id']];
            while ($queue !== []) {
                $id = array_shift($queue);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                array_push($queue, ...Supersession::where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
            }
            $this->assertArrayHasKey($lineage[$i - 1]['id'], $seen);
        }
    }
}
