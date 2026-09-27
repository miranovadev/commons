<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\LedgerService;
use App\Services\Observables\CorrespondenceCalculator;
use App\Services\Observables\CardinalPositionCorrespondenceCalculator;
use App\Services\Observables\DomainCardinalLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardinalCorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_lateral_position_is_continuous_not_categorical(): void
    {
        $this->assertSame(1.0, CorrespondenceCalculator::positionSimilarity(1, 1));
        $this->assertEqualsWithDelta(.3333333, CorrespondenceCalculator::positionSimilarity(1, 2), 1e-7);
        $this->assertEqualsWithDelta(.8181818, CorrespondenceCalculator::positionSimilarity(5, 6), 1e-7);
        $this->assertEqualsWithDelta(-.8095238, CorrespondenceCalculator::positionSimilarity(1, 20), 1e-7);
        $this->assertGreaterThan(
            CorrespondenceCalculator::positionSimilarity(1, 20),
            CorrespondenceCalculator::positionSimilarity(1, 2),
        );
    }

    public function test_domain_layout_assigns_breadth_first_depth_and_parent_relative_lateral_order(): void
    {
        [$horizon, $phase, $origin] = $this->fixture();

        $a = $this->node($phase, 'A', 'activity');
        $b = $this->node($phase, 'B', 'activity');
        $this->edge($phase, $origin, $a);
        $this->edge($phase, $origin, $b);

        $a1 = $this->node($phase, 'A1', 'frontier');
        $a2 = $this->node($phase, 'A2', 'frontier');
        $b1 = $this->node($phase, 'B1', 'frontier');
        $this->edge($phase, $a, $a1);
        $this->edge($phase, $a, $a2);
        $this->edge($phase, $b, $b1);

        $shared = $this->node($phase, 'Shared', 'canon');
        $this->edge($phase, $a1, $shared, 'reconcile');
        $this->edge($phase, $b1, $shared, 'reconcile');

        $layout = app(DomainCardinalLayoutService::class)->for($horizon->fresh());

        $this->assertSame([0, 1], [$layout->position($origin->id)->depth, $layout->position($origin->id)->lateral]);
        $this->assertSame([1, 1], [$layout->position($a->id)->depth, $layout->position($a->id)->lateral]);
        $this->assertSame([1, 2], [$layout->position($b->id)->depth, $layout->position($b->id)->lateral]);
        $this->assertSame([2, 1], [$layout->position($a1->id)->depth, $layout->position($a1->id)->lateral]);
        $this->assertSame([2, 2], [$layout->position($a2->id)->depth, $layout->position($a2->id)->lateral]);
        $this->assertSame([2, 3], [$layout->position($b1->id)->depth, $layout->position($b1->id)->lateral]);
        $this->assertSame([3, 1], [$layout->position($shared->id)->depth, $layout->position($shared->id)->lateral]);
    }

    public function test_row_balanced_position_scores_population_and_metric_displacement(): void
    {
        $this->assertSame(1.0, CardinalPositionCorrespondenceCalculator::rowScore([1, 2, 3, 4], [1, 2, 3, 4]));
        $this->assertSame(-0.5, CardinalPositionCorrespondenceCalculator::rowScore([1], [1, 2, 3, 4]));
        $this->assertEqualsWithDelta(
            0.6666667,
            CardinalPositionCorrespondenceCalculator::rowScore([1, 4], [1, 2]),
            1e-7,
        );
        $this->assertSame(-1.0, CardinalPositionCorrespondenceCalculator::rowScore([], [1, 2]));
    }

    public function test_domain_depth_continues_across_cycle_phase_repetition(): void
    {
        [$horizon, , $origin] = $this->fixture();

        $participant = Participant::where('substrate_id', $horizon->substrate_id)->firstOrFail();
        $previous = $origin;
        $nodes = [];

        foreach ([1, 2] as $cycleSequence) {
            $cycle = new Cycle;
            $cycle->forceFill([
                'participant_id' => $participant->id,
                'sequence' => $cycleSequence + 1,
                'context_horizon_id' => $horizon->id,
            ])->save();

            foreach (PhaseKind::cases() as $kind) {
                $phase = new Phase;
                $phase->forceFill([
                    'cycle_id' => $cycle->id,
                    'kind' => $kind,
                    'gate_proposition_id' => $previous->id,
                    'write_register' => $horizon->domain->registerFor($kind),
                    'started_at' => now(),
                ])->save();

                $node = $this->node(
                    $phase,
                    'Cycle '.$cycleSequence.' '.$kind->name,
                    $horizon->domain->registerFor($kind),
                    $kind === PhaseKind::Order ? 'committal' : 'expressive',
                );
                $this->edge($phase, $previous, $node, $kind === PhaseKind::Order ? 'commit' : 'variant');
                $nodes[] = $node;
                $previous = $node;
            }
        }

        $layout = app(DomainCardinalLayoutService::class)->for($horizon->fresh());

        foreach ($nodes as $index => $node) {
            $this->assertSame($index + 1, $layout->position($node->id)->depth);
            $this->assertSame(1, $layout->position($node->id)->lateral);
        }
        $this->assertSame(14, $layout->position($nodes[13]->id)->depth);
    }

    public function test_lateral_order_follows_causal_parent_order_not_phase_gate_or_global_creation_order(): void
    {
        [$horizon, $phase, $origin] = $this->fixture();
        $participant = $phase->cycle->participant;

        $parents = [];
        foreach (['Luna parent', 'Vela parent A', 'Vela parent B', 'Nova parent'] as $label) {
            $node = $this->node($phase, $label, 'activity');
            $this->edge($phase, $origin, $node);
            $parents[] = $node;
        }

        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->id,
            'sequence' => 2,
            'context_horizon_id' => $horizon->id,
        ])->save();
        $childPhase = new Phase;
        $childPhase->forceFill([
            'cycle_id' => $cycle->id,
            'kind' => PhaseKind::Persist,
            // Deliberately one shared producing Gate: cardinal ordering belongs to
            // the real causal parents below, not this Phase placement metadata.
            'gate_proposition_id' => $parents[0]->id,
            'write_register' => 'frontier',
            'started_at' => now(),
        ])->save();

        // Global creation order is Luna, Nova, Vela, Vela. Parent-relative
        // cardinal order must instead be Luna, Vela, Vela, Nova.
        $luna = $this->node($childPhase, 'Luna', 'frontier');
        $nova = $this->node($childPhase, 'Nova', 'frontier');
        $velaA = $this->node($childPhase, 'Vela A', 'frontier');
        $velaB = $this->node($childPhase, 'Vela B', 'frontier');

        $this->edge($childPhase, $parents[0], $luna);
        $this->edge($childPhase, $parents[3], $nova);
        $this->edge($childPhase, $parents[1], $velaA);
        $this->edge($childPhase, $parents[2], $velaB);

        $layout = app(DomainCardinalLayoutService::class)->for($horizon->fresh());

        $this->assertSame(
            [1, 2, 3, 4],
            collect([$luna, $velaA, $velaB, $nova])
                ->map(fn ($node) => $layout->position($node->id)->lateral)->all(),
        );
        $this->assertSame(
            [2, 2, 2, 2],
            collect([$luna, $velaA, $velaB, $nova])
                ->map(fn ($node) => $layout->position($node->id)->depth)->all(),
        );
    }

    private function fixture(): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'cardinal-v2', 'title' => 'Cardinal v2', 'visibility' => 'private', 'configuration' => []])->save();

        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'fixture'])->save();

        $horizon = app(ContextHorizonProjector::class)->currentFor($substrate);
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1, 'context_horizon_id' => $horizon->id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
            'write_register' => 'activity', 'started_at' => now()])->save();

        $origin = $this->node($phase, 'Origin', 'canon', 'committal');
        $phase->forceFill(['gate_proposition_id' => $origin->id])->save();

        return [$horizon, $phase, $origin];
    }

    private function node(Phase $phase, string $statement, string $register, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'statement' => $statement, 'register' => $register, 'tier' => $tier])->save();
        app(LedgerService::class)->record($node);

        return $node;
    }

    private function edge(Phase $phase, Proposition $source, Proposition $target, string $type = 'variant'): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }
}
