<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptConsumption;
use App\Models\Supersession;
use App\Services\BiasGateService;
use App\Services\CausalWriteService;
use App\Services\ContributionDispatcher;
use App\Services\HomeostaticDirectionService;
use App\Services\HomologousBiasService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PhasePositionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class HomologousBiasTest extends TestCase
{
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
        $this->createHomologyActor();
        $this->origin = $this->commit(null, 'emergence');
        $this->a = $this->commit($this->origin, 'settlement');
        $this->b = $this->commit($this->origin, 'settlement');
        $this->c = $this->commit($this->origin, 'settlement');
        $this->gateA = $this->source($this->a);
        $this->gateB = $this->source($this->b);
        $this->gateC = $this->source($this->c);
        $this->bias = $this->biasAt($this->a, $this->gateA);
        $this->measure($this->gateA, .8);
        $this->measure($this->gateB, -.7);
        $this->measure($this->gateC, -.7);
    }

    public function test_dominant_homologues_add_options_without_vetoing_either_local_direction(): void
    {
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
        $this->measure($this->gateB, .1);
        $this->measure($this->gateC, -1);
        $unattested = $this->source($this->c, attest: false);
        $this->measure($unattested, -1);
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateC->id], $this->biasOptionIds());
        $this->assertFalse(app(PhaseGenerationService::class)->available($this->bias, 'proposition.reclassify', position: $unattested));
        $this->reject($this->gateB);
        $this->reject($unattested);
    }

    public function test_population_cannot_bypass_the_current_gates_local_bias_rule(): void
    {
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

    public function test_zero_uses_chirality_default_and_does_not_mutate_measurements_or_state(): void
    {
        foreach ([[.5, -.5, 0.0], [0.0, 0.0, 0.0]] as $values) {
            foreach ([$this->gateA, $this->gateB, $this->gateC] as $index => $gate) {
                $this->measure($gate, $values[$index]);
            }
            $before = $this->stateSnapshot();
            $field = app(HomeostaticDirectionService::class)->forPosition($this->gateA, 'chirality');
            $this->assertSame(0.0, $field->mean);
            $this->assertSame(\App\Enums\ObservableDirection::Negative, $field->direction);
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
        [$entry, $gate, $source] = $reverse ? [$this->b, $this->gateB, $this->gateA] : [$this->a, $this->gateA, $this->gateB];
        $this->bias = $this->biasAt($entry, $gate);
        foreach ([$this->gateA, $this->gateB, $this->gateC] as $node) {
            $this->measure($node, -.5);
        }
        $receipt = app(HorizonReceiptService::class)->available($this->bias)->sole();
        $foreignReceipt = $source->receipts()->firstOrFail();
        $input = ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id];
        $this->reject($source, $foreignReceipt->id);
        $this->publishPotentialFixture($this->actor);
        $before = $this->stateSnapshot();
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->bias);
        $this->assertSame($before, $this->stateSnapshot());
        $this->assertContains($source->id, $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['proposition_id']['enum']);
        $this->assertSame([$receipt->id], $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum']);
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
        $this->assertSame($target->id, $state['plan']['gate']['id']);
        $this->assertSame($source->statement, $target->statement);
        $this->assertSame($this->bias->id, $target->phase_id);
        $this->assertSame($entry->id, $target->phase->cycle->entry_committal_id);
        $this->assertSame($horizon, $target->contextHorizonMembership->context_horizon_id);
        $this->assertSame([$source->id], $target->parents()->pluck('propositions.id')->all());
        $this->assertSame(0, $target->attestations()->count());
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id, 'source_proposition_id' => $source->id, 'proposition_id' => $target->id]);
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $foreignReceipt->id]);
        $this->assertSame($source->id, $foreignReceipt->fresh()->receiptable_id);
        $this->assertSame([$receipt->id], DB::table('proposition_receipt_links')->where('proposition_id', $target->id)->pluck('receipt_id')->all());
        $this->assertSame($entry->id, $this->bias->cycle->fresh()->entry_committal_id);
        // The source's Region retains its stock, but the shared classification
        // cannot be generated a second time from another participant occurrence.
        $other = $this->phase($source->phase->cycle->entryCommittal, $source, PhaseKind::Bias);
        $this->assertContains($foreignReceipt->id, app(HorizonReceiptService::class)->available($other)->pluck('id'));
        $this->assertFalse(app(PhaseGenerationService::class)->available($other, 'proposition.reclassify', position: $source));
        $this->reject($this->gateC, $receipt->id); // Consumed evidence cannot be used again.
    }

    public function test_stale_population_direction_rejects_a_previously_offered_source_without_consumption(): void
    {
        $this->assertContains($this->gateB->id, $this->biasOptionIds());
        $this->measure($this->gateC, 1);
        $this->assertNotContains($this->gateB->id, $this->biasOptionIds());
        $this->reject($this->gateB);
    }

    public function test_wrong_coordinates_and_plain_traverse_cannot_cross_over(): void
    {
        $wrongDepth = $this->source($this->commit($this->b, 'settlement'));
        $wrongDomain = $this->source($this->commit($this->origin, 'emergence'));
        $wrongPhase = $this->position($this->b, PhaseKind::Persist);
        $this->receiptFixture($wrongPhase);
        $this->vote($wrongPhase);
        foreach ([$wrongDepth, $wrongDomain, $wrongPhase] as $node) {
            $this->measure($node, -.9);
            $this->assertNotContains($node->id, $this->biasOptionIds());
            $this->reject($node);
        }
        $originalActor = $this->actor;
        $substrate = new \App\Models\Substrate;
        $substrate->forceFill(['slug' => 'foreign-bias', 'title' => 'Foreign', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new \App\Models\Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'outsider'])->save();
        $foreign = $this->source($this->commit(null, 'emergence'));
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
        $a = $this->commit($this->a, 'settlement');
        $b = $this->commit($this->a, 'settlement');
        $left = $this->source($a);
        $right = $this->source($b);
        $this->bias = $this->biasAt($a, $left);
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
    }

    public function test_foreign_exhaustion_uses_complete_horizon_continuations(): void
    {
        $middle = $this->position($this->b, PhaseKind::Bias);
        $this->edge($this->gateB, $middle, 'variant');
        $terminal = $this->commit($this->b, 'emergence');
        $this->edge($middle, $terminal, 'commit');
        $phase = $this->phase($terminal, $terminal, PhaseKind::Distinguish);
        $tragedy = app(\App\Services\TragedyService::class)->record($phase, ['field_version' => 1]);
        DB::table('commit_region_exhaustions')->insert([
            'governing_committal_id' => $terminal->id, 'context_horizon_id' => $phase->cycle->context_horizon_id,
            'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id, 'phase_id' => $phase->id,
            'terminal_proposition_id' => $terminal->id, 'observed_field_version' => 1, 'exhausted_at' => now(),
        ]);
        app(\App\Services\RegionalExhaustionMembership::class)->publish($terminal->id, [$terminal->id]);
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
        $counts = [];
        foreach ([2, 7] as $count) {
            if ($count === 7) {
                foreach (range(1, 5) as $index) {
                    $node = $this->source($this->commit($this->origin, 'settlement'));
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
        $vote = new Attestation;
        $vote->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($vote);
    }

    private function measure(Proposition $node, float $value): void
    {
        app(PropositionMeasurementService::class)->project($node, 0, $value, 0);
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

    private function input(Proposition $source, ?string $receipt = null): array
    {
        return ['action' => 'proposition.reclassify', 'proposition_id' => $source->id,
            'receipt_id' => $receipt ?? app(HorizonReceiptService::class)->available($this->bias)->firstOrFail()->id];
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
            DB::table('supersessions')->orderBy('id')->get()->toJson(), DB::table('receipt_consumptions')->orderBy('id')->get()->toJson()];
    }
}
