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
            $this->assertSame([], DB::getQueryLog(), 'Layout must not fetch more evidence or acquire locks.');
            DB::disableQueryLog();
            $after = json_decode($prompt['user'], true);
            $bodies = array_column(json_decode($prompt['evidence'], true)['loaded_receipts'], null, 'id');
            foreach ($after['plan']['receipts'] as &$receipt) {
                $receipt = [...($bodies[$receipt['id']] ?? []), ...$receipt];
            }
            foreach ($before['plan']['receipts'] as &$ref) {
                $ref = array_intersect_key($ref, array_flip(($ref['active'] ?? false)
                    ? ['id', 'type', 'reference', 'loaded', 'proposition_id', 'context_horizon_id', 'signature', 'capabilities', 'replayed_from_receipt_id', 'observation_error', 'evidence']
                    : ['id', 'type', 'reference', 'loaded']));
            }
            unset($ref);
            $this->assertEquals($before, $after);
            $this->assertSame($before['plan']['schema'], $after['plan']['schema']);

            foreach (['first', 'second'] as $name) {
                $expected = collect($bodies)->contains(fn ($body) => ($body['evidence']['content_excerpt'] ?? null) === $name.' observed evidence') ? 1 : 0;
                $this->assertSame($expected, substr_count($prompt['evidence'].$prompt['user'], $name.' observed evidence'));
                $this->assertStringNotContainsString($name.' observed evidence', $prompt['user']);
            }

            return array_keys($bodies);
        };
        $this->assertSame([], $packet());
        // Load in reverse creation order, then append the older observation.
        $context->read($fixture->viewer, $fixture->phase, $receipts[1]->id);
        $this->assertSame([$receipts[1]->id], $packet());
        $this->travel(1)->seconds();
        $context->read($fixture->viewer, $fixture->phase, $receipts[0]->id);
        $this->assertSame([$receipts[1]->id, $receipts[0]->id], $packet());
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
        $this->assertSame(0, substr_count($prompt['evidence'].$prompt['user'], 'PRIVATE-ATTACHED-OBSERVATION')); // Gate/Write are metadata even when loaded.
        $this->assertStringNotContainsString('PRIVATE-ATTACHED-OBSERVATION', $prompt['user']);
        $entry = collect(json_decode($prompt['user'], true)['plan']['receipts'])->firstWhere('id', $receipt->id);
        $this->assertTrue($entry['loaded']);
        $this->assertEqualsCanonicalizing(['id', 'type', 'reference', 'loaded'], array_keys($entry));
        $this->assertSame($fixture->phase->id, $loaded['plan']['phase']['id']);
    }

    public function test_receipt_heavy_gate_packet_keeps_memory_but_activates_no_bodies(): void
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
            if ($i < 3) {
                $context->read($fixture->viewer, $fixture->phase, $receipt->id);
            }
        }
        $this->publishPotentialFixture($fixture->viewer);
        $state = app(ParticipationService::class)->stateFor($fixture->viewer);
        $after = app(ParticipantContextSerializer::class)->inference($state);
        $before = $after;
        $before['plan']['receipts'] = $context->forPhase($fixture->phase)->all();
        $layout = app(ParticipantPromptLayout::class);
        $old = $layout->make($before);
        $new = $layout->make($after);
        $this->assertSame($old['evidence'], $new['evidence']);
        $this->assertSame($before['plan']['schema'], $after['plan']['schema']);
        $this->assertSame(0, $new['loaded_receipt_count']);
        $this->assertSame(3, ParticipantReceiptContext::active()->count());
        foreach ($after['plan']['receipts'] as $receipt) {
            if ($receipt['status'] !== 'loaded') {
                $this->assertArrayNotHasKey('evidence', $receipt);
            }
        }
        $bytes = fn ($value) => strlen(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $catalogue = fn ($packet) => array_values(array_filter($packet['plan']['receipts'], fn ($r) => $r['status'] !== 'loaded'));
        $metrics = ['catalogue_before' => $bytes($catalogue($before)), 'catalogue_after' => $bytes($catalogue($after)),
            'loaded_evidence' => strlen($new['evidence']), 'internal_packet' => $bytes($before),
            'packet_after' => strlen($new['evidence'].$new['user']), 'reads' => count($after['plan']['read_options']),
            'counterparts' => count($after['plan']['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum'] ?? [])];
        $this->assertLessThan($metrics['catalogue_before'], $metrics['catalogue_after']);
        fwrite(STDERR, "\nRECEIPT_CONTEXT_FIXTURE ".json_encode($metrics)."\n");
    }
}
