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
use App\Services\LedgerService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionOptionService;
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

    private function observe(ParticipantContextFixture $f, Phase $phase): Receipt
    {
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase, [
            'action' => 'instrument.execute', 'proposition_id' => $f->gate->id,
            'instrument' => ['kind' => 'citation', 'reference' => $f->seed->id],
        ]);

        return Receipt::whereHas('instrumentExecution', fn ($q) => $q->where('phase_id', $phase->id))->sole();
    }

    /** Catalogue component: use the existing transactional attachment operation. */
    private function consume(ParticipantContextFixture $f, Receipt $receipt): void
    {
        $source = $f->node($f->gate, 'frontier', position: PhaseKind::Persist);
        $target = $f->node($source, 'canon', 'reclassify', position: PhaseKind::Constrain);
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
        $this->assertSame($available, $policy->epistemicActionAvailable($f->phase, 'proposition.variant'));
        $this->assertSame($available, $policy->epistemicActionAvailable($f->phase, 'proposition.revise'));
        $this->assertSame(! $available && ! $used, app(PropositionOptionService::class)->instrumentOptionsFor($f->phase)->isNotEmpty());
        $this->assertSame($available, app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->isNotEmpty());
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $state = app(ParticipationService::class)->stateFor($f->viewer);
        $actions = $state['plan']['schema']['$defs']['actions'];
        // A consumed Receipt's structural source may remain a viable route.
        $this->assertSame($available, isset($actions['proposition.variant']));
        $this->assertSame(! $available && ! $used, isset($actions['instrument.execute']));
        $this->assertSame($state['plan']['schema'], app(ParticipantPromptFactory::class)->make(new Connection, $state)['schema']);
    }

    public function test_shared_receipt_enables_generation_without_loading_or_consumption(): void
    {
        $f = $this->fixture();
        $observer = $this->observer($f);
        $receipt = $this->observe($f, $observer);
        $this->assertDatabaseMissing('participant_receipt_contexts', ['participant_id' => $f->viewer->id, 'receipt_id' => $receipt->id]);
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
        $this->assertSame($observer->id, $receipt->instrumentExecution->phase_id);
        $this->assertContains($receipt->id, array_column($after['plan']['receipts'], 'id'));
    }

    public function test_consumption_invalidates_stale_generation_and_reopens_acquisition(): void
    {
        $f = $this->fixture();
        $receipt = $this->observe($f, $this->observer($f));
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema'];
        $this->assertArrayHasKey('proposition.variant', $schema['$defs']['actions']);
        $this->consume($f, $receipt);
        $this->assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase));
        $this->assertNotEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
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
        $this->observe($f, $this->observer($f));
        $this->assertFalse(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        $this->assertSame([$head->id], app(PropositionOptionService::class)->revisionOptionsFor($f->phase, $f->viewer)->modelKeys());
        $other = $this->observer($f);
        $this->assertEmpty(app(PropositionOptionService::class)->revisionOptionsFor($other, $other->cycle->participant));
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

    public function test_memory_observation_spends_allowance_but_shared_evidence_can_later_enable_generation(): void
    {
        $f = $this->fixture();
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $service = app(ParticipationService::class);
        $after = $service->contribute($f->viewer, $f->phase->id, ['action' => 'instrument.execute',
            'proposition_id' => $f->gate->id, 'instrument' => ['kind' => 'proposition_search', 'limit' => 3]]);
        $this->assertSame($f->phase->id, $after['plan']['phase']['id']);
        $this->assertSame(['participant.rest'], array_keys($after['plan']['schema']['$defs']['actions']));
        $this->assertSame('proposition_search', Receipt::sole()->type);
        $this->observe($f, $this->observer($f));
        $this->assertTrue(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
    }

    public function test_only_persist_activates_the_epistemic_policy(): void
    {
        $f = $this->fixture();
        foreach (PhaseKind::cases() as $kind) {
            $f->phase->kind = $kind;
            foreach (['proposition.variant', 'proposition.revise'] as $action) {
                $this->assertSame($kind !== PhaseKind::Persist, app(PhaseGenerationService::class)->epistemicActionAvailable($f->phase, $action));
            }
            foreach (['attestation.create', 'proposition.traverse', 'proposition.reclassify', 'participant.rest'] as $action) {
                $this->assertTrue(app(PhaseGenerationService::class)->epistemicActionAvailable($f->phase, $action));
            }
            $this->assertNotContains('preparation.execute', config("phases.{$kind->value}.actions"));
        }
    }
}
