<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitmentSnapshotService;
use App\Services\LedgerService;
use App\Services\Observables\ClosureCalculator;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ReinforcementService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class ObservableServiceTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    private Substrate $substrate;

    private Participant $participant;

    private Phase $phase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();

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

        $this->assertSame(1.0, $measurement->correspondence);
        $this->assertSame(-0.5, $measurement->chirality);
        $this->assertSame(-1.0, $measurement->closure);
    }

    public function test_first_developed_domain_compares_against_origin_with_inverse_square_attenuation(): void
    {
        $origin = $this->proposition('Origin.', 'canon', 'committal');
        $this->closeHorizon($origin, prominence: 0, turbulence: 1);

        $this->phase = $this->phase(2, $origin);
        $first = $this->proposition('First developed Domain.', 'signal');
        $this->supersede($origin, $first, 'variant');

        $raw = app(LocalMeasurementService::class)->calculate($first);
        $this->assertSame(-0.2642978, $raw->correspondence);

        $measurement = app(PropositionMeasurementService::class)->recalculate($first);
        $this->assertSame(round(($raw->correspondence + 1 / 4) / (1 + 1 / 4), 7), $measurement->correspondence);
    }

    public function test_component_ancestry_attenuates_independent_current_measurements_until_the_committal_boundary(): void
    {
        // Snapshot timestamps intentionally tie; fixture ancestry must be explicit.
        $this->freezeTime();
        $this->assertNull($this->phase->cycle->entry_committal_id);

        // Origin is the unique parentless provenance, not an expressive child.
        $origin = $this->proposition('Origin.', 'canon', 'committal');
        $this->closeHorizon($origin, prominence: 0, turbulence: 1);

        $this->phase = $this->phase(2, $origin);
        $settlementCycle = $this->phase->cycle->fresh('contextHorizon');
        $this->assertSame($origin->id, $settlementCycle->entry_committal_id);
        $this->assertSame(ContextDomain::Settlement, $settlementCycle->contextHorizon->domain);
        $this->assertSame($origin->id, $settlementCycle->contextHorizon->opened_by_proposition_id);
        $this->assertSame($origin->phase->cycle->context_horizon_id, $settlementCycle->contextHorizon->parent_context_horizon_id);
        $first = $this->proposition('Settlement activity.', 'signal');
        $this->supersede($origin, $first, 'variant');
        $boundary = $this->proposition(
            'Settlement canon.',
            'concord',
            'committal',
        );
        $this->supersede($first, $boundary, 'commit');
        // Authored read-model boundary; this component case does not prove Commit authority.
        $this->attest($first, 'support');
        $this->attest($boundary, 'support');
        $this->closeHorizon($boundary, prominence: 1, turbulence: 0);

        $this->assertTrue($origin->fresh()->commitmentSnapshot->evaluated_at->equalTo($boundary->fresh()->commitmentSnapshot->evaluated_at));

        $this->phase = $this->phase(3, $boundary);
        $emergenceCycle = $this->phase->cycle->fresh('contextHorizon');
        $this->assertSame($boundary->id, $emergenceCycle->entry_committal_id);
        $this->assertSame(ContextDomain::Emergence, $emergenceCycle->contextHorizon->domain);
        $this->assertSame($boundary->id, $emergenceCycle->contextHorizon->opened_by_proposition_id);
        $this->assertSame($settlementCycle->context_horizon_id, $emergenceCycle->contextHorizon->parent_context_horizon_id);
        $next = $this->proposition('Emergence activity.', 'activity');
        $this->supersede($boundary, $next, 'variant');
        $target = $this->proposition('Emergence canon.', 'canon');
        $this->supersede($next, $target, 'revise');
        $this->attest($next, 'refute');
        $this->attest($target, 'support');

        $this->loadedReceiptFixture($target);
        $measurement = app(PropositionMeasurementService::class)
            ->recalculate($target);

        $local = app(LocalMeasurementService::class);
        $headValues = $local->calculate($target);
        $this->assertSame(round(($headValues->correspondenceComponents->shapeTopology + $headValues->correspondenceComponents->phaseProfile) / 2, 7), $headValues->correspondence);
        $this->assertSame($headValues->chiralityComponents->average(), $headValues->chirality);
        $this->assertSame($headValues->closure()->average(), $measurement->closure);
        // Accumulation includes the intermediate position and boundary, not the
        // boundary's older ancestors. A matching shape does not prescribe C1 or Chirality.
        $middleValues = $local->calculate($next);
        $provenanceValues = $local->calculate($boundary);
        $mass = 1 + 1 / 4 + 1 / 9;
        foreach (['correspondence', 'chirality'] as $component) {
            $this->assertSame(round(($headValues->{$component} + $middleValues->{$component} / 4
                + $provenanceValues->{$component} / 9) / $mass, 7), $measurement->{$component});
        }
        $this->assertSame($headValues->closure()->average(), $measurement->closure);
    }

    public function test_closure_matches_committed_interval_similarity_examples(): void
    {
        $this->assertSame(1.0, ClosureCalculator::similarity(10, 10));
        $this->assertSame(0.3333333, ClosureCalculator::similarity(10, 15));
        $this->assertSame(0.0, ClosureCalculator::similarity(10, 20));
        $this->assertSame(-0.5, ClosureCalculator::similarity(10, 40));
    }

    public function test_phase_touches_remain_proposition_state_but_do_not_supply_closure(): void
    {
        $nodes = collect(['A', 'B', 'C'])->map(fn ($name) => $this->proposition($name, 'activity'));
        foreach ([0, 1, 1, 2, 1] as $index) {
            $this->touch($nodes[$index], 1);
        }
        $state = app(PropositionStateService::class);
        $this->assertSame([1, 3, 1], $nodes->map(fn ($node) => $state->touchCount($node))->all());
        $calc = app(ClosureCalculator::class);
        $before = $calc->components($nodes[0]);
        $this->touch($nodes[0], 20);
        Phase::query()->update(['started_at' => '2000-01-01', 'completed_at' => '2050-01-01']);
        Cycle::query()->update(['sequence' => 9000]);
        $this->assertEquals($before, $calc->components($nodes[0]));
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

        $this->assertSame(0, app(ReinforcementService::class)->count($proposition));
    }

    private function phase(int $sequence, ?Proposition $entryCommittal = null): Phase
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
                'entry_committal_id' => $entryCommittal?->getKey(),
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
            'write_register' => $cycle->contextHorizon->domain->registerFor(PhaseKind::Distinguish),
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
        // Snapshot assembly is isolated from empirical Closure history here.
        $this->componentClosureInput($proposition, ['cadence_interval' => 1,
            'history_proposition_id' => $proposition->phase->cycle->entry_committal_id]);
        app(CommitmentSnapshotService::class)->record(
            $proposition,
            $prominence,
            $turbulence,
            'observable-test-v1',
        );
    }

    /**
     * @param  Collection<int, Proposition>  $nodes
     * @return Collection<int, Supersession>
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
        $execution = new EvidenceExecution;
        $execution->forceFill([
            'phase_id' => $proposition->phase_id,
            'proposition_id' => $proposition->getKey(),
            'kind' => $type,
            'reference' => "{$type}:{$signature}",
            'status' => 'pending',
            'started_at' => now(),
        ]);
        $execution->save();
        $execution->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

        $receipt = new Receipt;
        $receipt->forceFill([
            'evidence_execution_id' => $execution->getKey(),
            'type' => $type,
            'reference' => "{$type}:{$signature}",
            'signature' => $signature,
        ]);
        $receipt->receiptable()->associate($proposition);
        $receipt->save();

        return $receipt;
    }
}
