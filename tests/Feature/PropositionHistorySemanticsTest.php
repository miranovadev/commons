<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\SupersessionStrength;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Observables\ReinforcementService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PropositionHistorySemanticsTest extends TestCase
{
    use RefreshDatabase;

    private Participant $participant;

    private Phase $history;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('schemas.cache.store', 'array');
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'fresh-history', 'title' => 'Fresh history',
            'visibility' => 'public', 'configuration' => [],
        ])->save();
        $this->participant = new Participant;
        $this->participant->forceFill([
            'substrate_id' => $substrate->id, 'callsign' => 'author', 'max_read_count' => 25,
        ])->save();
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $this->participant->id, 'sequence' => 1, 'completed_at' => now(),
            'context_horizon_id' => $this->horizonForRegisterFixture($this->participant, 'signal')->id,
        ])->save();
        $this->history = new Phase;
        $this->history->forceFill([
            'cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
            'write_register' => 'signal', 'started_at' => now(), 'completed_at' => now(),
        ])->save();
    }

    public static function expressiveOperations(): array
    {
        return [
            'Variant from Expressive' => ['variant', PhaseKind::Distinguish, 'concord', 'signal', 'expressive'],
            'Variant from Committal' => ['variant', PhaseKind::Distinguish, 'concord', 'signal', 'committal'],
            'Revise' => ['revise', PhaseKind::Persist, 'signal', 'stance', 'expressive'],
            'Reclassify' => ['reclassify', PhaseKind::Constrain, 'stance', 'concord', 'expressive'],
            'Mediate Reconcile' => ['reconcile', PhaseKind::Mediate, 'signal', 'stance', 'expressive'],
            'Bias unary hard supersession' => ['bias', PhaseKind::Bias, 'stance', 'concord', 'expressive'],
        ];
    }

    #[DataProvider('expressiveOperations')]
    public function test_non_commit_operations_create_fresh_history(
        string $operation,
        PhaseKind $kind,
        string $gateRegister,
        string $writeRegister,
        string $gateTier,
    ): void {
        $parent = $this->node('Provenance', 'concord', 'committal');
        if ($operation === 'reconcile') {
            $read = $this->node('Expressive Read', 'concord');
            $this->edge($parent, $read);
            $parent = $read;
        }
        $gate = $gateTier === 'committal' ? $parent : $this->node('Gate', $gateRegister);
        if ($gate->isNot($parent)) {
            $this->edge($parent, $gate);
        }
        $source = $gate;
        $input = ['action' => 'proposition.'.$operation, 'statement' => 'A fresh contribution'];
        if ($operation === 'revise') {
            $source = $this->node('Owned prior Write', $writeRegister);
            $this->edge($gate, $source);
            $input['proposition_id'] = $source->id;
        }
        if ($operation === 'bias') {
            $input = ['action' => 'proposition.bias', 'proposition_id' => $source->id];
        }
        $sources = collect([$source]);
        if ($operation === 'reconcile') {
            $other = $this->node('Other structure', $gateRegister);
            $this->edge($parent, $other);
            $sources->push($other);
            $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $other->id];
        }
        foreach ($sources as $item) {
            $this->giveHistory($item, $operation === 'revise' ? 'negative' : 'positive');
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->participant->id, 'sequence' => 2])->save();
        if ($kind === PhaseKind::Mediate) {
            $disperse = new Phase;
            $disperse->forceFill([
                'cycle_id' => $cycle->id, 'kind' => PhaseKind::Disperse,
                'write_register' => $gateRegister, 'gate_proposition_id' => $parent->id,
                'started_at' => now(), 'completed_at' => now(),
            ])->save();
        }
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $writeRegister,
            'gate_proposition_id' => $gate->id, 'started_at' => now(),
        ])->save();

        // Isolate history from Mediate/Bias candidate mathematics, covered separately.
        foreach ($sources as $item) {
            app(PropositionMeasurementService::class)->project($item, -0.8, -1, -1);
        }
        app(ContributionService::class)->validate($this->participant, $input);
        $state = app(PropositionStateService::class);
        $touches = $sources->mapWithKeys(fn (Proposition $item) => [$item->id => $state->touchCount($item)]);
        foreach ($sources as $item) {
            $this->assertGreaterThanOrEqual(2, $touches[$item->id]);
            $this->assertCount(1, $state->attestations(collect([$item])));
            $this->assertCount(2, $state->receipts($item));
        }
        $counts = [Attestation::count(), Receipt::count(), InstrumentExecution::count(), Phase::count()];
        $attestationTargets = Attestation::pluck('proposition_id', 'id')->all();
        $receiptTargets = Receipt::pluck('receiptable_id', 'id')->all();
        $propositions = Proposition::count();
        $target = app(ContributionDispatcher::class)->dispatch($this->participant, $phase, $input);

        $this->assertSame($propositions + 1, Proposition::count());
        $this->assertSame('expressive', $target->tier);
        $this->assertSame($writeRegister, $target->register);
        $this->assertSame(in_array($operation, ['reconcile', 'bias'], true) ? $gate->statement : $input['statement'], $target->statement);
        $edges = Supersession::where('target_proposition_id', $target->id)->get();
        $this->assertEqualsCanonicalizing($sources->pluck('id')->all(), $edges->pluck('source_proposition_id')->all());
        $this->assertCount($sources->count(), $edges);
        foreach ($edges as $edge) {
            $this->assertSame($operation === 'bias' ? 'reclassify' : $operation, $edge->type);
            $this->assertSame($operation === 'variant' ? SupersessionStrength::Soft : SupersessionStrength::Hard,
                SupersessionStrength::for($edge->type));
        }

        $this->assertSame([$target->id], $state->sourceIds($target)->all());
        $this->assertSame(0, $state->touchCount($target));
        $this->assertCount(0, $state->attestations(collect([$target])));
        $this->assertCount(0, $state->receipts($target));
        $this->assertSame(0, $target->instrumentExecutions()->count());
        $this->assertSame($counts, [Attestation::count(), Receipt::count(), InstrumentExecution::count(), Phase::count()]);
        $this->assertSame($attestationTargets, Attestation::pluck('proposition_id', 'id')->all());
        $this->assertSame($receiptTargets, Receipt::pluck('receiptable_id', 'id')->all());
        $this->assertSame(['missing_effective_attestation', 'missing_completed_receipt', 'missing_counted_reinforcement'],
            app(CommitmentAdmissibilityService::class)->grounding($target)['reasons']);
        $this->assertEquals(1, app(ReinforcementService::class)->weights(collect([$target]), $this->participant->substrate)[$target->id]);
        $this->assertSame(0.0, app(ChiralityCalculator::class)->leanFor(collect([$target->id]), $this->participant->substrate));

        // Progression earns a new touch; it must not transfer the sources' touches.
        $next = app(PhaseProgressionService::class)->advance($phase, $target);
        $this->assertSame($target->id, $next->gate_proposition_id);
        $this->assertSame(1, $state->touchCount($target));
        foreach ($sources as $item) {
            $this->assertSame($touches[$item->id], $state->touchCount($item));
            $this->assertCount(1, $state->attestations(collect([$item])));
            $this->assertCount(2, $state->receipts($item));
        }
    }

    private function giveHistory(Proposition $proposition, string $direction = 'positive'): void
    {
        $phases = [];
        foreach (range(1, 2) as $i) {
            $phase = new Phase;
            $phase->forceFill([
                'cycle_id' => $this->history->cycle_id, 'kind' => PhaseKind::Persist,
                'write_register' => $proposition->register, 'gate_proposition_id' => $proposition->id,
                'started_at' => now(), 'completed_at' => now(),
            ])->save();
            $phases[] = $phase;
        }
        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $phases[0]->id, 'proposition_id' => $proposition->id,
            'direction' => $direction, 'rationale' => 'Earned by the source only.',
        ])->save();
        app(LedgerService::class)->record($attestation);
        $executions = app(InstrumentExecutionService::class);
        foreach ([$proposition, $attestation] as $contribution) {
            $execution = $executions->start($phases[0], $proposition, 'hyperlink',
                'https://example.test/history/'.$contribution->id);
            $executions->complete($execution, $contribution, 'history-'.$contribution->id);
        }
    }

    private function node(string $statement, string $register, string $tier = 'expressive'): Proposition
    {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $this->history->id, 'register' => $register,
            'tier' => $tier, 'statement' => $statement,
        ])->save();
        app(LedgerService::class)->record($proposition);

        return $proposition;
    }

    private function edge(Proposition $source, Proposition $target): void
    {
        $edge = new Supersession;
        $edge->forceFill([
            'phase_id' => $this->history->id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => 'variant',
        ])->save();
        app(LedgerService::class)->record($edge);
    }
}
