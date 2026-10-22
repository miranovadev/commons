<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\ReceiptCapability;
use App\Models\InstrumentExecution;
use App\Models\ParticipantReceiptContext;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\Evidence\InstrumentInvocation;
use App\Services\OpenAIResponseSchema;
use App\Services\OperationalInstrumentService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PropositionStateService;
use App\Services\ReceiptReplayService;
use App\Services\RelevantInstrumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class RelevantInstrumentTest extends TestCase
{
    use RefreshDatabase;

    public static function invocations(): array
    {
        return [
            'canonical hyperlink' => [['kind' => 'hyperlink', 'reference' => 'https://EXAMPLE.test:443/a/../%70aper?q=1'], ['kind' => 'hyperlink', 'reference' => 'https://example.test/paper?q=1']],
            'normalized search' => [['kind' => 'web_search', 'query' => "  evidence\n discovery "], ['kind' => 'web_search', 'query' => 'evidence discovery']],
            'stable citation' => [['kind' => 'citation'], ['kind' => 'citation']],
        ];
    }

    #[DataProvider('invocations')]
    public function test_effective_receipts_suppress_duplicates_across_services_and_reclassification_without_mutating_history(array $input, array $equivalent): void
    {
        Http::fake();
        $f = new ParticipantContextFixture(1, 0, false);
        $target = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $target->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $target->id])->save();
        if ($input['kind'] === 'citation') {
            $input['reference'] = $f->seed->id;
            $equivalent['reference'] = ' '.$f->seed->id.' ';
        }
        $instruments = app(OperationalInstrumentService::class);
        $receipt = $this->observationFixture($target->phase, $target, $input);
        $this->consumeObservationFixture($f, $receipt);
        $history = $receipt->fresh()->getAttributes();
        $execution = $receipt->instrumentExecution->fresh()->getAttributes();
        $count = InstrumentExecution::count();
        $relevance = app(RelevantInstrumentService::class);
        $this->assertSame($receipt->id, $relevance->equivalent($target, $equivalent)->id);
        $this->assertSame($receipt->id, $relevance->equivalent($target, $equivalent, new ParticipationContext($f->phase))->id);
        $action = ['action' => 'instrument.execute', 'proposition_id' => $target->id, 'instrument' => $equivalent];
        $this->publishPotentialFixture($f->viewer);
        $schema = app(ParticipationService::class)->stateFor($f->viewer)['plan']['schema'];
        $normalized = app(InstrumentInvocation::class)->normalize($equivalent);
        $this->assertSame([['receipt_id' => $receipt->id, 'invocation' => $normalized]], $schema['x-commons']['instrument_exclusions'][$target->id]);
        $validator = new Validator;
        $this->assertFalse($validator->validate(Helper::toJSON(array_replace($action, ['instrument' => $normalized])), Helper::toJSON($schema))->isValid());
        try {
            app(ContributionService::class)->validateSchema($schema, $action);
            $this->fail('The authoritative schema accepted an equivalent normalized invocation.');
        } catch (\InvalidArgumentException) {
            $this->assertSame($count, InstrumentExecution::count());
        }
        foreach ([fn () => $instruments->execute($f->phase, $target, $equivalent),
            fn () => app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $action),
            fn () => app(ParticipationService::class)->contribute($f->viewer, $f->phase->id, $action)] as $submit) {
            try {
                $submit();
                $this->fail('Equivalent effective observation was accepted.');
            } catch (ConflictHttpException|\InvalidArgumentException) {
                $this->assertSame($count, InstrumentExecution::count());
                $this->assertNull($f->phase->fresh()->completed_at);
            }
        }
        $classification = $f->node($target, 'canon', 'reclassify');
        app(PropositionStateService::class)->preserveReclassificationEvidence($target, $classification);
        $this->assertSame($receipt->id, $relevance->equivalent($classification, $input)->id);
        $this->assertSame($receipt->id, $relevance->equivalent($classification, $input, new ParticipationContext($f->phase))->id);
        // A structural descendant with fresh evidence is a natural relevance boundary.
        $fresh = $f->node($classification, 'activity');
        $this->assertNull($relevance->equivalent($fresh, $input));
        $f->phase->forceFill(['gate_proposition_id' => $fresh->id])->save();
        $observedAgain = $instruments->execute($f->phase->fresh(), $fresh, $input);
        $this->assertNotSame($receipt->id, $observedAgain->id);
        $this->assertSame($history, $receipt->fresh()->getAttributes());
        $this->assertSame($execution, $receipt->instrumentExecution->fresh()->getAttributes());
        $this->assertSame(2, ParticipantReceiptContext::active()->count());
        $this->assertSame(0, DB::table('reinforcements')->count());
    }

    public static function distinctUrls(): array
    {
        return [
            ['http://example.test/paper?a=1'], ['https://example.test/paper?a=2'],
            ['https://example.test/paper?b=2&a=1'], ['https://example.test/paper?a=1&b=2'],
            ['https://example.test:8443/paper?a=1'], ['https://example.test/Paper?a=1'],
            ['https://example.test/paper/?a=1'], ['https://example.test/paper?a=1#section'],
        ];
    }

    #[DataProvider('distinctUrls')]
    public function test_url_identity_does_not_collapse_distinct_resources(string $url): void
    {
        $identity = app(InstrumentInvocation::class);
        $this->assertNotSame($identity->signature(['kind' => 'hyperlink', 'reference' => 'https://example.test/paper?a=1']),
            $identity->signature(['kind' => 'hyperlink', 'reference' => $url]));
    }

    public function test_schema_exposes_target_specific_exclusions_and_retains_other_free_form_resources(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $target = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $target->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $target->id])->save();
        $input = ['kind' => 'citation', 'reference' => $f->seed->id];
        $receipt = $this->observationFixture($target->phase, $target, $input);
        $this->consumeObservationFixture($f, $receipt);
        $this->publishPotentialFixture($f->viewer);
        $plan = app(ParticipationService::class)->stateFor($f->viewer)['plan'];
        $this->assertSame([['receipt_id' => $receipt->id, 'invocation' => $input]], $plan['schema']['x-commons']['instrument_exclusions'][$target->id]);
        $validator = new Validator;
        $duplicate = ['action' => 'instrument.execute', 'proposition_id' => $target->id, 'instrument' => $input];
        $this->assertArrayHasKey('not', $plan['schema']['$defs']['actions']['instrument.execute']);
        $this->assertFalse($validator->validate(Helper::toJSON($duplicate), Helper::toJSON($plan['schema']))->isValid());
        $transport = app(OpenAIResponseSchema::class)->fromCommons($plan['schema']);
        $this->assertStringContainsString($receipt->id, json_encode($transport));
        app(ContributionService::class)->validate($f->viewer, ['action' => 'instrument.execute', 'proposition_id' => $target->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/different']]);
        $other = app(OperationalInstrumentService::class)->execute($f->phase, $target, ['kind' => 'citation', 'reference' => $f->gate->id]);
        $this->assertNotSame($receipt->id, $other->id);
    }

    public function test_replay_capability_survives_but_equivalent_effective_evidence_cannot_be_replayed(): void
    {
        Http::fake();
        $f = new ParticipantContextFixture(1, 0, false);
        $target = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $target->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $target->id])->save();
        $receipt = app(OperationalInstrumentService::class)->execute($f->phase, $target, ['kind' => 'web_search', 'query' => 'evidence']);
        $this->assertTrue($receipt->supports(ReceiptCapability::Readable));
        $this->assertTrue($receipt->supports(ReceiptCapability::Replayable));
        $this->publishPotentialFixture($f->viewer);
        $plan = app(ParticipationService::class)->stateFor($f->viewer)['plan'];
        $this->assertFalse(collect($plan['receipts'])->firstWhere('id', $receipt->id)['can_replay']);
        $this->assertArrayNotHasKey('receipt.replay', $plan['schema']['$defs']['actions']);
        $count = InstrumentExecution::count();
        try {
            app(ReceiptReplayService::class)->replay($f->viewer, $f->phase, $receipt->id);
            $this->fail('Relevant receipt replay was allowed.');
        } catch (\DomainException) {
            $this->assertSame($count, InstrumentExecution::count());
        }
    }
}
