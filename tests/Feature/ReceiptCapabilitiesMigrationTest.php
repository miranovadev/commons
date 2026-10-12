<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\ReceiptCapability;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\LedgerService;
use App\Services\OperationalInstrumentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[\PHPUnit\Framework\Attributes\Group('component-contract')]
class ReceiptCapabilitiesMigrationTest extends TestCase
{
    use \Tests\Fixtures\IsolatesClosureOutput;

    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    public function test_upgrade_preserves_legacy_observations_without_backfilling_loads(): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'capability-upgrade', 'title' => 'Capability upgrade'])->save();
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
        $original = $receipt->fresh()->only(['metadata', 'signature', 'reference', 'instrument_execution_id']);
        $migration = require database_path('migrations/2026_09_14_120000_add_receipt_capabilities_and_replay.php');
        $migration->down();
        $migration->up();
        $legacy = $receipt->fresh();
        $this->assertSame($original, $legacy->only(array_keys($original)));
        $this->assertNull($legacy->capabilities);
        $this->assertTrue($legacy->supports(ReceiptCapability::Readable));
        $this->assertTrue($legacy->supports(ReceiptCapability::Replayable));
        $this->assertSame(0, ParticipantReceiptContext::count());
        config(['services.tavily.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['api.tavily.com/search' => Http::response(['results' => []])]);
        DB::beginTransaction();
        try {
            // The widened enum works with existing rows/FKs. Roll this observation
            // back so test teardown can exercise the legitimate legacy rollback.
            $search = app(OperationalInstrumentService::class)->execute($phase, $subject,
                ['kind' => 'web_search', 'query' => 'upgrade']);
            $this->assertNotNull($search->signature);
        } finally {
            DB::rollBack();
        }
    }
}
