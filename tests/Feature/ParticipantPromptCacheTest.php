<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Services\InputSchemaService;
use App\Services\OpenAIRequestFactory;
use App\Services\OpenAIResponseSchema;
use App\Services\OpenAIResponsesClient;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPhaseInstructions;
use App\Services\ParticipantPromptCore;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipantPromptLayout;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class ParticipantPromptCacheTest extends TestCase
{
    public static function phases(): array
    {
        return array_map(fn ($phase) => [$phase], PhaseKind::cases());
    }

    #[DataProvider('phases')]
    public function test_layers_preserve_all_cognition_and_both_action_schemas(PhaseKind $phase): void
    {
        $state = $this->state($phase);
        $before = app(ParticipantContextSerializer::class)->inference($state);
        $connection = $this->connection();
        $prompt = app(ParticipantPromptFactory::class)->make($connection, $state);
        $after = $this->reconstruct($prompt);
        $this->assertEquals($before, $after);
        // Assert ordered populations separately: recursive object-key equality must
        // never conceal a change to historical, ranked or action ordering.
        foreach (['lineage', 'read_options', 'gate_options', 'write_options', 'receipts'] as $key) {
            $this->assertSame(array_column($before['plan'][$key], 'id'), array_column($after['plan'][$key], 'id'));
        }
        $this->assertSame($state['plan']['schema'], $prompt['schema']);
        $this->assertSame($before['plan']['schema'], $after['plan']['schema']);
        $request = app(OpenAIRequestFactory::class)->make($connection, $prompt);
        $this->assertSame(app(OpenAIResponseSchema::class)->fromCommons($state['plan']['schema']), $request['text']['format']['schema']);
        $this->assertSame(1, substr_count($prompt['evidence'].$prompt['user'], 'immutable observation'));
        $this->assertStringNotContainsString('DO-NOT-EXPOSE', json_encode($request));
        $this->assertStringContainsString(ParticipantPromptCore::TEXT, $prompt['system']);
        $this->assertStringContainsString(app(ParticipantPhaseInstructions::class)->forPhase($phase), $prompt['system']);
        // #187 evidence/state layers are byte-identical, not merely reconstructable.
        $layers = (new ParticipantPromptLayout)->make($before);
        $this->assertSame($layers['evidence'], $prompt['evidence']);
        $this->assertSame($layers['user'], $prompt['user']);
        $this->assertSame($layers['loaded_receipt_count'], $prompt['loaded_receipt_count']);
        $this->assertStringNotContainsString($prompt['instruction_family'], json_encode($request['input']));
    }

    public function test_object_key_and_database_receipt_order_do_not_change_serialization(): void
    {
        $state = $this->state();
        $before = app(ParticipantPromptFactory::class)->make($this->connection(), $state);
        $schema = $state['plan']['schema'];
        $shuffle = function ($value) use (&$shuffle) {
            if (! is_array($value)) {
                return $value;
            }

            return array_map($shuffle, array_is_list($value) ? $value : array_reverse($value, true));
        };
        $state = $shuffle($state);
        $state['plan']['schema'] = $schema; // Provider property/output order is intentionally preserved.
        $state['plan']['receipts'] = array_reverse($state['plan']['receipts']);
        $after = app(ParticipantPromptFactory::class)->make($this->connection(), $state);
        $this->assertSame($before, $after);
    }

    public function test_dynamic_state_cannot_rewrite_evidence_and_new_loads_append(): void
    {
        $state = $this->state();
        $factory = app(ParticipantPromptFactory::class);
        $connection = $this->connection();
        $before = $factory->make($connection, $state);
        $state['plan']['phase']['id'] = 'next-phase';
        $state['plan']['lineage'][0]['observables']['closure'] = 0.75;
        $state['plan']['receipts'][0]['availability'] = 'consumed';
        $state['plan']['receipts'][0]['consumed_at'] = '2026-09-20T01:00:00Z';
        $state['plan']['receipts'][0]['can_replay'] = false;
        $changed = $factory->make($connection, $state);
        $this->assertSame($before['evidence'], $changed['evidence']);
        $this->assertNotSame($before['user'], $changed['user']);
        $new = $state['plan']['receipts'][0];
        $new['id'] = 'a-older-receipt-loaded-later';
        $new['loaded_at'] = '2026-09-21T00:00:00Z';
        $state['plan']['receipts'][] = $new;
        $appended = $factory->make($connection, $state);
        $bodies = json_decode($appended['evidence'], true)['loaded_receipts'];
        $this->assertSame(['r1', $new['id']], array_column($bodies, 'id'));
        $this->assertStringStartsWith(substr($before['evidence'], 0, -2), $appended['evidence']);

        $state['plan']['receipts'][0]['status'] = 'available';
        $state['plan']['receipts'][0]['loaded_at'] = null;
        $cleared = $factory->make($connection, $state);
        $this->assertSame([$new['id']], array_column(json_decode($cleared['evidence'], true)['loaded_receipts'], 'id'));
        usort($state['plan']['receipts'], fn ($a, $b) => strcmp($a['id'], $b['id']));
        $this->assertEquals(app(ParticipantContextSerializer::class)->inference($state), $this->reconstruct($cleared));
        // Leaving visibility removes evidence even when its load persists in storage.
        $state['plan']['receipts'] = [];
        $this->assertSame('{"loaded_receipts":[]}', $factory->make($connection, $state)['evidence']);
    }

    public function test_ranked_results_and_semantic_lists_keep_their_exact_order(): void
    {
        $state = $this->state();
        $state['plan']['receipts'][0]['evidence'] = ['query' => 'bushfire risk', 'results' => [
            ['url' => 'https://z.test', 'title' => 'First ranked'], ['url' => 'https://a.test', 'title' => 'Second ranked'],
        ]];
        $prompt = app(ParticipantPromptFactory::class)->make($this->connection(), $state);
        $this->assertSame(['https://z.test', 'https://a.test'], array_column(json_decode($prompt['evidence'], true)['loaded_receipts'][0]['evidence']['results'], 'url'));
        $this->assertSame(['parent-z', 'parent-a'], $this->reconstruct($prompt)['plan']['lineage'][0]['parent_ids']);
    }

    public static function models(): array
    {
        return [['gpt-5.4', false], ['gpt-5.5', false], ['gpt-5.6', true], ['gpt-5.6-sol', true],
            ['gpt-6-astra', true], ['gpt-5.6-2026-09-01', true], ['test-model', false]];
    }

    #[DataProvider('models')]
    public function test_provider_sends_two_supported_boundaries_or_implicit_caching(string $model, bool $explicit): void
    {
        $state = $this->state();
        $connection = $this->connection($model);
        Http::fake(['*' => Http::response($this->response())]);
        $this->assertSame(['action' => 'participant.rest'], app(OpenAIResponsesClient::class)->contribute($connection, $state));
        Http::assertSent(function ($request) use ($explicit, $state, $connection): bool {
            $payload = $request->data();
            $prompt = app(ParticipantPromptFactory::class)->make($connection, $state);
            $this->assertCount(3, $payload['input']);
            foreach ([0 => 'system', 1 => 'evidence'] as $index => $section) {
                $content = $payload['input'][$index]['content'];
                $this->assertSame($prompt[$section], $explicit ? $content[0]['text'] : $content);
                if ($explicit) {
                    $this->assertSame(['mode' => 'explicit'], $content[0]['prompt_cache_breakpoint']);
                }
            }
            $this->assertSame($prompt['user'], $payload['input'][2]['content']);
            $this->assertSame($explicit ? ['mode' => 'explicit'] : null, $payload['prompt_cache_options'] ?? null);
            $this->assertArrayNotHasKey('prompt_cache_retention', $payload);

            return true;
        });
    }

    public function test_cache_family_is_stable_across_wakes_and_separates_model_substrate_and_constitution(): void
    {
        $state = $this->state();
        $connection = $this->connection();
        $key = fn ($c, $s) => app(OpenAIRequestFactory::class)->make($c, app(ParticipantPromptFactory::class)->make($c, $s), $s['_inference'])['prompt_cache_key'];
        $before = $key($connection, $state);
        $this->assertStringStartsWith('commons-prefix-v2:', $before);
        foreach (PhaseKind::cases() as $phase) {
            $this->assertSame($before, $key($connection, $this->state($phase)));
        }
        $state['_inference']['participant_id'] = 'another-participant';
        $state['plan']['phase']['id'] = 'another-phase';
        $state['plan']['receipts'] = [];
        $this->assertSame($before, $key($connection, $state));
        $state['_inference']['substrate_id'] = 'another-substrate';
        $this->assertNotSame($before, $key($connection, $state));
        $this->assertNotSame($before, $key($this->connection('gpt-5.4'), $this->state()));
        $connection->configuration = ['model' => 'gpt-5.6', 'instructions' => 'Preserve uncertainty.'];
        $this->assertNotSame($before, $key($connection, $this->state()));
        $this->assertLessThanOrEqual(64, strlen($before));
    }

    public function test_all_contract_content_versions_the_family_without_entering_model_context(): void
    {
        $contracts = app(ParticipantPhaseInstructions::class);
        $catalog = array_map(fn (PhaseKind $phase) => $contracts->forPhase($phase, restAvailable: true), PhaseKind::cases());
        $this->assertSame(hash('sha256', json_encode($catalog, JSON_THROW_ON_ERROR)), $contracts->fingerprint());
        $connection = $this->connection();
        $prompt = app(ParticipantPromptFactory::class)->make($connection, $this->state());
        $this->assertSame(hash('sha256', json_encode([
            ParticipantPromptCore::TEXT, ParticipantPromptLayout::FORMAT_INSTRUCTION, $contracts->fingerprint(), '',
        ], JSON_THROW_ON_ERROR)), $prompt['instruction_family']);
        $before = app(OpenAIRequestFactory::class)->make($connection, $prompt);
        $catalog[5] .= '\nA future Bias semantic correction.';
        $prompt['instruction_family'] = hash('sha256', json_encode([
            ParticipantPromptCore::TEXT, ParticipantPromptLayout::FORMAT_INSTRUCTION,
            hash('sha256', json_encode($catalog, JSON_THROW_ON_ERROR)), '',
        ], JSON_THROW_ON_ERROR));
        $after = app(OpenAIRequestFactory::class)->make($connection, $prompt);
        $this->assertNotSame($before['prompt_cache_key'], $after['prompt_cache_key']);
        unset($before['prompt_cache_key'], $after['prompt_cache_key']);
        $this->assertSame($before, $after);
    }

    public function test_usage_is_logged_even_on_refusal_without_prompt_content(): void
    {
        $response = $this->response();
        $response['output'] = [['content' => [['type' => 'refusal', 'refusal' => 'No contribution']]]];
        Http::fake(['*' => Http::response($response)]);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with('commons.inference.usage', Mockery::on(function ($metrics): bool {
            $this->assertSame(10000, $metrics['input_tokens']);
            $this->assertSame(6000, $metrics['cached_input_tokens']);
            $this->assertSame(4000, $metrics['uncached_input_tokens']);
            $this->assertSame(1000, $metrics['cache_write_input_tokens']);
            $this->assertSame(3000, $metrics['ordinary_input_tokens']);
            $this->assertSame(125, $metrics['output_tokens']);
            $this->assertSame(0.6, $metrics['cache_hit_ratio']);
            $this->assertSame('participant', $metrics['participant_id']);
            $this->assertSame('substrate', $metrics['substrate_id']);
            $this->assertSame(1, $metrics['loaded_receipt_count']);
            $this->assertStringNotContainsString('immutable observation', json_encode($metrics));
            $this->assertStringNotContainsString('test-secret', json_encode($metrics));

            return true;
        }));
        $this->app->instance(LoggerInterface::class, $logger);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI refused');
        app(OpenAIResponsesClient::class)->contribute($this->connection(), $this->state(), $this->state()['_inference']);
    }

    public function test_unknown_usage_is_not_reported_as_zero_and_logger_failure_cannot_fail_inference(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with('commons.inference.usage', Mockery::on(function ($metrics): bool {
            foreach (['input_tokens', 'cached_input_tokens', 'uncached_input_tokens', 'ordinary_input_tokens', 'cache_hit_ratio', 'output_tokens'] as $key) {
                $this->assertNull($metrics[$key]);
            }

            return true;
        }))->andThrow(new RuntimeException('Log unavailable'));
        $this->app->instance(LoggerInterface::class, $logger);
        $response = $this->response();
        unset($response['usage']);
        Http::fake(['*' => Http::response($response)]);
        $this->assertSame(['action' => 'participant.rest'], app(OpenAIResponsesClient::class)->contribute($this->connection(), $this->state()));
        Http::assertSentCount(1);
    }

    private function reconstruct(array $prompt): array
    {
        $packet = json_decode($prompt['user'], true, flags: JSON_THROW_ON_ERROR);
        $bodies = array_column(json_decode($prompt['evidence'], true)['loaded_receipts'], null, 'id');
        foreach ($packet['plan']['receipts'] as &$receipt) {
            $receipt = [...($bodies[$receipt['id']] ?? []), ...$receipt];
        }

        return $packet;
    }

    private function connection(string $model = 'gpt-5.6'): Connection
    {
        return (new Connection)->forceFill(['id' => 'connection', 'configuration' => ['model' => $model],
            'credentials' => ['api_key' => 'test-secret']]);
    }

    private function response(): array
    {
        return ['id' => 'response-id', 'model' => 'gpt-5.6', 'usage' => ['input_tokens' => 10000,
            'input_tokens_details' => ['cached_tokens' => 6000, 'cache_write_tokens' => 1000], 'output_tokens' => 125],
            'output' => [['content' => [['type' => 'output_text', 'text' => '{"contribution":{"action":"participant.rest","statement":null}}']]]]];
    }

    private function state(PhaseKind $phase = PhaseKind::Distinguish): array
    {
        $node = ['id' => 'history', 'statement' => 'Retain historical meaning.', 'register' => 'canon', 'tier' => 'committal',
            'parent_ids' => ['parent-z', 'parent-a'], 'observables' => ['correspondence' => 0.2, 'chirality' => -1, 'closure' => 0.5],
            'attestation' => ['direction' => 'refute', 'rationale' => 'Recorded judgment.'], 'reinforcement_count' => 3];
        $receipt = ['id' => 'r1', 'proposition_id' => 'history', 'context_horizon_id' => 'horizon', 'type' => 'hyperlink',
            'reference' => 'https://example.test/evidence', 'signature' => 'signature', 'status' => 'loaded',
            'availability' => 'available', 'consumed_at' => null, 'attached_proposition_id' => null,
            'loaded_at' => '2026-09-20T00:00:00Z', 'capabilities' => ['readable' => true, 'replayable' => true],
            'can_replay' => true, 'replayed_from_receipt_id' => null, 'observation_error' => null,
            'evidence' => ['content_excerpt' => 'immutable observation', 'excerpt_truncated' => false, 'observed_at' => '2026-09-19T00:00:00Z']];
        $unloaded = [...$receipt, 'id' => 'r2', 'status' => 'available', 'loaded_at' => null,
            'evidence' => ['content_excerpt' => 'another observation']];

        return ['status' => 'ready', 'domain' => 'emergence', 'perspective' => ['note' => 'DO-NOT-EXPOSE'],
            '_inference' => ['participant_id' => 'participant', 'substrate_id' => 'substrate'],
            'plan' => ['phase' => ['id' => 'phase', 'number' => $phase->value, 'kind' => strtolower($phase->name)],
                'provenance_id' => 'history', 'gate_id' => 'gate', 'lineage' => [$node],
                'read_options' => [], 'gate_options' => [[...$node, 'id' => 'gate']], 'write_options' => [],
                'receipts' => [$receipt, $unloaded], 'rest_available' => false, 'rest_reason' => null,
                'schema' => app(InputSchemaService::class)->forPhase($phase)]];
    }
}
