<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Models\ParticipantReceiptContext;
use App\Services\HorizonReceiptService;
use App\Services\InstrumentExecutionService;
use App\Services\MediationReadService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipantPromptLayout;
use App\Services\ParticipationService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ParticipantPromptPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_load_clear_and_reload_preserve_the_current_packet_and_do_not_cache_stale_evidence(): void
    {
        $fixture = new ParticipantContextFixture(2, 0, false);
        $executions = app(InstrumentExecutionService::class);
        $receipts = [];
        foreach (['first', 'second'] as $name) {
            $read = app(MediationReadService::class)->anchorFor($fixture->phase);
            $execution = $executions->start($fixture->phase, $read, 'hyperlink', 'https://example.test/'.$name);
            $receipts[] = $executions->complete($execution, $read, verification: new VerifiedHyperlink(
                'example.test/'.$name, hash('sha256', $name), CarbonImmutable::now(), contentExcerpt: $name.' observed evidence',
            ));
        }
        $connection = (new Connection)->forceFill(['configuration' => []]);
        $context = app(ReceiptContextService::class);
        $packet = function () use ($fixture, $connection): array {
            $this->publishPotentialFixture($fixture->viewer);
            $state = app(ParticipationService::class)->stateFor($fixture->viewer);
            $before = app(ParticipantContextSerializer::class)->inference($state);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $prompt = app(ParticipantPromptFactory::class)->make($connection, $state);
            $queries = DB::getQueryLog();
            $this->assertNotEmpty($queries);
            foreach ($queries as $query) {
                $this->assertStringStartsWith('select ', $query['query']);
                $this->assertStringNotContainsString('for update', strtolower($query['query']));
            }
            DB::flushQueryLog();
            app(ParticipantPromptLayout::class)->make($before, $state['plan']['receipts'], $state['plan']['read_options']);
            $this->assertSame([], DB::getQueryLog(), 'Layout must not fetch more evidence or acquire locks.');
            DB::disableQueryLog();
            $after = json_decode($prompt['user'], true);
            $bodies = collect($after['receipts'])->filter(fn ($r) => isset($r['loaded_inline_content']))->keyBy('id')->all();
            $this->assertEquals($before, $after);
            $this->assertSame($before['actions'], $after['actions']);
            $references = json_decode($prompt['support'] ?: '{"receipts":[]}', true)['receipts'];
            foreach ($references as $reference) {
                $this->assertArrayNotHasKey('evidence', $reference);
            }

            foreach (['first', 'second'] as $name) {
                $expected = collect($bodies)->contains(fn ($body) => ($body['loaded_inline_content']['content_excerpt'] ?? null) === $name.' observed evidence') ? 1 : 0;
                $this->assertSame($expected, substr_count($prompt['evidence'].$prompt['user'], $name.' observed evidence'));
                $this->assertSame('', $prompt['evidence']);
                $this->assertSame('', $prompt['support']);
            }

            return array_keys($bodies);
        };
        $this->assertSame([$receipts[0]->id, $receipts[1]->id], $packet());
        foreach ($receipts as $receipt) {
            $context->clear($fixture->viewer, $fixture->phase, $receipt->id);
        }
        $this->assertSame([], $packet());
        // Load in reverse creation order; catalogue order remains Receipt identity order.
        $context->read($fixture->viewer, $fixture->phase, $receipts[1]->id);
        $this->assertSame([$receipts[1]->id], $packet());
        $this->travel(1)->seconds();
        $context->read($fixture->viewer, $fixture->phase, $receipts[0]->id);
        $this->assertSame([$receipts[0]->id, $receipts[1]->id], $packet());
        $context->clear($fixture->viewer, $fixture->phase, $receipts[1]->id);
        $this->assertSame([$receipts[0]->id], $packet());
        $this->travel(1)->seconds();
        $context->read($fixture->viewer, $fixture->phase, $receipts[1]->id);
        $this->assertSame([$receipts[0]->id, $receipts[1]->id], $packet());
    }

    public function test_attachment_removes_catalogue_entry_and_exposes_only_a_reference_until_read(): void
    {
        $fixture = new ParticipantContextFixture(2, 0, false);
        $executions = app(InstrumentExecutionService::class);
        $receipt = $executions->complete(
            $executions->start($fixture->phase, $fixture->gate, 'hyperlink', 'https://example.test/attached'),
            $fixture->gate, verification: new VerifiedHyperlink('example.test/attached', hash('sha256', 'attached'),
                CarbonImmutable::now(), contentExcerpt: 'PRIVATE-ATTACHED-OBSERVATION'),
        );
        app(ReceiptContextService::class)->clear($fixture->viewer, $fixture->phase, $receipt->id);
        $write = $fixture->node($fixture->gate, 'frontier', position: PhaseKind::Mediate);
        $packet = function () use ($fixture): array {
            $this->publishPotentialFixture($fixture->viewer);

            return app(ParticipationService::class)->stateFor($fixture->viewer);
        };
        $before = $packet();
        $catalogue = collect($before['plan']['receipts'])->firstWhere('id', $receipt->id);
        $this->assertSame('available', $catalogue['availability']);
        $this->assertArrayNotHasKey('evidence', $catalogue);
        $this->assertStringNotContainsString('PRIVATE-ATTACHED-OBSERVATION', json_encode($before));
        // Consumption requires the target's creating occurrence, not this viewer's Phase.
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($write->phase, $fixture->gate, $write, $receipt->id));
        $attached = $packet();
        $this->assertNotContains($receipt->id, array_column($attached['plan']['receipts'], 'id'));
        $node = collect($attached['plan']['write_options'])->firstWhere('id', $write->id);
        $this->assertSame([['id' => $receipt->id, 'type' => 'hyperlink', 'reference' => $receipt->reference, 'loaded' => false]], $node['receipts']);
        $this->assertStringNotContainsString('PRIVATE-ATTACHED-OBSERVATION', json_encode($attached));
        $this->assertArrayNotHasKey('receipt.read', $attached['plan']['schema']['$defs']['actions']);
        // Seed retained historical memory independently of this Phase's participation action.
        app(ReceiptContextService::class)->read($fixture->viewer, $fixture->phase, $receipt->id);
        $loaded = $packet();
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $loaded);
        $this->assertSame(1, substr_count($prompt['user'], 'PRIVATE-ATTACHED-OBSERVATION')); // Gate admits content even when also attached to Write.
        $entry = collect(json_decode($prompt['user'], true)['receipts'])->firstWhere('id', $receipt->id);
        $this->assertTrue($entry['loaded']);
        $this->assertEqualsCanonicalizing(['id', 'type', 'reference', 'loaded', 'loaded_inline_content'], array_keys($entry));
        $this->assertSame($fixture->phase->id, $loaded['plan']['phase']['id']);
    }

    public function test_receipt_heavy_gate_exposes_loaded_cognition_without_operational_activation(): void
    {
        $fixture = new ParticipantContextFixture(2, 3, false);
        $executions = app(InstrumentExecutionService::class);
        $context = app(ReceiptContextService::class);
        for ($i = 0; $i < 12; $i++) {
            $receipt = $executions->complete(
                $executions->start($fixture->phase, $fixture->gate, 'hyperlink', 'https://example.test/measurement/'.$i),
                $fixture->gate, verification: new VerifiedHyperlink('example.test/measurement/'.$i, hash('sha256', (string) $i),
                    CarbonImmutable::now(), contentExcerpt: str_repeat('Observed evidence '.$i.'. ', 150)),
            );
            if ($i >= 3) {
                $context->clear($fixture->viewer, $fixture->phase, $receipt->id);
            }
        }
        $this->publishPotentialFixture($fixture->viewer);
        $state = app(ParticipationService::class)->stateFor($fixture->viewer);
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $rawReceipts = $context->forPhase($fixture->phase)->all();
        $receipts = app(ParticipantContextSerializer::class)->receipts($rawReceipts);
        $layout = app(ParticipantPromptLayout::class);
        $new = $layout->make($packet, $receipts, $state['plan']['read_options']);
        $this->assertSame(3, $new['loaded_receipt_count']);
        $this->assertSame(3, ParticipantReceiptContext::active()->count());
        foreach ($receipts as $receipt) {
            $this->assertArrayNotHasKey('evidence', $receipt);
        }
        $references = json_decode($new['user'], true)['receipts'];
        $this->assertSame(array_column($receipts, 'id'), array_column($references, 'id'));
        foreach ($references as $reference) {
            $this->assertEqualsCanonicalizing(['id', 'type', 'reference', 'loaded', ...($reference['loaded'] ? ['loaded_inline_content'] : [])], array_keys($reference));
        }
        $this->assertStringContainsString('Observed evidence', $new['user']);
        $this->assertSame('', $new['evidence'].$new['support']);
    }
}
