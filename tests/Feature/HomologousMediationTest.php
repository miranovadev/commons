<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Models\User;
use App\Services\ActiveParticipantLineage;
use App\Services\BiasGateService;
use App\Services\BiasReceiptAuthorityService;
use App\Services\ChiralityGateService;
use App\Services\CommitRegionService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\CorrespondenceGateService;
use App\Services\ExhaustedContinuationService;
use App\Services\ExplorationPositionService;
use App\Services\GraphProjectionService;
use App\Services\HomeostaticDirectionService;
use App\Services\HomologousMediationService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\MediationReadService;
use App\Services\Observables\ObservableService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptLayout;
use App\Services\ParticipationService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseProgressionService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use App\Services\ReceiptContextService;
use App\Services\ReconciliationOptionService;
use App\Services\RegionalExhaustionMembership;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\AssertsLatePhaseContract;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\Support\RefreshIntegrationDatabase as RefreshDatabase;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class HomologousMediationTest extends TestCase
{
    use IsolatesClosureOutput;
    use AssertsLatePhaseContract;
    use RefreshDatabase;

    private Participant $actor;

    private Proposition $a;

    private Proposition $b;

    private Proposition $readA;

    private Proposition $readB;

    private Proposition $gateA;

    private Proposition $gateB;

    private Phase $mediate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'foreign-mediate', 'title' => 'Foreign Mediate', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
        $origin = $this->committedCoordinate(null, 'emergence');
        $this->a = $this->committedCoordinate($origin, 'settlement');
        $this->b = $this->committedCoordinate($origin, 'settlement');
        [$this->readA, $this->gateA] = $this->pair($this->a);
        [$this->readB, $this->gateB] = $this->pair($this->b);
        // Leave exactly the acting Cycle open for the public contribution path.
        Phase::query()->update(['completed_at' => now()]);
        Cycle::query()->update(['completed_at' => now()]);
        $disperse = $this->phase($this->a, $this->readA, PhaseKind::Disperse);
        $disperse->forceFill(['completed_at' => now()])->save();
        $this->mediate = new Phase;
        $this->mediate->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'gate_proposition_id' => $this->gateA->id, 'write_register' => 'stance', 'started_at' => now()])->save();
        $this->actor->forceFill(['current_proposition_id' => $this->gateA->id])->save();
        $this->measure($this->readA, -0.8);
        $this->measure($this->readB, -0.5);
        $this->measure($this->gateA, 0.8);
        $this->measure($this->gateB, 0.9);
    }

    public function test_boundary_reconcile_belongs_to_the_acting_region_not_its_foreign_operand(): void
    {
        $this->resolveExpressiveFieldFixture(1);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
        $profileBirth = app(\App\Services\Observables\PhaseProfileObservationService::class)->stored($target->id);
        $gateBirth = app(\App\Services\Observables\PhaseProfileObservationService::class)->stored($this->gateA->id);
        $this->assertSame($this->gateA->id, $profileBirth->preceding_phase_proposition_id);
        $this->assertSame((int) $profileBirth->birth_cycle_coordinate - (int) $gateBirth->birth_cycle_coordinate,
            (int) $profileBirth->cycle_interval);
        $tragedy = Tragedy::sole();
        $this->assertSame($target->id, $tragedy->terminal_proposition_id);
        $this->assertSame($this->a->id, $tragedy->governing_committal_id);
        $this->assertSame($this->mediate->cycle->context_horizon_id, $tragedy->context_horizon_id);
        $this->assertNotSame($this->b->id, $tragedy->governing_committal_id);
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id], $target->parents()->pluck('propositions.id')->all());
    }

    public function test_foreign_operands_are_exposed_without_importing_receipts_or_movement(): void
    {
        $receipt = $this->loadedReceiptFixture($this->gateB);
        $this->measure($this->gateB, .9);
        $context = new ParticipationContext($this->mediate);
        $this->assertTrue($context->propositions->has($this->gateB->id));
        $this->assertFalse(app(ReceiptContextService::class)->forPhase($this->mediate, $context)->contains('id', $receipt->id));
        $service = app(HomologousMediationService::class);
        foreach ([null, $context] as $readModel) {
            $pool = $service->poolFor($this->mediate, $readModel);
            $this->assertSame([$this->readB->id], $pool['reads']->pluck('id')->all());
            $this->assertSame([$this->gateB->id], $pool['gates']->pluck('id')->all());
        }
        $this->assertFalse(app(ReceiptContextService::class)->gateOptions($this->mediate, $context)->contains('id', $receipt->id));
        $this->assertNotSame($this->gateA->phase->cycle->context_horizon_id, $this->gateB->phase->cycle->context_horizon_id);
        $this->assertSame([$this->readA->id], app(MediationReadService::class)->visibleReadsFor($this->mediate, $context)->pluck('id')->all());
        $this->assertSame([$this->gateB->id], app(ReconciliationOptionService::class)->forPhase($this->mediate, $context)->pluck('id')->all());
        $this->assertFalse(app(PropositionOptionService::class)->traversalOptionsFor($this->mediate, $context)->contains('id', $this->gateB->id));
        $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
    }

    public function test_retained_read_direction_controls_eligibility_including_zero_hysteresis(): void
    {
        $service = app(HomologousMediationService::class);
        $this->measure($this->readB, 0);
        $this->assertSame([$this->gateB->id], $service->poolFor($this->mediate)['gates']->pluck('id')->all());
        $this->measure($this->readB, 0.5);
        $this->measure($this->gateB, -0.8);
        $this->assertTrue($service->poolFor($this->mediate)['gates']->isEmpty());
        $this->measure($this->readB, 0);
        $this->assertTrue($service->poolFor($this->mediate)['gates']->isEmpty());
        $this->measure($this->readA, 0.5);
        $this->assertSame([$this->gateB->id], $service->poolFor($this->mediate)['gates']->pluck('id')->all());
    }

    public function test_population_can_oppose_either_causal_read_direction_without_vetoing_the_local_operand(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        $c = $this->committedCoordinate($origin, 'settlement');
        [$readC, $gateC] = $this->pair($c);
        $local = $this->expression($this->a, $this->readA, PhaseKind::Disperse);
        foreach ([1.0, -1.0] as $causalSign) {
            $this->measure($this->readA, .8 * $causalSign);
            $this->measure($this->readB, -.7 * $causalSign);
            $this->measure($readC, -.7 * $causalSign);
            $context = new ParticipationContext($this->mediate->fresh());
            $pool = app(HomologousMediationService::class)->poolFor($this->mediate, $context);
            $this->assertEqualsCanonicalizing([$this->gateB->id, $gateC->id], $pool['gates']->pluck('id')->all());
            $this->assertEqualsCanonicalizing([$local->id, $this->gateB->id, $gateC->id],
                app(ReconciliationOptionService::class)->forPhase($this->mediate, $context)->pluck('id')->all());
            $this->assertSame([$this->readA->id], app(MediationReadService::class)
                ->visibleReadsFor($this->mediate, $context)->pluck('id')->all());
            $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
        }
    }

    public function test_matching_causal_read_is_one_member_and_non_dominant_homologues_are_not_added(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        [$readC, $gateC] = $this->pair($this->committedCoordinate($origin, 'settlement'));
        $this->measure($this->readA, .6);
        $this->measure($this->readB, .5);
        $this->measure($readC, -.4);
        $context = new ParticipationContext($this->mediate);
        $structure = app(HomologousMediationService::class)->structuralPoolFor($this->mediate, $context);
        $this->assertCount(3, $structure['population']);
        $this->assertSame(1, $structure['population']->where('id', $this->readA->id)->count());
        $this->assertSame([$this->gateB->id], app(ReconciliationOptionService::class)
            ->forPhase($this->mediate, $context)->pluck('id')->all());
        $this->assertFalse(app(ReconciliationOptionService::class)->forPhase($this->mediate, $context)->contains('id', $gateC->id));
    }

    public function test_tied_population_uses_correspondence_default_and_preserves_the_local_route(): void
    {
        $local = $this->expression($this->a, $this->readA, PhaseKind::Disperse);
        foreach ([1.0, -1.0] as $memberSign) {
            $this->measure($this->readA, -.5 * $memberSign);
            $this->measure($this->readB, .5 * $memberSign);
            // The second observation has all-zero values but retains member directions.
            foreach ([false, true] as $zeroMembers) {
                if ($zeroMembers) {
                    $this->measure($this->readA, 0);
                    $this->measure($this->readB, 0);
                }
                $context = new ParticipationContext($this->mediate);
                $foreign = $memberSign > 0 ? [$this->gateB->id] : [];
                $this->assertSame($foreign, app(HomologousMediationService::class)
                    ->poolFor($this->mediate, $context)['gates']->pluck('id')->all());
                $this->assertEqualsCanonicalizing([$local->id, ...$foreign], app(ReconciliationOptionService::class)
                    ->forPhase($this->mediate, $context)->pluck('id')->all());
                $this->assertSame([$this->readA->id], app(MediationReadService::class)
                    ->visibleReadsFor($this->mediate, $context)->pluck('id')->all());
                $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
            }
        }
    }

    public function test_same_region_homologous_reads_can_add_dominant_operands_beside_the_local_causal_route(): void
    {
        [$readC, $gateC] = $this->pair($this->a);
        $local = $this->expression($this->a, $this->readA, PhaseKind::Disperse);
        $this->measure($this->readA, .2);
        $this->measure($this->readB, -.7);
        $this->measure($readC, -.7);
        $this->assertEqualsCanonicalizing([$local->id, $this->gateB->id, $gateC->id],
            app(ReconciliationOptionService::class)->forPhase($this->mediate)->pluck('id')->all());
    }

    public function test_homologous_local_operands_keep_their_evidence_while_foreign_operands_do_not_import_it(): void
    {
        [$readC, $gateC] = $this->pair($this->a);
        $localReceipt = $this->loadedReceiptFixture($gateC);
        $foreignReceipt = $this->loadedReceiptFixture($this->gateB);
        $this->measure($gateC, .8);
        $this->measure($this->gateB, .9);
        $this->measure($this->readA, .2);
        $this->measure($this->readB, -.7);
        $this->measure($readC, -.7);
        $this->publishPotentialFixture($this->actor);
        $context = new ParticipationContext($this->mediate);
        $foreign = app(HomologousMediationService::class)->foreignGateIdsFor($this->mediate, $context);
        $this->assertSame([$this->gateB->id], $foreign->all());
        $receipts = app(ReceiptContextService::class)->forPhase($this->mediate, $context);
        $this->assertTrue($receipts->contains('id', $localReceipt->id));
        $this->assertFalse($receipts->contains('id', $foreignReceipt->id));
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->mediate);
        $gates = collect($plan['gate_options'])->keyBy('id');
        $this->assertContains($localReceipt->id, array_column($gates[$gateC->id]['receipts'], 'id'));
        $this->assertNotEmpty($gates[$gateC->id]['instruments']);
        $this->assertSame([], $gates[$this->gateB->id]['receipts']);
        $this->assertSame([], $gates[$this->gateB->id]['instruments']);
    }

    public function test_a_read_without_an_available_gate_still_contributes_to_the_structural_population(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        $c = $this->committedCoordinate($origin, 'settlement');
        $readOnly = $this->expression($c, $c, PhaseKind::Constrain);
        $this->measure($this->readA, .2);
        $this->measure($this->readB, .2);
        $this->measure($readOnly, -.9);
        $this->assertTrue(app(HomologousMediationService::class)->poolFor($this->mediate)['gates']->isEmpty());
        $this->measure($readOnly, 0);
        $this->assertSame([$this->gateB->id], app(HomologousMediationService::class)->poolFor($this->mediate)['gates']->pluck('id')->all());
    }

    public function test_mixed_entry_creating_domains_do_not_restrict_same_domain_same_depth_reads(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        $a = $this->committedCoordinate($this->a, 'settlement');
        $b = $this->committedCoordinate($this->committedCoordinate($origin, 'emergence'), 'settlement');
        [$readA, $gateA] = $this->pair($a);
        [$readB, $gateB] = $this->pair($b);
        $phase = $this->mediateAt($a, $readA, $gateA);
        $this->measure($readA, -.4);
        $this->measure($readB, -.6);
        $this->assertNotSame($a->phase->cycle->contextHorizon->domain, $b->phase->cycle->contextHorizon->domain);
        $this->assertSame($readA->phase->cycle->contextHorizon->domain, $readB->phase->cycle->contextHorizon->domain);
        $this->assertSame([$gateB->id], app(ReconciliationOptionService::class)->forPhase($phase)->pluck('id')->all());
    }

    public function test_stale_population_change_rejects_a_previously_exposed_operand_even_when_its_read_is_unchanged(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        [$readC] = $this->pair($this->committedCoordinate($origin, 'settlement'));
        $this->measure($this->readA, .2);
        $this->measure($this->readB, .4);
        $this->measure($readC, .2);
        $this->assertTrue(app(ReconciliationOptionService::class)->forPhase($this->mediate)->contains('id', $this->gateB->id));
        $this->measure($readC, -.9);
        $this->assertFalse(app(ReconciliationOptionService::class)->forPhase($this->mediate)->contains('id', $this->gateB->id));
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
                ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
            $this->fail('Population direction must be recomputed during write authorization.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
            $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
        }
    }

    public function test_distinct_homologous_regions_in_the_same_horizon_are_also_discovered(): void
    {
        $a = $this->committedCoordinate($this->a, 'settlement');
        $b = $this->committedCoordinate($this->a, 'settlement');
        [$readA, $gateA] = $this->pair($a);
        [$readB, $gateB] = $this->pair($b);
        $disperse = $this->phase($a, $readA, PhaseKind::Disperse);
        $disperse->forceFill(['completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'gate_proposition_id' => $gateA->id, 'write_register' => 'stance', 'started_at' => now()])->save();
        $this->assertSame($gateA->phase->cycle->context_horizon_id, $gateB->phase->cycle->context_horizon_id);
        $pool = app(HomologousMediationService::class)->poolFor($phase);
        $this->assertSame([$readB->id], $pool['reads']->pluck('id')->all());
        $this->assertSame([$gateB->id], $pool['gates']->pluck('id')->all());
    }

    public function test_wrong_phase_register_and_non_immediate_children_are_excluded(): void
    {
        $this->expression($this->b, $this->readB, PhaseKind::Distinguish); // Signal, but wrong producing phase.
        $this->expression($this->b, $this->readB, PhaseKind::Disperse, 'stance');
        $middle = $this->expression($this->b, $this->readB, PhaseKind::Mediate);
        $this->expression($this->b, $middle, PhaseKind::Disperse);
        $this->assertSame([$this->gateB->id], app(HomologousMediationService::class)->poolFor($this->mediate)['gates']->pluck('id')->all());
    }

    public function test_wrong_read_position_and_non_homologous_regions_are_excluded(): void
    {
        $wrong = $this->expression($this->b, $this->b, PhaseKind::Bias);
        $this->expression($this->b, $wrong, PhaseKind::Disperse);
        $badRegister = $this->expression($this->b, $this->b, PhaseKind::Constrain, 'stance');
        $this->expression($this->b, $badRegister, PhaseKind::Disperse);
        $deeper = $this->committedCoordinate($this->b, 'settlement');
        $this->pair($deeper);
        $unrelated = $this->committedCoordinate(null, 'settlement');
        $this->pair($unrelated);
        $otherDomain = $this->committedCoordinate($this->a, 'emergence');
        $this->pair($otherDomain);
        $pool = app(HomologousMediationService::class)->poolFor($this->mediate);
        $this->assertSame([$this->readB->id], $pool['reads']->pluck('id')->all());
        $this->assertSame([$this->gateB->id], $pool['gates']->pluck('id')->all());
    }

    public function test_commit_consumed_gate_is_unavailable_but_other_hard_replacements_remain_reusable(): void
    {
        $replacement = $this->expression($this->b, $this->gateB, PhaseKind::Mediate, edgeType: 'reconcile');
        $service = app(HomologousMediationService::class);
        $this->assertSame([$this->gateB->id], $service->poolFor($this->mediate)['gates']->pluck('id')->all());
        $commit = $this->committedCoordinate($this->b, 'settlement');
        $this->edge($this->gateB, $commit, 'commit');
        $this->assertTrue($service->poolFor($this->mediate)['gates']->isEmpty());
        $this->assertNotNull($replacement->fresh());
    }

    public function test_missing_causal_anchor_and_non_mediate_phase_fail_closed(): void
    {
        $service = app(HomologousMediationService::class);
        $this->assertTrue($service->poolFor($this->gateA->phase)['gates']->isEmpty());
        $this->mediate->cycle->phases()->where('kind', PhaseKind::Disperse->value)->update(['completed_at' => null]);
        $this->assertTrue($service->poolFor($this->mediate)['reads']->isEmpty());
    }

    public function test_query_count_does_not_grow_per_foreign_read_or_gate(): void
    {
        $counts = [];
        foreach ([1, 6] as $count) {
            if ($count === 6) {
                foreach (range(1, 5) as $index) {
                    $this->pair($this->b);
                }
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $pool = app(HomologousMediationService::class)->poolFor($this->mediate->fresh());
                $counts[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount($count, $pool['gates']);
        }
        $this->assertSame($counts[0], $counts[1], 'Adding independent foreign candidates must not add per-candidate queries.');
    }

    public function test_public_cross_horizon_reconcile_preserves_ownership_gate_route_and_foreign_input_geometry(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        $c = $this->committedCoordinate($origin, 'settlement');
        $populationRead = $this->expression($c, $c, PhaseKind::Constrain);
        $receipt = $this->loadedReceiptFixture($this->gateB);
        $this->measure($this->gateB, .9);
        $this->measure($this->readA, .8);
        $this->measure($this->readB, -.7);
        $this->measure($populationRead, -.7);
        // Additional measurement fixtures must not become the actor's live Phase.
        Phase::where('id', '!=', $this->mediate->id)->update(['completed_at' => now()]);
        Cycle::where('id', '!=', $this->mediate->cycle_id)->update(['completed_at' => now()]);
        $this->publishPotentialFixture($this->actor);
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->mediate);
        $this->assertSame([$this->readA->id], array_column($plan['read_options'], 'id'));
        $this->assertSame([$this->gateB->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame([$this->gateB->id], $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
        $this->assertNotContains($receipt->id, array_column($plan['receipts'], 'id'));
        $this->assertSame([], $plan['gate_options'][0]['receipts']);
        $this->assertSame([], $plan['gate_options'][0]['instruments']);
        $this->assertNotContains($this->gateB->id, array_column($plan['traversal_options'], 'id'));
        $horizonA = $this->mediate->cycle->context_horizon_id;
        $horizonB = $this->gateB->phase->cycle->context_horizon_id;
        $foreignBefore = $this->gateB->fresh()->getAttributes();
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id];
        app(ParticipationService::class)->contribute($this->actor, $this->mediate->id, $input);
        $result = Proposition::findOrFail($this->actor->fresh()->current_proposition_id);
        $this->assertSame($this->mediate->id, $result->phase_id);
        $this->assertSame($this->a->id, $result->phase->cycle->entry_committal_id);
        $this->assertSame($this->gateA->id, $result->phase->gate_proposition_id);
        $this->assertSame($horizonA, $result->phase->cycle->context_horizon_id);
        $this->assertSame($this->a->id, app(CommitRegionService::class)->seedFor($result)->id);
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id], $result->parents()->pluck('propositions.id')->all());
        $lineage = app(ActiveParticipantLineage::class)->endingAt($result)->pluck('id');
        $this->assertContains($this->a->id, $lineage);
        $this->assertNotContains($this->b->id, $lineage);
        $this->assertNotContains($this->gateB->id, $lineage);
        $this->assertSame($foreignBefore, $this->gateB->fresh()->getAttributes());
        $this->assertSame($this->b->id, app(CommitRegionService::class)->seedFor($this->gateB)->id);
        app(ContextHorizonProjector::class)->rebuild($this->actor->substrate);
        $this->assertSame($horizonA, $result->fresh()->contextHorizonMembership->context_horizon_id);
        $this->assertSame($horizonB, $this->gateB->fresh()->contextHorizonMembership->context_horizon_id);
        $this->publishPotentialFixture($this->actor);
        $local = app(PotentialProjectionService::class)->region($horizonA)['local'];
        $this->assertContains([$this->gateB->id, $result->id, 'reconcile'], $local['foreign_inputs']);
        $this->assertArrayNotHasKey($this->gateB->id, $local['nodes']);
        $this->assertNotContains([$this->gateB->id, $result->id, 'reconcile'], $local['edges']);
        app(GraphProjectionService::class)->load($this->actor->substrate, collect([$result]));
        $this->assertSame($this->a->id, $result->getAttribute('field')['committal_proposition_id']);
        $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $this->gateB->id, 'target_proposition_id' => $result->id, 'type' => 'reconcile']);
        User::factory()->create(); // Bootstrap the real graph API.
        $substrate = $this->actor->substrate;
        $substrate->forceFill(['visibility' => 'public'])->save();
        foreach (['active', 'history'] as $view) {
            $nodes = collect($this->getJson("/api/substrates/{$substrate->slug}/propositions?view={$view}")
                ->assertOk()->json('data'))->keyBy('id');
            $this->assertContains($this->gateB->id, $nodes[$result->id]['parent_ids']);
            $this->assertContains(['id' => $result->id, 'type' => 'reconcile'], $nodes[$this->gateB->id]['children']);
            $this->assertSame($this->a->id, $nodes[$result->id]['field']['committal_proposition_id']);
            $this->assertSame($horizonA, $nodes[$result->id]['context_horizon']['id']);
            $this->assertSame($this->b->id, $nodes[$this->gateB->id]['field']['committal_proposition_id']);
            $this->assertSame($horizonB, $nodes[$this->gateB->id]['context_horizon']['id']);
        }
        $this->assertFalse(app(ReconciliationOptionService::class)->forPhase($this->mediate->fresh())->contains('id', $this->gateB->id));
    }

    public function test_local_and_foreign_counterparts_are_combined_and_stale_foreign_direction_is_rechecked(): void
    {
        $local = $this->expression($this->a, $this->readA, PhaseKind::Disperse);
        $this->assertEqualsCanonicalizing([$local->id, $this->gateB->id],
            app(ReconciliationOptionService::class)->forPhase($this->mediate)->pluck('id')->all());
        // Keep the population negative: a tie now correctly defaults positive.
        $this->measure($this->readB, 0.5);
        $this->assertSame([$local->id], app(ReconciliationOptionService::class)->forPhase($this->mediate)->pluck('id')->all());
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
                ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
            $this->fail('A stale foreign Read direction must not authorize a write.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
        }
    }

    public function test_same_horizon_foreign_result_does_not_become_the_operand_regions_mediate_write(): void
    {
        $a = $this->committedCoordinate($this->a, 'settlement');
        $b = $this->committedCoordinate($this->a, 'settlement');
        [$readA, $gateA] = $this->pair($a);
        [$readB, $gateB] = $this->pair($b);
        // Isolate option scope with a real two-parent representation. Public
        // contribution execution is covered by the cross-Horizon test above.
        $foreignResult = $this->expression($a, $gateA, PhaseKind::Mediate, edgeType: 'reconcile');
        $this->edge($gateB, $foreignResult, 'reconcile');
        $localResult = $this->expression($b, $gateB, PhaseKind::Mediate, edgeType: 'reconcile');
        $disperse = $this->phase($b, $readB, PhaseKind::Disperse);
        $disperse->forceFill(['completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'gate_proposition_id' => $gateB->id, 'write_register' => 'stance', 'started_at' => now()])->save();
        $this->assertSame($foreignResult->phase->cycle->context_horizon_id, $phase->cycle->context_horizon_id);
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $writes = app(PropositionOptionService::class)->writeOptionsFor($phase, $context);
            $this->assertSame([$localResult->id], $writes->pluck('id')->all());
            $this->assertFalse($writes->contains('id', $foreignResult->id));
        }
        $this->assertEqualsCanonicalizing([$gateA->id, $gateB->id], $foreignResult->parents()->pluck('propositions.id')->all());
    }

    public function test_foreign_exhaustion_uses_complete_authoritative_horizon_and_current_epochs(): void
    {
        $options = app(ReconciliationOptionService::class);
        $this->assertTrue($options->forPhase($this->mediate)->contains('id', $this->gateB->id));
        [$terminal, $middle, $tragedy] = $this->exhaustContinuation($this->b, $this->gateB);
        $horizonB = $this->gateB->phase->cycle->context_horizon_id;
        $this->assertNotSame($horizonB, app(ContextHorizonProjector::class)->forEntry($terminal)->id);
        $context = new ParticipationContext($this->mediate->fresh());
        $this->assertFalse($context->propositions->has($middle->id));
        $this->assertFalse($context->propositions->has($terminal->id));
        $this->assertTrue(app(HomologousMediationService::class)->poolFor($this->mediate, $context)['gates']->contains('id', $this->gateB->id));
        $this->assertFalse(app(ExhaustedContinuationService::class)->ids($this->mediate)->has($this->gateB->id));
        foreach ([null, $context] as $readModel) {
            $this->assertFalse($options->forPhase($this->mediate, $readModel)->contains('id', $this->gateB->id));
        }
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
                ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
            $this->fail('A foreign exhausted continuation cannot be reconciled.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
            $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
        }

        // A live alternative in B, absent from A's comparison context, keeps B's
        // Gate eligible. B's exhaustion proof must not retire the entire Horizon.
        $live = $this->expression($this->b, $this->gateB, PhaseKind::Mediate);
        $context = new ParticipationContext($this->mediate->fresh());
        $this->assertFalse($context->propositions->has($live->id));
        $this->assertTrue($options->forPhase($this->mediate, $context)->contains('id', $this->gateB->id));
        $this->assertFalse(app(ReceiptContextService::class)->forPhase($this->mediate, $context)->contains('id', $tragedy->receipt_id));
        $position = app(ExplorationPositionService::class)->forProposition($live);
        DB::table('continuation_failures')->insert([
            'proposition_id' => $live->id, 'context_horizon_id' => $horizonB,
            'structural_position' => $position['structural_position'], 'exploration_epoch' => 0,
            'phase_id' => $live->phase_id, 'failed_at' => now(),
        ]);
        $this->assertFalse($options->forPhase($this->mediate->fresh())->contains('id', $this->gateB->id));
        DB::table('horizon_exploration_states')->insert([...$position, 'epoch' => 1]);
        $this->assertTrue($options->forPhase($this->mediate->fresh())->contains('id', $this->gateB->id));
    }

    public function test_cross_horizon_directed_pairs_are_independent_and_used_pairs_do_not_reopen(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        $c = $this->committedCoordinate($origin, 'settlement');
        [, $gateC] = $this->pair($c);
        $options = app(ReconciliationOptionService::class);
        $dispatcher = app(ContributionDispatcher::class);
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id];
        $forward = $dispatcher->dispatch($this->actor, $this->mediate, $input);
        $originalActor = $this->actor;
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $originalActor->substrate_id, 'callsign' => 'competitor',
            'current_proposition_id' => $this->gateA->id])->save();
        // Fresh discovery still sees B structurally; authorization consumes only A→B.
        $freshA = $this->mediateAt($this->a, $this->readA, $this->gateA);
        $this->assertTrue(app(HomologousMediationService::class)->poolFor($freshA)['gates']->contains('id', $this->gateB->id));
        $this->assertSame([$gateC->id], $options->forPhase($freshA)->pluck('id')->all());
        $before = Proposition::count();
        try {
            $dispatcher->dispatch($this->actor, $freshA, $input);
            $this->fail('A used A→B pair must not reopen through foreign discovery.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
        }
        $distinct = $dispatcher->dispatch($this->actor, $freshA,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $gateC->id]);
        // B has its own participant and governed Cycle; no movement/handoff API is added.
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $originalActor->substrate_id, 'callsign' => 'reverse',
            'current_proposition_id' => $this->gateB->id])->save();
        $inversePhase = $this->mediateAt($this->b, $this->readB, $this->gateB);
        $this->assertTrue($options->forPhase($inversePhase)->contains('id', $this->gateA->id));
        $inverse = $dispatcher->dispatch($this->actor, $inversePhase,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateA->id]);
        $this->assertNotSame($forward->id, $inverse->id);
        $this->assertNotSame($forward->id, $distinct->id);
        $this->assertEqualsCanonicalizing($forward->parents->pluck('id')->all(), $inverse->parents->pluck('id')->all());
        $this->assertSame($this->a->id, $forward->phase->cycle->entry_committal_id);
        $this->assertSame($this->b->id, $inverse->phase->cycle->entry_committal_id);
        $this->assertSame($this->gateA->id, $forward->phase->gate_proposition_id);
        $this->assertSame($this->gateB->id, $inverse->phase->gate_proposition_id);
        $this->assertFalse($options->forPhase($inversePhase->fresh())->contains('id', $this->gateA->id));
        $this->assertFalse($options->forPhase($freshA->fresh())->contains('id', $this->gateB->id));
    }

    #[Group('query')]
    public function test_complete_plans_have_bounded_query_growth_across_foreign_horizons(): void
    {
        $counts = [];
        $origin = $this->a->phase->cycle->entryCommittal;
        foreach ([1, 6] as $count) {
            if ($count === 6) {
                foreach (range(1, 5) as $index) {
                    $this->pair($this->committedCoordinate($origin, 'settlement'));
                }
            }
            $this->publishPotentialFixture($this->actor);
            foreach (['cold', 'warm'] as $temperature) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $plan = app(InputPlanService::class)->forPhase($this->actor->fresh(), $this->mediate->fresh());
                    $counts[$count][$temperature] = count(DB::getQueryLog());
                } finally {
                    DB::disableQueryLog();
                }
                $this->assertCount($count, $plan['gate_options']);
            }
        }
        fwrite(STDERR, sprintf("FOREIGN HORIZON GROWTH: cold %d -> %d; warm %d -> %d\n",
            $counts[1]['cold'], $counts[6]['cold'], $counts[1]['warm'], $counts[6]['warm']));
        $this->assertLessThan(500, $counts[6]['cold']);
        $this->assertLessThan(200, $counts[6]['warm']);
        $this->assertSame($counts[1], $counts[6], 'Additional foreign Horizons must not add plan queries.');
    }

    #[Group('query')]
    public function test_exhausted_continuation_queries_are_batched_across_foreign_horizons(): void
    {
        $origin = $this->a->phase->cycle->entryCommittal;
        $closed = collect([$this->gateB]);
        $live = collect([$this->expression($this->b, $this->readB, PhaseKind::Disperse)]);
        $this->exhaustContinuation($this->b, $this->gateB);
        $counts = [];
        foreach ([1, 6] as $count) {
            if ($count === 6) {
                foreach (range(1, 5) as $index) {
                    $owner = $this->committedCoordinate($origin, 'settlement');
                    [$read, $gate] = $this->pair($owner);
                    $closed->push($gate);
                    $live->push($this->expression($owner, $read, PhaseKind::Disperse));
                    $this->exhaustContinuation($owner, $gate);
                }
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $options = app(ReconciliationOptionService::class)->forPhase($this->mediate->fresh());
                $counts[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
            $this->assertEqualsCanonicalizing($live->pluck('id')->all(), $options->pluck('id')->all());
            $horizons = $closed->map(fn ($gate) => $gate->phase->cycle->context_horizon_id);
            $batch = app(ExhaustedContinuationService::class)->idsForHorizons($horizons);
            foreach ($closed as $gate) {
                $single = app(ExhaustedContinuationService::class)->ids($gate->phase);
                $this->assertTrue($single->has($gate->id));
                $this->assertEquals($single, $batch->only($single->keys()->all()));
            }
        }
        fwrite(STDERR, sprintf("FOREIGN EXHAUSTION GROWTH: %d -> %d\n", ...$counts));
        $this->assertSame($counts[0], $counts[1], 'Foreign exhaustion must not query once per Horizon.');
    }

    public static function gateCoherence(): array
    {
        return [
            'positive coherent' => [.5, .5, true],
            'negative coherent' => [-.5, -.5, true],
            'positive negative' => [.5, -.5, false],
            'negative positive' => [-.5, .5, false],
        ];
    }

    #[DataProvider('gateCoherence')]
    #[Group('late-phase')]
    public function test_causal_gate_coherence_controls_write_without_removing_visibility(float $shape, float $profile, bool $allowed): void
    {
        $service = app(PropositionMeasurementService::class);
        foreach ([$this->gateA, $this->gateB] as $gate) {
            $service->project($gate, $shape, 0, 0, correspondenceComponents: new CorrespondenceValues($shape, $profile));
        }
        $this->publishPotentialFixture($this->actor);
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->mediate);
        $this->assertLatePhaseContract($plan, PhaseKind::Mediate, $this->gateA->id, $allowed,
            $shape > 0 ? 'positive' : 'negative', $profile > 0 ? 'positive' : 'negative');
        $this->assertSame($allowed, isset($plan['schema']['$defs']['actions']['proposition.reconcile']));
        $visible = app(ParticipantContextSerializer::class)->plan($plan);
        $this->assertContains($this->gateA->id, array_column($visible['gate_options'], 'id'));
        $this->assertSame($allowed, $visible['correspondence_admissibility']['admissible']);
        $packet = app(ParticipantContextSerializer::class)->inference(['status' => 'ready', 'plan' => $visible]);
        $prompt = app(ParticipantPromptLayout::class)->make($packet);
        $this->assertEquals($visible['correspondence_admissibility'], json_decode($prompt['user'], true)['plan']['correspondence_admissibility']);
        if (! $allowed) {
            $this->assertNotContains($this->gateB->id, array_column($visible['gate_options'], 'id'));
            $this->assertTrue($plan['rest_available']);
            $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
            $before = [Proposition::count(), Supersession::count()];
            try {
                app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
                    ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
                $this->fail('An inadmissible causal Gate cannot Write.');
            } catch (\DomainException) {
                $this->assertSame($before, [Proposition::count(), Supersession::count()]);
            }
        }
    }

    public function test_candidate_coherence_and_retained_pair_direction_are_checked_after_dominance(): void
    {
        $service = app(PropositionMeasurementService::class);
        $options = app(ReconciliationOptionService::class);
        $service->project($this->gateB, .8, 0, 0, correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $this->assertTrue($options->forPhase($this->mediate)->isEmpty());
        // Coherent Negative candidate remains incompatible with the Positive causal Gate.
        $this->measure($this->gateB, -.8);
        $this->assertTrue($options->forPhase($this->mediate)->isEmpty());
        $this->measure($this->gateA, -.8);
        $this->assertSame([$this->gateB->id], $options->forPhase($this->mediate)->pluck('id')->all());
        // Numeric zeros retain each negative component and the combined direction.
        foreach ([$this->gateA, $this->gateB] as $gate) {
            $service->project($gate, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(0, 0));
        }
        $this->assertSame([$this->gateB->id], $options->forPhase($this->mediate)->pluck('id')->all());
    }

    public function test_inadmissible_actionless_mediate_can_rest_with_zero_or_positive_potential(): void
    {
        app(PropositionMeasurementService::class)->project($this->gateA, .5, 0, 0,
            correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $this->publishPotentialFixture($this->actor);
        foreach ([0.0, .75] as $potential) {
            // Alter the disposable diagnostic only; action eligibility is unchanged.
            DB::table('potential_region_states')->where('context_horizon_id', $this->mediate->cycle->context_horizon_id)
                ->update(['remaining_potential' => $potential]);
            $plan = app(InputPlanService::class)->forPhase($this->actor, $this->mediate);
            $this->assertSame($potential, app(PotentialProjectionService::class)->region($this->mediate->cycle->context_horizon_id)['potential']);
            $this->assertTrue($plan['rest_available']);
            $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        }
    }

    public function test_pairing_uses_resolved_correspondence_not_the_local_component_mean(): void
    {
        app(PropositionMeasurementService::class)->project($this->gateB, -.2, 0, 0,
            correspondenceComponents: new CorrespondenceValues(.8, .8));
        $this->assertTrue(app(ReconciliationOptionService::class)->forPhase($this->mediate)->isEmpty());
    }

    public function test_stale_component_admissibility_is_rechecked_before_parent_edges_are_created(): void
    {
        $this->assertSame([$this->gateB->id], app(ReconciliationOptionService::class)->forPhase($this->mediate)->pluck('id')->all());
        app(PropositionMeasurementService::class)->project($this->gateB, .9, 0, 0,
            correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $before = [Proposition::count(), Supersession::count()];
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
                ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
            $this->fail('Stale component eligibility cannot authorize a Write.');
        } catch (\DomainException) {
            $this->assertSame($before, [Proposition::count(), Supersession::count()]);
        }
    }

    public function test_incoherent_members_still_determine_full_read_population_dominance(): void
    {
        [$readC, $gateC] = $this->pair($this->committedCoordinate($this->a->phase->cycle->entryCommittal, 'settlement'));
        $this->measure($this->readA, .2);
        $this->measure($this->readB, .2);
        app(PropositionMeasurementService::class)->project($readC, -.9, 0, 0,
            correspondenceComponents: new CorrespondenceValues(-.8, .8));
        app(PropositionMeasurementService::class)->project($gateC, .8, 0, 0,
            correspondenceComponents: new CorrespondenceValues(-.8, .8));
        $context = new ParticipationContext($this->mediate);
        $pool = app(HomologousMediationService::class)->structuralPoolFor($this->mediate, $context);
        $field = app(HomeostaticDirectionService::class)->forPopulation($this->readA, $pool['population'], 'correspondence', $context);
        $this->assertCount(3, $field->values);
        $this->assertSame('negative', $field->direction->value);
        $this->assertSame([$readC->id], app(HomologousMediationService::class)->poolFor($this->mediate, $context)['reads']->pluck('id')->all());
        $this->assertTrue(app(ReconciliationOptionService::class)->forPhase($this->mediate, $context)->isEmpty());
    }

    public function test_coherent_positive_inputs_produce_the_normal_result_without_sign_coercion(): void
    {
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
        $normal = app(ObservableService::class)->components($target);
        $actual = $target->fresh()->measurement;
        $this->assertSame($normal->correspondence, $actual->correspondence);
        $this->assertSame($normal->correspondenceComponents->shapeTopology, $actual->shape_topology);
        $this->assertSame($normal->correspondenceComponents->phaseProfile, $actual->phase_profile);
        // Phase Profile replaces Cardinal's negative result in this fixed history.
        // Do not change the authored population to recover the former sign.
        $this->assertSame(.0004164, $actual->correspondence);
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id], $target->parents()->pluck('propositions.id')->all());
    }

    #[Group('component-contract')]
    #[Group('late-phase')]
    public function test_admissible_mediate_can_reach_an_inadmissible_bias_without_outcome_coercion(): void
    {
        // Actual preceding-Domain evidence gives Orientation a sign; this test
        // does not rely on the old Positive primitive fallback at numeric zero.
        $origin = $this->a->phase->cycle->entryCommittal;
        $vote = new Attestation;
        $vote->forceFill(['phase_id' => $origin->phase_id, 'proposition_id' => $origin->id, 'direction' => 'support'])->save();
        app(LedgerService::class)->record($vote);
        // Additional genuine entries make the current attestation rate lower.
        foreach (range(1, 3) as $index) {
            $entrant = new Participant;
            $entrant->forceFill(['substrate_id' => $this->actor->substrate_id, 'callsign' => 'cadence-entrant-'.$index])->save();
            $entryCycle = new Cycle;
            $entryCycle->forceFill(['participant_id' => $entrant->id, 'sequence' => 1, 'context_horizon_id' => $this->mediate->cycle->context_horizon_id, 'entry_committal_id' => $this->a->id])->save();
        }
        $this->assertTrue(app(CorrespondenceGateService::class)->evaluate($this->gateA)['admissible']);
        $this->assertSame([$this->gateB->id], app(ReconciliationOptionService::class)->forPhase($this->mediate)->pluck('id')->all());
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->mediate,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
        $bias = app(PhaseProgressionService::class)->advance($this->mediate, $target);
        $this->assertSame(PhaseKind::Bias, $bias->kind);
        $this->groundFixture($target);
        app(PropositionMeasurementService::class)->recalculate($target);
        $diagnostic = app(ChiralityGateService::class)->evaluate($target->fresh());
        $this->assertFalse($diagnostic['admissible'], json_encode([$diagnostic, app(ObservableService::class)->components($target)->chiralityComponents]));
        // Ordinary local evidence authority is present; Chirality alone blocks the Write.
        $this->assertContains($target->id, app(BiasGateService::class)->forPhase($bias)->pluck('id'));
        $this->assertNotEmpty(app(BiasReceiptAuthorityService::class)->receiptsFor($bias, $target));
        $this->publishPotentialFixture($this->actor);
        $plan = app(InputPlanService::class)->forPhase($this->actor, $bias);
        $this->assertLatePhaseContract($plan, PhaseKind::Bias, $target->id, false, 'positive', 'negative');
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $this->assertSame($target->id, $bias->gate_proposition_id);
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id], $target->parents()->pluck('propositions.id')->all());
    }

    private function mediateAt(Proposition $owner, Proposition $read, Proposition $gate): Phase
    {
        $disperse = $this->phase($owner, $read, PhaseKind::Disperse);
        $disperse->forceFill(['completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'gate_proposition_id' => $gate->id, 'write_register' => 'stance', 'started_at' => now()])->save();

        return $phase;
    }

    /** Install immutable regional proof; the existing exhaustion lifecycle tests cover producing it. */
    private function exhaustContinuation(Proposition $owner, Proposition $gate): array
    {
        $middle = $this->expression($owner, $gate, PhaseKind::Mediate);
        $terminal = $this->committedCoordinate($owner, 'emergence');
        $this->edge($middle, $terminal, 'commit');
        $phase = $this->phase($terminal, $terminal, PhaseKind::Distinguish);
        $tragedy = $this->boundaryTragedyFixture($phase->gate);
        DB::table('commit_region_exhaustions')->insert([
            'governing_committal_id' => $terminal->id, 'context_horizon_id' => $phase->cycle->context_horizon_id,
            'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id,
            'phase_id' => $phase->id, 'terminal_proposition_id' => $terminal->id,
            'observed_field_version' => 1, 'exhausted_at' => now(),
        ]);
        app(RegionalExhaustionMembership::class)->publish($terminal->id, [$terminal->id]);

        return [$terminal, $middle, $tragedy];
    }

    private function pair(Proposition $governor): array
    {
        $read = $this->expression($governor, $governor, PhaseKind::Constrain);
        $gate = $this->expression($governor, $read, PhaseKind::Disperse);
        $this->measure($read, -0.5);

        return [$read, $gate];
    }

    private function expression(Proposition $governor, Proposition $parent, PhaseKind $kind, ?string $register = null, string $edgeType = 'variant'): Proposition
    {
        $phase = $this->phase($governor, $parent, $kind);
        if ($register !== null) {
            $phase->forceFill(['write_register' => $register])->save();
        }
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $register ?? $phase->write_register, 'statement' => 'Comparison fixture'])->save();
        app(LedgerService::class)->record($node);
        $this->edge($parent, $node, $edgeType);
        if ($kind === PhaseKind::Disperse) {
            // This fixture isolates operand scope/authority using explicitly coherent Gates.
            $this->measure($node, .8);
        }

        return $node;
    }

    private function edge(Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    private function measure(Proposition $node, float $value): void
    {
        app(PropositionMeasurementService::class)->project($node, $value, 0, 0,
            correspondenceComponents: new CorrespondenceValues($value, $value));
    }

    private function committedCoordinate(?Proposition $governor, string $after): Proposition
    {
        $phase = $this->phase($governor, $governor, PhaseKind::Order);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'committal', 'register' => $phase->write_register, 'statement' => 'Region seed'])->save();
        $entry = app(LedgerService::class)->record($node);
        $horizon = $phase->cycle->contextHorizon;
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $entry->id, 'supersession_id' => null, 'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
            'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $horizon->domain, 'direction_after' => $after,
            'depth' => 0, 'cadence' => 0, 'cadence_interval' => $governor === null ? null : 1,
            'calculator_version' => $governor === null ? 'origin-bootstrap-v1' : 'ownership-fixture', 'evaluated_at' => now()])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);

        return $node;
    }

    private function phase(?Proposition $governor, ?Proposition $gate, PhaseKind $kind, bool $malformed = false): Phase
    {
        $horizon = $governor === null ? (ContextHorizon::where('substrate_id', $this->actor->substrate_id)->whereNull('parent_context_horizon_id')->first()
            ?? app(ContextHorizonProjector::class)->currentFor($this->actor->substrate))
            : ($malformed ? $gate->phase->cycle->contextHorizon : app(ContextHorizonProjector::class)->forEntry($governor));
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->actor->id, 'sequence' => (int) $this->actor->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id, 'entry_committal_id' => $governor?->id]);
        if ($malformed) {
            Cycle::withoutEvents(fn () => $cycle->save());
        } else {
            $cycle->save();
        }
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $gate?->id,
            'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()])->save();

        return $phase;
    }
}
