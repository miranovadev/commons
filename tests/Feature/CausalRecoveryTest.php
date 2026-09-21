<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\HorizonReceiptService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use App\Services\ReferencePotentialChargeService;
use App\Services\TragedyFinalAttemptService;
use App\Services\TragedyReceiptScopeBackfill;
use App\Services\TragedyReceiptScopeService;
use App\Services\TragedyService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CausalRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    private ContextHorizon $root;

    private array $measurements = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'recovery', 'title' => 'Recovery', 'visibility' => 'public', 'configuration' => []])->save();
        $this->root = app(ContextHorizonProjector::class)->currentFor($this->substrate);
    }

    public function test_ordinary_receipt_regions_are_local_in_both_domains(): void
    {
        $boundary = $this->committal($this->root, .9, boundary: true);
        $settlement = app(ContextHorizonProjector::class)->forEntry($boundary);
        $this->assertNotSame($this->root->domain, $settlement->domain);
        foreach ([$this->root, $settlement] as $horizon) {
            $a = $this->committal($horizon, .5);
            $b = $this->committal($horizon, .5);
            $source = $this->expressive($horizon, $a);
            $instruments = app(InstrumentExecutionService::class);
            $receipt = $instruments->complete($instruments->start($source->phase, $source, 'citation', $a->id), $source);
            $service = app(HorizonReceiptService::class);
            $this->assertContains($receipt->id, $service->available($this->phase($horizon, $a, PhaseKind::Distinguish))->pluck('id'));
            $this->assertNotContains($receipt->id, $service->available($this->phase($horizon, $b, PhaseKind::Distinguish))->pluck('id'));
        }
    }

    public function test_farther_ancestor_can_beat_a_positive_nearer_candidate_after_attenuation(): void
    {
        $far = $this->committal($this->root, .95);
        $near = $this->committal($this->root, .02, $far);
        DB::table('supersessions')->where('target_proposition_id', $near->id)->update(['type' => 'commit']);
        $position = $this->expressive($this->root, $near);
        $this->opportunity($far);
        $this->opportunity($near);
        $selection = $this->recover($position);
        $this->assertSame($far->id, $selection['proposition']->id);
        $this->assertSame(2, $selection['distance']);
        $this->assertEqualsWithDelta(.95 / 9, $selection['potential_charge'], 1e-9);
        $this->assertSame(.95, $selection['raw_potential_charge']);
    }

    public function test_tragedy_receipt_follows_the_real_non_nearest_recovery_and_is_shared_only_at_its_destination(): void
    {
        $far = $this->committal($this->root, .95);
        $near = $this->committal($this->root, .02, $far);
        DB::table('supersessions')->where('target_proposition_id', $near->id)->update(['type' => 'commit']);
        $position = $this->expressive($this->root, $near);
        $this->opportunity($far);
        $this->opportunity($near);
        $carrier = $this->resting($this->root, $position);
        $terminal = $carrier->cycles()->latest('sequence')->first()->phases()->first();
        $far->commitmentSnapshot->forceFill(['calculator_version' => 'origin-bootstrap-v1'])->save();
        $tragedy = app(TragedyService::class)->record($terminal, ['field_version' => $this->substrate->fresh()->field_version]);
        $receipt = Receipt::findOrFail($tragedy->receipt_id);
        $original = $receipt->getAttributes();
        $this->refreshMeasurements();
        $selection = app(PotentialChargeService::class)->recoverFrom($position, $this->root, $carrier);
        $this->assertSame($far->id, $selection['proposition']->id);
        app(CycleEntryService::class)->prepare($carrier);
        $this->assertSame($far->id, $carrier->fresh()->current_proposition_id);
        $viewer = $this->phase($this->root, $far, PhaseKind::Distinguish);
        $service = app(HorizonReceiptService::class);
        $this->assertNotSame($carrier->id, $viewer->cycle->participant_id);
        $this->assertContains($receipt->id, $service->available($viewer)->pluck('id'));
        $elsewhere = $this->phase($this->root, $near, PhaseKind::Distinguish);
        $this->assertNotContains($receipt->id, $service->available($elsewhere)->pluck('id'));
        $this->assertEquals($original, $receipt->fresh()->getAttributes());
        $this->assertSame($tragedy->participant_id, $carrier->id);
    }

    public function test_real_recovery_by_a_second_participant_retreats_the_shared_basin(): void
    {
        $a = $this->committal($this->root, .2);
        $b = $this->committal($this->root, .95, $a);
        $c = $this->committal($this->root, .01, $b);
        DB::table('supersessions')->whereIn('target_proposition_id', [$b->id, $c->id])->update(['type' => 'commit']);
        $position = $this->expressive($this->root, $c);
        $this->opportunity($a);
        $opportunity = $this->expressive($this->root, $b);
        $this->measurements[$opportunity->id] = 0;
        $trigger = $this->resting($this->root, $position);
        $terminal = $trigger->cycles()->latest('sequence')->first()->phases()->first();
        $a->commitmentSnapshot->forceFill(['calculator_version' => 'origin-bootstrap-v1'])->save();
        $tragedy = app(TragedyService::class)->record($terminal, ['field_version' => $this->substrate->fresh()->field_version]);
        $this->refreshMeasurements();
        $selection = app(PotentialChargeService::class)->recoverFrom($position, $this->root, $trigger);
        $this->assertSame($b->id, $selection['proposition']->id);
        try {
            DB::transaction(function () use ($trigger): void {
                app(CycleEntryService::class)->prepare($trigger);
                throw new DomainException('Rollback recovery');
            });
        } catch (DomainException $e) {
            $this->assertSame('Rollback recovery', $e->getMessage());
        }
        $this->assertSame($position->id, $trigger->fresh()->current_proposition_id);
        $this->assertDatabaseHas('tragedy_receipt_scopes', ['tragedy_id' => $tragedy->id, 'current_committal_id' => $c->id]);
        app(CycleEntryService::class)->prepare($trigger);
        $this->assertSame($b->id, $trigger->fresh()->current_proposition_id);
        $this->assertDatabaseHas('tragedy_receipt_scopes', ['tragedy_id' => $tragedy->id, 'current_committal_id' => $b->id]);
        // Controlled routing values make the existing engine select A on the next real retreat.
        $this->measurements[$opportunity->id] = .95;
        $other = $this->resting($this->root, $opportunity);
        $this->refreshMeasurements();
        $selection = app(PotentialChargeService::class)->recoverFrom($opportunity, $this->root, $other);
        $this->assertSame($a->id, $selection['proposition']->id);
        app(CycleEntryService::class)->prepare($other);
        $this->assertSame($a->id, $other->fresh()->current_proposition_id);
        $this->assertDatabaseHas('tragedy_receipt_scopes', ['tragedy_id' => $tragedy->id, 'current_committal_id' => $a->id]);
        $this->assertNotSame($trigger->id, $other->id);
        $this->assertSame($trigger->id, $tragedy->fresh()->participant_id);
        $this->assertSame($b->id, $trigger->fresh()->current_proposition_id);
    }

    public function test_scope_migration_replays_all_carriers_and_preserves_legacy_proof_rows(): void
    {
        $a = $this->committal($this->root, .9);
        $b = $this->committal($this->root, .5, $a);
        $c = $this->committal($this->root, .1, $b);
        $trigger = $this->resting($this->root, $this->expressive($this->root, $c));
        $terminal = $trigger->cycles()->latest('sequence')->first()->phases()->first();
        $a->commitmentSnapshot->forceFill(['calculator_version' => 'origin-bootstrap-v1'])->save();
        $tragedy = app(TragedyService::class)->record($terminal, ['field_version' => $this->substrate->fresh()->field_version]);
        $this->phase($this->root, $b, PhaseKind::Distinguish, $trigger);
        $other = $this->resting($this->root, $this->expressive($this->root, $b));
        $entry = $this->phase($this->root, $a, PhaseKind::Distinguish, $other);
        $receipt = Receipt::findOrFail($tragedy->receipt_id)->getAttributes();
        $provenance = $tragedy->getAttributes();
        $ledger = DB::table('substrate_ledger_entries')->orderBy('id')->get()->all();
        $migration = require database_path('migrations/2026_09_21_000100_project_tragedy_receipt_basins.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('tragedy_receipt_scopes', ['tragedy_id' => $tragedy->id,
            'current_committal_id' => $a->id, 'recovery_phase_id' => $entry->id]);
        $this->assertSame($receipt, Receipt::findOrFail($tragedy->receipt_id)->getAttributes());
        $this->assertSame($provenance, $tragedy->fresh()->getAttributes());
        $this->assertEquals($ledger, DB::table('substrate_ledger_entries')->orderBy('id')->get()->all());
        // Re-running backfill is idempotent, and packet reads do not replay history.
        app(TragedyReceiptScopeBackfill::class)->run();
        $context = new ParticipationContext($entry);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $scope = app(TragedyReceiptScopeService::class)->seeds($context);
        $this->assertSame($scope, app(TragedyReceiptScopeService::class)->seeds($context));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($a->id, $scope[$tragedy->receipt_id]);
        $this->assertStringNotContainsString('cycles', $queries[0]['query']);
    }

    public function test_scope_backfill_withholds_ambiguous_legacy_recovery_order_without_rewriting_evidence(): void
    {
        $a = $this->committal($this->root, .9);
        $b = $this->committal($this->root, .5, $a);
        $trigger = $this->resting($this->root, $this->expressive($this->root, $b));
        $terminal = $trigger->cycles()->latest('sequence')->first()->phases()->first();
        $a->commitmentSnapshot->forceFill(['calculator_version' => 'origin-bootstrap-v1'])->save();
        $tragedy = app(TragedyService::class)->record($terminal, ['field_version' => $this->substrate->fresh()->field_version]);
        $cycle = (new Cycle)->forceFill(['participant_id' => $trigger->id, 'context_horizon_id' => $this->root->id, 'sequence' => 2]);
        $cycle->save();
        // Simulate a legacy recovery with no ordering witness finer than Tragedy's ULID millisecond.
        $entry = (new Phase)->forceFill(['id' => substr($tragedy->id, 0, 10).'ZZZZZZZZZZZZZZZZ',
            'cycle_id' => $cycle->id, 'gate_proposition_id' => $a->id, 'kind' => PhaseKind::Distinguish,
            'write_register' => $this->root->domain->registerFor(PhaseKind::Distinguish), 'started_at' => now()]);
        $entry->save();
        $before = Receipt::findOrFail($tragedy->receipt_id)->getAttributes();
        $migration = require database_path('migrations/2026_09_21_000100_project_tragedy_receipt_basins.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('tragedy_receipt_scopes', ['tragedy_id' => $tragedy->id, 'current_committal_id' => null]);
        $this->assertNotContains($tragedy->receipt_id, app(HorizonReceiptService::class)->available($entry)->pluck('id'));
        $this->assertSame($before, Receipt::findOrFail($tragedy->receipt_id)->getAttributes());
    }

    public function test_unconsumed_tragedy_follows_the_current_basin_via_another_participant_then_freezes(): void
    {
        $oldest = $this->committal($this->root, .95);
        $far = $this->committal($this->root, .95, $oldest);
        $near = $this->committal($this->root, .5, $far);
        $start = $this->committal($this->root, .1, $near);
        $position = $this->expressive($this->root, $start);
        $trigger = $this->resting($this->root, $position);
        $terminal = $trigger->cycles()->latest('sequence')->first()->phases()->first();
        $far->commitmentSnapshot->forceFill(['calculator_version' => 'origin-bootstrap-v1'])->save();
        $tragedy = app(TragedyService::class)->record($terminal, ['field_version' => $this->substrate->fresh()->field_version]);
        $receipt = Receipt::findOrFail($tragedy->receipt_id);
        $original = $receipt->getAttributes();
        $provenance = $tragedy->getAttributes();
        $scope = fn () => DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->value('current_committal_id');
        $follow = fn (Phase $entry) => DB::transaction(fn () => app(TragedyReceiptScopeService::class)->followRecovery($entry));
        $this->assertSame($start->id, $scope());
        // Focused persisted recovery witnesses. The selection engine is exercised separately above.
        $first = $this->phase($this->root, $near, PhaseKind::Distinguish, $trigger);
        $follow($first);
        $this->assertSame($near->id, $scope()); // Cannot overshoot the actual C -> B destination.
        $viewer = $this->phase($this->root, $near, PhaseKind::Distinguish);
        $this->assertNotSame($trigger->id, $viewer->cycle->participant_id);
        $this->assertContains($receipt->id, app(HorizonReceiptService::class)->available($viewer)->pluck('id'));
        $this->assertTrue(app(TragedyFinalAttemptService::class)->available($viewer));
        $variant = $this->node($viewer, $near);
        DB::transaction(fn () => app(TragedyFinalAttemptService::class)->record($viewer, $variant));
        $this->assertDatabaseHas('tragedy_final_attempts', ['tragedy_id' => $tragedy->id, 'proposition_id' => $variant->id]);
        // The original trigger's normal completion/entry does not carry shared scope.
        $first->forceFill(['completed_at' => now()])->save();
        $first->cycle->forceFill(['completed_at' => now()])->save();
        $normal = $this->phase($this->root, $far, PhaseKind::Distinguish, $trigger);
        $follow($normal);
        $this->assertSame($near->id, $scope());
        // A valid recovery from another region cannot move the basin at B.
        $elsewhere = $this->resting($this->root, $position);
        $follow($this->phase($this->root, $far, PhaseKind::Distinguish, $elsewhere));
        $this->assertSame($near->id, $scope());
        // D recovers from B, independently of the trigger's current position.
        $other = $this->resting($this->root, $this->expressive($this->root, $near));
        $second = $this->phase($this->root, $far, PhaseKind::Distinguish, $other);
        $this->assertNotSame($trigger->id, $other->id);
        $follow($second);
        $this->assertSame($far->id, $scope());
        $this->assertNotContains($receipt->id, app(HorizonReceiptService::class)->available($viewer)->pluck('id'));
        $this->assertContains($receipt->id, app(HorizonReceiptService::class)->available($second)->pluck('id'));
        $source = $this->expressive($this->root, $far);
        $classificationPhase = $this->phase($this->root, $source, PhaseKind::Constrain);
        $classification = $this->node($classificationPhase, $source);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($classificationPhase, $source, $classification, $receipt->id));
        $consumption = $receipt->fresh()->consumption->getAttributes();
        $frozen = DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->first();
        $later = $this->resting($this->root, $classification);
        $follow($this->phase($this->root, $oldest, PhaseKind::Distinguish, $later));
        $this->assertEquals($frozen, DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->first());
        $this->assertArrayNotHasKey($receipt->id, app(TragedyReceiptScopeService::class)->seeds(new ParticipationContext($second)));
        $this->assertSame($consumption, $receipt->fresh()->consumption->getAttributes());
        $this->assertEquals($original, $receipt->fresh()->getAttributes());
        $this->assertSame($provenance, $tragedy->fresh()->getAttributes());
        // Rebuilding legacy consumed scope must ignore the later valid recovery to the oldest ancestor.
        $migration = require database_path('migrations/2026_09_21_000100_project_tragedy_receipt_basins.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('tragedy_receipt_scopes', ['tragedy_id' => $tragedy->id, 'current_committal_id' => $far->id]);
        $this->assertSame($consumption, $receipt->fresh()->consumption->getAttributes());
        $this->assertSame($provenance, $tragedy->fresh()->getAttributes());
    }

    public function test_foreign_recovery_entry_cannot_transport_an_unconsumed_tragedy(): void
    {
        $origin = $this->committal($this->root, .9);
        $origin->commitmentSnapshot->forceFill(['calculator_version' => 'origin-bootstrap-v1'])->save();
        $terminal = $this->expressive($this->root, $origin);
        $carrier = $this->resting($this->root, $terminal);
        $phase = $carrier->cycles()->latest('sequence')->first()->phases()->first();
        $tragedy = app(TragedyService::class)->record($phase, ['field_version' => $this->substrate->fresh()->field_version]);
        $foreign = $this->committal($this->root, .9);
        $entry = $this->phase($this->root, $foreign, PhaseKind::Distinguish, $carrier);
        DB::transaction(fn () => app(TragedyReceiptScopeService::class)->followRecovery($entry));
        $this->assertSame($origin->id, DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->value('current_committal_id'));
        $this->assertNotContains($tragedy->receipt_id, app(HorizonReceiptService::class)->available($entry)->pluck('id'));
    }

    public function test_attenuation_can_make_the_nearer_ancestor_win_despite_lower_raw_potential(): void
    {
        $far = $this->committal($this->root, .95);
        $near = $this->committal($this->root, .5, $far);
        DB::table('supersessions')->where('target_proposition_id', $near->id)->update(['type' => 'commit']);
        $position = $this->expressive($this->root, $near);
        $this->opportunity($far);
        $this->opportunity($near);
        $selection = $this->recover($position);
        $this->assertSame($near->id, $selection['proposition']->id);
        $this->assertSame(1, $selection['distance']);
        $this->assertEqualsWithDelta(.125, $selection['potential_charge'], 1e-9);
    }

    public function test_reconciliation_follows_its_recorded_gate_without_a_shortcut_through_another_parent(): void
    {
        $shared = $this->committal($this->root, .9);
        $weak = $this->committal($this->root, .01, $shared);
        $left = $this->expressive($this->root, $shared);
        $right = $this->expressive($this->root, $weak);
        $rest = $this->expressive($this->root, $right);
        $this->edge($left, $rest, 'reconcile');
        $this->opportunity($shared);
        $this->opportunity($weak);
        $selection = $this->recover($rest);
        $this->assertSame($shared->id, $selection['proposition']->id);
        $this->assertSame(3, $selection['distance']); // The untraversed parent would shorten this to 2.
        $this->assertEqualsWithDelta(.9 / 16, $selection['potential_charge'], 1e-9);
        // Shared ancestry is not accumulated or counted twice.
        $this->assertSame(.9, $selection['raw_potential_charge']);
    }

    public function test_connected_descendants_siblings_and_unrelated_committals_are_all_ineligible(): void
    {
        $ancestor = $this->committal($this->root, .1);
        $position = $this->expressive($this->root, $ancestor);
        $descendant = $this->committal($this->root, 1, $position);
        $sibling = $this->committal($this->root, 1, $ancestor);
        $disconnected = $this->committal($this->root, 1);
        foreach ([$ancestor, $descendant, $sibling, $disconnected] as $candidate) {
            $this->opportunity($candidate);
        }
        $selection = $this->recover($position);
        $this->assertSame($ancestor->id, $selection['proposition']->id);
        $this->assertSame(1, $selection['distance']);
        $this->assertNotSame($disconnected->id, $selection['proposition']->id);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($position);
        $this->assertSame([$ancestor->id], $inspection['eligible_candidate_ids']);
    }

    public function test_shared_reconciliation_uses_the_observers_arrival_gate_instead_of_the_creators_gate(): void
    {
        $ancestor = $this->committal($this->root, .9);
        $short = $this->expressive($this->root, $ancestor);
        $long = $this->expressive($this->root, $this->expressive($this->root, $ancestor));
        $result = $this->node($this->phase($this->root, $short, PhaseKind::Mediate), $short);
        $this->edge($long, $result, 'reconcile');
        $observer = $this->participant();
        $mediate = $this->phase($this->root, $long, PhaseKind::Mediate, $observer);
        $mediate->forceFill(['completed_at' => now()])->save();
        (new Phase)->forceFill(['cycle_id' => $mediate->cycle_id, 'kind' => PhaseKind::Bias,
            'gate_proposition_id' => $result->id, 'write_register' => $this->root->domain->registerFor(PhaseKind::Bias),
            'started_at' => now()])->save();
        $observer->forceFill(['current_proposition_id' => $result->id])->save();
        $this->opportunity($ancestor);
        $this->refreshMeasurements();
        foreach ([PotentialChargeService::class, ReferencePotentialChargeService::class] as $service) {
            $selection = app($service)->recoverFrom($result, $this->root, $observer);
            $this->assertSame($ancestor->id, $selection['proposition']->id);
            $this->assertSame(3, $selection['distance']);
            $this->assertEqualsWithDelta(.9 / 16, $selection['potential_charge'], 1e-9);
        }
    }

    public function test_a_later_revisit_cannot_rewrite_an_inherited_earlier_arrival(): void
    {
        $ancestor = $this->committal($this->root, .9);
        $short = $this->expressive($this->root, $ancestor);
        $long = $this->expressive($this->root, $this->expressive($this->root, $ancestor));
        $result = $this->node($this->phase($this->root, $short, PhaseKind::Mediate), $short);
        $this->edge($long, $result, 'reconcile');
        $author = $this->participant();
        $earlier = $this->phase($this->root, $long, PhaseKind::Mediate, $author);
        $earlier->forceFill(['completed_at' => now()])->save();
        $bias = (new Phase)->forceFill(['cycle_id' => $earlier->cycle_id, 'kind' => PhaseKind::Bias,
            'gate_proposition_id' => $result->id, 'write_register' => $this->root->domain->registerFor(PhaseKind::Bias),
            'started_at' => now()]);
        $bias->save();
        $position = $this->node($bias, $result);
        $later = $this->phase($this->root, $short, PhaseKind::Mediate, $author);
        $later->forceFill(['completed_at' => now()])->save();
        (new Phase)->forceFill(['cycle_id' => $later->cycle_id, 'kind' => PhaseKind::Bias,
            'gate_proposition_id' => $result->id, 'write_register' => $this->root->domain->registerFor(PhaseKind::Bias),
            'started_at' => now()])->save();
        $this->opportunity($ancestor);
        $selection = $this->recover($position);
        $this->assertSame($ancestor->id, $selection['proposition']->id);
        $this->assertSame(4, $selection['distance']);
        $this->assertEqualsWithDelta(.9 / 25, $selection['potential_charge'], 1e-9);
    }

    public function test_a_legacy_tragedy_override_cannot_select_an_unrelated_committal(): void
    {
        $ancestor = $this->committal($this->root, .5);
        $position = $this->expressive($this->root, $ancestor);
        $unrelated = $this->committal($this->root, .9);
        $this->opportunity($ancestor);
        $this->opportunity($unrelated);
        $observer = $this->resting($this->root, $position);
        $observer->cycles()->sole()->forceFill(['recovery_proposition_id' => $unrelated->id])->save();
        $this->refreshMeasurements();
        app(CycleEntryService::class)->prepare($observer);
        $this->assertSame($ancestor->id, $observer->fresh()->current_proposition_id);
    }

    public function test_crossing_an_exhausted_horizon_retains_intermediate_ancestral_committals_and_true_distance(): void
    {
        $origin = $this->committal($this->root, -.9, boundary: true);
        $parent = app(ContextHorizonProjector::class)->forEntry($origin);
        $intermediate = $this->committal($parent, .9, $origin);
        $bridge = $this->expressive($parent, $intermediate);
        $boundary = $this->committal($parent, .01, $bridge, boundary: true);
        $current = app(ContextHorizonProjector::class)->forEntry($boundary);
        $position = $this->expressive($current, $boundary);
        $this->opportunity($origin);
        $this->opportunity($intermediate);
        $this->exhaust($position);
        $selection = $this->recover($position);
        $this->assertSame($intermediate->id, $selection['proposition']->id);
        $this->assertSame($parent->id, $selection['context_horizon_id']);
        $this->assertSame(3, $selection['distance']);
        $this->assertEqualsWithDelta(.9 / 16, $selection['potential_charge'], 1e-9);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($position, $current);
        $this->assertEqualsCanonicalizing([$origin->id, $intermediate->id], $inspection['eligible_candidate_ids']);
        $this->assertSame([$current->id, $parent->id], array_column($inspection['searched_basins'], 'context_horizon_id'));
    }

    public function test_revisions_and_historical_inputs_remain_real_backward_hops(): void
    {
        $ancestor = $this->committal($this->root, .8);
        $original = $this->node($this->phase($this->root, $ancestor, PhaseKind::Distinguish), $ancestor);
        $revision = $this->node($this->phase($this->root, $ancestor, PhaseKind::Distinguish));
        $this->edge($original, $revision, 'revise');
        $position = $this->expressive($this->root, $revision);
        $this->opportunity($ancestor);
        $selection = $this->recover($position);
        $this->assertSame($ancestor->id, $selection['proposition']->id);
        $this->assertSame(3, $selection['distance']);
        $this->assertEqualsWithDelta(.8 / 16, $selection['potential_charge'], 1e-9);
    }

    public function test_cyclic_proposition_lineage_is_rejected_instead_of_shortened(): void
    {
        $ancestor = $this->committal($this->root, .5);
        $position = $this->expressive($this->root, $ancestor);
        $this->edge($position, $ancestor);
        $this->refreshMeasurements();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('acyclic proposition lineage');
        app(PotentialChargeService::class)->recoverFrom($position);
    }

    public function test_an_unexplored_position_is_not_silently_excluded_from_potential(): void
    {
        $ancestor = $this->committal($this->root, .9);
        $position = $this->expressive($this->root, $ancestor);
        $this->measurements[$position->id] = 0;
        $continuation = $this->expressive($this->root, $position);
        $this->measurements[$continuation->id] = 0;
        $this->assertSame($ancestor->id, $this->recover($position)['proposition']->id);
        $this->assertSame(.9, $this->recover($position)['raw_potential_charge']);
    }

    public function test_first_tragedy_returns_to_its_opening_committal_without_crossing_the_boundary(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h3, $commit);
        $this->opportunity($origin);
        $participant = $this->participant();
        $this->explore($position);
        $order = $this->phase($h3, $position, participant: $participant);
        $participant->forceFill(['current_proposition_id' => $position->id])->save();
        $this->refreshMeasurements();
        $rest = app(ParticipationService::class)->contribute($participant, $order->id, ['action' => 'participant.rest']);
        $this->assertSame('ready', $rest['status']);
        $this->assertSame('distinguish', $rest['plan']['phase']['kind']);
        $this->assertNotNull($order->cycle->fresh()->rested_at);
        $historicalOwners = DB::table('cycles')->pluck('context_horizon_id', 'id')->all();
        $selection = $this->recover($position);
        $this->assertSame($commit->id, $selection['proposition']->id);
        $this->assertSame($h2->id, app(ContextHorizonProjector::class)->forEntry($origin)->id);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h3->id, $cycle->context_horizon_id);
        $this->assertSame('activity', $cycle->currentPhase->write_register);
        $state = app(ParticipationService::class)->contribute($participant, $cycle->currentPhase->id,
            ['action' => 'proposition.variant', 'statement' => 'A new Signal in the selected historical region.']);
        $write = $participant->fresh()->position;
        $this->assertSame('activity', $write->register);
        $this->assertSame('emergence', $state['domain']);
        $this->assertSame($h3->id, $write->contextHorizonMembership->context_horizon_id);
        foreach ($historicalOwners as $id => $owner) {
            $this->assertSame($owner, Cycle::findOrFail($id)->context_horizon_id);
        }
        $this->assertNotNull($h2->fresh()->closed_at);
        $this->assertSame($commit->id, $h2->fresh()->closed_by_proposition_id);
    }

    public function test_recovery_can_cross_multiple_domain_boundaries_without_rewriting_historical_ownership(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $later = $this->committal($h3, -.01, $commit, boundary: true);
        $h4 = app(ContextHorizonProjector::class)->forEntry($later);
        $position = $this->expressive($h4, $later);
        $this->opportunity($origin);
        $this->exhaust($position);
        $this->assertNull($h3->fresh()->exhausted_at, 'Parent requires its own final attempt');
        $this->exhaust($this->expressive($h3, $commit));
        $participant = $this->resting($h4, $position);
        $selection = $this->recover($position);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame(3, $selection['distance']);
        app(CycleEntryService::class)->enter($participant);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h2->id, $cycle->context_horizon_id);
        $this->assertSame($h3->id, $later->fresh()->phase->cycle->context_horizon_id);
        $this->assertSame($h3->id, $h4->fresh()->parent_context_horizon_id);
    }

    public function test_emergence_candidate_wins_and_next_cycle_creates_activity(): void
    {
        [$origin, , $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h3, $commit);
        $this->opportunity($commit);
        $participant = $this->resting($h3, $position);
        $this->assertSame($commit->id, $this->recover($position)['proposition']->id);
        app(CycleEntryService::class)->enter($participant);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h3->id, $cycle->context_horizon_id);
        $this->assertSame($commit->id, $cycle->currentPhase->gate_proposition_id);
        app(ParticipationService::class)->contribute($participant, $cycle->currentPhase->id,
            ['action' => 'proposition.variant', 'statement' => 'Activity follows the selected Emergence entry.']);
        $this->assertSame('activity', $participant->fresh()->position->register);
    }

    public function test_non_boundary_committal_stays_in_its_closed_own_horizon(): void
    {
        [, $h2, $boundary, $h3] = $this->boundaryChain();
        $sameDomain = $this->committal($h2, -.3);
        $this->assertSame($h2->id, app(ContextHorizonProjector::class)->forEntry($sameDomain)->id);
        $participant = $this->participant();
        $participant->forceFill(['current_proposition_id' => $sameDomain->id])->save();
        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($h2->id, $participant->cycles()->sole()->context_horizon_id);
        $this->assertSame($h3->id, app(ContextHorizonProjector::class)->forEntry($boundary)->id);
    }

    public function test_newest_descendant_cannot_be_requested_for_an_old_gate(): void
    {
        [$origin, , , $h3] = $this->boundaryChain();
        $this->expectException(DomainException::class);
        app(ContextHorizonProjector::class)->forEntry($origin, $h3);
    }

    public function test_missing_direct_boundary_horizon_fails_instead_of_searching_other_descendants(): void
    {
        [$origin, $h2] = $this->boundaryChain();
        $gate = $this->committal($h2, .1, $origin, boundary: true, projectBoundary: false);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('must explicitly open its direct child');
        app(ContextHorizonProjector::class)->forEntry($gate);
    }

    public function test_multiple_resting_participants_independently_enter_the_same_valid_historical_region(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h3, $commit);
        $this->opportunity($origin);
        $this->exhaust($position);
        $participants = [$this->resting($h3, $position), $this->resting($h3, $position)];
        foreach ($participants as $participant) {
            $this->refreshMeasurements();
            app(CycleEntryService::class)->enter($participant);
            $cycle = $participant->cycles()->whereNull('completed_at')->sole();
            $this->assertSame($h2->id, $cycle->context_horizon_id);
            $this->assertSame($origin->id, $cycle->currentPhase->gate_proposition_id);
        }
    }

    public function test_zero_potential_unexplored_basin_remains_a_structural_entry(): void
    {
        $gate = $this->committal($this->root, 0);
        $position = $this->expressive($this->root, $gate);
        $participant = $this->resting($this->root, $position);
        $this->assertSame($gate->id, $this->recover($position)['proposition']->id);
        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($gate->id, $participant->fresh()->current_proposition_id);
        $this->assertSame(2, $participant->cycles()->count());
        $this->assertSame(0, DB::table('exhaustion_states')->count());
    }

    public function test_direct_phase_creation_cannot_pair_old_origin_with_newest_horizon(): void
    {
        [$origin, , , $h3] = $this->boundaryChain();
        $this->expectException(DomainException::class);
        $this->phase($h3, $origin, PhaseKind::Distinguish);
    }

    public function test_direct_phase_creation_rejects_the_opposite_domains_register(): void
    {
        $phase = $this->phase($this->root);
        $this->expectException(DomainException::class);
        $phase->forceFill(['write_register' => 'signal'])->save();
    }

    public function test_boundary_entry_cannot_choose_its_old_owner_instead_of_its_direct_child(): void
    {
        [$origin] = $this->boundaryChain();
        $this->expectException(DomainException::class);
        $this->phase($this->root, $origin, PhaseKind::Distinguish);
    }

    public function test_invalid_direct_progression_fails_before_changing_position_or_completing_the_phase(): void
    {
        [$origin, , $commit, $h3] = $this->boundaryChain();
        $phase = $this->phase($h3, $commit, PhaseKind::Distinguish);
        $participant = $phase->cycle->participant;
        $participant->forceFill(['current_proposition_id' => $commit->id])->save();
        try {
            app(PhaseProgressionService::class)->advance($phase, $origin);
            $this->fail('Invalid progression must reject before moving the Participant.');
        } catch (DomainException) {
            $this->assertSame($commit->id, $participant->fresh()->current_proposition_id);
            $this->assertNull($phase->fresh()->completed_at);
            $this->assertSame(1, $phase->cycle->phases()->count());
        }
    }

    public function test_direct_proposition_creation_rejects_the_opposite_domains_register(): void
    {
        $phase = $this->phase($this->root);
        $this->expectException(DomainException::class);
        (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => 'signal',
            'tier' => 'expressive', 'statement' => 'Invalid Domain'])->save();
    }

    public function test_direct_dispatch_rechecks_phase_vocabulary_even_when_the_in_memory_object_is_modified(): void
    {
        $gate = $this->committal($this->root, .2);
        $phase = $this->phase($this->root, $gate, PhaseKind::Distinguish);
        $phase->write_register = 'signal';
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
                ['action' => 'proposition.variant', 'statement' => 'Invalid Signal']);
            $this->fail('Direct dispatch must reject a mismatched Domain.');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
        }
    }

    public function test_phase_and_proposition_cannot_be_reparented_to_reassign_horizon_ownership(): void
    {
        $node = $this->expressive($this->root);
        $other = $this->phase($this->root);
        foreach ([$node, $node->phase] as $model) {
            $column = $model instanceof Proposition ? 'phase_id' : 'cycle_id';
            $original = $model->$column;
            try {
                $model->forceFill($model instanceof Proposition ? ['phase_id' => $other->id] : ['cycle_id' => $other->cycle_id])->save();
                $this->fail('Creating ownership must be durable.');
            } catch (DomainException) {
                $this->assertSame($original, $model->fresh()->$column);
            }
        }
    }

    public function test_bulk_updates_cannot_reassign_phase_proposition_or_horizon_ownership(): void
    {
        $node = $this->expressive($this->root);
        $other = $this->phase($this->root);
        foreach ([
            ['phases', $node->phase_id, 'cycle_id', $other->cycle_id],
            ['propositions', $node->id, 'phase_id', $other->id],
            ['context_horizons', $this->root->id, 'domain', 'settlement'],
        ] as [$table, $id, $column, $value]) {
            $original = DB::table($table)->where('id', $id)->value($column);
            try {
                DB::transaction(fn () => DB::table($table)->where('id', $id)->update([$column => $value]));
                $this->fail('Bulk SQL must preserve causal ownership.');
            } catch (QueryException) {
                $this->assertSame($original, DB::table($table)->where('id', $id)->value($column));
            }
        }
    }

    public function test_audit_reports_legacy_invalid_entries_without_relabelling_history(): void
    {
        [$origin, , $commit, $h3] = $this->boundaryChain();
        $phase = $this->phase($h3, $commit, PhaseKind::Distinguish);
        DB::table('phases')->where('id', $phase->id)->update(['gate_proposition_id' => $origin->id]);
        $before = $phase->fresh()->getAttributes();
        $this->artisan('commons:causal-audit', ['substrate' => $this->substrate->slug])
            ->expectsOutputToContain('Causal audit: 1 invalid relationships. No records changed.')
            ->assertExitCode(1);
        $this->assertSame($before, $phase->fresh()->getAttributes());
        $this->assertSame($h3->id, $phase->cycle->fresh()->context_horizon_id);
    }

    public function test_identity_migration_rolls_back_and_reapplies_without_changing_history(): void
    {
        $node = $this->expressive($this->root);
        $attributes = $node->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_09_15_120000_protect_causal_ownership.php');
        $migration->down();
        $migration->up();
        $this->assertSame($attributes, $node->fresh()->getAttributes());
    }

    public function test_rest_in_an_earlier_horizon_cannot_enter_a_descendant_horizon(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h2, $origin);
        $this->opportunity($origin);
        $this->opportunity($commit);
        $participant = $this->resting($h2, $position);
        $selection = $this->recover($position);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame($h2->id, $selection['context_horizon_id']);
        $this->assertSame(.9, $selection['raw_potential_charge']);
        $this->assertGreaterThan(.01, abs($origin->commitmentSnapshot->tension));
        app(CycleEntryService::class)->prepare($participant);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h2->id, $cycle->context_horizon_id);
        $this->assertSame('signal', $cycle->currentPhase->write_register);
        $this->assertSame($origin->id, $participant->fresh()->current_proposition_id);
    }

    public function test_real_rest_stays_on_ancestral_lineage_without_changing_another_participants_active_cycle(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h2, $origin);
        $this->opportunity($origin);
        $this->opportunity($commit);
        $participant = $this->participant();
        $phase = $this->phase($h2, $position, participant: $participant);
        $participant->forceFill(['current_proposition_id' => $position->id])->save();
        $other = $this->participant();
        $otherPhase = $this->phase($h2, $origin, PhaseKind::Distinguish, $other);
        $other->forceFill(['current_proposition_id' => $origin->id])->save();
        $this->refreshMeasurements();
        $before = [$other->fresh()->getAttributes(), $otherPhase->fresh()->getAttributes(), $otherPhase->cycle->fresh()->getAttributes()];
        $result = app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame('ready', $result['status']);
        $this->assertSame($origin->id, $result['plan']['gate_id']);
        $this->assertSame($h2->id, $result['cycle']['context_horizon_id']);
        $this->assertSame('settlement', $result['domain']);
        $this->assertNotNull($phase->cycle->fresh()->rested_at);
        app(CycleEntryService::class)->prepare($other);
        $this->assertSame($before, [$other->fresh()->getAttributes(), $otherPhase->fresh()->getAttributes(), $otherPhase->cycle->fresh()->getAttributes()]);
    }

    public function test_local_potential_ranks_ancestral_non_boundary_committals_inside_the_current_horizon(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $local = $this->committal($h3, .9, $commit);
        $position = $this->expressive($h3, $local);
        $this->opportunity($origin);
        $this->opportunity($commit);
        $this->opportunity($local);
        $selection = $this->recover($position);
        $this->assertSame($local->id, $selection['proposition']->id);
        $this->assertSame($h3->id, $selection['context_horizon_id']);
        $this->assertSame(1, $selection['distance']);
        $this->assertEqualsWithDelta(.9 / 4, $selection['potential_charge'], 1e-9);
    }

    public function test_a_same_horizon_commit_does_not_shadow_a_stronger_ancestor_during_recovery(): void
    {
        $origin = $this->committal($this->root, -.9, boundary: true);
        $horizon = app(ContextHorizonProjector::class)->forEntry($origin);
        $commit = $this->committal($horizon, -.01, $origin);
        DB::table('supersessions')->where('target_proposition_id', $commit->id)->update(['type' => 'commit']);
        $this->opportunity($origin);
        $position = $this->expressive($horizon, $commit);
        $this->refreshMeasurements();
        $state = DB::table('potential_region_states')->where('context_horizon_id', $horizon->id)->first();
        $legacy = json_decode($state->local_state, true, flags: JSON_THROW_ON_ERROR);
        $legacy['edges'] = array_map(fn (array $edge): array => array_slice($edge, 0, 2), $legacy['edges']);
        DB::table('potential_region_states')->where('context_horizon_id', $horizon->id)
            ->update(['local_state' => json_encode($legacy, JSON_THROW_ON_ERROR)]);
        $selection = app(PotentialChargeService::class)->recoverFrom($position);
        $reference = app(ReferencePotentialChargeService::class)->recoverFrom($position);

        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame($reference['proposition']->id, $selection['proposition']->id);
        $this->assertSame($horizon->id, $selection['context_horizon_id']);
        $this->assertSame(.9, (float) $selection['raw_potential_charge']);
        $this->assertSame(2, $selection['distance']);
        $this->assertEqualsWithDelta(.9 / 9, $selection['potential_charge'], 1e-9);
    }

    public function test_neither_potential_nor_recency_admits_a_sibling_horizon(): void
    {
        [$origin, $h2, $first, $h3] = $this->boundaryChain();
        $winner = $this->committal($h2, .8, $origin, boundary: true);
        $newest = $this->committal($h2, .02, $origin, boundary: true);
        foreach ([$origin, $first, $winner, $newest] as $candidate) {
            $this->opportunity($candidate);
        }
        $position = $this->expressive($h2, $origin);
        $selection = $this->recover($position);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame(1, $selection['distance']);
        $this->assertEqualsWithDelta(.9 / 4, $selection['potential_charge'], 1e-9);
    }

    public function test_viable_current_domain_retains_its_participant_despite_stronger_sibling_and_origin(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $sibling = $this->committal($h2, .8, $origin, boundary: true);
        foreach ([$origin, $commit, $sibling] as $candidate) {
            $this->opportunity($candidate);
        }
        $position = $this->expressive($h3, $commit);
        $this->assertSame($commit->id, $this->recover($position)['proposition']->id);
    }

    public function test_exhausted_current_horizon_continues_backward_and_cannot_recover_into_a_sibling(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $sibling = $this->committal($h2, .01, $origin, boundary: true);
        $this->opportunity($origin);
        $this->opportunity($sibling);
        $position = $this->expressive($h3, $commit);
        $this->exhaust($position);
        $this->refreshMeasurements();
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($position, $h3);
        $this->assertSame($origin->id, $inspection['selection']['proposition']->id);
        $this->assertSame(1, $inspection['fallback_depth']);
        $this->assertSame(2, $inspection['selection']['distance']);
        $this->assertSame([$h3->id, $h2->id], array_column($inspection['searched_basins'], 'context_horizon_id'));
        $this->assertSame([$origin->id], $inspection['eligible_candidate_ids']);
    }

    public function test_a_viable_descendant_never_shadows_an_ancestral_recovery_candidate(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $later = $this->committal($h3, -.00001, $commit, boundary: true);
        $h4 = app(ContextHorizonProjector::class)->forEntry($later);
        foreach ([$origin, $commit, $later] as $candidate) {
            $this->opportunity($candidate);
        }
        $position = $this->expressive($h2, $origin);
        $selection = $this->recover($position);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame($h2->id, $selection['context_horizon_id']);
    }

    public function test_neither_deep_nor_shallow_siblings_enter_the_recovery_candidate_set(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $deep = $this->committal($h3, -.001, $commit, boundary: true);
        $sibling = $this->committal($h2, .5, $origin, boundary: true);
        foreach ([$origin, $commit, $deep, $sibling] as $candidate) {
            $this->opportunity($candidate);
        }
        $position = $this->expressive($h2, $origin);
        $selection = $this->recover($position);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame([$origin->id], app(PotentialChargeService::class)->inspectRecoveryFrom($position)['eligible_candidate_ids']);
    }

    public function test_current_basin_cannot_borrow_potential_from_cross_domain_expressive_edges(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $historical = $this->expressive($h2, $origin);
        $this->measurements[$historical->id] = 0;
        $this->edge($commit, $historical); // Legacy cross-Domain edge cannot expand the scoring scope.
        $position = $this->expressive($h3, $commit);
        $selection = $this->recover($position);
        $this->assertSame($commit->id, $selection['proposition']->id);
        $this->assertSame(0.0, (float) $selection['raw_potential_charge']);
    }

    public function test_zero_potential_downstream_domain_stays_open_without_changing_history(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $this->opportunity($origin);
        $position = $this->expressive($h3, $commit);
        $before = ContextHorizon::orderBy('sequence')->get()->map->getAttributes()->all();
        $this->assertSame($commit->id, $this->recover($position)['proposition']->id);
        $this->assertSame($before, ContextHorizon::orderBy('sequence')->get()->map->getAttributes()->all());
        // New shared evidence changes Potential without changing structural openness.
        $this->opportunity($commit);
        $this->assertSame($commit->id, $this->recover($position)['proposition']->id);
    }

    public function test_retained_boundary_is_distance_zero_and_does_not_admit_its_descendants(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $this->opportunity($origin);
        $this->opportunity($commit);
        $local = $this->committal($h3, .2, $commit);
        $this->opportunity($local);
        $this->refreshMeasurements();
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($commit);
        $this->assertSame($h3->id, $inspection['starting_context_horizon_id']);
        $this->assertSame($commit->id, $inspection['selection']['proposition']->id);
        $this->assertSame([$commit->id], $inspection['eligible_candidate_ids']);
        $this->assertSame(0, $inspection['selection']['distance']);
    }

    public function test_recovery_diagnostics_and_selection_are_read_only(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h2, $origin);
        $this->opportunity($commit);
        $participant = $this->resting($h2, $position);
        $this->refreshMeasurements();
        $before = [Cycle::count(), Proposition::count(), $participant->fresh()->getAttributes()];
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|create)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($position, $h2);
        $this->assertSame($origin->id, $inspection['selection']['proposition']->id);
        $this->assertSame($h2->id, $inspection['starting_context_horizon_id']);
        $this->assertSame(0, $inspection['fallback_depth']);
        $this->assertSame([$h2->id], array_column($inspection['searched_basins'], 'context_horizon_id'));
        $this->assertSame($before, [Cycle::count(), Proposition::count(), $participant->fresh()->getAttributes()]);
        $this->artisan('commons:recovery-inspect', ['participant' => $participant->id])
            ->expectsOutputToContain($origin->id)
            ->assertSuccessful();
        $this->assertSame([], $writes);
    }

    public function test_recovery_cannot_be_scoped_to_an_unrelated_horizon_by_a_supplied_context(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $sibling = $this->committal($h2, .8, $origin, boundary: true);
        $position = $this->expressive($h3, $commit);
        $this->expectException(DomainException::class);
        app(PotentialChargeService::class)->inspectRecoveryFrom($position,
            app(ContextHorizonProjector::class)->forEntry($sibling));
    }

    public function test_non_best_sibling_update_stops_before_unchanged_ancestors(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $winner = $this->committal($h2, .8, $origin, boundary: true);
        $this->opportunity($winner);
        $this->opportunity($commit);
        $this->refreshMeasurements();
        $before = DB::table('potential_region_states')->whereIn('context_horizon_id', [$this->root->id, $h2->id])
            ->orderBy('context_horizon_id')->get()->toJson();
        $changed = Proposition::whereHas('phase.cycle', fn ($q) => $q->where('context_horizon_id', $h3->id))->where('tier', 'expressive')->sole();
        app(PropositionMeasurementService::class)->project($changed, .005, .005, .005);
        $this->assertTrue(app(PotentialProjectionService::class)->refresh($this->substrate->id));
        $this->assertSame($before, DB::table('potential_region_states')->whereIn('context_horizon_id', [$this->root->id, $h2->id])
            ->orderBy('context_horizon_id')->get()->toJson());
        $this->assertSame(.005, (float) DB::table('potential_entries')->where('proposition_id', $commit->id)->value('raw_potential_charge'));
        $terrain = fn () => DB::table('potential_region_states')->orderBy('context_horizon_id')
            ->get(['context_horizon_id', 'local_state', 'frontier', 'remaining_potential'])->toJson();
        $incremental = $terrain();
        $this->assertTrue(app(PotentialProjectionService::class)->rebuild($this->substrate->id, false));
        $this->assertSame($incremental, $terrain());
    }

    private function explore(Proposition $node): void
    {
        $this->loadedReceiptFixture($node);
        $attestation = (new Attestation)->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'refute']);
        $attestation->save();
        app(LedgerService::class)->record($attestation);
    }

    private function exhaust(Proposition $node): void
    {
        $horizon = $node->phase->cycle->contextHorizon;
        $opening = Proposition::findOrFail($horizon->opened_by_proposition_id);
        $this->explore($node);
        $phase = $this->phase($horizon, $node);
        $this->refreshMeasurements();
        app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $tragedy = Tragedy::where('context_horizon_id', $horizon->id)->sole();
        $source = $this->node($this->phase($horizon, $opening, PhaseKind::Persist), $opening);
        $this->classificationEvidenceFixture($source);
        $classification = $this->phase($horizon, $source, PhaseKind::Constrain);
        $target = app(ContributionDispatcher::class)->dispatch($classification->cycle->participant, $classification,
            ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $tragedy->receipt_id]);
        $this->explore($target);
        $terminal = $this->phase($horizon, $target);
        $this->refreshMeasurements();
        app(ParticipationService::class)->contribute($terminal->cycle->participant, $terminal->id, ['action' => 'participant.rest']);
        $this->assertNotNull($horizon->fresh()->exhausted_at);
        $this->refreshMeasurements();
    }

    private function boundaryChain(): array
    {
        $origin = $this->committal($this->root, -.9, boundary: true);
        $h2 = app(ContextHorizonProjector::class)->forEntry($origin);
        $commit = $this->committal($h2, .01, $origin, boundary: true);
        $h3 = app(ContextHorizonProjector::class)->forEntry($commit);
        $this->assertSame(ContextDomain::Settlement, $h2->domain);
        $this->assertSame(ContextDomain::Emergence, $h3->domain);

        return [$origin, $h2, $commit, $h3];
    }

    private function participant(): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => 'observer-'.str()->ulid()])->save();

        return $participant;
    }

    private function phase(ContextHorizon $horizon, ?Proposition $gate = null, PhaseKind $kind = PhaseKind::Order, ?Participant $participant = null): Phase
    {
        $participant ??= $this->participant();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id,
            'sequence' => (int) $participant->cycles()->max('sequence') + 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'gate_proposition_id' => $gate?->id, 'kind' => $kind,
            'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()])->save();

        return $phase;
    }

    private function expressive(ContextHorizon $horizon, ?Proposition $parent = null): Proposition
    {
        return $this->node($this->phase($horizon, $parent), $parent);
    }

    private function node(Phase $phase, ?Proposition $parent = null, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => $tier, 'statement' => 'Causal recovery fixture'])->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $this->edge($parent, $node);
        }

        return $node;
    }

    private function committal(ContextHorizon $horizon, float $tension, ?Proposition $parent = null, bool $boundary = false, bool $projectBoundary = true): Proposition
    {
        $node = $this->node($this->phase($horizon, $parent), $parent, 'committal');
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
            'ledger_entry_id' => $node->ledgerEntry->id, 'correspondence' => $tension, 'chirality' => $tension, 'closure' => $tension,
            'prominence' => (1 + $tension) / 2, 'turbulence' => (1 - $tension) / 2, 'tension' => $tension,
            'direction_before' => $horizon->domain, 'direction_after' => $boundary ? $horizon->domain->opposite() : $horizon->domain,
            'calculator_version' => 'controlled-routing-fixture', 'evaluated_at' => now()])->save();
        if ($boundary && $projectBoundary) {
            app(ContextHorizonProjector::class)->apply($snapshot);
        }

        return $node;
    }

    private function opportunity(Proposition $committal): void
    {
        $horizon = app(ContextHorizonProjector::class)->forEntry($committal);
        $node = $this->expressive($horizon, $committal);
        $this->measurements[$node->id] = 0;
    }

    private function edge(Proposition $source, Proposition $target, string $type = 'variant'): void
    {
        (new Supersession)->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
    }

    private function resting(ContextHorizon $horizon, Proposition $position): Participant
    {
        $participant = $this->participant();
        $phase = $this->phase($horizon, $position, participant: $participant);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $participant->forceFill(['current_proposition_id' => $position->id])->save();

        return $participant;
    }

    private function recover(Proposition $position): ?array
    {
        $this->refreshMeasurements();

        $actual = app(PotentialChargeService::class)->recoverFrom($position);
        $reference = app(ReferencePotentialChargeService::class)->recoverFrom($position);
        $this->assertSame($reference['proposition']->id ?? null, $actual['proposition']->id ?? null);
        $this->assertSame($reference['distance'] ?? null, $actual['distance'] ?? null);
        $this->assertEqualsWithDelta($reference['potential_charge'] ?? 0, $actual['potential_charge'] ?? 0, 1e-12);

        return $actual;
    }

    /** Controlled values only for routing; Potential still evaluates the real continuation graph. */
    private function refreshMeasurements(): void
    {
        foreach ($this->measurements as $id => $value) {
            app(PropositionMeasurementService::class)->project(Proposition::findOrFail($id), $value, $value, $value);
        }
        // Routing fixtures supply controlled raw values. Apply their actual
        // mutation worklist incrementally; production derives these raw values.
        DB::table('potential_dirty_horizons')->where('substrate_id', $this->substrate->id)
            ->update(['measurements_dirty' => false]);
        $this->assertTrue(app(PotentialProjectionService::class)->refresh($this->substrate->id));
    }
}
