<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionService;
use App\Services\InputSchemaService;
use App\Services\InstrumentExecutionService;
use App\Services\OpenAIResponseSchema;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipationService;
use App\Services\ReceiptReplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class InstrumentReplayActionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $target = $f->node($f->gate, 'frontier', position: PhaseKind::Mediate);
        $other = $f->node($f->gate, 'frontier', position: PhaseKind::Mediate);
        // Archival catalogue evidence can have distinct observation and grounding
        // targets. New execution must ground the observed target, not copy ownership.
        $execution = app(InstrumentExecutionService::class)->start($f->phase, $target, 'web_search', 'archival query',
            ['kind' => 'web_search', 'query' => 'archival query']);
        $execution->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $source = (new Receipt)->forceFill(['instrument_execution_id' => $execution->id, 'type' => 'web_search',
            'reference' => 'archival query', 'capabilities' => ['readable', 'replayable'], 'metadata' => []]);
        $source->receiptable()->associate($other);
        $source->save();
        config(['services.web_search.provider' => 'tavily', 'services.tavily.api_key' => 'test']);
        Http::preventStrayRequests();
        Http::fake(['api.tavily.com/search' => Http::response(['results' => [['title' => 'Result', 'url' => 'https://example.com/result', 'content' => 'New observation']]])]);
        $this->publishPotentialFixture($f->viewer);

        return [$f, $target, $other, $source];
    }

    public function test_all_phase_contracts_retire_standalone_receipt_actions(): void
    {
        foreach (PhaseKind::cases() as $kind) {
            $schema = app(InputSchemaService::class)->forPhase($kind);
            foreach (['receipt.read', 'receipt.replay'] as $action) {
                $this->assertArrayNotHasKey($action, $schema['$defs']['actions']);
                $this->assertNotContains($action, config('phases.'.$kind->value.'.actions'));
            }
        }
    }

    public function test_replay_is_target_bound_in_commons_and_provider_schemas_and_uses_normal_execution(): void
    {
        [$f, $target, $other, $source] = $this->scenario();
        $service = app(ParticipationService::class);
        $citation = app(OperationalInstrumentService::class)->execute($f->phase, $target, ['kind' => 'citation', 'reference' => $f->seed->id]);
        $this->publishPotentialFixture($f->viewer);
        $schema = $service->stateFor($f->viewer)['plan']['schema'];
        $this->assertNotEmpty($schema['x-commons']['instrument_exclusions'][$target->id]);
        $input = ['action' => 'instrument.execute', 'proposition_id' => $target->id,
            'instrument' => ['replay_receipt_id' => $source->id]];
        app(ContributionService::class)->validateSchema($schema, $input);
        $this->assertArrayNotHasKey('receipt.replay', $schema['$defs']['actions']);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $branches = $transport['properties']['contribution']['anyOf'];
        $wire = array_replace(array_fill_keys($branches[0]['required'], null), $input);
        $validator = new Validator;
        $this->assertTrue($validator->validate(Helper::toJSON(['contribution' => $wire]), Helper::toJSON($transport))->isValid());
        foreach ([
            [...$input, 'proposition_id' => $other->id],
            [...$input, 'instrument' => ['replay_receipt_id' => $citation->id]],
            [...$input, 'instrument' => ['replay_receipt_id' => (string) str()->ulid()]],
            [...$input, 'instrument' => ['replay_receipt_id' => $source->id, 'kind' => 'web_search', 'query' => 'injected']],
        ] as $invalid) {
            $this->assertFalse($validator->validate(Helper::toJSON($invalid), Helper::toJSON($schema))->isValid());
            $this->assertFalse($validator->validate(Helper::toJSON(['contribution' => array_replace($wire, $invalid)]), Helper::toJSON($transport))->isValid());
            try {
                $service->contribute($f->viewer, $f->phase->id, $invalid);
                $this->fail('Invalid replay accepted');
            } catch (\InvalidArgumentException) {
                $this->assertNull($f->phase->fresh()->completed_at);
            }
        }
        Http::assertNothingSent();
        $original = $source->fresh()->getAttributes();
        $invocation = $source->instrumentExecution->getAttributes();
        $next = $service->contribute($f->viewer, $f->phase->id, $input);
        $result = Receipt::whereHas('instrumentExecution', fn ($q) => $q->where('replayed_from_receipt_id', $source->id))->sole();
        $this->assertSame($target->id, $result->receiptable_id);
        $this->assertSame((new Proposition)->getMorphClass(), $result->receiptable_type);
        $this->assertSame($source->replayInvocation(), $result->instrumentExecution->input);
        $this->assertNotNull($result->signature);
        $this->assertNotNull($result->ledgerEntry);
        $this->assertSame($original, $source->fresh()->getAttributes());
        $this->assertSame($invocation, $source->instrumentExecution->fresh()->getAttributes());
        $this->assertSame(2, ParticipantReceiptContext::count());
        $this->assertNotNull($f->phase->fresh()->completed_at);
        $this->assertSame('bias', $next['plan']['phase']['kind']);
        Http::assertSentCount(1);
    }

    public function test_domain_revalidates_the_observed_target_before_replay_io(): void
    {
        [$f, , $other, $source] = $this->scenario();
        try {
            app(ReceiptReplayService::class)->observe($f->viewer, $f->phase, $source->id, $other->id);
            $this->fail('Foreign observation target accepted');
        } catch (\DomainException) {
            Http::assertNothingSent();
            $this->assertSame(0, ParticipantReceiptContext::count());
        }
    }
}
