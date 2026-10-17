<?php

namespace Tests\Feature;

use App\Services\ParticipantPromptLayout;
use App\Services\ParticipantReceiptProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantReceiptProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_is_unique_scoped_and_deterministic_with_id_only_references(): void
    {
        $r = ['id' => 'r1', 'type' => 'web_search', 'reference' => 'question', 'loaded' => false];
        $registers = [];
        foreach (['read', 'gate', 'write'] as $role) {
            $registers[$role] = ['propositions' => [['id' => $role, 'statement' => $role, 'receipts' => [$r, $r]]]];
        }
        $state = ['participant' => ['id' => 'viewer'], 'plan' => ['receipts' => [
            [...$r, 'id' => 'unrelated'], $r,
            ['id' => 'memory', 'type' => 'proposition_search', 'reference' => 'query', 'loaded' => true,
                'load_mode' => 'memory', 'memory_active' => true, 'proposition_collection' => ['results' => []]],
        ]]];
        $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers, $state);
        $this->assertSame(['memory', 'r1'], array_column($catalogue, 'id'));
        foreach ($registers as $register) {
            $this->assertSame(['r1'], $register['propositions'][0]['receipts']);
        }
        $packet = ['participant' => $state['participant'], 'receipts' => $catalogue, ...$registers, 'actions' => []];
        $prompt = app(ParticipantPromptLayout::class)->make($packet);
        $this->assertSame(['participant', 'receipts', 'read', 'gate', 'write', 'actions'], array_keys(json_decode($prompt['user'], true)));
        $this->assertSame('', $prompt['evidence']);
        $this->assertSame('', $prompt['support']);
        $this->assertSame(1, substr_count($prompt['user'], '"reference":"question"'));
        $this->assertSame(1, substr_count($prompt['user'], '"results":[]'));
    }
}
