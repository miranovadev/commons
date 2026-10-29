<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseGenerationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionOptionService;
use App\Services\TerminalContinuationService;
use App\Services\TragedyService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExhaustionFixture;
use Tests\Fixtures\GovernedExpressionFixture;
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
        $this->instrumentGateFixture($phase);
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

    public function test_authorised_instrument_encounters_its_existing_zero_target(): void
    {
        $fixture = new GovernedExpressionFixture;
        $fixture->phase = app(PhaseProgressionService::class)->advance($fixture->phase, $fixture->expression);
        $this->measure($fixture->expression, 1);
        $this->resolveExpressiveFieldFixture(1);
        app(ContributionDispatcher::class)->dispatch($fixture->actor, $fixture->phase,
            ['action' => 'instrument.execute', 'proposition_id' => $fixture->expression->id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/direct-terminal']]);
        $this->assertDatabaseHas('terminal_encounters', ['proposition_id' => $fixture->expression->id,
            'action' => 'instrument.execute', 'participant_id' => $fixture->actor->id]);
        $this->assertSame(1, $fixture->expression->evidenceExecutions()->count());
    }

    public static function attestationResults(): array
    {
        return [[1.0, true], [.5, false]];
    }

    #[DataProvider('attestationResults')]
    public function test_authorised_attestation_observes_resulting_not_previous_charge(float $result, bool $terminal): void
    {
        $fixture = new GovernedExpressionFixture;
        $fixture->writePersist();
        $this->measure($fixture->expression, 1);
        $this->resolveExpressiveFieldFixture($result);
        app(ContributionDispatcher::class)->dispatch($fixture->actor, $fixture->phase,
            ['action' => 'attestation.create', 'proposition_id' => $fixture->expression->id, 'direction' => 'support']);
        $this->assertSame($terminal, DB::table('terminal_encounters')->where('proposition_id', $fixture->expression->id)->exists());
        $this->assertSame(1, $fixture->expression->effectiveAttestations()->count());
    }

    public function test_authorised_expressive_traverse_encounters_an_already_zero_write(): void
    {
        $fixture = new GovernedExpressionFixture;
        $dispatcher = app(ContributionDispatcher::class);
        $persist = $fixture->phase->replicate();
        $persist->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $fixture->expression->id, 'write_register' => $fixture->phase->cycle->contextHorizon->domain->registerFor(PhaseKind::Persist)])->save();
        $dispatcher->dispatch($persist->cycle->participant, $persist,
            ['action' => 'instrument.execute', 'proposition_id' => $fixture->expression->id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/traverse-terminal']]);
        $dispatcher->dispatch($fixture->actor, $fixture->phase,
            ['action' => 'attestation.create', 'proposition_id' => $fixture->expression->id, 'direction' => 'support']);
        // Structural revisit component: real Cycle ownership and earlier effective
        // Attestation. This is not a claim that a full intervening Cycle ran.
        $cycle = (new Cycle)->forceFill(['participant_id' => $fixture->actor->id,
            'sequence' => (int) $fixture->actor->cycles()->max('sequence') + 1, 'entry_committal_id' => $fixture->origin->id,
            'context_horizon_id' => $fixture->phase->cycle->context_horizon_id]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
            'gate_proposition_id' => $fixture->origin->id, 'write_register' => $fixture->expression->register, 'started_at' => now()]);
        $phase->save();
        $this->measure($fixture->expression, 1);
        $dispatcher->dispatch($fixture->actor, $phase,
            ['action' => 'proposition.traverse', 'proposition_id' => $fixture->expression->id]);
        $this->assertDatabaseHas('terminal_encounters', ['proposition_id' => $fixture->expression->id,
            'phase_id' => $phase->id, 'action' => 'proposition.traverse']);
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

    public function test_resolution_keeps_an_ordinary_instrument_on_a_nonterminal_sibling(): void
    {
        $first = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $sibling = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($first);
        $this->resolveExpressiveFieldFixture(.5);
        $persist = $this->phase($sibling, PhaseKind::Persist);
        $this->assertContains($sibling->id, app(PropositionOptionService::class)
            ->instrumentOptionsFor($persist)->pluck('id'));
        app(ContributionDispatcher::class)->dispatch($persist->cycle->participant, $persist,
            ['action' => 'instrument.execute', 'proposition_id' => $sibling->id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/surviving-route']]);
        $this->assertDatabaseCount('terminal_encounters', 1);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame(1, $sibling->evidenceExecutions()->count());
    }

    public function test_later_terminal_encounter_cannot_close_a_third_surviving_branch(): void
    {
        $first = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $later = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $survivor = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($first);
        $tragedy = Tragedy::sole()->getAttributes();
        $this->encounter($later);
        $this->assertDatabaseCount('terminal_encounters', 2);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertFalse(app(TerminalContinuationService::class)->closed($survivor));
        $this->assertSame($tragedy, Tragedy::sole()->getAttributes());
        $this->assertSame(1, Receipt::where('type', 'tragedy')->count());
    }

    /** Persisted Commit topology and controlled fields; not an end-to-end Commit lifecycle. */
    public function test_descendant_exhaustion_closes_only_its_parent_route_until_the_sibling_terminates(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $child = $this->commitFrom($source);
        $first = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $sibling = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $childHead = $this->node($this->phase($child, PhaseKind::Distinguish), $child);
        $this->encounter($first);
        $parentTragedy = Tragedy::sole()->getAttributes();
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->encounter($childHead);
        $this->assertTrue(app(TerminalContinuationService::class)->closed($source));
        $this->assertFalse(app(TerminalContinuationService::class)->closed($sibling));
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $child->id]);
        $this->assertDatabaseMissing('commit_region_exhaustions', ['governing_committal_id' => $this->origin->id]);
        $this->encounter($sibling);
        $this->assertDatabaseCount('commit_region_exhaustions', 2);
        $this->assertSame($parentTragedy, Tragedy::where('governing_committal_id', $this->origin->id)->sole()->getAttributes());
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
    }

    /** A terminal old source cannot erase a previously established Commit exit. */
    public function test_live_commit_exit_survives_its_source_then_recursively_closes_the_resolving_parent(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $child = $this->commitFrom($source);
        $childHead = $this->node($this->phase($child, PhaseKind::Distinguish), $child);
        $this->encounter($source);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($child));
        $this->encounter($childHead);
        $this->assertDatabaseCount('commit_region_exhaustions', 2);
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $this->origin->id,
            'descendant_exhaustion_id' => $child->id]);
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
    }

    /** Controlled multi-parent topology verifies persisted route propagation, not Reconcile authorisation. */
    public function test_multi_parent_terminal_join_preserves_an_independent_causal_route(): void
    {
        $left = $this->node($this->phase($this->origin, PhaseKind::Disperse), $this->origin);
        $right = $this->node($this->phase($this->origin, PhaseKind::Disperse), $this->origin);
        $join = $this->node($this->phase($left, PhaseKind::Mediate), $left);
        (new Supersession)->forceFill(['phase_id' => $join->phase_id,
            'source_proposition_id' => $right->id, 'target_proposition_id' => $join->id, 'type' => 'reconcile'])->save();
        $sibling = $this->node($this->phase($right, PhaseKind::Mediate), $right);
        $this->encounter($join);
        $service = app(TerminalContinuationService::class);
        $this->assertTrue($service->closed($left));
        $this->assertFalse($service->closed($right));
        $this->assertFalse($service->closed($sibling));
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->encounter($sibling);
        $this->assertTrue($service->closed($right));
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }

    public function test_exhausted_region_plan_offers_rest_only(): void
    {
        $target = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($target);
        $plan = app(InputPlanService::class)->forPhase($target->phase->cycle->participant, $target->phase);
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $this->assertSame([], $plan['traversal_options']);
        $this->assertSame([], $plan['instrument_options']);
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
        $this->instrumentGateFixture($sibling->phase);
        $this->encounter($target);
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($sibling->phase->cycle->participant, $sibling->phase,
                ['action' => 'proposition.variant', 'statement' => 'Stale client']);
            $this->fail('Resolution must reject a stale Variant.');
        } catch (DomainException) {
            $this->assertSame($before, Proposition::count());
        }
    }

    public function test_resolution_revokes_a_previously_authorised_revision_elsewhere_in_the_region(): void
    {
        $gate = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $prior = $this->phase($gate, PhaseKind::Persist);
        $write = $this->node($prior, $gate);
        $this->explore($write);
        $prior->forceFill(['completed_at' => now()])->save();
        $prior->cycle->forceFill(['completed_at' => now()])->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $prior->cycle->participant_id,
            'sequence' => 2, 'entry_committal_id' => $this->origin->id,
            'context_horizon_id' => $prior->cycle->context_horizon_id]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Persist,
            'gate_proposition_id' => $gate->id, 'write_register' => $prior->write_register, 'started_at' => now()]);
        $phase->save();
        $actor = $cycle->participant;
        $this->instrumentGateFixture($phase);
        $options = app(PropositionOptionService::class);
        $this->assertContains($write->id, $options->revisionOptionsFor($phase, $actor)->pluck('id'));
        $terminal = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($terminal);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertFalse(app(TerminalContinuationService::class)->closed($write));
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertTrue($options->revisionOptionsFor($phase, $actor, $context)->isEmpty());
        }
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($actor, $phase,
                ['action' => 'proposition.revise', 'proposition_id' => $write->id, 'statement' => 'Stale revision']);
            $this->fail('Resolution must revoke the earlier revision authority.');
        } catch (DomainException) {
            $this->assertSame($before, Proposition::count());
        }
    }

    public function test_resolution_does_not_restrict_generation_in_a_live_descendant_region(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $child = $this->commitFrom($source);
        $phase = $this->phase($child, PhaseKind::Distinguish);
        $this->instrumentGateFixture($phase);
        $terminal = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->encounter($terminal);
        $this->assertTrue(app(PhaseGenerationService::class)->available($phase, 'proposition.variant'));
        $this->resolveExpressiveFieldFixture(.5);
        $write = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Independent descendant exploration']);
        $this->assertSame($child->id, $write->phase->cycle->entry_committal_id);
        $this->assertDatabaseCount('tragedies', 1);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_terminal_committed_source_is_not_reintroduced_as_a_bias_traverse_target(): void
    {
        $gate = $this->node($this->phase($this->origin, PhaseKind::Mediate), $this->origin);
        $child = $this->commitFrom($gate);
        $source = $child->parents()->wherePivot('type', 'commit')->sole();
        $sibling = $this->node($this->phase($gate, PhaseKind::Bias), $gate);
        $phase = $this->phase($gate, PhaseKind::Bias);
        $options = app(PropositionOptionService::class);
        $this->assertContains($source->id, $options->traversalOptionsFor($phase)->pluck('id'));
        $snapshot = $child->commitmentSnapshot->getAttributes();
        $this->encounter($source);
        $this->assertFalse(app(TerminalContinuationService::class)->closed($gate));
        $this->assertFalse(app(TerminalContinuationService::class)->closed($sibling));
        $this->assertFalse(app(ExhaustionService::class)->unavailable($child));
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertNotContains($source->id, $options->traversalOptionsFor($phase, $context)->pluck('id'));
            $this->assertTrue($options->instrumentOptionsFor($phase, $context)->isEmpty(), 'Bias never offers Instrumentation');
        }
        $this->assertSame($snapshot, $child->fresh()->commitmentSnapshot->getAttributes());
        $this->expectException(DomainException::class);
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.traverse', 'proposition_id' => $source->id]);
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
