<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\User;
use App\Services\AttestationEvidenceService;
use App\Services\ContributionDispatcher;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipationService;
use App\Services\Participation\ParticipationContext;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class ReceiptContextTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    private function scenario(): array
    {
        $fixture = new ParticipantContextFixture(1, 0);
        $reference = $fixture->node($fixture->seed, 'activity');
        $subject = $fixture->node($reference, 'frontier', 'reclassify', position: PhaseKind::Persist);
        $fixture->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $reference->id, 'write_register' => 'frontier'])->save();
        $receipt = app(OperationalInstrumentService::class)->execute($subject->phase, $subject, [
            'kind' => 'citation', 'reference' => $reference->id,
        ]);

        return [$fixture, $reference, $subject, $receipt];
    }

    private function move($fixture, $subject): Phase
    {
        $this->publishPotentialFixture($fixture->viewer);
        app(ParticipationService::class)->contribute($fixture->viewer, $fixture->phase->id, [
            'action' => 'attestation.create', 'proposition_id' => $subject->id, 'direction' => 'support',
        ]);

        return $fixture->viewer->cycles()->latest('sequence')->first()->phases()->whereNull('completed_at')->firstOrFail();
    }

    public function test_gate_load_accompanies_forward_contribution_and_persists_across_cycles(): void
    {
        [$fixture, $reference, $subject, $receipt] = $this->scenario();
        $service = app(ParticipationService::class);
        $this->publishPotentialFixture($fixture->viewer);
        $before = $service->stateFor($fixture->viewer)['plan'];
        $this->assertSame('already-in-context', collect($before['receipts'])->firstWhere('id', $receipt->id)['status']);
        $this->assertArrayNotHasKey('receipt.read', $before['schema']['$defs']['actions']);
        $this->assertSame(0, app(ReinforcementService::class)->count($subject));
        $this->assertPublicCount($fixture, $subject, 0);
        $this->assertSame(0, ParticipantReceiptContext::count());
        $phase = $this->move($fixture, $subject);
        $this->publishPotentialFixture($fixture->viewer);
        $plan = $service->stateFor($fixture->viewer)['plan'];
        $this->assertContains($receipt->id, $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['receipt_options']['properties']['load']['items']['enum']);
        $this->publishPotentialFixture($fixture->viewer);
        $state = $service->contribute($fixture->viewer, $phase->id, ['action' => 'proposition.reclassify', 'proposition_id' => $subject->id, 'receipt_id' => $receipt->id, 'receipt_options' => ['load' => [$receipt->id]]]);
        $classification = $state['plan']['gate_id'];
        $this->assertNotSame($phase->id, $state['plan']['phase']['id']);
        $this->assertPublicCount($fixture, $subject, 1);
        $this->assertNotNull($phase->fresh()->completed_at);
        $loaded = collect($state['plan']['receipts'])->firstWhere('id', $receipt->id);
        $this->assertSame('loaded', $loaded['status']);
        $this->assertArrayNotHasKey('evidence', $loaded, 'A loaded Gate Receipt is metadata until its Proposition occupies Read.');
        $this->assertTrue($loaded['loaded']);
        $this->assertArrayNotHasKey('receipt.read', $state['plan']['schema']['$defs']['actions']);
        $this->assertSame(1, app(ReinforcementService::class)->count($subject));
        $this->assertSame(1, app(ReinforcementService::class)->count($subject, new ParticipationContext($phase)));
        $this->assertSame(1, DB::table('receipt_context_events')->count());

        $phase->cycle->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
        $phase->cycle->forceFill(['completed_at' => now()])->save();
        $horizon = $this->laterHorizonFixture($phase->cycle->contextHorizon);
        $next = $this->newPhase($fixture->viewer, $horizon->opened_by_proposition_id, $horizon);
        $this->assertSame($horizon->id, $next->cycle->context_horizon_id);
        $this->assertNotSame($phase->cycle->context_horizon_id, $next->cycle->context_horizon_id);
        $this->publishPotentialFixture($fixture->viewer);
        $later = $service->stateFor($fixture->viewer)['plan'];
        $this->assertNotContains($receipt->id, array_column($later['receipts'], 'id'));
        $this->assertSame(1, ParticipantReceiptContext::active()->count());
        // Return to the subject's actual historical Horizon; retained evidence becomes visible again.
        $next->forceFill(['completed_at' => now()])->save();
        $next->cycle->forceFill(['completed_at' => now()])->save();
        $returned = $this->newPhase($fixture->viewer, $subject->id);
        $this->assertSame($phase->cycle->context_horizon_id, $returned->cycle->context_horizon_id);
        $this->publishPotentialFixture($fixture->viewer);
        $later = $service->stateFor($fixture->viewer)['plan'];
        $this->assertSame('loaded', collect($later['receipts'])->firstWhere('id', $receipt->id)['status']);
        $this->assertSame($loaded['loaded_at'], collect($later['receipts'])->firstWhere('id', $receipt->id)['loaded_at']);
        $this->assertArrayNotHasKey('receipt.read', $later['schema']['$defs']['actions']);
        $this->assertSame(1, ParticipantReceiptContext::count());

        $other = new Participant;
        $other->forceFill(['substrate_id' => $fixture->substrate->id, 'callsign' => 'Another reader'])->save();
        $otherPhase = $this->newPhase($other, $subject->id);
        $this->publishPotentialFixture($other);
        $service->contribute($other, $otherPhase->id, ['action' => 'attestation.create', 'proposition_id' => $classification, 'direction' => 'support', 'receipt_options' => ['load' => [$receipt->id]]]);
        $this->assertSame(2, app(ReinforcementService::class)->count($subject));
        $this->assertPublicCount($fixture, $subject, 2);
        $commit = $fixture->projectedCommittal($subject, 'canon');
        $this->assertPublicCount($fixture, $commit, 2);
        $variant = $fixture->node($subject, 'frontier', 'variant', position: PhaseKind::Persist);
        $this->assertPublicCount($fixture, $variant, 0);
        $this->assertSame(2, ParticipantReceiptContext::count());
        $this->assertSame(2, DB::table('receipt_context_events')->count());
        $this->assertSame($receipt->id, $receipt->fresh()->id);
    }

    private function assertPublicCount($fixture, $subject, int $expected): void
    {
        if (! User::exists()) {
            User::factory()->create();
        }
        $url = "/api/substrates/{$fixture->substrate->slug}/propositions";
        $this->getJson($url.'/'.$subject->id)->assertOk()->assertJsonPath('data.reinforcement_count', $expected);
        $nodes = collect($this->getJson($url)->assertOk()->json('data'));
        $this->assertSame($expected, $nodes->firstWhere('id', $subject->id)['reinforcement_count']);
    }

    public function test_backend_rejects_lineage_redundancy_hidden_receipts_and_foreign_participant(): void
    {
        [$fixture, $reference, $subject, $receipt] = $this->scenario();
        try {
            app(ContributionDispatcher::class)->dispatch($fixture->viewer, $fixture->phase, ['action' => 'receipt.read', 'receipt_id' => $receipt->id]);
            $this->fail('A Cite already in context cannot be loaded through a direct dispatcher call.');
        } catch (DomainException) {
            $this->assertSame(0, ParticipantReceiptContext::count());
        }
        $foreign = new ParticipantContextFixture(1, 0);
        $hidden = $foreign->node($foreign->seed, 'canon');
        $hiddenReceipt = app(OperationalInstrumentService::class)->execute($hidden->phase, $hidden, ['kind' => 'citation', 'reference' => $foreign->seed->id]);
        $phase = $this->move($fixture, $subject);
        foreach ([$hiddenReceipt->id, strtolower((string) str()->ulid())] as $id) {
            try {
                app(ReceiptContextService::class)->read($fixture->viewer, $phase, $id);
                $this->fail('Unencountered evidence cannot be loaded.');
            } catch (DomainException) {
                $this->assertSame(0, ParticipantReceiptContext::count());
            }
        }
        $other = new Participant;
        $other->forceFill(['substrate_id' => $fixture->substrate->id, 'callsign' => 'Wrong reader'])->save();
        $this->expectException(DomainException::class);
        app(ReceiptContextService::class)->read($other, $phase, $receipt->id);
    }

    public function test_clear_reduces_active_measurement_and_reload_preserves_history(): void
    {
        [$fixture, , $subject, $receipt] = $this->scenario();
        $phase = $this->move($fixture, $subject);
        $local = app(LocalMeasurementService::class);
        $before = $local->calculate($subject)->closure()->average();
        $service = app(ReceiptContextService::class);
        $load = $service->read($fixture->viewer, $phase, $receipt->id);
        $this->assertSame($before, $local->calculate($subject)->closure()->average());
        $service->clear($fixture->viewer, $phase, $receipt->id);
        $this->assertSame(0, app(ReinforcementService::class)->count($subject));
        $this->assertPublicCount($fixture, $subject, 0);
        $this->assertEquals($before, $local->calculate($subject)->closure()->average());
        $this->assertNotNull($load->fresh()->cleared_at);
        $this->assertSame($receipt->id, $receipt->fresh()->id);
        $service->clear($fixture->viewer, $phase, $receipt->id);
        $this->assertSame(2, DB::table('receipt_context_events')->count());
        $reloaded = $service->read($fixture->viewer, $phase, $receipt->id);
        $this->assertSame($load->id, $reloaded->id);
        $this->assertSame(1, app(ReinforcementService::class)->count($subject));
        $this->assertSame(['loaded', 'cleared', 'loaded'], DB::table('receipt_context_events')->orderBy('id')->pluck('action')->all());
    }

    public function test_verified_hyperlink_receipts_require_load_and_equivalent_evidence_cannot_stack(): void
    {
        [$fixture, , $subject] = $this->scenario();
        $phase = $this->move($fixture, $subject);
        $executions = app(InstrumentExecutionService::class);
        $receipts = [];
        foreach (['https://example.test/page?a=1', 'http://example.test/page#section'] as $url) {
            $execution = $executions->start($phase, $subject, 'hyperlink', $url);
            $receipts[] = $executions->complete($execution, $subject, verification: new VerifiedHyperlink(
                'example.test/page', hash('sha256', 'verified body'), CarbonImmutable::now(), contentExcerpt: 'verified body',
            ));
        }
        $this->assertSame($receipts[0]->signature, $receipts[1]->signature);
        $this->assertSame(0, app(ReinforcementService::class)->count($subject));
        $service = app(ReceiptContextService::class);
        foreach ($receipts as $receipt) {
            $service->read($fixture->viewer, $phase, $receipt->id);
        }
        $this->assertSame(1, app(ReinforcementService::class)->count($subject));
        $this->assertSame(2, ParticipantReceiptContext::active()->count());
        $this->publishPotentialFixture($fixture->viewer);
        $packet = app(ParticipationService::class)->stateFor($fixture->viewer)['plan'];
        $this->assertArrayNotHasKey('evidence', collect($packet['receipts'])->firstWhere('id', $receipts[0]->id));
        $service->clear($fixture->viewer, $phase, $receipts[0]->id);
        $this->assertSame(1, app(ReinforcementService::class)->count($subject));
        $service->clear($fixture->viewer, $phase, $receipts[1]->id);
        $this->assertSame(0, app(ReinforcementService::class)->count($subject));
    }

    public function test_loaded_cite_content_blocks_equivalent_new_citations_and_clear_restores_readability(): void
    {
        [$fixture, $reference, $subject, $first] = $this->scenario();
        $phase = $this->move($fixture, $subject);
        $service = app(ReceiptContextService::class);
        $service->read($fixture->viewer, $phase, $first->id);
        // Legacy duplicate history still obeys load uniqueness; new dispatches reject it.
        $execution = app(InstrumentExecutionService::class)->start($phase, $subject, 'citation', $reference->id);
        $duplicate = app(InstrumentExecutionService::class)->complete($execution, $subject);
        $this->assertSame('already-in-context', $service->forPhase($phase)->firstWhere('id', $duplicate->id)['status']);
        $this->assertSame(1, ParticipantReceiptContext::count());
        $service->clear($fixture->viewer, $phase, $first->id);
        $this->assertSame('available', $service->forPhase($phase)->firstWhere('id', $duplicate->id)['status']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot discard persistent Receipt context history');
        (require database_path('migrations/2026_09_14_000000_create_participant_receipt_contexts.php'))->down();
    }

    public function test_loaded_citation_knowledge_survives_outside_the_hydrated_causal_lineage(): void
    {
        [$fixture, $reference, $subject, $first] = $this->scenario();
        $phase = $this->move($fixture, $subject);
        $service = app(ReceiptContextService::class);
        $service->read($fixture->viewer, $phase, $first->id);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now()])->save();
        $horizon = $this->laterHorizonFixture($phase->cycle->contextHorizon);
        $next = $this->newPhase($fixture->viewer, $horizon->opened_by_proposition_id, $horizon);
        $next->forceFill(['kind' => PhaseKind::Distinguish, 'write_register' => $horizon->domain->registerFor(PhaseKind::Distinguish)])->save();
        $newSubject = app(ContributionDispatcher::class)->dispatch($fixture->viewer, $next,
            ['action' => 'proposition.variant', 'statement' => 'A new lineage']);
        $duplicate = app(OperationalInstrumentService::class)->execute($next, $newSubject,
            ['kind' => 'citation', 'reference' => $reference->id]);
        $context = new ParticipationContext($next->fresh());
        $this->assertFalse($context->propositions->has($subject->id));
        $this->assertFalse($context->propositions->has($reference->id));
        $this->assertSame('already-in-context', $service->forPhase($next, $context)->firstWhere('id', $duplicate->id)['status']);
        $this->assertNotContains($first->id, $service->forPhase($next, $context)->pluck('id'));
        // Clearing remembered evidence changes availability without importing old history.
        $service->clear($fixture->viewer, $next, $first->id);
        $this->assertSame('available', $service->forPhase($next)->firstWhere('id', $duplicate->id)['status']);
    }

    public function test_loading_and_clearing_evidence_preserve_attestation_value_and_history(): void
    {
        [$fixture, , $subject] = $this->scenario();
        $phase = $this->move($fixture, $subject);
        $negative = new Attestation;
        $negativeAuthor = (new Participant)->forceFill(['substrate_id' => $fixture->substrate->id, 'callsign' => 'negative-author']);
        $negativeAuthor->save();
        $negativePhase = $this->newPhase($negativeAuthor, $subject->id);
        $negative->forceFill(['phase_id' => $negativePhase->id, 'proposition_id' => $subject->id, 'direction' => 'refute'])->save();
        app(LedgerService::class)->record($negative);
        $evidence = app(AttestationEvidenceService::class);
        $this->assertSame(0.0, $evidence->valueFor(collect([$subject])));
        $receipt = $this->loadedReceiptFixture($subject, $negative);
        $this->assertSame(0.0, $evidence->valueFor(collect([$subject])));
        $reader = $receipt->instrumentExecution->phase->cycle->participant;
        $readerPhase = $this->newPhase($reader, $subject->id);
        app(ReceiptContextService::class)->clear($reader, $readerPhase, $receipt->id);
        $this->assertSame(0.0, $evidence->valueFor(collect([$subject])));
        $this->assertSame(ObservableDirection::Positive, $evidence->directionFor($subject));
        $context = new ParticipationContext($phase);
        $this->assertSame(0.0, $evidence->valueFor(collect([$subject]), $context));
        $this->assertSame(ObservableDirection::Positive, $evidence->directionFor($subject, context: $context));
        $this->assertSame(0, app(ReinforcementService::class)->count($subject, $context));
        $this->assertSame(2, DB::table('substrate_ledger_entries')->where('kind', 'receipt_context')->count());
    }

    private function newPhase(Participant $participant, string $gateId, ?ContextHorizon $horizon = null): Phase
    {
        $gate = Proposition::findOrFail($gateId);
        $horizon ??= $gate->phase->cycle->contextHorizon;
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => $participant->cycles()->count() + 1,
            'context_horizon_id' => $horizon->id,
            'entry_committal_id' => $gate->tier === 'committal' ? $gate->id : $gate->phase->cycle->entry_committal_id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Constrain, 'gate_proposition_id' => $gateId,
            'write_register' => 'canon', 'started_at' => now()])->save();

        return $phase;
    }
}
