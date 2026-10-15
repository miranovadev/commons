<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionOptionService;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use App\Services\ReceiptReplayService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class TragedyWarrantSeparationTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public static function writingPositions(): array
    {
        return [[PhaseKind::Distinguish], [PhaseKind::Persist], [PhaseKind::Disperse]];
    }

    #[DataProvider('writingPositions')]
    public function test_only_tragedy_is_visible_but_neither_shared_nor_instrument_evidence(PhaseKind $kind): void
    {
        $gate = $kind === PhaseKind::Distinguish ? $this->origin
            : $this->node($this->phase($this->origin, PhaseKind::from($kind->value - 1)), $this->origin);
        $phase = $this->phase($gate, $kind);
        $write = $this->node($phase, $gate);
        $tragedy = $this->tragedyBesideFixture($write);
        $receipt = Receipt::findOrFail($tragedy->receipt_id);
        $service = app(HorizonReceiptService::class);
        $this->assertFalse($receipt->observationComplete());
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertTrue($service->ordinaryAvailable($phase, $context)->isEmpty());
            $this->assertSame($receipt->id, $service->tragedyWarrant($phase, $context)->id);
            $this->assertTrue(app(PropositionStateService::class)->receipts($write, $context)->isEmpty());
            $this->assertContains($write->id, app(PropositionOptionService::class)->instrumentOptionsFor($phase, $context)->pluck('id'));
            $this->assertNotContains($write->id, app(PropositionOptionService::class)->attestationOptionsFor($phase, $context)->pluck('id'));
        }
        $this->assertEmpty($service->instrumentedIds($phase, collect([$write->id])));
        $item = app(ReceiptContextService::class)->forPhase($phase)->firstWhere('id', $receipt->id);
        $this->assertSame('homologous_boundary_escape', $item['purpose']);
        $this->assertArrayNotHasKey('evidence', $item);
        $this->assertNull($item['signature']);
        $this->assertFalse($item['loaded']);
        $this->assertFalse($item['active']);
        $this->assertFalse($item['can_replay']);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $this->assertStringContainsString($receipt->id, json_encode($plan['schema']));
        $this->assertNotContains($receipt->id, app(ReceiptContextService::class)->gateOptions($phase)->pluck('id'));
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($phase->cycle->participant, $phase, ['load' => [$receipt->id]]));
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertTrue($service->ordinaryAvailable($phase, $context)->isEmpty());
            $this->assertContains($write->id, app(PropositionOptionService::class)->instrumentOptionsFor($phase, $context)->pluck('id'));
            $this->assertNotContains($write->id, app(PropositionOptionService::class)->attestationOptionsFor($phase, $context)->pluck('id'));
        }
        $this->assertEmpty($service->instrumentedIds($phase, collect([$write->id])));
        $instruments = app(InstrumentExecutionService::class);
        $ordinary = $instruments->complete($instruments->start($phase, $write, 'citation', $this->origin->id), $write);
        $this->assertSame([$ordinary->id], $service->ordinaryAvailable($phase)->pluck('id')->all());
        $this->assertContains($write->id, app(PropositionOptionService::class)->attestationOptionsFor($phase)->pluck('id'));
    }

    public function test_tragedy_does_not_suppress_instrument_when_the_only_ordinary_receipt_was_consumed(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $write = $this->node($phase, $this->origin);
        $this->tragedyBesideFixture($write);
        $this->receiptFixture($write);
        $ordinary = app(HorizonReceiptService::class)->ordinaryAvailable($phase)->sole();
        $source = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $consumer = $this->phase($source, PhaseKind::Constrain);
        $target = $this->node($consumer, $source);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($consumer, $source, $target, $ordinary->id));
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($phase)->isEmpty());
        $this->assertContains($write->id, app(HorizonReceiptService::class)->instrumentedIds($phase, collect([$write->id])));
        $this->assertContains($write->id, app(PropositionOptionService::class)->instrumentOptionsFor($phase)->pluck('id'));
    }

    public function test_non_bias_schema_and_dispatcher_reject_tragedy_even_with_ordinary_evidence_available(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $tragedy = $this->tragedyBesideFixture($source);
        $this->receiptFixture($source);
        $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $phase = $this->phase($source, PhaseKind::Constrain);
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($phase->cycle->participant, $phase, ['load' => [$tragedy->receipt_id]]));
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $input = ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $tragedy->receipt_id];
        $this->assertNotContains($tragedy->receipt_id, $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum']);
        try {
            app(ContributionService::class)->validateSchema($plan['schema'], $input);
            $this->fail('Non-Bias schema must reject a Tragedy warrant.');
        } catch (\InvalidArgumentException) {
            $before = Proposition::count();
        }
        try {
            app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase, $input);
            $this->fail('Non-Bias dispatcher must reject a Tragedy warrant.');
        } catch (DomainException) {
            $this->assertSame($before, Proposition::count());
            $this->assertDatabaseCount('receipt_consumptions', 0);
        }
    }

    public static function loadOperations(): array
    {
        return [['load'], ['unload']];
    }

    #[DataProvider('loadOperations')]
    public function test_warrant_memory_options_do_not_grant_evidence_read_or_replay(string $operation): void
    {
        $gate = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $tragedy = $this->tragedyBesideFixture($gate);
        $phase = $this->phase($gate, PhaseKind::Constrain);
        $this->receiptFixture($gate);
        $actor = $phase->cycle->participant;
        $plan = app(InputPlanService::class)->forPhase($actor, $phase);
        foreach ($plan['schema']['$defs']['actions'] as $action) {
            if (($action['properties']['action']['const'] ?? null) !== 'participant.rest') {
                $this->assertContains($tragedy->receipt_id, $action['properties']['receipt_options']['properties'][$operation]['items']['enum']);
            }
        }
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($actor, $phase, [$operation => [$tragedy->receipt_id]]));
        $expected = $operation === 'load' ? 1 : 0;
        $this->assertDatabaseCount('participant_receipt_contexts', $expected);
        try {
            app(ReceiptContextService::class)->read($actor, $phase, $tragedy->receipt_id);
            $this->fail('Legacy read API cannot create a warrant load.');
        } catch (DomainException) {
            $this->assertDatabaseCount('participant_receipt_contexts', $expected);
        }
        $this->assertNull(Receipt::findOrFail($tragedy->receipt_id)->replayInvocation());
        $this->expectException(DomainException::class);
        app(ReceiptReplayService::class)->replay($actor, $phase, $tragedy->receipt_id);
    }

    public static function archivedSubjects(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('archivedSubjects')]
    public function test_archived_load_remains_auditable_without_becoming_active_evidence(bool $foreign): void
    {
        $node = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $terminal = $node;
        if ($foreign) {
            $entry = $this->commitFrom($node, boundary: true);
            $terminal = $this->node($this->phase($entry, PhaseKind::Persist), $entry);
        }
        $tragedy = $this->boundaryTragedyFixture($terminal);
        $phase = $this->phase($node, PhaseKind::Constrain);
        $load = (new ParticipantReceiptContext)->forceFill(['participant_id' => $phase->cycle->participant_id,
            'receipt_id' => $tragedy->receipt_id,
            'proposition_id' => $node->id, 'evidence_signature' => 'historical-tragedy', 'loaded_at' => now()]);
        $load->save();
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertSame(0, app(ReinforcementService::class)->count($node, $context));
        }
        $item = app(ReceiptContextService::class)->forPhase($phase)->firstWhere('id', $tragedy->receipt_id);
        $this->assertTrue($item['loaded']);
        $this->assertTrue($item['memory_active']);
        $this->assertFalse($item['active']);
        $this->assertNull($item['signature']);
        $this->assertDatabaseHas('participant_receipt_contexts', ['id' => $load->id, 'cleared_at' => null]);
    }
}
