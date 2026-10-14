<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\PhaseGenerationService;
use App\Services\PropositionMeasurementService;
use App\Services\TerminalContinuationService;
use App\Services\TragedyService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

/** Lifecycle component coverage with explicit numerical fields; not natural catastrophe trajectories. */
class DirectTragedyResolutionTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_authorised_first_birth_can_exhaust_immediately(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->resolveExpressiveFieldFixture(1);
        $target = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Direct boundary component']);
        $this->assertDatabaseCount('tragedies', 1);
        $this->assertDatabaseCount('terminal_encounters', 1);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertDatabaseHas('terminal_encounters', ['proposition_id' => $target->id,
            'phase_id' => $phase->id, 'participant_id' => $phase->cycle->participant_id, 'action' => 'proposition.variant']);
        $this->assertTrue(app(ExhaustionService::class)->unavailable($this->origin));
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
    }

    public static function encounters(): array
    {
        return [['attestation.create'], ['instrument.execute'], ['proposition.traverse']];
    }

    #[DataProvider('encounters')]
    public function test_existing_zero_target_uses_encounter_actor_not_birth_actor(string $action): void
    {
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->measure($target, 1);
        $this->assertDatabaseCount('tragedies', 0);
        // Isolate the post-authorisation observer: dispatcher authority is tested separately.
        DB::transaction(fn () => app(TragedyService::class)->record($target->fresh(), $phase, $action));
        $this->assertDatabaseHas('terminal_encounters', ['proposition_id' => $target->id,
            'participant_id' => $phase->cycle->participant_id, 'phase_id' => $phase->id, 'action' => $action]);
        $this->assertNotSame($target->phase_id, Tragedy::sole()->phase_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }

    public function test_result_above_zero_and_passive_measurements_do_not_create_terminal_evidence(): void
    {
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->measure($target, 1);
        $this->phase($this->origin, PhaseKind::Distinguish); // Genuine Cycle entry, no direct encounter.
        $this->assertDatabaseCount('terminal_encounters', 0);
        $this->measure($target, .5); // Isolate the resulting Attestation field, not its previous value.
        DB::transaction(fn () => app(TragedyService::class)->record($target->fresh(), $target->phase, 'attestation.create'));
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_sibling_survives_resolution_and_later_terminal_encounter_exhausts_once(): void
    {
        $first = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $sibling = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($first);
        $tragedy = Tragedy::sole()->getAttributes();
        $receipt = Receipt::where('type', 'tragedy')->sole()->getAttributes();
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertFalse(app(TerminalContinuationService::class)->closed($sibling));
        $this->assertFalse(app(PhaseGenerationService::class)->available($sibling->phase, 'proposition.variant'));
        $this->assertFalse(app(PhaseGenerationService::class)->available($sibling->phase, 'proposition.revise'));
        $this->encounter($sibling);
        $this->assertDatabaseCount('terminal_encounters', 2);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertSame($tragedy, Tragedy::sole()->getAttributes());
        $this->assertSame($receipt, Receipt::where('type', 'tragedy')->sole()->getAttributes());
    }

    public function test_terminal_state_survives_positive_charge_and_repeat_observation(): void
    {
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($target);
        $before = DB::table('terminal_encounters')->first();
        $this->measure($target, .2);
        $this->assertTrue(app(TerminalContinuationService::class)->closed($target));
        $this->encounter($target);
        $this->assertEquals($before, DB::table('terminal_encounters')->first());
        $this->assertDatabaseCount('terminal_encounters', 1);
        $this->assertDatabaseCount('tragedies', 1);
    }

    public function test_stale_variant_is_rejected_before_any_write(): void
    {
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $sibling = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($target);
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($sibling->phase->cycle->participant, $sibling->phase,
                ['action' => 'proposition.variant', 'statement' => 'Stale client']);
            $this->fail('Resolution must reject a stale Variant.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
        }
    }

    public function test_terminal_publication_rolls_back_as_one_event(): void
    {
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        DB::beginTransaction();
        $this->encounter($target);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        DB::rollBack();
        foreach (['tragedies', 'terminal_encounters', 'commit_region_exhaustions', 'exhausted_region_memberships'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, Receipt::where('type', 'tragedy')->count());
    }

    public static function immutableTables(): array
    {
        return [['terminal_encounters'], ['tragedies'], ['commit_region_exhaustions']];
    }

    #[DataProvider('immutableTables')]
    public function test_database_rejects_rewriting_event_authority(string $table): void
    {
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($target);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table($table)->update(['observed_field_version' => 999]));
    }

    private function encounter(Proposition $target): void
    {
        $this->measure($target, 1);
        DB::transaction(fn () => app(TragedyService::class)->record($target->fresh(), $target->phase, 'instrument.execute'));
    }
}
