<?php

namespace Tests\Feature;

use App\Domain\Evidence\SearchObservation;
use App\Enums\PhaseKind;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionService;
use App\Services\EvidenceExecutionService;
use App\Services\InputSchemaService;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipationService;
use App\Services\ReceiptReplayService;
use Carbon\CarbonImmutable;
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
        $target = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $target->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $target->id])->save();
        $other = $f->node($f->gate, 'frontier', position: PhaseKind::Mediate);
        // Historical observations retain exact execution/Receipt provenance.
        // They do not spend the active occurrence's personal allowance.
        $execution = app(EvidenceExecutionService::class)->start($target->phase, $target, 'web_search', 'archival query',
            ['kind' => 'web_search', 'query' => 'archival query']);
        $source = app(EvidenceExecutionService::class)->complete($execution, $target,
            observation: new SearchObservation('archival query', CarbonImmutable::now(), [], 'fixture'));
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

    public function test_consumed_replayable_history_preserves_relevance_and_fresh_execution_target_binding(): void
    {
        [$f, $target, $other, $source] = $this->scenario();
        $service = app(ParticipationService::class);
        $citation = $this->observationFixture($target->phase, $target, ['kind' => 'citation', 'reference' => $f->seed->id]);
        // Consumption reopens acquisition, but equivalent retained observations
        // remain excluded by the independent relevance rule.
        $this->consumeObservationFixture($f, $source);
        $this->consumeObservationFixture($f, $citation);
        $this->publishPotentialFixture($f->viewer);
        $plan = $service->stateFor($f->viewer)['plan'];
        $sourceOption = collect($plan['receipts'])->firstWhere('id', $source->id);
        $this->assertTrue($sourceOption['capabilities']['replayable']);
        $this->assertFalse($sourceOption['can_replay']);
        $schema = $plan['schema'];
        $this->assertNotEmpty($schema['x-commons']['instrument_exclusions'][$target->id]);
        $input = ['action' => 'instrument.execute', 'proposition_id' => $target->id,
            'instrument' => ['kind' => 'web_search', 'query' => 'fresh query']];
        app(ContributionService::class)->validateSchema($schema, $input);
        $this->assertArrayNotHasKey('receipt.replay', $schema['$defs']['actions']);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $branches = $transport['properties']['contribution']['anyOf'] ?? [$transport['properties']['contribution']];
        $wire = array_replace(array_fill_keys($branches[0]['required'], null), $input);
        $validator = new Validator;
        $this->assertTrue($validator->validate(Helper::toJSON(['contribution' => $wire]), Helper::toJSON($transport))->isValid());
        foreach ([
            [...$input, 'proposition_id' => $other->id],
            [...$input, 'instrument' => ['replay_receipt_id' => $source->id]],
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
        $invocation = $source->evidenceExecution->fresh()->getAttributes();
        $next = $service->contribute($f->viewer, $f->phase->id, $input);
        $result = Receipt::where('phase_id', $f->phase->id)->sole();
        $this->assertSame($target->id, $result->receiptable_id);
        $this->assertSame((new Proposition)->getMorphClass(), $result->receiptable_type);
        $this->assertSame($input['instrument'], $result->evidenceExecution->input);
        $this->assertNull($result->evidenceExecution->replayed_from_receipt_id);
        $this->assertNotNull($result->signature);
        $this->assertNotNull($result->ledgerEntry);
        $this->assertSame($original, $source->fresh()->getAttributes());
        $this->assertSame($invocation, $source->evidenceExecution->fresh()->getAttributes());
        $this->assertSame(3, ParticipantReceiptContext::count());
        $this->assertNull($f->phase->fresh()->completed_at);
        $this->assertSame('persist', $next['plan']['phase']['kind']);
        $this->assertArrayNotHasKey('instrument.execute', $next['plan']['schema']['$defs']['actions']);
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
            $this->assertSame(1, ParticipantReceiptContext::count());
        }
    }
}
