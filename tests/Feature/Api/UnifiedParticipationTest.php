<?php

namespace Tests\Feature\Api;

use App\Models\Connection;
use App\Models\Participant;
use App\Services\ContributionService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use Tests\Support\ParticipationHttpTestCase;

class UnifiedParticipationTest extends ParticipationHttpTestCase
{
    public function test_authenticated_external_client_resumes_the_same_context_and_schema_as_internal_inference(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('Qwen');
        $url = '/api/substrates/'.$this->substrateSlug.'/participants/'.$actor;
        $this->deleteJson('/api/session')->assertNoContent();
        $this->getJson($url.'/participate')->assertUnauthorized();
        $this->postJson('/api/session', ['email' => 'operator@example.test', 'password' => 'deterministic-test-password'])->assertOk();
        $this->getJson($url.'/participate')->assertOk()->assertJsonPath('data.entry.mode', 'origin')
            ->assertJsonPath('data.entry.requirements', ['statement', 'attestation'])
            ->assertJsonMissingPath('data.entry.distance')->assertJsonMissingPath('data.entry.potential_charge')
            ->assertJsonPath('data.context', null)->assertJsonPath('data.schema', null);

        $entered = $this->enterParticipant($actor, true);
        $phase = $entered['phase']['id'];
        $instrument = ['action' => 'instrument.execute', 'proposition_id' => $entered['context']['gate']['acting_id'],
            'instrument' => ['kind' => 'proposition_search', 'limit' => 1]];
        foreach ([$instrument, ['action' => 'proposition.variant', 'statement' => 'A distinction grounded in this observation.']] as $input) {
            $state = $this->readParticipation($actor);
            $this->assertSame($entered, $state);
            $this->assertSame($state, $this->getJson($url.'/workspace')->assertOk()->json('data'));
            $this->assertSame($state, $this->getJson($url.'/plan')->assertOk()->json('data'));

            $operational = app(ParticipationService::class)->stateFor(Participant::findOrFail($actor));
            $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $operational);
            $this->assertEquals($state['context'], json_decode($prompt['user'], true, flags: JSON_THROW_ON_ERROR));
            $this->assertSame($state['schema'], $operational['plan']['schema']);
            $this->assertSame($state['schema'], $prompt['schema']);
            $this->assertArrayNotHasKey('plan', $state);
            $this->assertArrayNotHasKey('actions', $state['context']);
            $this->assertArrayNotHasKey('schema', $state['context']);
            $this->assertCognitiveKeys($state['context']);
            app(ContributionService::class)->validateSchema($state['schema'], $input);
            $entered = $this->postContribution($actor, $phase, $input)->assertOk()->json('data');
        }
        $this->assertSame('persist', $entered['phase']['kind']);
        $this->assertNotSame($phase, $entered['phase']['id']);
        $this->postContribution($actor, $phase, $instrument)->assertConflict();
    }

    private function assertCognitiveKeys(array $values): void
    {
        $diagnostics = ['observables', 'attestation_summary', 'attestation_counts', 'reinforcement_count',
            'correspondence', 'chirality', 'closure', 'field', 'tension', 'charge', 'potential_charge',
            'depth', 'cadence', 'historical_extent', 'historical_rhythm', 'closure_history', 'structural_position'];
        foreach ($values as $key => $value) {
            $this->assertNotContains($key, $diagnostics);
            if (is_array($value)) {
                $this->assertCognitiveKeys($value);
            }
        }
    }
}
