<?php

namespace Tests\Feature\Api;

use App\Domain\Evidence\PreparationInput;
use App\Models\User;
use App\Services\EvidenceExecutionService;
use App\Services\PreparationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\PreparationFixture;
use Tests\Support\RefreshEvidenceDatabase;
use Tests\TestCase;

class PreparationHistoryApiTest extends TestCase
{
    use RefreshEvidenceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('artifacts');
        User::factory()->create();
    }

    public function test_pending_and_presented_history_never_replays_content_or_changes_state(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $url = "/api/substrates/{$f->substrate->slug}/preparations/{$execution->id}";
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.receipt_id', null)->assertJsonPath('data.outcome', null)
            ->assertJsonPath('data.presented_at', null)->assertJsonPath('data.deliveries', []);
        $reading = $service->delivery($f->viewer, $execution, 'human-text');
        // Extraction alone is not presentation.
        $this->getJson($url)->assertOk()->assertJsonPath('data.deliveries', []);
        $service->acknowledge($f->viewer, $execution, $reading['presentation_token']);
        Storage::disk('artifacts')->deleteDirectory('');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $response = $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'presented')
            ->assertJsonPath('data.resolved_at', null)->assertJsonPath('data.outcome', null)
            ->assertJsonPath('data.deliveries.0.payload_bytes', 23);
        $this->assertStringNotContainsString('preserved artifact body', $response->getContent());
        $this->assertStringNotContainsString('presentation_token', $response->getContent());
        $this->assertStringNotContainsString('"path"', $response->getContent());
        $this->assertStringNotContainsString('"content"', $response->getContent());
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query);
        }
    }

    public static function outcomes(): array
    {
        return [['contributed'], ['deferred'], ['failed']];
    }

    #[DataProvider('outcomes')]
    public function test_historical_outcomes_and_requested_actual_boundaries_remain_distinct(string $outcome): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $second = $fixture->receipt(str_repeat('x', 20000));
        $input = $fixture->input([$fixture->source, $second]);
        foreach ($input['artifacts'] as &$selection) {
            $selection['parameters'] = ['offset' => 2, 'max_chars' => 20000];
        }
        unset($selection);
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        $reading = $service->delivery($f->viewer, $execution, 'inline-text');
        $service->acknowledge($f->viewer, $execution, $reading['presentation_token']);
        if ($outcome === 'contributed') {
            $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'An investigated Proposition.']);
        } elseif ($outcome === 'deferred') {
            $service->defer($f->viewer, $execution, 'Need another source.');
        } else {
            $service->fail($f->viewer, $execution, 'provider', '1', 'inference', '<script>unsafe()</script> A bounded failure.');
        }
        $execution->refresh();
        $url = "/api/substrates/{$f->substrate->slug}/preparations";
        $before = $this->getJson("{$url}/{$execution->id}")->assertOk()
            ->assertJsonPath('data.outcome', $outcome)->assertJsonCount(2, 'data.selections')->assertJsonCount(2, 'data.deliveries')
            ->assertJsonPath('data.selections.1.parameters.max_chars', 20000)
            ->assertJsonPath('data.deliveries.1.boundaries.offset', 2)
            ->assertJsonPath('data.deliveries.1.boundaries.has_more', true)->json('data');
        $this->assertSame($f->viewer->id, $before['participant']['id']);
        $this->assertSame($f->gate->id, $before['originating_gate_id']);
        $this->assertSame($f->phase->id, $before['phase']['id']);
        $this->assertNotNull($before['resolved_at']);
        $this->assertLessThan(20000, $before['deliveries'][1]['boundaries']['length']);
        $this->assertSame($execution->receipt->id, $before['receipt_id']);
        if ($outcome === 'failed') {
            $this->assertSame('failed', $before['status']);
            $this->assertSame('inference', $before['failure']['stage']);
            $this->assertSame('inference_failure', $before['failure']['classification']);
        } elseif ($outcome === 'deferred') {
            $this->assertSame('Need another source.', $before['deferment_reason']);
        } else {
            $this->assertSame($execution->resulting_proposition_id, $before['resulting_proposition_id']);
            $this->getJson("{$url}?proposition_id={$execution->resulting_proposition_id}")->assertOk()->assertJsonPath('data.items.0.id', $execution->id);
        }
        $this->getJson("{$url}?participant_id={$f->viewer->id}")->assertOk()->assertJsonPath('data.items.0.id', $execution->id);
        $this->getJson("{$url}?proposition_id={$f->gate->id}")->assertOk()->assertJsonPath('data.items.0.id', $execution->id);
        $fixture->consume($execution->receipt);
        $this->assertEquals($before, $this->getJson("{$url}/{$execution->id}")->assertOk()->json('data'));
    }

    public function test_failure_without_acknowledgement_or_receipt_does_not_invent_delivery_or_outcome(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->fail($f->viewer, $execution, 'reader', '1', 'stale', 'Gate changed.');
        $this->getJson("/api/substrates/{$f->substrate->slug}/preparations/{$execution->id}")->assertOk()
            ->assertJsonPath('data.status', 'failed')->assertJsonPath('data.receipt_id', null)
            ->assertJsonPath('data.outcome', null)->assertJsonPath('data.deliveries', [])
            ->assertJsonPath('data.failure.stage', 'stale');
    }

    public function test_observer_permissions_cross_substrate_binding_and_instrument_exclusion(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $execution = app(PreparationService::class)->start($f->viewer, $f->phase, $fixture->input());
        $other = (new PreparationFixture)->context;
        $base = "/api/substrates/{$f->substrate->slug}/preparations";
        $this->getJson("{$base}/{$fixture->source->evidence_execution_id}")->assertNotFound();
        $this->getJson("/api/substrates/{$other->substrate->slug}/preparations/{$execution->id}")->assertNotFound();
        $this->getJson("{$base}?participant_id={$other->viewer->id}")->assertNotFound();
        $this->getJson("{$base}?proposition_id={$other->gate->id}")->assertNotFound();
        $this->getJson($base)->assertUnprocessable();
        $this->getJson("{$base}?participant_id={$f->viewer->id}&proposition_id={$f->gate->id}")->assertUnprocessable();
        $f->substrate->forceFill(['visibility' => 'private'])->save();
        $this->getJson("{$base}/{$execution->id}")->assertForbidden();
        $user = User::factory()->create();
        $this->actingAs($user)->getJson("{$base}?participant_id={$f->viewer->id}")->assertForbidden();
        $membership = $f->substrate->memberships()->make();
        $membership->forceFill(['user_id' => $user->id, 'status' => 'active'])->save();
        $this->getJson("{$base}/{$execution->id}")->assertOk();
        $this->getJson("{$base}?participant_id={$f->viewer->id}")->assertOk();
    }

    public function test_history_has_bounded_stable_pagination(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        for ($i = 0; $i < 21; $i++) {
            $cycle = $f->phase->cycle->replicate();
            $cycle->sequence = 100 + $i;
            $cycle->save();
            $phase = $f->phase->replicate();
            $phase->cycle_id = $cycle->id;
            $phase->save();
            DB::transaction(fn () => app(EvidenceExecutionService::class)->startPreparation($phase, PreparationInput::from($fixture->input())));
        }
        $url = "/api/substrates/{$f->substrate->slug}/preparations?participant_id={$f->viewer->id}";
        $first = $this->getJson($url)->assertOk()->assertJsonCount(20, 'data.items')->json('data');
        $second = $this->getJson($url.'&before='.$first['next_cursor'])->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.next_cursor', null)->json('data');
        $this->assertNotContains($second['items'][0]['id'], array_column($first['items'], 'id'));
    }
}
