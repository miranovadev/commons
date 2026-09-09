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

        $measurement = app(PropositionMeasurementService::class)
            ->recalculate($target);

        $this->assertSame(1.0, $measurement->correspondence);
        $this->assertSame(1.0, $measurement->chirality);
        $this->assertSame(1.0, $measurement->closure);
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
        $horizon = $left->first()
            ->contextHorizonMembership
            ->horizon;
        $comparison = new LineageComparison(
            new DomainLineage($horizon, $left, $leftEdges),
            new DomainLineage($horizon, $right, $rightEdges),
        );

        $closure = app(ClosureCalculator::class)->calculate($comparison);

        // Equal depth (+1) averaged with the example cadence (-0.75).
        $this->assertSame(0.125, $closure);
    }

    public function test_only_unique_non_citation_receipts_reinforce(): void
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

        $this->assertSame(1.5, $weights->get($proposition->getKey()));
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
