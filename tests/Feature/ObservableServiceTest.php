<?php

namespace Tests\Feature;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitmentSnapshotService;
use App\Services\LedgerService;
use App\Services\Observables\ClosureCalculator;
use App\Services\Observables\ReinforcementService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservableServiceTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    private Participant $participant;

    private Phase $phase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->substrate = new Substrate;
        $this->substrate->forceFill([
            'slug' => 'observable-tests',
            'title' => 'Observable tests',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $this->substrate->save();

        $this->participant = new Participant;
        $this->participant->forceFill([
            'substrate_id' => $this->substrate->getKey(),
            'callsign' => 'observer',
        ]);
        $this->participant->save();

        $this->phase = $this->phase(1);
    }

    public function test_the_origin_uses_the_documented_bootstrap_values(): void
    {
        $origin = $this->proposition('Origin.', 'canon', 'committal');
        $measurement = app(PropositionMeasurementService::class)
            ->recalculate($origin);

        $this->assertSame(-1.0, $measurement->correspondence);
        $this->assertSame(-1.0, $measurement->chirality);
        $this->assertSame(-1.0, $measurement->closure);
    }

    public function test_matching_lineages_in_complementary_horizons_align(): void
    {
        // Bootstrap at the same depth as the two-node complementary lineages,
        // so the intermediate boundary is admissible under Closure hysteresis.
        $seed = $this->proposition('Origin activity.', 'activity');
        $origin = $this->proposition('Origin.', 'canon', 'committal');
        $this->supersede($seed, $origin, 'revise');
        $this->closeHorizon($origin, prominence: 0, turbulence: 1);

        $first = $this->proposition('Settlement activity.', 'activity');
        $this->supersede($origin, $first, 'variant');
        $boundary = $this->proposition(
            'Settlement canon.',
            'canon',
            'committal',
        );
        $this->supersede($first, $boundary, 'revise');
        $this->attest($first, 'refute');
        $this->attest($boundary, 'support');
        $this->closeHorizon($boundary, prominence: 1, turbulence: 0);

        $next = $this->proposition('Emergence activity.', 'activity');
        $this->supersede($boundary, $next, 'variant');
        $target = $this->proposition('Emergence canon.', 'canon');
        $this->supersede($next, $target, 'revise');
        $this->attest($next, 'refute');
        $this->attest($target, 'support');

        $this->verifiedReceiptFixture($target);
        $measurement = app(PropositionMeasurementService::class)
            ->recalculate($target);

        $local = app(\App\Services\Observables\LocalMeasurementService::class);
        $headValues = $local->calculate($target);
        $this->assertSame(1.0, $headValues->correspondence);
        $this->assertSame(1.0, $headValues->chirality);
        $this->assertSame(1.0, $headValues->closure()->average());
        // Accumulation includes the intermediate position and boundary, not the
        // boundary's older ancestors. Raw comparisons still align exactly.
        $middleValues = $local->calculate($next);
        $provenanceValues = $local->calculate($boundary);
        $mass = 1 + 1 / 4 + 1 / 9;
        foreach (['correspondence', 'chirality'] as $component) {
            $this->assertSame(round(($headValues->{$component} + $middleValues->{$component} / 4
                + $provenanceValues->{$component} / 9) / $mass, 7), $measurement->{$component});
        }
        $depth = round(($headValues->depth + $middleValues->depth / 4 + $provenanceValues->depth / 9) / $mass, 7);
        $cadence = round(($headValues->cadence + $middleValues->cadence / 4 + $provenanceValues->cadence / 9) / $mass, 7);
        $this->assertSame(round(($depth + $cadence) / 2, 7), $measurement->closure);
    }

    public function test_closure_matches_the_documented_cadence_example(): void
    {
        $left = collect([1, 2, 3, 8])->map(
            fn (int $sequence) => $this->proposition(
                "Left {$sequence}.",
                'activity',
                phase: $this->phase($sequence),
            ),
        );
        $right = collect([12, 16, 21, 26])->map(
            fn (int $sequence) => $this->proposition(
                "Right {$sequence}.",
                'activity',
                phase: $this->phase($sequence),
            ),
        );
        $leftEdges = $this->chain($left);
        $rightEdges = $this->chain($right);
        foreach ([[$left, [2, 2, 3, 2]], [$right, [1, 1, 9, 1]]] as [$nodes, $touches]) {
            foreach ($nodes as $index => $node) {
                $this->touch($node, $touches[$index]);
            }
        }
        $horizon = $left->first()
            ->contextHorizonMembership
            ->horizon;
        $comparison = new LineageComparison(
            new DomainLineage($horizon, $left, $leftEdges),
            new DomainLineage($horizon, $right, $rightEdges),
        );

        $closure = app(ClosureCalculator::class)->calculate($comparison);

        // Equal active-head Depth (+1). The touch CVs have ratio 1/6,
        // so their existing normalized comparison is -2/3.
        $this->assertSame(0.1666667, $closure);
    }

    public function test_closure_observes_active_heads_and_not_superseded_touch_or_receipt_populations(): void
    {
        $old = $this->proposition('Historical.', 'activity');
        $left = $this->proposition('Active left.', 'activity');
        $right = $this->proposition('Active right.', 'activity');
        $edges = collect([$this->supersede($old, $left, 'revise'), $this->supersede($old, $right, 'variant')]);
        $lineage = new DomainLineage($old->contextHorizonMembership->horizon, collect([$old, $left, $right]), $edges);
        $this->touch($left, 2);
        $this->touch($right, 2);
        $calculator = app(ClosureCalculator::class);
        $this->assertSame(2.0, $calculator->depth($lineage));
        $this->assertSame(0.0, $calculator->coefficientOfVariation($lineage));
        $this->touch($old, 20);
        $this->receipt($old, 'artefact', 'historical-weight');
        $this->assertSame(2.0, $calculator->depth($lineage));
        $this->assertSame(0.0, $calculator->coefficientOfVariation($lineage));

        // Raw Depth stays structural even when a legacy Receipt has a signature.
        $this->receipt($left, 'artefact', 'active-weight');
        $this->assertEqualsWithDelta(2, $calculator->depth($lineage), 1e-10);
        $this->touch($left, 2);
        $this->assertEqualsWithDelta(1 / 3, $calculator->coefficientOfVariation($lineage), 1e-10);
    }

    public function test_phase_touches_are_per_proposition_and_independent_of_time_or_cycle_spacing(): void
    {
        $nodes = collect(['A', 'B', 'C'])->map(fn ($name) => $this->proposition($name, 'activity'));
        foreach ([0, 1, 1, 2, 1] as $index) {
            $this->touch($nodes[$index], 1);
        }
        $state = app(\App\Services\PropositionStateService::class);
        $this->assertSame([1, 3, 1], $nodes->map(fn ($node) => $state->touchCount($node))->all());
        $lineage = new DomainLineage($nodes[0]->contextHorizonMembership->horizon, $nodes, collect());
        $calculator = app(ClosureCalculator::class);
        $cv = $calculator->coefficientOfVariation($lineage);
        $this->assertEqualsWithDelta(sqrt(8) / 5, $cv, 1e-10);
        Phase::query()->update(['started_at' => '2000-01-01', 'completed_at' => '2050-01-01']);
        Cycle::query()->update(['sequence' => 9000]);
        $this->assertSame($cv, $calculator->coefficientOfVariation($lineage));
        $this->assertSame(0, \App\Models\CommitmentSnapshot::count());
    }

    private function touch(Proposition $proposition, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $phase = new Phase;
            $phase->forceFill(['cycle_id' => $proposition->phase->cycle_id,
                'kind' => PhaseKind::Persist, 'gate_proposition_id' => $proposition->id,
                'write_register' => 'frontier', 'started_at' => now(), 'completed_at' => now()])->save();
        }
    }

    public function test_legacy_receipt_signatures_without_verification_do_not_reinforce(): void
    {
        $this->substrate->configuration = [
            'observables' => ['contribution_receipt_ratio' => 2],
        ];
        $this->substrate->save();
        $proposition = $this->proposition('Grounded.', 'activity');

        $this->receipt($proposition, 'artefact', 'unique-artefact');
        $this->receipt($proposition, 'citation', 'unique-citation');
        $this->receipt($proposition, 'hyperlink', null);

        $weights = app(ReinforcementService::class)->weights(
            collect([$proposition]),
            $this->substrate->fresh(),
        );

        $this->assertSame(1.0, $weights->get($proposition->getKey()));
    }

    private function phase(int $sequence): Phase
    {
        $cycle = Cycle::query()
            ->whereBelongsTo($this->participant)
            ->where('sequence', $sequence)
            ->first();

        if ($cycle === null) {
            $cycle = new Cycle;
            $cycle->forceFill([
                'participant_id' => $this->participant->getKey(),
                'sequence' => $sequence,
            ]);
            $cycle->save();
        }

        $existing = Phase::query()
            ->whereBelongsTo($cycle)
            ->where('kind', PhaseKind::Distinguish->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Distinguish,
            'write_register' => 'activity',
            'started_at' => now(),
        ]);
        $phase->save();

        return $phase;
    }

    private function proposition(
        string $statement,
        string $register,
        string $tier = 'expressive',
        ?Phase $phase = null,
    ): Proposition {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => ($phase ?? $this->phase)->getKey(),
            'register' => $register,
            'tier' => $tier,
            'statement' => $statement,
        ]);
        $proposition->save();
        app(LedgerService::class)->record($proposition);

        return $proposition;
    }

    private function supersede(
        Proposition $source,
        Proposition $target,
        string $type,
    ): Supersession {
        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $target->phase_id,
            'source_proposition_id' => $source->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => $type,
        ]);
        $supersession->save();
        app(LedgerService::class)->record($supersession);

        return $supersession;
    }

    private function attest(
        Proposition $proposition,
        string $direction,
    ): Attestation {
        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $proposition->phase_id,
            'proposition_id' => $proposition->getKey(),
            'direction' => $direction,
        ]);
        $attestation->save();
        app(LedgerService::class)->record($attestation);

        return $attestation;
    }

    private function closeHorizon(
        Proposition $proposition,
        float $prominence,
        float $turbulence,
    ): void {
        $this->groundFixture($proposition);
        app(PropositionMeasurementService::class)->project(
            $proposition,
            correspondence: -1,
            chirality: -1,
            closure: -1,
        );
        app(CommitmentSnapshotService::class)->record(
            $proposition,
            $prominence,
            $turbulence,
            'observable-test-v1',
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Proposition>  $nodes
     * @return \Illuminate\Support\Collection<int, Supersession>
     */
    private function chain($nodes)
    {
        return $nodes
            ->zip($nodes->slice(1))
            ->filter(fn ($pair) => $pair[1] !== null)
            ->map(
                fn ($pair) => $this->supersede(
                    $pair[0],
                    $pair[1],
                    'variant',
                ),
            )
            ->values();
    }

    private function receipt(
        Proposition $proposition,
        string $type,
        ?string $signature,
    ): Receipt {
        $execution = new InstrumentExecution;
        $execution->forceFill([
            'phase_id' => $proposition->phase_id,
            'proposition_id' => $proposition->getKey(),
            'kind' => $type,
            'reference' => "{$type}:{$signature}",
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $execution->save();

        $receipt = new Receipt;
        $receipt->forceFill([
            'instrument_execution_id' => $execution->getKey(),
            'type' => $type,
            'reference' => "{$type}:{$signature}",
            'signature' => $signature,
        ]);
        $receipt->receiptable()->associate($proposition);
        $receipt->save();

        return $receipt;
    }
}
