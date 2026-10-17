<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Enums\RestReason;
use App\Models\ParticipantReceiptContext;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipationService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class GateReceiptOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $service = app(InstrumentExecutionService::class);
        $r = $service->complete($service->start($f->phase, $f->gate, 'hyperlink', 'https://example.test/gate'), $f->gate,
            verification: new VerifiedHyperlink('example.test/gate', hash('sha256', 'gate'), CarbonImmutable::now(), contentExcerpt: 'Gate evidence'));
        $this->publishPotentialFixture($f->viewer);

        return [$f, $r, ['action' => 'attestation.create', 'proposition_id' => $f->writes[0], 'direction' => 'support']];
    }

    public function test_exact_gate_ids_appear_in_both_authoritative_and_strict_transport_schemas(): void
    {
        [$f, $r, $input] = $this->scenario();
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema'];
        foreach (['load', 'unload'] as $operation) {
            $this->assertSame([$r->id], $schema['$defs']['actions']['attestation.create']['properties']['receipt_options']['properties'][$operation]['items']['enum']);
        }
        $input['receipt_options'] = ['load' => [$r->id], 'unload' => []];
        app(ContributionService::class)->validateSchema($schema, $input);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $this->assertStringContainsString($r->id, json_encode($transport));
        $this->assertStringNotContainsString('uniqueItems', json_encode($transport));
        $this->assertDatabaseCount('participant_receipt_contexts', 1);
    }

    public function test_load_and_unload_are_atomic_with_normal_actions_and_desired_states_are_idempotent(): void
    {
        [$f, $r, $input] = $this->scenario();
        $input['receipt_options'] = ['load' => [$r->id]];
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $input);
        $this->assertSame(1, ParticipantReceiptContext::active()->count());
        $this->assertDatabaseCount('receipt_context_events', 1);
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($f->viewer, $f->phase, ['load' => [$r->id]]));
        $this->assertDatabaseCount('receipt_context_events', 1);
        $input['proposition_id'] = $f->writes[1];
        $input['receipt_options'] = ['unload' => [$r->id]];
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $input);
        $this->assertSame(0, ParticipantReceiptContext::active()->count());
        $this->assertDatabaseCount('receipt_context_events', 2);
        $this->expectException(\DomainException::class);
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($f->viewer, $f->phase, ['unload' => [$r->id]]));
    }

    public function test_rest_rejects_receipt_options_and_preserves_load_context(): void
    {
        [$f] = $this->scenario();
        $gate = $f->node($f->seed, 'canon', position: PhaseKind::Bias);
        $f->phase->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'canon', 'gate_proposition_id' => $gate->id])->save();
        $f->viewer->forceFill(['current_proposition_id' => $gate->id])->save();
        $instruments = app(InstrumentExecutionService::class);
        $r = $instruments->complete($instruments->start($f->phase, $gate, 'hyperlink', 'https://example.test/rest'), $gate,
            verification: new VerifiedHyperlink('example.test/rest', hash('sha256', 'rest'), CarbonImmutable::now()));
        $this->assertSame([$r->id], app(ReceiptContextService::class)->gateOptions($f->phase)->pluck('id')->all());
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($f->viewer, $f->phase, ['load' => [$r->id]]));
        $this->publishPotentialFixture($f->viewer);
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema'];
        $this->assertArrayHasKey('participant.rest', $schema['$defs']['actions']);
        $this->assertArrayNotHasKey('receipt_options', $schema['$defs']['actions']['participant.rest']['properties']);
        $this->assertArrayNotHasKey('receipt.read', $schema['$defs']['actions']);
        $before = ParticipantReceiptContext::get()->map->getAttributes()->all();
        $events = DB::table('receipt_context_events')->count();
        foreach ([['load' => [$r->id]], ['unload' => [$r->id]], []] as $options) {
            try {
                app(ParticipationService::class)->contribute($f->viewer, $f->phase->id,
                    ['action' => 'participant.rest', 'receipt_options' => $options]);
                $this->fail('Rest accepted Receipt options');
            } catch (\InvalidArgumentException) {
                $this->assertNull($f->phase->fresh()->completed_at);
                $this->assertNull($f->phase->cycle->fresh()->rested_at);
                $this->assertSame($before, ParticipantReceiptContext::get()->map->getAttributes()->all());
                $this->assertDatabaseCount('receipt_context_events', $events);
            }
        }
        app(ParticipationService::class)->contribute($f->viewer, $f->phase->id, ['action' => 'participant.rest']);
        $this->assertNotNull($f->phase->cycle->fresh()->rested_at);
        $this->assertSame(RestReason::NoAvailableMove, $f->phase->cycle->fresh()->rest_reason);
        $this->assertSame($before, ParticipantReceiptContext::get()->map->getAttributes()->all());
        $this->assertDatabaseCount('receipt_context_events', $events);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_invalid_receipt_selection_rolls_back_the_normal_contribution(): void
    {
        [$f, $r, $input] = $this->scenario();
        $before = DB::table('substrate_ledger_entries')->count();
        foreach ([['load' => ['unknown']], ['load' => [$r->id], 'unload' => [$r->id]], ['load' => [['nested']]]] as $options) {
            try {
                app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, [...$input, 'receipt_options' => $options]);
                $this->fail('Invalid options accepted');
            } catch (\DomainException) {
                $this->assertDatabaseCount('attestations', 0);
                $this->assertDatabaseCount('participant_receipt_contexts', 1);
                $this->assertSame($before, DB::table('substrate_ledger_entries')->count());
            }
        }
    }

    public function test_existing_non_gate_and_foreign_region_receipts_cannot_be_injected(): void
    {
        [$f, $r, $input] = $this->scenario();
        $service = app(InstrumentExecutionService::class);
        $foreign = $f->node(null, 'canon', 'variant', 'committal');
        foreach ([$f->node($f->seed, 'activity'), $f->node($foreign, 'activity')] as $index => $node) {
            $receipt = $service->complete($service->start($f->phase, $node, 'hyperlink', 'https://example.test/'.$node->id), $node,
                verification: new VerifiedHyperlink('example.test/'.$node->id, hash('sha256', $node->id), CarbonImmutable::now()));
            try {
                app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, [...$input, 'receipt_options' => ['load' => [$receipt->id]]]);
                $this->fail('Non-Gate Receipt accepted');
            } catch (\DomainException) {
                $this->assertDatabaseCount('attestations', 0);
                $this->assertDatabaseCount('participant_receipt_contexts', $index + 2);
            }
        }
        $this->assertSame([$r->id], app(ReceiptContextService::class)->gateOptions($f->phase)->pluck('id')->all());
    }

    public function test_invalid_action_never_changes_loads_and_an_outer_failure_rolls_back_both(): void
    {
        [$f, $r, $input] = $this->scenario();
        try {
            app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, [...$input, 'proposition_id' => $f->seed->id,
                'receipt_options' => ['load' => [$r->id]]]);
            $this->fail('Invalid action accepted');
        } catch (\DomainException) {
            $this->assertDatabaseCount('participant_receipt_contexts', 1);
        }
        try {
            DB::transaction(function () use ($f, $r, $input): void {
                app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, [...$input, 'receipt_options' => ['load' => [$r->id]]]);
                throw new \DomainException('Phase transition failed');
            });
        } catch (\DomainException) {
            $this->assertDatabaseCount('attestations', 0);
            $this->assertDatabaseCount('receipt_context_events', 1);
        }
    }
}
