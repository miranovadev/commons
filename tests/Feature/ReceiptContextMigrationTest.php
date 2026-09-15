<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReceiptContextMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_upgrade_preserves_historical_allocations_without_inventing_loads(): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'receipt-upgrade', 'title' => 'Receipt upgrade'])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'Legacy verifier'])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order, 'write_register' => 'canon'])->save();
        $subject = new Proposition;
        $subject->forceFill(['phase_id' => $phase->id, 'register' => 'canon', 'tier' => 'expressive', 'statement' => 'Legacy evidence'])->save();
        app(LedgerService::class)->record($subject);
        $receipt = app(OperationalInstrumentService::class)->execute($phase, $subject,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/legacy']);
        DB::table('reinforcements')->insert([
            'id' => strtolower((string) str()->ulid()), 'proposition_id' => $subject->id,
            'participant_id' => $participant->id, 'receipt_id' => $receipt->id, 'created_at' => now(),
        ]);
        $history = $receipt->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_09_14_000000_create_participant_receipt_contexts.php');
        $indexes = require database_path('migrations/2026_09_15_150000_index_context_horizon_working_set.php');
        $indexes->down();
        $migration->down();
        $migration->up();
        $indexes->up();
        $this->assertSame($history, $receipt->fresh()->getAttributes());
        $this->assertSame(1, DB::table('reinforcements')->count());
        $this->assertSame(0, ParticipantReceiptContext::count());
        $this->assertSame(0, DB::table('receipt_context_events')->count());
        $this->assertSame(0, app(ReinforcementService::class)->count($subject));
    }
}
