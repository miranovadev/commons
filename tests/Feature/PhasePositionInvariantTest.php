<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Enums\RegisterPosition;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\BiasGateService;
use App\Services\ClosureGateService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\CommitRegionService;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\MediationReadService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhasePositionService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionOptionService;
use App\Services\ReconciliationOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhasePositionInvariantTest extends TestCase
{
    use RefreshDatabase;

    public static function domains(): array
    {
        return [['settlement'], ['emergence']];
    }

    #[DataProvider('domains')]
    public function test_constrain_is_context_but_never_an_order_candidate_across_participants_and_cycles(string $domain): void
    {
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::from($domain));
        $early = $nodes[3];
        $late = $nodes[6];
        foreach ([$early, $late] as $node) {
            $this->groundFixture($node);
        }
        $order = $this->phase($reader, PhaseKind::Order, $late);
        $regions = app(CommitRegionService::class);
        $this->assertSame($early->register, $late->register);
        $this->assertSame($early->tier, $late->tier);
        $this->assertNotSame($author->id, $reader->id);
        $this->assertNotSame($early->phase->cycle_id, $order->cycle_id);
        $this->assertContains($early->id, $regions->membersFor($late)->pluck('id'));
        $this->assertTrue(app(CommitmentAdmissibilityService::class)->grounding($early)['admissible']);

        $this->assertSame([$late->id], $regions->forPhase($order)->pluck('id')->all());
        $eligibility = app(CommitmentAdmissibilityService::class)->evaluate($early, $order);
        $this->assertFalse($eligibility['admissible']);
        $this->assertFalse($eligibility['requirements']['phase_position']);
        $this->assertContains('invalid_order_phase_position', $eligibility['reasons']);
        $this->assertNotContains('source_already_committed', $eligibility['reasons']);
        $this->assertTrue(app(CommitmentAdmissibilityService::class)->evaluate($late, $order)['admissible']);
        $plan = app(InputPlanService::class)->forPhase($reader, $order, workspace: true);
        $this->assertSame([$late->id], $plan['schema']['$defs']['actions']['proposition.commit']['properties']['proposition_id']['enum']);
        $this->assertSame([], $plan['gate_options']);
        $this->assertSame([$late->id], array_column($plan['write_options'], 'id'));
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($reader, $order, ['action' => 'proposition.commit', 'proposition_id' => $early->id]);
            $this->fail('An attractive Constrain classification must never bypass Bias.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Closure candidate', $exception->getMessage());
        }
        $this->assertSame($count, Proposition::count());
        $this->assertSame(PhaseKind::Constrain, $early->fresh()->phase->kind);
        $commit = app(ContributionDispatcher::class)->dispatch($reader, $order, ['action' => 'proposition.commit', 'proposition_id' => $late->id]);
        $this->assertSame('committal', $commit->tier);
        $this->assertSame([$late->id], $commit->parents()->pluck('propositions.id')->all());
    }

    #[DataProvider('domains')]
    public function test_all_seven_positions_and_three_registers_agree_in_sql_and_hydrated_context(string $domain): void
    {
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::from($domain));
        $positions = app(PhasePositionService::class);
        $expected = $domain === 'emergence'
            ? ['activity', 'frontier', 'canon', 'activity', 'frontier', 'canon', 'canon']
            : ['signal', 'stance', 'concord', 'signal', 'stance', 'concord', 'concord'];
        foreach (PhaseKind::cases() as $produced) {
            $phase = $this->phase($author, $produced, $nodes[0]);
            $this->assertSame($expected[$produced->value - 1], $phase->write_register);
            foreach (RegisterPosition::cases() as $registerPosition) {
                $node = $this->node($phase);
                $node->forceFill(['register' => $phase->cycle->contextHorizon->domain->registerAt($registerPosition)])->save();
                foreach (PhaseKind::cases() as $operation) {
                    $expectedMember = $produced === $operation && $node->register === $expected[$operation->value - 1];
                    $this->assertSame($expectedMember, $positions->matches($node, $phase, $operation),
                        $domain.': '.$produced->name.' / '.$node->register.' at '.$operation->name);
                    $this->assertSame($expectedMember, $positions->scope(Proposition::whereKey($node->id), $phase, $operation)->exists());
                }
            }
        }
        $this->assertSame([null, null, null, null, PhaseKind::Constrain, PhaseKind::Mediate, PhaseKind::Bias],
            array_map($positions->candidatePosition(...), PhaseKind::cases()));
    }

    #[DataProvider('domains')]
    public function test_mediate_reads_constrain_and_reconciles_only_disperse_children(string $domain): void
    {
        // Isolate structural rules from a new Charge-boundary event.
        $this->resolveExpressiveFieldFixture(.8);
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::from($domain));
        $other = $this->node($this->phase($author, PhaseKind::Disperse, $nodes[3]));
        $this->edge($nodes[3], $other);
        $early = $this->node($this->phase($author, PhaseKind::Distinguish, $nodes[0]));
        $this->edge($nodes[3], $early); // Same first-register label and same Read parent: still wrong position.
        $disperse = $this->phase($reader, PhaseKind::Disperse, $nodes[3]);
        $disperse->forceFill(['completed_at' => now()])->save();
        $mediate = new Phase;
        $mediate->forceFill(['cycle_id' => $disperse->cycle_id, 'kind' => PhaseKind::Mediate,
            'write_register' => $disperse->cycle->contextHorizon->domain->registerFor(PhaseKind::Mediate),
            'gate_proposition_id' => $nodes[4]->id, 'started_at' => now()])->save();
        foreach ([null, new ParticipationContext($mediate)] as $context) {
            $service = app(MediationReadService::class);
            $this->assertSame([$nodes[3]->id], $service->readsFor($mediate, $context)->pluck('id')->all());
            $this->assertEqualsCanonicalizing([$nodes[4]->id, $other->id], $service->gatesFor($mediate, $context)->pluck('id')->all());
            $this->assertSame([$other->id], app(ReconciliationOptionService::class)->forPhase($mediate, $context)->pluck('id')->all());
        }
        $plan = app(InputPlanService::class)->forPhase($reader, $mediate);
        $this->assertSame([$other->id], $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
        try {
            app(ContributionDispatcher::class)->dispatch($reader, $mediate, ['action' => 'proposition.reconcile', 'other_proposition_id' => $early->id]);
            $this->fail('An early first-register node is not a Disperse counterpart.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Gate counterpart', $exception->getMessage());
        }
        $result = app(ContributionDispatcher::class)->dispatch($reader, $mediate, ['action' => 'proposition.reconcile', 'other_proposition_id' => $other->id]);
        $this->assertSame(PhaseKind::Mediate, $result->phase->kind);
    }

    #[DataProvider('domains')]
    public function test_bias_never_selects_persist_and_never_offers_constrain_as_its_write(string $domain): void
    {
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::from($domain));
        $early = $this->node($this->phase($author, PhaseKind::Persist, $nodes[1]));
        $this->edge($nodes[1], $early);
        $late = $this->node($this->phase($author, PhaseKind::Mediate, $nodes[4]));
        $this->edge($nodes[4], $late);
        foreach ([$early, $late, $nodes[3], $nodes[6]] as $node) {
            $this->groundFixture($node);
        }
        $bias = $this->phase($reader, PhaseKind::Bias, $late);
        foreach ([null, new ParticipationContext($bias)] as $context) {
            $this->assertSame([$late->id], app(BiasGateService::class)->populationFor($bias, $context)->pluck('id')->all());
            $this->assertSame([], app(PropositionOptionService::class)->writeOptionsFor($bias, $context)->pluck('id')->all());
        }
        $plan = app(InputPlanService::class)->forPhase($reader, $bias);
        $this->assertSame([$late->id], $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['proposition_id']['enum']);
        $this->assertSame([], $plan['write_options']); // Correct position on another route is still not local.
        try {
            app(ContributionDispatcher::class)->dispatch($reader, $bias, $this->reclassificationInputFixture($bias, $early));
            $this->fail('Persist cannot bypass Mediate.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('exact preceding Phase position', $exception->getMessage());
        }
    }

    #[DataProvider('domains')]
    public function test_traverse_and_revision_preserve_position_even_with_the_same_parent_and_register(string $domain): void
    {
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::from($domain));
        $wrong = $this->node($this->phase($author, PhaseKind::Bias, $nodes[5]));
        $this->edge($nodes[2], $wrong); // Malformed legacy edge cannot make Bias an early Write.
        foreach ([$nodes[3], $wrong] as $node) {
            $this->receiptFixture($node);
            $attestation = new Attestation;
            $attestation->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'refute'])->save();
            app(LedgerService::class)->record($attestation);
        }
        $constrain = $this->phase($author, PhaseKind::Constrain, $nodes[2]);
        foreach ([null, new ParticipationContext($constrain)] as $context) {
            $options = app(PropositionOptionService::class);
            $this->assertSame([$nodes[3]->id], $options->writeOptionsFor($constrain, $context)->pluck('id')->all());
            $this->assertSame([$nodes[3]->id], $options->traversalOptionsFor($constrain, $context)->pluck('id')->all());
            $this->assertNotContains($wrong->id, $options->revisionOptionsFor($constrain, $author, $context)->pluck('id'));
        }
        $this->expectException(DomainException::class);
        app(ContributionDispatcher::class)->dispatch($author, $constrain, ['action' => 'proposition.traverse', 'proposition_id' => $wrong->id]);
    }

    public function test_a_producing_phase_cannot_be_promoted_by_changing_its_kind(): void
    {
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::Emergence);
        $this->expectException(DomainException::class);
        $nodes[3]->phase->forceFill(['kind' => PhaseKind::Bias])->save();
    }

    #[DataProvider('domains')]
    public function test_progression_rejects_a_same_register_result_from_another_position(string $domain): void
    {
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::from($domain));
        foreach (PhaseKind::cases() as $kind) {
            $phase = $this->phase($reader, $kind, $nodes[0]);
            $wrongKind = PhaseKind::from([1 => 4, 2 => 5, 3 => 6, 4 => 1, 5 => 2, 6 => 3, 7 => 3][$kind->value]);
            $wrong = $this->node($this->phase($author, $wrongKind, $nodes[0]), $kind === PhaseKind::Order ? 'committal' : 'expressive');
            $before = [$reader->fresh()->current_proposition_id, Phase::count()];
            try {
                app(PhaseProgressionService::class)->advance($phase, $wrong);
                $this->fail('Progression must preserve '.$kind->name.' position.');
            } catch (DomainException) {
                $this->assertNull($phase->fresh()->completed_at);
                $this->assertSame($before, [$reader->fresh()->current_proposition_id, Phase::count()]);
            }
        }
    }

    public function test_historical_diagnostic_reports_early_commit_sources_without_writing(): void
    {
        [$author, $reader, $nodes] = $this->fixture(ContextDomain::Emergence);
        $this->artisan('commons:validate-phase-positions')->expectsOutputToContain('0 phase-position violation(s)')->assertSuccessful();
        $order = $this->phase($reader, PhaseKind::Order, $nodes[6]);
        $historicalCommit = $this->node($order, 'committal');
        $this->edge($nodes[3], $historicalCommit, 'commit');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->artisan('commons:validate-phase-positions', ['--substrate' => $reader->substrate_id])
            ->expectsOutputToContain('invalid_operational_edge')
            ->expectsOutputToContain('1 phase-position violation(s)')->assertFailed();
        $this->assertSame([], array_values(array_filter($queries,
            fn ($sql) => preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate)\b/i', $sql))));
        $this->assertSame(PhaseKind::Constrain, $nodes[3]->fresh()->phase->kind);
        $this->assertSame($nodes[3]->id, $historicalCommit->fresh()->parents()->sole()->id);
    }

    private function fixture(ContextDomain $domain): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'phase-positions', 'title' => 'Phase positions', 'visibility' => 'private', 'configuration' => []])->save();
        $author = $this->participant($substrate, 'author');
        $reader = $this->participant($substrate, 'reader');
        $horizon = $this->horizonForRegisterFixture($author, $domain->registerFor(PhaseKind::Distinguish));
        $originPhase = $this->phase($author, PhaseKind::Order, horizonId: $horizon->id);
        $origin = $this->node($originPhase, 'committal');
        if ($horizon->opened_by_proposition_id !== null) {
            $this->commitAncestryFixture($origin, Proposition::findOrFail($horizon->opened_by_proposition_id));
        }
        $nodes = [0 => $origin];
        foreach (range(1, 6) as $kind) {
            $phase = $this->phase($author, PhaseKind::from($kind), $nodes[$kind - 1]);
            $nodes[$kind] = $this->node($phase);
            $this->edge($nodes[$kind - 1], $nodes[$kind], match ($kind) {
                1, 2, 4 => 'variant', 5 => 'reconcile', default => 'reclassify'
            });
        }
        $closure = [...(new ClosureValues(1, 1))->resolve(), 'history_proposition_id' => null, 'history_source' => 'negative-default', 'cadence_interval' => 1];
        $this->mock(ClosureGateService::class, fn ($mock) => $mock->shouldReceive('preview', 'forCommitment')->andReturn($closure));

        return [$author, $reader, $nodes];
    }

    private function participant(Substrate $substrate, string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign])->save();

        return $participant;
    }

    private function phase(Participant $participant, PhaseKind $kind, ?Proposition $gate = null, ?string $horizonId = null): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'entry_committal_id' => ($gate?->tier === 'committal' ? $gate->id : $gate?->phase->cycle->entry_committal_id),
            'context_horizon_id' => $horizonId ?? $gate->phase->cycle->context_horizon_id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $cycle->contextHorizon->domain->registerFor($kind),
            'gate_proposition_id' => $gate?->id, 'started_at' => now()])->save();

        return $phase;
    }

    private function node(Phase $phase, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => $tier, 'statement' => $phase->kind->name])->save();
        app(LedgerService::class)->record($node);
        if ($tier === 'committal') {
            $this->freezeRegionFixture($node);
        }

        return $node;
    }

    private function edge(Proposition $source, Proposition $target, string $type = 'variant'): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id, 'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }
}
