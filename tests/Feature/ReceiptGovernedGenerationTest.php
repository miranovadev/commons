<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Connection;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\InputSchemaService;
use App\Services\LedgerService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionOptionService;
use App\Services\ReceiptContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ReceiptGovernedGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): ParticipantContextFixture
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $f->gate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $f->gate->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $f->gate->id])->save();

        return $f;
    }

    private function observer(ParticipantContextFixture $f): Phase
    {
        $participant = (new Participant)->forceFill(['substrate_id' => $f->substrate->id,
            'callsign' => 'observer-'.str()->ulid(), 'current_proposition_id' => $f->gate->id]);
        $participant->save();
        $cycle = $f->phase->cycle->replicate();
        $cycle->participant_id = $participant->id;
        $cycle->save();
        $phase = $f->phase->replicate();
        $phase->cycle_id = $cycle->id;
        $phase->save();

        return $phase->fresh();
    }

    private function observe(ParticipantContextFixture $f, Phase $phase, string $kind = 'citation'): Receipt
    {
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase, [
            'action' => 'instrument.execute', 'proposition_id' => $phase->gate_proposition_id,
            'instrument' => $kind === 'citation' ? ['kind' => 'citation', 'reference' => $f->seed->id] : ['kind' => $kind],
        ]);

        return Receipt::whereHas('evidenceExecution', fn ($q) => $q->where('phase_id', $phase->id))->sole();
    }

    /** Catalogue component: use the existing transactional attachment operation. */
    private function consume(ParticipantContextFixture $f, Receipt $receipt): void
    {
        $consumer = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'consumer-'.str()->ulid()]);
        $consumer->save();
        $source = $f->node($f->gate, 'frontier', position: PhaseKind::Persist, participant: $consumer);
        $target = $f->node($source, 'canon', 'reclassify', position: PhaseKind::Constrain, participant: $consumer);
        $this->assertNotSame($f->viewer->id, $target->phase->cycle->participant_id);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($target->phase, $source, $target, $receipt->id));
    }

    public static function availabilityCases(): array
    {
        return ['neither' => [false, false], 'shared receipt' => [true, false],
            'receipt and used allowance' => [true, true], 'consumed and used allowance' => [false, true]];
    }

    #[DataProvider('availabilityCases')]
    public function test_shared_receipt_and_personal_allowance_are_independent(bool $available, bool $used): void
    {
        $f = $this->fixture();
        if ($available || $used) {
            $receipt = $this->observe($f, $used ? $f->phase : $this->observer($f));
            if (! $available) {
                $this->consume($f, $receipt);
            }
        }
        $policy = app(PhaseGenerationService::class);
        $this->assertSame($available && $used, $policy->epistemicActionAvailable($f->phase, 'proposition.variant'));
        $this->assertSame($available && $used, $policy->epistemicActionAvailable($f->phase, 'proposition.revise'));
        $this->assertSame(! $used, app(PropositionOptionService::class)->instrumentOptionsFor($f->phase)->isNotEmpty());
        $this->assertSame($available, app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->isNotEmpty());
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $state = app(ParticipationService::class)->stateFor($f->viewer);
        $actions = $state['plan']['schema']['$defs']['actions'];
        // A consumed Receipt's structural source may remain a viable route.
        $this->assertSame($available && $used, isset($actions['proposition.variant']));
        $this->assertSame(! $used, isset($actions['instrument.execute']));
        $this->assertSame($state['plan']['schema'], app(ParticipantPromptFactory::class)->make(new Connection, $state)['schema']);
        if ($used) {
            $suppliedTargets = app(InputSchemaService::class)->forParticipant($f->viewer, [], [$f->gate->id],
                instrumentOptionIds: [$f->gate->id]);
            $this->assertArrayNotHasKey('instrument.execute', $suppliedTargets['$defs']['actions']);
        }
    }

    public static function ordinaryKinds(): array
    {
        return ['citation' => ['citation'], 'proposition search' => ['proposition_search']];
    }

    #[DataProvider('ordinaryKinds')]
    public function test_shared_receipt_requires_personal_acquisition_before_generation_without_consumption(string $kind): void
    {
        $f = $this->fixture();
        $observer = $this->observer($f);
        $otherGate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $observer->forceFill(['gate_proposition_id' => $otherGate->id])->save();
        $observer->cycle->participant->forceFill(['current_proposition_id' => $otherGate->id])->save();
        $receipt = $this->observe($f, $observer, $kind);
        $this->assertSame($otherGate->id, $receipt->evidenceExecution->proposition_id);
        $this->assertNotSame($f->gate->id, $receipt->evidenceExecution->proposition_id);
        $this->assertDatabaseMissing('participant_receipt_contexts', ['participant_id' => $f->viewer->id, 'receipt_id' => $receipt->id]);
        $this->assertFalse(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        $this->observe($f, $f->phase);
        $this->assertTrue(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        DB::table('participant_receipt_contexts')->where('receipt_id', $receipt->id)->update(['cleared_at' => now()]);
        $this->assertTrue(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $after = app(ParticipationService::class)->contribute($f->viewer, $f->phase->id,
            ['action' => 'proposition.variant', 'statement' => 'Develop a shared observation']);
        $this->assertSame('constrain', $after['plan']['phase']['kind']);
        $this->assertDatabaseCount('receipt_consumptions', 0);
        $this->assertDatabaseMissing('proposition_receipt_links', ['receipt_id' => $receipt->id]);
        $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $f->gate->id, 'target_proposition_id' => $after['current_proposition_id'], 'type' => 'variant']);
        $this->assertSame($observer->id, $receipt->evidenceExecution->phase_id);
        $this->assertContains($receipt->id, array_column($after['plan']['receipts'], 'id'));
    }

    #[DataProvider('ordinaryKinds')]
    public function test_consumption_invalidates_stale_generation_without_resetting_acquisition(string $kind): void
    {
        $f = $this->fixture();
        $receipt = $this->observe($f, $f->phase, $kind);
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema'];
        $this->assertArrayHasKey('proposition.variant', $schema['$defs']['actions']);
        $this->consume($f, $receipt);
        $this->assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase));
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
        $before = Proposition::count();
        $input = ['action' => 'proposition.variant', 'statement' => 'Stale proposal'];
        try {
            app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $input);
            $this->fail('Direct dispatch accepted stale evidence availability.');
        } catch (ConflictHttpException) {
            $this->assertSame($before, Proposition::count());
        }
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $this->expectException(ConflictHttpException::class);
        app(ParticipationService::class)->contribute($f->viewer, $f->phase->id, $input, $schema);
    }

    public function test_prepared_instrument_and_direct_service_recheck_shared_availability(): void
    {
        $f = $this->fixture();
        $input = ['kind' => 'citation', 'reference' => $f->seed->id];
        $service = app(OperationalInstrumentService::class);
        $observed = $service->observeFor($f->phase, $f->gate, $input);
        $this->observe($f, $this->observer($f));
        $count = Receipt::count();
        try {
            $service->execute($f->phase, $f->gate, $input, observed: $observed);
            $this->fail('Prepared Instrument ignored a newly shared Receipt.');
        } catch (ConflictHttpException) {
            $this->assertSame($count, Receipt::count());
        }
        $this->expectException(ConflictHttpException::class);
        $service->observeFor($f->phase, $f->gate, $input);
    }

    public function test_available_evidence_does_not_override_viable_routes_or_revision_ownership(): void
    {
        $f = $this->fixture();
        $head = $f->node($f->gate, 'frontier', position: PhaseKind::Persist);
        $vote = (new Attestation)->forceFill(['phase_id' => $head->phase_id, 'proposition_id' => $head->id, 'direction' => 'refute']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->assertEmpty(app(PropositionOptionService::class)->revisionOptionsFor($f->phase, $f->viewer));
        $observer = $this->observer($f);
        app(OperationalInstrumentService::class)->execute($observer, $f->gate, ['kind' => 'proposition_search']);
        $this->assertFalse(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        $this->assertSame([$head->id], app(PropositionOptionService::class)->revisionOptionsFor($f->phase, $f->viewer)->modelKeys());
        $other = $this->observer($f);
        $this->assertEmpty(app(PropositionOptionService::class)->revisionOptionsFor($other, $other->cycle->participant));
        $this->observe($f, $f->phase);
        $count = Receipt::count();
        $revised = app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase,
            ['action' => 'proposition.revise', 'proposition_id' => $head->id, 'statement' => 'Revise after shared observation']);
        $this->assertSame([$head->id], $revised->parents()->pluck('propositions.id')->all());
        $this->assertSame($count, Receipt::count());
        $this->assertDatabaseCount('receipt_consumptions', 0);
    }

    public function test_other_regions_do_not_supply_ordinary_evidence(): void
    {
        $f = $this->fixture();
        $f->growUnrelatedTo(1);
        $this->assertGreaterThan(0, Receipt::count());
        // The growth fixture's first observation belongs to our Region; consume it.
        foreach (app(HorizonReceiptService::class)->ordinaryAvailable($f->phase) as $receipt) {
            $this->consume($f, $receipt);
        }
        $foreign = $f->node(Proposition::findOrFail($f->unrelated[0])->phase->cycle->entryCommittal, 'activity');
        $f->evidence($foreign);
        $this->assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase));
        $this->assertFalse(app(PhaseGenerationService::class)->epistemicActionAvailable($f->phase, 'proposition.variant'));
    }

    public function test_empty_proposition_search_enables_generation_and_consumption_retires_availability(): void
    {
        $f = $this->fixture();
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $service = app(ParticipationService::class);
        $after = $service->contribute($f->viewer, $f->phase->id, ['action' => 'instrument.execute',
            'proposition_id' => $f->gate->id, 'instrument' => ['kind' => 'proposition_search', 'limit' => 3]]);
        $this->assertSame($f->phase->id, $after['plan']['phase']['id']);
        $this->assertArrayHasKey('proposition.variant', $after['plan']['schema']['$defs']['actions']);
        $receipt = Receipt::sole();
        $this->assertSame('proposition_search', $receipt->type);
        $this->assertTrue($receipt->isEvidence());
        $this->assertNull($receipt->consumption);
        $this->assertSame([], $receipt->metadata['proposition_collection']['results']);
        $this->assertSame([$receipt->id], app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->modelKeys());
        $other = $this->observer($f);
        $this->assertFalse(app(PhaseGenerationService::class)->available($other, 'proposition.variant'));
        $context = app(ReceiptContextService::class);
        $context->clear($f->viewer, $f->phase, $receipt->id);
        $this->assertTrue(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        $context->read($other->cycle->participant, $other, $receipt->id);
        $this->assertFalse(app(PhaseGenerationService::class)->available($other, 'proposition.variant'));

        $this->assertTrue(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
        $this->consume($f, $receipt);
        $this->assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($other));
        $this->assertFalse(app(PhaseGenerationService::class)->epistemicActionAvailable($other, 'proposition.variant'));
        $this->assertNotEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($other));
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
    }

    public function test_persist_and_disperse_activate_the_epistemic_policy(): void
    {
        $f = $this->fixture();
        foreach (PhaseKind::cases() as $kind) {
            $f->phase->kind = $kind;
            foreach (['proposition.variant', 'proposition.revise'] as $action) {
                $this->assertSame(! in_array($kind, [PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Disperse], true), app(PhaseGenerationService::class)->epistemicActionAvailable($f->phase, $action));
            }
            foreach (['attestation.create', 'proposition.traverse', 'proposition.reclassify', 'participant.rest'] as $action) {
                $this->assertTrue(app(PhaseGenerationService::class)->epistemicActionAvailable($f->phase, $action));
            }
            $this->assertSame(in_array($kind, [PhaseKind::Persist, PhaseKind::Disperse], true), in_array('preparation.start', config("phases.{$kind->value}.actions"), true));
        }
    }

    public function test_spent_allowance_and_consumed_evidence_preserve_independent_attestation_and_traverse(): void
    {
        $f = $this->fixture();
        $head = $f->node($f->gate, 'frontier', position: PhaseKind::Persist);
        $vote = (new Attestation)->forceFill(['phase_id' => $head->phase_id, 'proposition_id' => $head->id, 'direction' => 'refute']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $receipt = $this->observe($f, $f->phase);
        // An existing Write's own historical evidence remains eligible for
        // judgment after consumption; generic structural parents do not inherit it.
        $this->receiptFixture($head);
        $this->consume($f, Receipt::where('receiptable_id', $head->id)->sole());
        $this->consume($f, $receipt);
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $plan = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        foreach (['instrument.execute', 'proposition.variant', 'proposition.revise'] as $action) {
            $this->assertArrayNotHasKey($action, $plan['schema']['$defs']['actions']);
        }
        foreach (['attestation.create', 'proposition.traverse'] as $action) {
            $this->assertArrayHasKey($action, $plan['schema']['$defs']['actions']);
            $this->assertStringContainsString($head->id, json_encode($plan['schema']['$defs']['actions'][$action]));
        }
        $this->assertFalse($plan['rest_available']);
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase,
            ['action' => 'attestation.create', 'proposition_id' => $head->id, 'direction' => 'support']);
        $this->assertSame('support', $head->effectiveAttestations()->sole()->direction);
        $this->assertDatabaseCount('receipt_consumptions', 2);
    }
}
