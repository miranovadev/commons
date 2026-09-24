<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\ParticipantReceiptContext;
use App\Models\Receipt;
use App\Services\HorizonReceiptService;
use App\Services\Observables\ObservableService;
use App\Services\Observables\ReinforcementService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptLayout;
use App\Services\Participation\ParticipationContext;
use App\Services\ReceiptContextService;
use App\Services\TragedyMemoryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class TragedyMemoryTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    private function memoryFixture(): array
    {
        $terminal = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $tragedy = $this->boundaryTragedyFixture($terminal);
        $phase = $this->phase($terminal, PhaseKind::Persist);

        return [$terminal, $tragedy, $phase, Receipt::findOrFail($tragedy->receipt_id)];
    }

    private function desired($phase, $receipt, string $operation = 'load'): void
    {
        DB::transaction(fn () => app(ReceiptContextService::class)->desired(
            $phase->cycle->participant, $phase, [$operation => [$receipt->id]],
        ));
    }

    public function test_load_is_participant_specific_memory_and_unload_preserves_history(): void
    {
        [$terminal, $tragedy, $phase, $receipt] = $this->memoryFixture();
        $service = app(ReceiptContextService::class);
        $before = $service->forPhase($phase)->firstWhere('id', $receipt->id);
        $this->assertFalse($before['loaded']);
        $this->assertArrayNotHasKey('tragedy_memory', $before);
        $this->desired($phase, $receipt);
        $load = ParticipantReceiptContext::sole();
        $this->assertNull($load->evidence_signature);
        $this->assertSame($terminal->id, $load->proposition_id);
        $item = $service->forPhase($phase)->firstWhere('id', $receipt->id);
        $this->assertTrue($item['memory_active']);
        $this->assertFalse($item['active']);
        $this->assertNull($item['signature']);
        $this->assertArrayNotHasKey('evidence', $item);
        $this->assertSame([$this->origin->id], array_column($item['tragedy_memory']['committed_chain'], 'proposition_id'));
        $this->assertSame($terminal->id, $item['tragedy_memory']['catastrophe']['proposition_id']);
        $other = $this->phase($terminal, PhaseKind::Persist);
        $this->assertFalse($service->forPhase($other)->firstWhere('id', $receipt->id)['loaded']);
        $this->desired($phase, $receipt); // Idempotent, no duplicate event.
        $this->assertDatabaseCount('receipt_context_events', 1);
        $this->desired($phase, $receipt, 'unload');
        $item = $service->forPhase($phase)->firstWhere('id', $receipt->id);
        $this->assertFalse($item['memory_active']);
        $this->assertArrayNotHasKey('tragedy_memory', $item);
        $this->assertDatabaseCount('receipt_context_events', 2);
        $this->assertDatabaseHas('tragedies', ['id' => $tragedy->id]);
        $this->assertDatabaseCount('receipt_consumptions', 0);
        $this->assertSame($receipt->id, app(HorizonReceiptService::class)->tragedyWarrant($phase)->id);
    }

    public function test_memory_never_changes_database_or_context_backed_field_values(): void
    {
        [$terminal, , $phase, $receipt] = $this->memoryFixture();
        $calculate = fn ($context) => app(ObservableService::class)->calculate($terminal->fresh(), $context);
        $direct = $calculate(null);
        $hydrated = $calculate(new ParticipationContext($phase));
        $stored = DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson();
        $potential = DB::table('potential_region_states')->get()->toJson();
        $version = $this->substrate->fresh()->field_version;
        $this->desired($phase, $receipt);
        $this->assertSame($version, $this->substrate->fresh()->field_version);
        $this->assertSame($stored, DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson());
        $this->assertSame($potential, DB::table('potential_region_states')->get()->toJson());
        $this->assertEquals($direct, $calculate(null));
        $this->assertEquals($hydrated, $calculate(new ParticipationContext($phase)));
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertSame(0, app(ReinforcementService::class)->count($terminal, $context));
            $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context)->isEmpty());
        }
        $this->desired($phase, $receipt, 'unload');
        $this->assertSame($version, $this->substrate->fresh()->field_version);
    }

    public function test_historical_boundary_is_not_replaced_by_current_measurements(): void
    {
        [$terminal, , $phase, $receipt] = $this->memoryFixture();
        $this->measure($terminal, .2);
        $this->desired($phase, $receipt);
        $memory = app(ReceiptContextService::class)->forPhase($phase)->firstWhere('id', $receipt->id)['tragedy_memory'];
        $this->assertEquals(1, $memory['catastrophe']['tension']);
        $this->assertEquals(0, $memory['catastrophe']['charge']);
        $this->assertSame(.2, $terminal->fresh()->measurement->correspondence);
    }

    public function test_governance_chain_ignores_foreign_causal_parent_and_siblings(): void
    {
        $a = $this->commitFrom($this->origin);
        $owner = $this->commitFrom($a);
        $sibling = $this->commitFrom($this->origin);
        $terminal = $this->node($this->phase($owner, PhaseKind::Distinguish), $sibling);
        $tragedy = $this->boundaryTragedyFixture($terminal);
        $receipt = Receipt::findOrFail($tragedy->receipt_id);
        $service = app(TragedyMemoryService::class);
        $ancestor = $this->phase($a);
        $memory = $service->describe($receipt, $ancestor);
        $this->assertSame([$a->id, $owner->id], array_column($memory['committed_chain'], 'proposition_id'));
        $this->assertTrue($memory['starts_at_current_committal']);
        $unrelated = $service->describe($receipt, $this->phase($sibling));
        $this->assertSame([$this->origin->id, $a->id, $owner->id], array_column($unrelated['committed_chain'], 'proposition_id'));
        $this->assertFalse($unrelated['starts_at_current_committal']);
        $this->assertFalse($unrelated['earlier_history_omitted']);
        $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $sibling->id, 'target_proposition_id' => $terminal->id]);
        $descendant = $this->commitFrom($owner);
        $backward = $service->describe($receipt, $this->phase($descendant));
        $this->assertSame([$descendant->id, $owner->id], array_column($backward['committed_chain'], 'proposition_id'));
        $this->assertTrue($backward['starts_at_current_committal']);
        $this->assertSame('backward', $backward['chain_direction']);
        $this->assertFalse($backward['earlier_history_omitted']);
    }

    public function test_foreign_unloaded_memory_cannot_be_imported_by_id(): void
    {
        [$terminal, , , $receipt] = $this->memoryFixture();
        $foreign = $this->phase($this->commitFrom($terminal));
        $this->assertNotContains($receipt->id, app(ReceiptContextService::class)->loadOptions($foreign)->pluck('id'));
        $this->expectException(DomainException::class);
        $this->desired($foreign, $receipt);
    }

    public function test_exhaustion_removes_warrant_not_loaded_memory(): void
    {
        [, , $phase, $receipt] = $this->memoryFixture();
        $this->desired($phase, $receipt);
        $later = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->boundaryTragedyFixture($later);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertNull(app(HorizonReceiptService::class)->tragedyWarrant($phase));
        $item = app(ReceiptContextService::class)->forPhase($phase)->firstWhere('id', $receipt->id);
        $this->assertTrue($item['memory_active']);
        $this->assertSame(0, app(ReinforcementService::class)->count($later));
    }

    public function test_prompt_keeps_memory_out_of_the_loaded_evidence_prefix(): void
    {
        [, , $phase, $receipt] = $this->memoryFixture();
        $this->desired($phase, $receipt);
        $receipts = app(ParticipantContextSerializer::class)->receipts(app(ReceiptContextService::class)->forPhase($phase)->all());
        $packet = ['plan' => ['receipts' => $receipts]];
        $prompt = app(ParticipantPromptLayout::class)->make($packet);
        $this->assertSame([], json_decode($prompt['evidence'], true)['loaded_receipts']);
        $this->assertTrue(json_decode($prompt['user'], true)['plan']['receipts'][0]['memory_active']);
        $this->assertArrayHasKey('tragedy_memory', json_decode($prompt['user'], true)['plan']['receipts'][0]);
    }

    public function test_failed_transaction_restores_memory_state_and_events(): void
    {
        [, , $phase, $receipt] = $this->memoryFixture();
        try {
            DB::transaction(function () use ($phase, $receipt): void {
                $this->desired($phase, $receipt);
                throw new DomainException('rollback');
            });
        } catch (DomainException) {
            $this->assertDatabaseCount('participant_receipt_contexts', 0);
            $this->assertDatabaseCount('receipt_context_events', 0);
        }
    }

    public function test_memory_migration_refuses_to_discard_null_signature_history(): void
    {
        [, , $phase, $receipt] = $this->memoryFixture();
        $this->desired($phase, $receipt);
        $migration = require database_path('migrations/2026_09_25_000000_allow_non_evidential_receipt_memory.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    #[Group('query')]
    public function test_deep_committed_memory_is_bounded_ordered_and_constant_query_cost(): void
    {
        [, , $phase, $short] = $this->memoryFixture();
        $service = app(TragedyMemoryService::class);
        $read = function ($receipt) use ($service, $phase): array {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $memory = $service->describe($receipt, $phase);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$memory, $queries];
        };
        [, $small] = $read($short);
        $owner = $this->origin;
        $ids = [$owner->id];
        for ($i = 0; $i < TragedyMemoryService::CHAIN_LIMIT + 2; $i++) {
            $owner = $this->commitFrom($owner);
            $ids[] = $owner->id;
        }
        $terminal = $this->node($this->phase($owner, PhaseKind::Distinguish), $owner);
        $tragedy = $this->boundaryTragedyFixture($terminal);
        [$memory, $large] = $read(Receipt::findOrFail($tragedy->receipt_id));
        $this->assertSame($small, $large);
        $this->assertSame(2, $large);
        $this->assertTrue($memory['earlier_history_omitted']);
        $this->assertSame(array_slice($ids, -TragedyMemoryService::CHAIN_LIMIT), array_column($memory['committed_chain'], 'proposition_id'));
    }
}
