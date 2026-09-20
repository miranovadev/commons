<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Models\Connection;
use App\Services\InstrumentExecutionService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
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
            $execution = $executions->start($fixture->phase, $fixture->gate, 'hyperlink', 'https://example.test/'.$name);
            $receipts[] = $executions->complete($execution, $fixture->gate, verification: new VerifiedHyperlink(
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
            $this->assertEquals($before, $after);
            $this->assertSame($before['plan']['schema'], $after['plan']['schema']);

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
}
