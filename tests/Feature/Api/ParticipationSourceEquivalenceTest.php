<?php

namespace Tests\Feature\Api;

use App\Contracts\ParticipantInference;
use App\Models\Connection;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantPromptFactory;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationSourceEquivalenceTest extends ScriptedParticipationTestCase
{
    public function test_the_real_worker_uses_the_http_action_plan_and_stops_after_rest_and_recovery(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('worker-equivalence');
        $initial = $this->enterParticipant($actor, true);
        $connection = $this->postJson('/api/connections', [
            'provider' => 'openai', 'name' => 'Worker', 'configuration' => ['model' => 'example-model'],
            'credentials' => ['api_key' => 'test-only'],
        ])->assertCreated()->json('data.id');
        $link = $this->postJson('/api/substrates/'.$this->substrateSlug.'/participants/'.$actor.'/connections',
            ['connection_id' => $connection, 'is_enabled' => true])->assertCreated()->json('data.id');
        $phases = [];
        $positions = [];
        $this->mock(ParticipantInference::class)->shouldReceive('contribute')->times(3)
            ->andReturnUsing(function ($connection, $state) use ($actor, $initial, &$phases, &$positions): array {
                $this->assertSame($this->readParticipation($actor), json_decode(json_encode($state), true));
                $index = count($phases);
                $this->assertSame(['distinguish', 'persist', 'constrain'][$index], $state['plan']['phase']['kind']);
                $this->assertSame('settlement', $state['domain']);
                $this->assertSame($initial['context_horizon_id'], $state['context_horizon_id']);
                $this->assertSame($initial['cycle']['id'], $state['cycle']['id']);
                $this->assertSame($state['current_proposition_id'], $state['plan']['gate_id']);
                if ($index > 0) {
                    $this->assertDatabaseHas('supersessions', [
                        'source_proposition_id' => $positions[$index - 1],
                        'target_proposition_id' => $state['current_proposition_id'],
                    ]);
                }
                $positions[] = $state['current_proposition_id'];
                $phases[] = $state['plan']['phase']['kind'];

                // Exact synchronous worker inputs; no autonomous inference policy.
                return [
                    ['action' => 'proposition.variant', 'statement' => 'Testable opportunity 0'],
                    ['action' => 'proposition.variant', 'statement' => 'Testable opportunity 0'],
                    ['action' => 'participant.rest'],
                ][count($phases) - 1];
            });
        $this->wakeParticipant($link);
        $after = $this->readParticipation($actor);
        $this->assertSame(['distinguish', 'persist', 'constrain'], $phases);
        $this->assertSame('distinguish', $after['plan']['phase']['kind']);
        $this->assertSame($initial['current_proposition_id'], $after['current_proposition_id']);
        $this->assertGreaterThan($initial['cycle']['sequence'], $after['cycle']['sequence']);
        $this->assertDatabaseHas('cycles', ['id' => $initial['cycle']['id'], 'rest_reason' => 'no_available_move']);
        $this->assertDatabaseHas('participant_connections', ['id' => $link, 'last_error' => null]);
        $this->assertDatabaseCount('horizon_tragedies', 0);
    }

    public function test_connection_driven_and_manual_participation_share_the_complete_action_contract(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/source-equivalence.json', 1);
        $actor = $journey['participants']['source-equivalence'];
        $origin = $journey['responses'][0]['current_proposition_id'];
        $connection = $this->postJson('/api/connections', [
            'provider' => 'openai', 'name' => 'Deterministic inference',
            'configuration' => ['model' => 'example-model'], 'credentials' => ['api_key' => 'test-only'],
        ])->assertCreated()->json('data.id');
        $url = '/api/substrates/'.$this->substrateSlug.'/participants/'.$actor.'/connections';
        $link = $this->postJson($url, ['connection_id' => $connection, 'is_enabled' => false])
            ->assertCreated()->json('data.id');
        $seen = [];
        for ($step = 1; $step <= 47; $step++) {
            $manual = $this->readParticipation($actor);
            $this->patchJson($url.'/'.$link, ['is_enabled' => true])->assertOk();
            $connected = $this->readParticipation($actor);
            $this->assertSame($manual, $connected, 'Connection identity must not change participation semantics.');
            $input = $this->nextScriptInput();
            $prompt = app(ParticipantPromptFactory::class)->make(Connection::findOrFail($connection), $connected);
            $this->assertSame($connected['plan']['schema'], $prompt['schema']);
            $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
            $contribution = $transport['properties']['contribution'];
            $variant = $contribution['anyOf'][0] ?? $contribution;
            $envelope = ['contribution' => array_replace(array_fill_keys($variant['required'], null), $input)];
            $this->assertTrue(app(Validator::class)->validate(Helper::toJSON($envelope), Helper::toJSON($transport))->isValid());
            $seen[$connected['plan']['phase']['kind']] = true;
            $seen[$input['action']] = true;
            $after = $this->continueParticipationScript(1)['responses'][$step];
            $this->patchJson($url.'/'.$link, ['is_enabled' => false])->assertOk();
            $this->assertSame($after, $this->readParticipation($actor));
        }
        $this->assertParticipationScriptComplete();
        foreach (['distinguish', 'persist', 'constrain', 'disperse', 'mediate', 'bias', 'order',
            'participant.rest', 'proposition.commit'] as $position) {
            $this->assertArrayHasKey($position, $seen);
        }
        $this->assertSame('distinguish', $after['plan']['phase']['kind']);
        $this->assertNotSame($origin, $after['last_commitment']['proposition_id']);
    }
}
