<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Enums\RegisterPosition;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\AttestationEvidenceService;
use App\Services\BiasGateService;
use App\Services\ClosureOptionService;
use App\Services\CommitRegionPolarityService;
use App\Services\CommitRegionService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\MediationReadService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use App\Services\ReconciliationOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainOperationalEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public static function domains(): array
    {
        return [[ContextDomain::Emergence], [ContextDomain::Settlement]];
    }

    #[DataProvider('domains')]
    public function test_operational_pools_exclude_analogous_registers_in_both_query_paths(ContextDomain $domain): void
    {
        [$cycles, $seed] = $this->regions();
        $cycle = $cycles[$domain->value];
        $foreign = $cycles[$domain->opposite()->value];
        $first = $domain->registerAt(RegisterPosition::First);
        $second = $domain->registerAt(RegisterPosition::Second);
        $third = $domain->registerAt(RegisterPosition::Third);
        $anchor = $this->node($cycle, $third, $seed);
        $otherRead = $this->node($cycle, $third, $seed);
        $foreignRead = $this->node($foreign, $domain->opposite()->registerAt(RegisterPosition::Third), $seed);
        $gate = $this->node($cycle, $first, $anchor);
        $counterpart = $this->node($cycle, $first, $otherRead);
        $foreignGate = $this->node($foreign, $domain->opposite()->registerAt(RegisterPosition::First), $anchor);
        $wrongReadChild = $this->node($cycle, $first, $foreignRead);
        $disperse = $this->phase($cycle, PhaseKind::Disperse, $anchor, completed: true);
        $mediate = $this->phase($cycle, PhaseKind::Mediate, $gate);
        $measurements = app(PropositionMeasurementService::class);
        foreach ([$anchor, $otherRead, $foreignRead] as $read) {
            $measurements->project($read, .7, 0, 0);
        }
        $reads = app(MediationReadService::class);
        foreach ([null, new ParticipationContext($mediate)] as $context) {
            $this->assertEqualsCanonicalizing([$anchor->id, $otherRead->id], $reads->readsFor($mediate, $context)->pluck('id')->all());
            $this->assertEqualsCanonicalizing([$gate->id, $counterpart->id], $reads->gatesFor($mediate, $context)->pluck('id')->all());
            $this->assertSame([$counterpart->id], app(ReconciliationOptionService::class)->forPhase($mediate, $context)->pluck('id')->all());
        }
        $plan = app(InputPlanService::class)->forParticipant($cycle->participant);
        $this->assertSame([$counterpart->id], $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
        foreach ([$foreignGate, $wrongReadChild] as $invalid) {
            $this->reject($mediate, ['action' => 'proposition.reconcile', 'other_proposition_id' => $invalid->id]);
        }
        // Reusing the same valid inputs still permits competing reconciliations.
        $dispatcher = app(ContributionDispatcher::class);
        $x = $dispatcher->dispatch($cycle->participant, $mediate, ['action' => 'proposition.reconcile', 'other_proposition_id' => $counterpart->id]);
        $this->reject($mediate, ['action' => 'proposition.reconcile', 'other_proposition_id' => $counterpart->id]);
        $inverse = $this->phase($cycle, PhaseKind::Mediate, $counterpart);
        $y = $dispatcher->dispatch($cycle->participant, $inverse, ['action' => 'proposition.reconcile', 'other_proposition_id' => $gate->id]);
        $this->assertNotSame($x->id, $y->id);
        foreach ([$x, $y] as $result) {
            $this->assertSame($second, $result->register);
            $this->assertEqualsCanonicalizing([$gate->id, $counterpart->id], $result->parents->pluck('id')->all());
        }
        // Even the implicit anchor must have the exact Read register.
        $disperse->forceFill(['gate_proposition_id' => $foreignRead->id])->save();
        foreach ([null, new ParticipationContext($mediate)] as $context) {
            $this->assertCount(0, $reads->readsFor($mediate, $context));
        }

        $foreignMiddle = $this->node($foreign, $domain->opposite()->registerAt(RegisterPosition::Second), $gate);
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $foreignMiddle->phase_id, 'proposition_id' => $foreignMiddle->id, 'direction' => 'refute'])->save();
        app(LedgerService::class)->record($attestation);
        $biasPhase = $this->phase($cycle, PhaseKind::Bias, $x);
        foreach ([null, new ParticipationContext($biasPhase)] as $context) {
            $bias = app(BiasGateService::class);
            $this->assertEqualsCanonicalizing([$x->id, $y->id], $bias->populationFor($biasPhase, $context)->pluck('id')->all());
            $this->assertSame(ObservableDirection::Positive, app(AttestationEvidenceService::class)->populationDirection($seed, $second, $context));
            $this->assertEqualsCanonicalizing([$x->id, $y->id], $bias->forPhase($biasPhase, $context)->pluck('id')->all());
        }
        $this->reject($biasPhase, ['action' => 'proposition.bias', 'proposition_id' => $foreignMiddle->id]);
        $this->classificationEvidenceFixture($x);
        $biased = $dispatcher->dispatch($cycle->participant, $biasPhase, ['action' => 'proposition.bias', 'proposition_id' => $x->id]);
        $this->assertSame($third, $biased->register);

        $order = $this->phase($cycle, PhaseKind::Order, $biased);
        foreach ([null, new ParticipationContext($order)] as $context) {
            $pool = app(CommitRegionService::class)->forPhase($order, context: $context);
            $this->assertContains($biased->id, $pool->pluck('id'));
            $this->assertNotContains($foreignRead->id, $pool->pluck('id'));
            $this->assertTrue($pool->every(fn ($node) => $node->register === $third));
            $this->assertNotContains($foreignRead->id, app(ClosureOptionService::class)->forPhase($order, $context)->pluck('id'));
        }
        $this->reject($order, ['action' => 'proposition.commit', 'proposition_id' => $foreignRead->id]);
    }

    private function regions(): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'eligibility', 'title' => 'Eligibility', 'visibility' => 'public', 'configuration' => []])->save();
        $emergence = $this->cycle($substrate, 'emergence');
        $seed = $this->node($emergence, 'canon', tier: 'committal');
        app(CommitRegionPolarityService::class)->establishOrigin($seed, 'support');
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $seed->id, 'substrate_id' => $substrate->id, 'ledger_entry_id' => $seed->ledgerEntry->id,
            'correspondence' => -.5, 'chirality' => -.5, 'closure' => -.5, 'prominence' => .25, 'turbulence' => .75, 'tension' => -.5,
            'direction_before' => 'emergence', 'direction_after' => 'settlement', 'calculator_version' => 'eligibility-fixture', 'evaluated_at' => now()])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);
        // The old Emergence Cycle remains valid while Settlement operates too.
        $settlement = $this->cycle($substrate, 'settlement');

        return [['emergence' => $emergence, 'settlement' => $settlement], $seed];
    }

    private function cycle(Substrate $substrate, string $callsign): Cycle
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1])->save();

        return $cycle;
    }

    private function phase(Cycle $cycle, PhaseKind $kind, ?Proposition $gate = null, bool $completed = false): Phase
    {
        if (! $completed) {
            $cycle->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
        }
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $gate?->id,
            'write_register' => $cycle->contextHorizon->domain->registerFor($kind), 'started_at' => now(), 'completed_at' => $completed ? now() : null])->save();

        return $phase;
    }

    private function node(Cycle $cycle, string $register, ?Proposition $parent = null, string $tier = 'expressive'): Proposition
    {
        $kind = match (RegisterPosition::forRegister($register)) {
            RegisterPosition::First => PhaseKind::Distinguish,
            RegisterPosition::Second => PhaseKind::Persist,
            RegisterPosition::Third => PhaseKind::Constrain,
        };
        $phase = $this->phase($cycle, $kind, $parent, completed: true);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Local '.$register])->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => 'variant'])->save();
            app(LedgerService::class)->record($edge);
        }

        return $node;
    }

    private function reject(Phase $phase, array $input): void
    {
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase, $input);
            $this->fail('A candidate from the opposite Domain was accepted.');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
        }
    }
}
