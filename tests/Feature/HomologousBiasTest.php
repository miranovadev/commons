<?php

namespace Tests\Feature;

use App\Domain\Observables\ChiralityValues;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptConsumption;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Services\ActiveParticipantLineage;
use App\Services\BiasGateService;
use App\Services\BiasReceiptAuthorityService;
use App\Services\CausalWriteService;
use App\Services\ChiralityGateService;
use App\Services\CommitRegionService;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\ExplorationPositionService;
use App\Services\GraphProjectionService;
use App\Services\HomeostaticDirectionService;
use App\Services\HomologousBiasService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ObservableService;
use App\Services\Observables\ReinforcementService;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptLayout;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PhasePositionService;
use App\Services\PhaseProgressionService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use App\Services\RegionalExhaustionMembership;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\AssertsLatePhaseContract;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class HomologousBiasTest extends TestCase
{
    use IsolatesClosureOutput;
    use AssertsLatePhaseContract;
    use CreatesHomologousPositions;
    use RefreshDatabase;

    private Proposition $origin;

    private Proposition $a;

    private Proposition $b;

    private Proposition $c;

    private Proposition $gateA;

    private Proposition $gateB;

    private Proposition $gateC;

    private Phase $bias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
        $this->isolateCorrespondenceOutput();
        $this->createHomologyActor();
        $this->origin = $this->committedCoordinate(null, 'emergence');
        $this->a = $this->committedCoordinate($this->origin, 'settlement');
        $this->b = $this->committedCoordinate($this->origin, 'settlement');
        $this->c = $this->committedCoordinate($this->origin, 'settlement');
        $this->gateA = $this->source($this->a);
        $this->gateB = $this->source($this->b);
        $this->gateC = $this->source($this->c);
        $this->bias = $this->biasAt($this->a, $this->gateA);
        $this->measure($this->gateA, .8);
        $this->measure($this->gateB, -.7);
        $this->measure($this->gateC, -.7);
    }

    public function test_boundary_reclassification_belongs_to_the_acting_region_not_its_foreign_source(): void
    {
        $this->warrant();
        $this->resolveExpressiveFieldFixture(-1);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($this->gateB));
        $profileBirth = app(\App\Services\Observables\PhaseProfileObservationService::class)->stored($target->id);
        $this->assertNotNull($profileBirth->birth_cycle_coordinate);
        $this->assertNull($profileBirth->preceding_phase_proposition_id);
        $this->assertNull($profileBirth->cycle_interval);
        $this->assertSame('no_local_predecessor', $profileBirth->unavailable_reason);

        $tragedy = Tragedy::sole();
        $this->assertSame($this->gateA->id, $tragedy->terminal_proposition_id);
        $this->assertDatabaseCount('tragedies', 1);
        $this->assertSame($this->a->id, $tragedy->governing_committal_id);
        $this->assertSame($this->bias->cycle->context_horizon_id, $tragedy->context_horizon_id);
        $this->assertNotSame($this->b->id, $tragedy->governing_committal_id);
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $this->a->id,
            'terminal_proposition_id' => $target->id, 'tragedy_id' => $tragedy->id]);
        $this->assertDatabaseMissing('commit_region_exhaustions', ['governing_committal_id' => $this->b->id]);
        // The accepted Bias write still completes into an Order/Rest position.
        $next = app(PhaseProgressionService::class)->advance($this->bias, $target);
        $this->assertSame(PhaseKind::Order, $next->kind);
        $this->publishPotentialFixture($this->actor);
        $this->assertSame(['participant.rest'], array_keys(app(InputPlanService::class)->forPhase($this->actor, $next)['schema']['$defs']['actions']));
    }

    public function test_dominant_homologues_add_options_without_vetoing_either_local_direction(): void
    {
        $this->warrant();
        foreach ([1.0, -1.0] as $sign) {
            $this->measure($this->gateA, .8 * $sign);
            $this->measure($this->gateB, -.7 * $sign);
            $this->measure($this->gateC, -.7 * $sign);
            $context = new ParticipationContext($this->bias);
            $this->assertCount(3, app(HomologousBiasService::class)->populationFor($this->bias, $context));
            $this->assertEqualsCanonicalizing([$this->gateB->id, $this->gateC->id],
                app(HomologousBiasService::class)->forPhase($this->bias, $context)->pluck('id')->all());
            $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id, $this->gateC->id], $this->biasOptionIds($context));
            $this->assertFalse(app(PhasePositionService::class)->acceptsGate($this->bias, $this->gateB));
            $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
        }
        $this->measure($this->gateA, -.8); // It may also naturally match the field.
        $this->assertContains($this->gateA->id, $this->biasOptionIds());
    }

    public function test_non_dominant_homologues_and_unattested_members_do_not_gain_actions(): void
    {
        $this->warrant();
        $this->measure($this->gateB, .1);
        $this->measure($this->gateC, -1);
        $unattested = $this->source($this->c, attest: false);
        $this->measure($unattested, -1);
        $this->measure($this->gateB, .1);
        $this->measure($this->gateC, -1);
        $this->coherentChiralityFixture($this->gateA);
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateC->id], $this->biasOptionIds());
        $this->assertFalse(app(PhaseGenerationService::class)->available($this->bias, 'proposition.reclassify', position: $unattested));
        $this->reject($this->gateB);
        $this->reject($unattested);
    }

    public function test_population_cannot_bypass_the_current_gates_local_bias_rule(): void
    {
        $this->warrant();
        // Local evidence now opposes its positive ancestral frame. Numeric
        // population membership must not re-authorize the current Gate.
        $this->vote($this->gateA, 'refute');
        $this->vote($this->gateA, 'refute');
        $this->measure($this->gateA, -.8);
        $this->assertNotContains($this->gateA->id, app(BiasGateService::class)->forPhase($this->bias)->pluck('id'));
        $this->assertNotContains($this->gateA->id, $this->biasOptionIds());
        $this->assertContains($this->gateB->id, $this->biasOptionIds());
        $this->reject($this->gateA);
    }

    public function test_zero_retains_population_history_and_does_not_mutate_measurements_or_state(): void
    {
        $this->warrant();
        // Fixture entries established Negative population history before these zero means.
        foreach ([[.5, -.5, 0.0], [0.0, 0.0, 0.0]] as $values) {
            foreach ([$this->gateA, $this->gateB, $this->gateC] as $index => $gate) {
                $this->measure($gate, $values[$index]);
            }
            $before = $this->stateSnapshot();
            $field = app(HomeostaticDirectionService::class)->forPosition($this->gateA, 'chirality');
            $this->assertSame(0.0, $field->mean);
            $this->assertSame(ObservableDirection::Negative, $field->direction);
            $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id, $this->gateC->id], $this->biasOptionIds());
            $this->assertSame($before, $this->stateSnapshot());
        }
    }

    public static function directions(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('directions')]
    public function test_cross_region_reclassification_uses_only_entry_receipts_and_advances_to_its_owned_result(bool $reverse): void
    {
        $this->warrant();
        [$entry, $gate, $source] = $reverse ? [$this->b, $this->gateB, $this->gateA] : [$this->a, $this->gateA, $this->gateB];
        $this->bias = $this->biasAt($entry, $gate);
        foreach ([$this->gateA, $this->gateB, $this->gateC] as $node) {
            $this->measure($node, -.5);
        }
        $this->warrant();
        $receipt = app(BiasReceiptAuthorityService::class)->receiptsFor($this->bias, $source)->sole();
        $foreignReceipt = app(PropositionStateService::class)->receipts($source)->firstOrFail();
        $input = ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id];
        $this->assertDatabaseMissing('participant_receipt_contexts', ['receipt_id' => $receipt->id]);
        $this->reject($source, $foreignReceipt->id);
        $this->publishPotentialFixture($this->actor);
        $before = $this->stateSnapshot();
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->bias);
        $this->assertSame($before, $this->stateSnapshot());
        $this->assertContains($source->id, $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['proposition_id']['enum']);
        app(ContributionService::class)->validateSchema($plan['schema'], $input);
        $branch = collect($plan['schema']['$defs']['actions']['proposition.reclassify']['anyOf'])
            ->first(fn ($branch) => $branch['properties']['proposition_id']['const'] === $source->id);
        $this->assertSame($receipt->id, $branch['properties']['receipt_id']['const']);
        $option = collect($plan['gate_options'])->firstWhere('id', $source->id);
        $this->assertSame([], $option['receipts']);
        $this->assertSame([], $option['instruments']);
        $this->assertNotContains($foreignReceipt->id, array_column($plan['receipts'], 'id'));
        $this->assertNotContains($source->id, array_column($plan['traversal_options'], 'id'));
        $this->assertNotContains($source->id, array_column($plan['read_options'], 'id'));
        $horizon = $this->bias->cycle->context_horizon_id;
        $state = app(ParticipationService::class)->contribute($this->actor, $this->bias->id, $input);
        $target = Proposition::findOrFail($this->actor->fresh()->current_proposition_id);
        $this->assertSame('order', $state['plan']['phase']['kind']);
        $this->assertSame($target->id, Phase::findOrFail($state['plan']['phase']['id'])->gate_proposition_id);
        $this->assertSame($source->statement, $target->statement);
        $this->assertSame($this->bias->id, $target->phase_id);
        $this->assertSame($entry->id, $target->phase->cycle->entry_committal_id);
        $this->assertSame($horizon, $target->contextHorizonMembership->context_horizon_id);
        $this->assertSame([$source->id], $target->parents()->pluck('propositions.id')->all());

        // Geometry belongs to the independent C1 contracts, not these supplied
        // committed-coordinate inputs. No extra causal parent is invented.
        $this->assertDatabaseMissing('supersessions', [
            'source_proposition_id' => $gate->id,
            'target_proposition_id' => $target->id,
        ]);
        foreach ([null, new ParticipationContext($target->phase)] as $context) {
            $lineage = app(ActiveParticipantLineage::class)->endingAt($target, $context);
            $this->assertSame($source->phase->cycle->entry_committal_id, $lineage->first()->id);
            $this->assertNotContains($entry->id, $lineage->pluck('id'));
        }
        $local = app(PotentialProjectionService::class)->region($horizon)['local'];
        $this->assertContains([$source->id, $target->id, 'reclassify'], $local['foreign_inputs']);
        $this->assertArrayNotHasKey($source->id, $local['nodes']);
        $this->assertNotContains([$source->id, $target->id, 'reclassify'], $local['edges']);
        $this->assertSame($entry->id, $local['nodes'][$target->id]['seed_id']);
        $this->assertSame(0, $target->attestations()->count());
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id, 'source_proposition_id' => $source->id, 'proposition_id' => $target->id]);
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $foreignReceipt->id]);
        $this->assertSame($source->id, $foreignReceipt->fresh()->receiptable_id);
        $this->assertSame([$receipt->id], DB::table('proposition_receipt_links')->where('proposition_id', $target->id)->pluck('receipt_id')->all());
        $this->assertSame($entry->id, $this->bias->cycle->fresh()->entry_committal_id);
        // The source's Region retains its stock, but the shared classification
        // cannot be generated a second time from another participant occurrence.
        $other = $this->phase($source->phase->cycle->entryCommittal, $source, PhaseKind::Bias);
        $this->assertContains($foreignReceipt->id, app(HorizonReceiptService::class)->ordinaryAvailable($other)->pluck('id'));
        $this->assertFalse(app(PhaseGenerationService::class)->available($other, 'proposition.reclassify', position: $source));
        $this->reject($this->gateC, $receipt->id); // Consumed evidence cannot be used again.
    }

    public function test_stale_population_direction_rejects_a_previously_offered_source_without_consumption(): void
    {
        $this->warrant();
        $this->assertContains($this->gateB->id, $this->biasOptionIds());
        $this->measure($this->gateC, 1);
        $this->assertNotContains($this->gateB->id, $this->biasOptionIds());
        $this->reject($this->gateB);
    }

    public function test_wrong_coordinates_and_plain_traverse_cannot_cross_over(): void
    {
        $this->controlledClosureFixture(-1);
        $this->warrant();
        $wrongDepth = $this->source($this->committedCoordinate($this->b, 'settlement'));
        $wrongDomain = $this->source($this->committedCoordinate($this->origin, 'emergence'));
        $wrongPhase = $this->position($this->b, PhaseKind::Persist);
        $this->edge($this->b, $wrongPhase, 'variant');
        $this->receiptFixture($wrongPhase);
        $this->vote($wrongPhase);
        foreach ([$wrongDepth, $wrongDomain, $wrongPhase] as $node) {
            $this->measure($node, -.9);
            $this->assertNotContains($node->id, $this->biasOptionIds());
            $this->reject($node);
        }
        $originalActor = $this->actor;
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'foreign-bias', 'title' => 'Foreign', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'outsider'])->save();
        $foreign = $this->source($this->committedCoordinate(null, 'emergence'));
        $this->measure($foreign, -.9);
        $this->actor = $originalActor;
        $this->assertNotContains($foreign->id, $this->biasOptionIds());
        $this->reject($foreign);
        $before = $this->stateSnapshot();
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias,
                ['action' => 'proposition.traverse', 'proposition_id' => $this->gateB->id]);
            $this->fail('Homology cannot authorize ordinary Traverse.');
        } catch (DomainException) {
            $this->assertSame($before, $this->stateSnapshot());
        }
    }

    public function test_same_horizon_foreign_result_is_not_adopted_as_the_source_regions_write(): void
    {
        $this->warrant();
        $a = $this->committedCoordinate($this->a, 'settlement');
        $b = $this->committedCoordinate($this->a, 'settlement');
        $left = $this->source($a);
        $right = $this->source($b);
        $this->bias = $this->biasAt($a, $left);
        $this->warrant();
        $this->measure($left, -.5);
        $this->measure($right, -.5);
        $this->assertSame($left->phase->cycle->context_horizon_id, $right->phase->cycle->context_horizon_id);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($right));
        $this->assertContains($target->id, app(CausalWriteService::class)->forPhase($this->bias)->pluck('id'));
        $other = $this->phase($b, $right, PhaseKind::Bias);
        foreach ([null, new ParticipationContext($other)] as $context) {
            $this->assertNotContains($target->id, app(CausalWriteService::class)->forPhase($other, $context)->pluck('id'));
            $this->assertNotContains($target->id, app(PropositionOptionService::class)->traversalOptionsFor($other, $context)->pluck('id'));
        }
        $revision = $this->position($a, PhaseKind::Bias);
        $this->edge($target, $revision, 'revise');
        foreach ([null, new ParticipationContext($revision->phase)] as $context) {
            $this->assertSame($b->id, app(ActiveParticipantLineage::class)->endingAt($revision, $context)->first()->id);
            $this->assertSame($a->id, app(CommitRegionService::class)->seedFor($revision, $context)->id);
        }
    }

    #[DataProvider('directions')]
    public function test_spent_additional_gates_require_their_own_current_epoch_to_reopen(bool $sameRegion): void
    {
        $this->warrant();
        $candidate = $sameRegion ? $this->source($this->a) : $this->gateB;
        foreach ([$this->gateA, $this->gateB, $this->gateC, $candidate] as $node) {
            $this->measure($node, -.5);
        }
        $this->assertContains($candidate->id, $this->biasOptionIds());
        $field = app(HomeostaticDirectionService::class)->forPosition($this->gateA, 'chirality');
        $position = app(ExplorationPositionService::class)->forProposition($candidate);
        DB::table('continuation_failures')->insert([
            'proposition_id' => $candidate->id, 'context_horizon_id' => $position['context_horizon_id'],
            'structural_position' => $position['structural_position'], 'exploration_epoch' => 0,
            'phase_id' => $candidate->phase_id, 'failed_at' => now(),
        ]);
        $failures = DB::table('continuation_failures')->get()->toJson();
        foreach ([null, new ParticipationContext($this->bias)] as $context) {
            $this->assertNotContains($candidate->id, $this->biasOptionIds($context));
            $this->assertContains($this->gateA->id, $this->biasOptionIds($context));
        }
        $this->assertSame($field->values, app(HomeostaticDirectionService::class)->forPosition($this->gateA, 'chirality')->values);
        $this->reject($candidate);
        $unrelated = app(ExplorationPositionService::class)->forProposition($this->gateC);
        DB::table('horizon_exploration_states')->insert([...$unrelated, 'epoch' => 1]);
        $this->assertNotContains($candidate->id, $this->biasOptionIds());
        DB::table('horizon_exploration_states')->insert([...$position, 'epoch' => 1]);
        $this->assertContains($candidate->id, $this->biasOptionIds());
        $this->assertSame($failures, DB::table('continuation_failures')->get()->toJson());
        $this->assertSame($this->gateA->id, $this->actor->fresh()->current_proposition_id);
    }

    public function test_unrecorded_foreign_edges_cannot_impersonate_bias_transitions(): void
    {
        $this->warrant();
        $target = $this->position($this->a, PhaseKind::Bias);
        $target->phase->forceFill(['gate_proposition_id' => $this->gateA->id])->save();
        $this->edge($this->gateB, $target, 'reclassify');
        foreach ([null, new ParticipationContext($target->phase)] as $context) {
            try {
                app(ActiveParticipantLineage::class)->endingAt($target, $context);
                $this->fail('An unrecorded foreign edge must not redirect resolved provenance.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('provenance', $exception->getMessage());
            }
        }
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Potential projection requires causal Horizon edges');
        $this->publishPotentialFixture($this->actor);
    }

    public function test_foreign_exhaustion_uses_complete_horizon_continuations(): void
    {
        $this->warrant();
        $middle = $this->position($this->b, PhaseKind::Bias);
        $this->edge($this->gateB, $middle, 'variant');
        $terminal = $this->committedCoordinate($this->b, 'emergence');
        $this->edge($middle, $terminal, 'commit');
        $phase = $this->phase($terminal, $terminal, PhaseKind::Distinguish);
        $tragedy = $this->boundaryTragedyFixture($phase->gate);
        DB::table('commit_region_exhaustions')->insert([
            'governing_committal_id' => $terminal->id, 'context_horizon_id' => $phase->cycle->context_horizon_id,
            'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id, 'phase_id' => $phase->id,
            'terminal_proposition_id' => $terminal->id, 'observed_field_version' => 1, 'exhausted_at' => now(),
        ]);
        app(RegionalExhaustionMembership::class)->publish($terminal->id, [$terminal->id]);
        $this->measure($this->gateB, -.7);
        $context = new ParticipationContext($this->bias);
        $this->assertFalse($context->propositions->has($middle->id));
        $this->assertNotContains($this->gateB->id, $this->biasOptionIds($context));
        $this->reject($this->gateB);
        $live = $this->position($this->b, PhaseKind::Bias);
        $this->edge($this->gateB, $live, 'variant');
        $this->measure($this->gateB, -.7);
        $this->assertContains($this->gateB->id, $this->biasOptionIds());
    }

    #[Group('query')]
    public function test_complete_bias_plans_are_bounded_across_foreign_horizons(): void
    {
        $this->warrant();
        $counts = [];
        foreach ([2, 7] as $count) {
            if ($count === 7) {
                foreach (range(1, 5) as $index) {
                    $node = $this->source($this->committedCoordinate($this->origin, 'settlement'));
                    $this->measure($node, -.7);
                }
                $this->closeFixtureCycles();
            }
            $this->publishPotentialFixture($this->actor);
            foreach (['cold', 'warm'] as $temperature) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                try {
                    $plan = app(InputPlanService::class)->forPhase($this->actor->fresh(), $this->bias->fresh());
                    $counts[$count][$temperature] = count(DB::getQueryLog());
                } finally {
                    DB::disableQueryLog();
                }
                $this->assertCount($count + 1, $plan['gate_options']);
            }
        }
        fwrite(STDERR, sprintf("BIAS HORIZON GROWTH: cold %d -> %d; warm %d -> %d\n",
            $counts[2]['cold'], $counts[7]['cold'], $counts[2]['warm'], $counts[7]['warm']));
        $this->assertLessThan(220, $counts[7]['warm']);
        $this->assertSame($counts[2], $counts[7], 'Bias plans must not query once per member or foreign Horizon.');
    }

    public function test_population_is_observable_but_foreign_actions_require_a_tragedy_warrant(): void
    {
        $this->assertCount(3, app(HomologousBiasService::class)->populationFor($this->bias));
        $this->assertEquals([$this->gateA->id], $this->biasOptionIds());
        $this->publishPotentialFixture($this->actor);
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->bias);
        $this->assertSame([$this->gateA->id], $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['proposition_id']['enum']);
        $this->reject($this->gateB);
        $this->assertDatabaseCount('tragedies', 0);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($this->gateA));
        $this->assertSame([$this->gateA->id], $target->parents()->pluck('propositions.id')->all());
    }

    public function test_schema_and_dispatcher_enforce_source_receipt_pairs_atomically(): void
    {
        $tragedy = $this->warrant();
        $ordinary = app(HorizonReceiptService::class)->ordinaryAvailable($this->bias)->where('type', '!=', 'tragedy')->sole();
        $this->publishPotentialFixture($this->actor);
        $schema = app(InputPlanService::class)->forPhase($this->actor, $this->bias)['schema'];
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $branches = collect($transport['properties']['contribution']['anyOf'])
            ->filter(fn ($branch) => $branch['properties']['action']['enum'] === ['proposition.reclassify']);
        $this->assertSame([$ordinary->id], $branches->first(fn ($branch) => $branch['properties']['proposition_id']['enum'] === [$this->gateA->id])['properties']['receipt_id']['enum']);
        $this->assertSame([$tragedy->receipt_id], $branches->first(fn ($branch) => $branch['properties']['proposition_id']['enum'] === [$this->gateB->id])['properties']['receipt_id']['enum']);
        foreach ([[$this->gateA, $ordinary->id], [$this->gateB, $tragedy->receipt_id]] as [$source, $receipt]) {
            app(ContributionService::class)->validateSchema($schema, $this->input($source, $receipt));
        }
        foreach ([[$this->gateA, $tragedy->receipt_id], [$this->gateB, $ordinary->id]] as [$source, $receipt]) {
            try {
                app(ContributionService::class)->validateSchema($schema, $this->input($source, $receipt));
                $this->fail('The schema must reject the wrong source/Receipt pairing.');
            } catch (\InvalidArgumentException) {
                $this->reject($source, $receipt);
            }
        }
        $this->assertDatabaseCount('receipt_consumptions', 0);
    }

    public function test_a_tragedy_receipt_alone_never_advertises_the_local_gate(): void
    {
        $tragedy = $this->warrant();
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($this->actor, $this->bias, ['load' => [$tragedy->receipt_id]]));
        DB::table('instrument_executions')->update(['status' => 'failed']);
        $this->assertNotContains($this->gateA->id, $this->biasOptionIds());
        $this->assertContains($this->gateB->id, $this->biasOptionIds());
        $this->reject($this->gateA, $tragedy->receipt_id);
    }

    public function test_loaded_memory_survives_real_homologous_consumption_without_weight_or_authority(): void
    {
        $tragedy = $this->warrant();
        $contexts = app(ReceiptContextService::class);
        DB::transaction(fn () => $contexts->desired($this->actor, $this->bias, ['load' => [$tragedy->receipt_id]]));
        $this->resolveExpressiveFieldFixture(.8);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($this->gateB));
        $this->assertNull(app(HorizonReceiptService::class)->tragedyWarrant($this->bias));
        $this->assertSame(1, ReceiptConsumption::where('receipt_id', $tragedy->receipt_id)->count());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $next = app(PhaseProgressionService::class)->advance($this->bias, $target);
        $item = $contexts->forPhase($next)->firstWhere('id', $tragedy->receipt_id);
        $this->assertSame('consumed', $item['availability']);
        $this->assertTrue($item['memory_active']);
        $this->assertFalse($item['active']);
        $this->assertSame($this->gateA->id, $item['tragedy_memory']['catastrophe']['proposition_id']);
        foreach ([null, new ParticipationContext($next)] as $context) {
            $this->assertSame(0, app(ReinforcementService::class)->count($target, $context));
        }
        DB::transaction(fn () => $contexts->desired($this->actor, $next, ['unload' => [$tragedy->receipt_id]]));
        $this->assertFalse($contexts->forPhase($next)->firstWhere('id', $tragedy->receipt_id)['memory_active'] ?? false);
        $this->assertSame(1, ReceiptConsumption::where('receipt_id', $tragedy->receipt_id)->count());
    }

    public function test_escape_can_atomically_load_memory_without_requiring_a_prior_load(): void
    {
        $tragedy = $this->warrant();
        $this->resolveExpressiveFieldFixture(.8);
        $input = [...$this->input($this->gateB), 'receipt_options' => ['load' => [$tragedy->receipt_id]]];
        $this->publishPotentialFixture($this->actor);
        app(ContributionService::class)->validateSchema(app(InputPlanService::class)->forPhase($this->actor, $this->bias)['schema'], $input);
        app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $input);
        $this->assertDatabaseHas('participant_receipt_contexts', ['participant_id' => $this->actor->id,
            'receipt_id' => $tragedy->receipt_id, 'evidence_signature' => null, 'cleared_at' => null]);
        $this->assertSame(1, ReceiptConsumption::where('receipt_id', $tragedy->receipt_id)->count());
    }

    public function test_foreign_or_recovery_relocated_tragedy_is_not_the_current_regions_warrant(): void
    {
        $foreign = $this->boundaryTragedyFixture($this->gateB);
        $this->measure($this->gateB, -.7);
        $this->reject($this->gateB, $foreign->receipt_id);
        DB::table('tragedy_receipt_scopes')->where('tragedy_id', $foreign->id)->update(['current_committal_id' => $this->a->id]);
        $this->assertContains($foreign->receipt_id, app(HorizonReceiptService::class)->catalogue($this->bias)->pluck('id'));
        $this->assertNotContains($foreign->receipt_id, app(HorizonReceiptService::class)->ordinaryAvailable($this->bias)->pluck('id'));
        $this->assertNull(app(HorizonReceiptService::class)->tragedyWarrant($this->bias));
        $this->assertNotContains($this->gateB->id, $this->biasOptionIds());
        $this->reject($this->gateB, $foreign->receipt_id);
    }

    public function test_stale_scope_and_consumed_warrants_fail_without_another_target(): void
    {
        $tragedy = $this->warrant();
        $this->publishPotentialFixture($this->actor);
        $schema = app(InputPlanService::class)->forPhase($this->actor, $this->bias)['schema'];
        $input = $this->input($this->gateB, $tragedy->receipt_id);
        app(ContributionService::class)->validateSchema($schema, $input);
        DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->update(['current_committal_id' => $this->b->id]);
        $this->reject($this->gateB, $tragedy->receipt_id);
        DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->update(['current_committal_id' => $this->a->id]);
        $this->resolveExpressiveFieldFixture(.8);
        app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $input);
        // Another foreign source still has no source/register result, but the warrant is spent.
        $this->reject($this->gateC, $tragedy->receipt_id);
        $this->assertNotContains($this->gateC->id, $this->biasOptionIds());
        $this->assertSame(1, ReceiptConsumption::where('receipt_id', $tragedy->receipt_id)->count());
    }

    public function test_rollback_after_reclassification_restores_the_warrant_and_all_causal_writes(): void
    {
        $tragedy = $this->warrant();
        $this->resolveExpressiveFieldFixture(.8);
        $before = $this->stateSnapshot();
        try {
            DB::transaction(function () use ($tragedy): void {
                app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($this->gateB, $tragedy->receipt_id));
                $this->assertSame(1, ReceiptConsumption::where('receipt_id', $tragedy->receipt_id)->count());
                throw new DomainException('Roll back after consumption');
            });
        } catch (DomainException $exception) {
            $this->assertSame('Roll back after consumption', $exception->getMessage());
        }
        $this->assertSame($before, $this->stateSnapshot());
        $this->assertContains($this->gateB->id, $this->biasOptionIds());
        $this->assertDatabaseCount('receipt_consumptions', 0);
    }

    public function test_another_same_region_expressive_path_uses_ordinary_evidence_without_tragedy(): void
    {
        $other = $this->source($this->a);
        $this->measure($other, -.7);
        $this->assertContains($other->id, $this->biasOptionIds());
        $this->assertSame('ordinary', app(BiasReceiptAuthorityService::class)->authority($this->bias, $other, new ParticipationContext($this->bias)));
        app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($other));
        $this->assertDatabaseCount('receipt_consumptions', 1);
    }

    public function test_positive_charge_escape_leaves_region_open_but_later_local_boundary_exhausts_it(): void
    {
        $tragedy = $this->warrant();
        $this->resolveExpressiveFieldFixture(.8);
        $escape = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($this->gateB));
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id, 'proposition_id' => $escape->id]);
        $gate = $this->position($this->a, PhaseKind::Constrain);
        $phase = $this->phaseAtPositionFixture($gate->phase, PhaseKind::Disperse);
        $phase->forceFill(['gate_proposition_id' => $gate->id])->save();
        $this->resolveExpressiveFieldFixture(-1);
        $later = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'A later catastrophe after escape']);
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $this->a->id,
            'terminal_proposition_id' => $later->id, 'receipt_id' => $tragedy->receipt_id]);
        $this->assertSame(1, ReceiptConsumption::where('receipt_id', $tragedy->receipt_id)->count());
    }

    private function warrant(): Tragedy
    {
        $gate = $this->bias->gate;
        $values = $gate->measurement;
        $tragedy = $this->boundaryTragedyFixture($gate);
        app(PropositionMeasurementService::class)->project($gate, $values->correspondence, $values->chirality, $values->closure);

        return $tragedy;
    }

    private function source(Proposition $entry, bool $attest = true): Proposition
    {
        $node = $this->position($entry, PhaseKind::Mediate);
        $this->edge($entry, $node, 'variant');
        $this->receiptFixture($node);
        if ($attest) {
            $this->vote($node);
        }

        return $node;
    }

    private function vote(Proposition $node, string $direction = 'support'): void
    {
        $phase = $node->attestations()->where('phase_id', $node->phase_id)->exists()
            ? $this->phase($node->phase->cycle->entryCommittal, $node, PhaseKind::Bias) : $node->phase;
        $vote = new Attestation;
        $vote->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($vote);
    }

    public static function chiralityCoherence(): array
    {
        return [[.5, .5, true], [-.5, -.5, true], [.5, -.5, false], [-.5, .5, false]];
    }

    private function components(Proposition $gate, float $orientation, float $cadence, float $combined = .8): void
    {
        app(PropositionMeasurementService::class)->project($gate, 0, $combined, 0,
            chiralityComponents: new ChiralityValues($orientation, $cadence));
    }

    #[DataProvider('chiralityCoherence')]
    #[Group('late-phase')]
    public function test_causal_chirality_coherence_controls_local_write_and_preserves_visibility(float $orientation, float $cadence, bool $allowed): void
    {
        $this->components($this->gateA, $orientation, $cadence);
        $this->publishPotentialFixture($this->actor);
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->bias);
        $this->assertLatePhaseContract($plan, PhaseKind::Bias, $this->gateA->id, $allowed,
            $orientation > 0 ? 'positive' : 'negative', $cadence > 0 ? 'positive' : 'negative');
        $this->assertSame($allowed, app(PhaseGenerationService::class)->available($this->bias, 'proposition.reclassify', position: $this->gateA));
        $this->assertSame($allowed, in_array($this->gateA->id, array_column($plan['gate_options'], 'id')));
        $visible = app(ParticipantContextSerializer::class)->plan($plan);
        $this->assertContains($this->gateA->id, array_column($visible['gate_options'], 'id'));
        $diagnostic = $visible['chirality_admissibility'];
        $this->assertSame($allowed, $diagnostic['admissible']);
        $this->assertSame($allowed ? null : 'component_directions_disagree', $diagnostic['reason']);
        $this->assertSame($orientation > 0 ? 'positive' : 'negative', $diagnostic['orientation_direction']);
        $this->assertSame($cadence > 0 ? 'positive' : 'negative', $diagnostic['attestation_cadence_direction']);
        $this->assertSame('positive', $diagnostic['chirality_direction']);
        $packet = app(ParticipantContextSerializer::class)->inference(['status' => 'ready', 'plan' => $visible]);
        $prompt = app(ParticipantPromptLayout::class)->make($packet);
        $this->assertEquals($diagnostic, json_decode($prompt['user'], true)['plan']['chirality_admissibility']);
        if (! $allowed) {
            $this->reject($this->gateA);
        }
    }

    public function test_zero_components_use_independent_retained_directions(): void
    {
        foreach ([[.5, -.5, false], [-.5, .5, false], [-.5, -.5, true], [.5, .5, true]] as [$orientation, $cadence, $allowed]) {
            $this->components($this->gateA, $orientation, $cadence);
            $this->components($this->gateA, 0, 0, 0);
            $diagnostic = app(ChiralityGateService::class)->evaluate($this->gateA->fresh());
            $this->assertSame($allowed, $diagnostic['admissible']);
            $this->assertSame($orientation > 0 ? 'positive' : 'negative', $diagnostic['orientation_direction']);
            $this->assertSame($cadence > 0 ? 'positive' : 'negative', $diagnostic['attestation_cadence_direction']);
            $this->assertSame($allowed, app(PhaseGenerationService::class)->available($this->bias, 'proposition.reclassify', position: $this->gateA->fresh()));
        }
    }

    public function test_inadmissible_member_determines_dominance_before_action_filtering(): void
    {
        $this->warrant();
        $this->measure($this->gateA, .2);
        $this->measure($this->gateB, .2);
        $this->components($this->gateC, -.8, .2, -.9);
        $context = new ParticipationContext($this->bias);
        $population = app(HomologousBiasService::class)->populationFor($this->bias, $context);
        $field = app(HomeostaticDirectionService::class)->forPopulation($this->gateA, $population, 'chirality', $context);
        $this->assertCount(3, $field->values);
        $this->assertSame(-.1666667, $field->mean);
        $this->assertSame(ObservableDirection::Negative, $field->direction);
        $this->assertSame([$this->gateC->id], app(HomologousBiasService::class)->forPhase($this->bias, $context)->pluck('id')->all());
        $this->assertSame([$this->gateA->id], $this->biasOptionIds($context));
        $this->components($this->gateC, -.8, -.2, -.9);
        $this->assertContains($this->gateC->id, $this->biasOptionIds());
        // Foreign sources are unary; the causal Gate's coherence is not a veto.
        $this->components($this->gateA, .8, -.2, .2);
        $this->assertSame([$this->gateC->id], $this->biasOptionIds());
    }

    public function test_actionless_inadmissible_bias_rests_at_zero_and_positive_potential(): void
    {
        foreach ([$this->gateA, $this->gateB, $this->gateC] as $gate) {
            $this->components($gate, .8, -.2);
        }
        $this->publishPotentialFixture($this->actor);
        foreach ([0.0, .75] as $potential) {
            DB::table('potential_region_states')->where('context_horizon_id', $this->bias->cycle->context_horizon_id)
                ->update(['remaining_potential' => $potential]);
            $plan = app(InputPlanService::class)->forPhase($this->actor, $this->bias);
            $this->assertSame($potential, app(PotentialProjectionService::class)->region($this->bias->cycle->context_horizon_id)['potential']);
            $this->assertTrue($plan['rest_available']);
            $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        }
    }

    #[DataProvider('directions')]
    public function test_stale_local_or_homologous_source_is_rejected_without_any_write(bool $foreign): void
    {
        $this->warrant();
        $source = $foreign ? $this->gateB : $this->gateA;
        $this->assertContains($source->id, $this->biasOptionIds());
        $input = $this->input($source);
        $this->components($source, .5, -.5, $foreign ? -.7 : .8);
        $before = $this->stateSnapshot();
        $classifications = DB::table('reclassification_results')->get()->toJson();
        $attachments = DB::table('participant_receipt_contexts')->get()->toJson();
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $input);
            $this->fail('Stale component coherence cannot authorize Reclassify.');
        } catch (DomainException) {
            $this->assertSame($before, $this->stateSnapshot());
            $this->assertSame($classifications, DB::table('reclassification_results')->get()->toJson());
            $this->assertSame($attachments, DB::table('participant_receipt_contexts')->get()->toJson());
        }
    }

    public function test_reclassification_result_uses_ordinary_measurement_without_operand_coherence_constraint(): void
    {
        $this->components($this->gateA, .8, .8);
        // Remove the controlled calculator: the accepted result is measured by real v2.
        $this->app->forgetInstance(LocalMeasurementService::class);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($this->gateA));
        $actual = app(ObservableService::class)->components($target);
        $this->assertEquals($actual->observables()->chirality, $target->fresh()->measurement->chirality);
        $this->assertSame([$this->gateA->id], $target->parents()->pluck('propositions.id')->all());
        $this->assertSame($actual->chiralityComponents->orientation, $target->fresh()->measurement->orientation);
        $this->assertSame($actual->chiralityComponents->cadence, $target->fresh()->measurement->attestation_cadence);
    }

    private function measure(Proposition $node, float $value): void
    {
        app(PropositionMeasurementService::class)->project($node, 0, $value, 0,
            chiralityComponents: new ChiralityValues($value, $value));
    }

    private function edge(Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    private function closeFixtureCycles(): void
    {
        Phase::query()->where('id', '!=', $this->bias->id)->update(['completed_at' => now()]);
        Cycle::query()->where('id', '!=', $this->bias->cycle_id)->update(['completed_at' => now()]);
    }

    private function biasAt(Proposition $entry, Proposition $gate): Phase
    {
        Phase::query()->update(['completed_at' => now()]);
        Cycle::query()->update(['completed_at' => now()]);
        $phase = $this->phase($entry, $gate, PhaseKind::Bias);
        $this->actor->forceFill(['current_proposition_id' => $gate->id])->save();

        return $phase;
    }

    private function biasOptionIds(?ParticipationContext $context = null): array
    {
        return app(PropositionOptionService::class)->gateOptionsFor($this->bias, $context)->pluck('id')->all();
    }

    public function test_graph_renewal_marks_the_consumed_foreign_escape_target_only(): void
    {
        $tragedy = $this->warrant();
        $this->resolveExpressiveFieldFixture(.8);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($this->gateB));
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id, 'proposition_id' => $target->id]);
        // The warrant is spent and the source's current direction can change;
        // presentation must retain the accepted historical crossover.
        $this->measure($this->gateB, .9);
        $nodes = Proposition::whereKey([$target->id, $this->gateB->id])->with(['measurement', 'phase.cycle'])->get()->keyBy('id');
        app(GraphProjectionService::class)->load($this->actor->substrate, $nodes, historical: true);
        $this->assertTrue($nodes[$target->id]->renewal);
        $this->assertFalse($nodes[$this->gateB->id]->renewal);
    }

    public static function ordinaryGraphSources(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('ordinaryGraphSources')]
    public function test_local_and_same_region_homologous_classifications_are_not_renewal(bool $homologous): void
    {
        $source = $homologous ? $this->source($this->a) : $this->gateA;
        if ($homologous) {
            $this->measure($source, -.7);
        }
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($source));
        app(GraphProjectionService::class)->load($this->actor->substrate, collect([$target]), historical: true);
        $this->assertFalse($target->renewal);
    }

    private function input(Proposition $source, ?string $receipt = null): array
    {
        return ['action' => 'proposition.reclassify', 'proposition_id' => $source->id,
            'receipt_id' => $receipt ?? (app(BiasReceiptAuthorityService::class)->receiptsFor($this->bias, $source)->first()
                ?? app(HorizonReceiptService::class)->ordinaryAvailable($this->bias)->firstOrFail())->id];
    }

    private function reject(Proposition $source, ?string $receipt = null): void
    {
        $input = $this->input($source, $receipt);
        $before = [Proposition::count(), Supersession::count(), ReceiptConsumption::count()];
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $input);
            $this->fail('An unauthorized Bias source or Receipt must not produce a write.');
        } catch (DomainException) {
            $this->assertSame($before, [Proposition::count(), Supersession::count(), ReceiptConsumption::count()]);
        }
    }

    private function stateSnapshot(): array
    {
        return [DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson(),
            DB::table('propositions')->orderBy('id')->get()->toJson(), DB::table('participants')->orderBy('id')->get()->toJson(),
            DB::table('supersessions')->orderBy('id')->get()->toJson(), DB::table('receipt_consumptions')->orderBy('receipt_id')->get()->toJson()];
    }
}
