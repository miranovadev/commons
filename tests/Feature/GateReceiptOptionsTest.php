<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Models\ParticipantReceiptContext;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\OpenAIResponseSchema;
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
        $this->assertDatabaseCount('participant_receipt_contexts', 0);
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
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($f->viewer, $f->phase, ['unload' => [$r->id]]));
        $this->assertDatabaseCount('receipt_context_events', 2);
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
                $this->assertDatabaseCount('participant_receipt_contexts', 0);
                $this->assertSame($before, DB::table('substrate_ledger_entries')->count());
            }
        }
    }

    public function test_existing_non_gate_and_foreign_region_receipts_cannot_be_injected(): void
    {
        [$f, $r, $input] = $this->scenario();
        $service = app(InstrumentExecutionService::class);
        $foreign = $f->node(null, 'canon', 'variant', 'committal');
        foreach ([$f->node($f->seed, 'activity'), $f->node($foreign, 'activity')] as $node) {
            $receipt = $service->complete($service->start($f->phase, $node, 'hyperlink', 'https://example.test/'.$node->id), $node,
                verification: new VerifiedHyperlink('example.test/'.$node->id, hash('sha256', $node->id), CarbonImmutable::now()));
            try {
                app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, [...$input, 'receipt_options' => ['load' => [$receipt->id]]]);
                $this->fail('Non-Gate Receipt accepted');
            } catch (\DomainException) {
                $this->assertDatabaseCount('attestations', 0);
                $this->assertDatabaseCount('participant_receipt_contexts', 0);
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
            $this->assertDatabaseCount('participant_receipt_contexts', 0);
        }
        try {
            DB::transaction(function () use ($f, $r, $input): void {
                app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, [...$input, 'receipt_options' => ['load' => [$r->id]]]);
                throw new \DomainException('Phase transition failed');
            });
        } catch (\DomainException) {
            $this->assertDatabaseCount('attestations', 0);
            $this->assertDatabaseCount('receipt_context_events', 0);
        }
    }
}
