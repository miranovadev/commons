<?php

namespace Tests\Feature;

use App\Domain\Observables\ObservableValues;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\ExplorationEpochService;
use App\Services\HorizonReceiptService;
use App\Services\PotentialChargeService;
use App\Services\ProjectionService;
use App\Services\TragedyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExhaustionFixture;
use Tests\Fixtures\NaturalBoundaryHistory;
use Tests\TestCase;

class ChargeBoundaryTragedyTest extends TestCase
{
    use ExhaustionFixture, NaturalBoundaryHistory, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public static function fields(): array
    {
        return ['positive room' => [.8, false], 'negative room' => [-.8, false],
            'neutral' => [0.0, false], 'positive boundary' => [1.0, true],
            'negative boundary' => [-1.0, true], 'rounded positive boundary' => [.99999999, true],
            'rounded negative boundary' => [-.99999999, true], 'inside precision' => [.9999998, false]];
    }

    #[DataProvider('fields')]
    public function test_fresh_expression_uses_the_canonical_resolved_charge(float $value, bool $tragic): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->resolveExpressiveFieldFixture($value);
        $target = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Expression may reach the field boundary']);
        $field = app(ProjectionService::class)->field(new ObservableValues($value, $value, $value));
        $this->assertSame($tragic ? 1 : 0, Tragedy::count());
        $this->assertSame($tragic ? 1 : 0, Receipt::where('type', 'tragedy')->count());
        $this->assertDatabaseHas('propositions', ['id' => $target->id]);
        $this->assertNull($phase->cycle->fresh()->rested_at);
        $this->assertDatabaseCount('commit_region_exhaustions', $tragic ? 1 : 0);
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
        if ($tragic) {
            $tragedy = Tragedy::sole();
            $receipt = Receipt::findOrFail($tragedy->receipt_id);
            $this->assertSame($this->origin->id, $tragedy->governing_committal_id);
            $this->assertSame($target->id, $tragedy->terminal_proposition_id);
            $this->assertNotSame($phase->gate_proposition_id, $tragedy->terminal_proposition_id);
            $this->assertSame($phase->id, $tragedy->phase_id);
            $this->assertSame($phase->cycle->context_horizon_id, $tragedy->context_horizon_id);
            $this->assertSame($target->id, $receipt->receiptable_id);
            $this->assertEquals($field, $receipt->metadata['field']);
            $this->assertSame($target->id, $receipt->metadata['terminal_proposition_id']);
            $this->assertSame($this->origin->id, $receipt->metadata['governing_committal_id']);
            $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
        } else {
            $this->assertGreaterThan(0, $field['charge']);
        }
    }

    public function test_ordinary_unmocked_observables_can_reach_the_boundary(): void
    {
        [$this->substrate, $this->origin, , $seed] = $this->naturalBoundaryHistory();
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $target = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'An untested expressive continuation']);
        $measurement = $target->measurement;
        $field = app(ProjectionService::class)->field(new ObservableValues(
            $measurement->correspondence, $measurement->chirality, $measurement->closure));
        $this->assertSame(0.0, $field['charge']);
        $this->assertSame($target->id, Tragedy::sole()->terminal_proposition_id);
    }

    public function test_successful_revision_can_create_a_boundary_tragedy(): void
    {
        $gate = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $prior = $this->phase($gate, PhaseKind::Persist);
        $head = $this->node($prior, $gate);
        $this->explore($head); // Its author has an effective prior-Cycle Refute.
        $prior->forceFill(['completed_at' => now()])->save();
        $prior->cycle->forceFill(['completed_at' => now()])->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $prior->cycle->participant_id,
            'sequence' => 2, 'entry_committal_id' => $this->origin->id,
            'context_horizon_id' => $prior->cycle->context_horizon_id]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id,
            'kind' => PhaseKind::Persist, 'gate_proposition_id' => $gate->id,
            'write_register' => $prior->write_register, 'started_at' => now()]);
        $phase->save();
        $this->resolveExpressiveFieldFixture(-1);
        $target = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.revise', 'proposition_id' => $head->id, 'statement' => 'Boundary revision']);
        $tragedy = Tragedy::sole();
        $this->assertSame($target->id, $tragedy->terminal_proposition_id);
        $this->assertSame($this->origin->id, $tragedy->governing_committal_id);
    }

    public function test_zero_potential_allows_further_spent_evidenced_variants_with_positive_charge(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->resolveExpressiveFieldFixture(.8);
        $first = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'No demonstrated tension reduction']);
        $this->explore($first);
        DB::table('continuation_failures')->insert(['proposition_id' => $first->id,
            'context_horizon_id' => $phase->cycle->context_horizon_id, 'structural_position' => $phase->kind->value,
            'exploration_epoch' => app(ExplorationEpochService::class)->forPhase($phase),
            'phase_id' => $phase->id, 'failed_at' => now()]);
        $this->publishPotentialFixture($this->substrate);
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($phase));
        $second = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Still structurally possible']);
        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
    }

    public function test_zero_potential_rest_does_not_create_tragedy(): void
    {
        [, $terminal, , , $head] = $this->exhaustedBranch();
        foreach (Proposition::where('tier', 'expressive')->get() as $node) {
            $this->measure($node, .8); // Commit .5 -> expressive .8: increasing Tension, Charge .2.
        }
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($terminal));
        $this->rest($terminal);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_increasing_tension_from_point_four_to_point_eight_is_not_tragedy(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $seed = $this->commitFrom($root, .4);
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $this->resolveExpressiveFieldFixture(.8);
        $target = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Increasing tension remains expressive']);
        $measurement = $target->measurement;
        $field = app(ProjectionService::class)->field(new ObservableValues(
            $measurement->correspondence, $measurement->chirality, $measurement->closure));
        $this->assertSame(.4, $seed->commitmentSnapshot->tension);
        $this->assertSame(.8, $field['tension']);
        $this->assertSame(.2, $field['charge']);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_descendant_terminal_event_recursively_closes_resolving_parent(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $seed = $this->commitFrom($root, .4);
        $this->resolveExpressiveFieldFixture(1);
        foreach ([$this->origin, $seed] as $region) {
            $gate = $this->node($this->phase($region, PhaseKind::Constrain), $region);
            $phase = $this->phase($gate, PhaseKind::Disperse);
            $this->receiptFixture($gate);
            $target = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
                ['action' => 'proposition.variant', 'statement' => 'A regional boundary']);
            $this->assertSame($target->id, Tragedy::where('governing_committal_id', $region->id)->sole()->terminal_proposition_id);
        }
        $this->assertSame(1, Tragedy::distinct()->count('context_horizon_id'));
        $this->assertDatabaseCount('tragedies', 2);
        $this->assertSame(2, Receipt::where('type', 'tragedy')->count());
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $this->origin->id,
            'descendant_exhaustion_id' => $seed->id]);
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $seed->id]);
        $this->assertTrue(app(ExhaustionService::class)->unavailable($seed));

    }

    public function test_later_direct_encounter_retains_first_identity_and_exhausts(): void
    {
        $firstPhase = $this->phase($this->origin, PhaseKind::Distinguish);
        $read = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $secondPhase = $this->phase($read, PhaseKind::Disperse);
        $this->resolveExpressiveFieldFixture(1);
        $first = app(ContributionDispatcher::class)->dispatch($firstPhase->cycle->participant, $firstPhase,
            ['action' => 'proposition.variant', 'statement' => 'First boundary contact']);
        $tragedy = Tragedy::sole()->getAttributes();
        $receipt = Receipt::where('type', 'tragedy')->sole()->getAttributes();
        // Observer component: Resolution cannot author a second Variant.
        $second = $read;
        $this->measure($second, 1);
        DB::transaction(fn () => app(TragedyService::class)->record($second, $secondPhase, 'instrument.execute'));
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($tragedy, Tragedy::sole()->getAttributes());
        $this->assertSame($receipt, Receipt::where('type', 'tragedy')->sole()->getAttributes());
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertDatabaseHas('commit_region_exhaustions', ['terminal_proposition_id' => $second->id,
            'tragedy_id' => $tragedy['id'], 'governing_committal_id' => $this->origin->id]);
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
        $migration = require database_path('migrations/2026_09_24_000000_record_exhaustion_boundary_field.php');
        try {
            $migration->down();
            $this->fail('Cannot discard a fresh boundary witness');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Cannot discard', $exception->getMessage());
        }
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }

    public function test_later_encounter_rollback_restores_resolution_and_warrant(): void
    {
        $firstPhase = $this->phase($this->origin, PhaseKind::Distinguish);
        $read = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $phase = $this->phase($read, PhaseKind::Disperse);
        $this->resolveExpressiveFieldFixture(-1);
        app(ContributionDispatcher::class)->dispatch($firstPhase->cycle->participant, $firstPhase,
            ['action' => 'proposition.variant', 'statement' => 'First catastrophe']);
        $tragedy = Tragedy::sole()->getAttributes();
        $before = Proposition::count();
        try {
            DB::transaction(function () use ($phase): void {
                $this->measure($phase->gate, -1);
                app(TragedyService::class)->record($phase->gate, $phase, 'instrument.execute');
                $this->assertDatabaseCount('commit_region_exhaustions', 1);
                throw new \DomainException('rollback second');
            });
        } catch (\DomainException $exception) {
            $this->assertSame('rollback second', $exception->getMessage());
        }
        $this->assertSame($before, Proposition::count());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseCount('exhausted_region_memberships', 0);
        $this->assertSame($tragedy, Tragedy::sole()->getAttributes());
        $this->assertNotNull(app(HorizonReceiptService::class)->tragedyWarrant($phase));
    }

    public function test_historical_boundary_recalculation_and_repeat_observation_do_not_exhaust(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->resolveExpressiveFieldFixture(1);
        $first = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Only one fresh event']);
        DB::transaction(fn () => app(TragedyService::class)->record($first, $first->phase, 'proposition.variant'));
        $historical = $first->fresh();
        $this->measure($historical, -1);
        // Passive recalculation has no action witness and never calls the observer.
        $this->publishPotentialFixture($this->substrate, true);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertDatabaseCount('tragedies', 1);
    }

    public function test_committal_and_maintenance_measurements_do_not_create_tragedy(): void
    {
        $this->measure($this->origin, 1);
        $this->assertNull(app(TragedyService::class)->record($this->origin, $this->origin->phase, 'proposition.variant'));
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->measure($target, 1);
        $this->publishPotentialFixture($this->substrate, true);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_failed_or_rolled_back_contributions_do_not_leave_a_catastrophe(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->resolveExpressiveFieldFixture(-1);
        $before = Proposition::count();
        try {
            DB::transaction(function () use ($phase): void {
                app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
                    ['action' => 'proposition.variant', 'statement' => 'Rolled back birth']);
                $this->assertDatabaseCount('tragedies', 1);
                throw new \DomainException('rollback');
            });
        } catch (\DomainException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }
        $this->assertSame($before, Proposition::count());
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertSame(0, Receipt::where('type', 'tragedy')->count());
        try {
            app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
                ['action' => 'proposition.reclassify', 'proposition_id' => $this->origin->id]);
            $this->fail('The ordinary phase/action validation must reject this contribution');
        } catch (\DomainException) {
            $this->assertDatabaseCount('tragedies', 0);
            $this->assertSame($before, Proposition::count());
        }
    }
}
