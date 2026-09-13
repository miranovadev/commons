<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Enums\{PhaseKind, RegisterPosition};
use App\Models\{Attestation, Cycle, Participant, Phase, Proposition, Substrate, Supersession};
use App\Services\{BiasGateService, ClosureGateService, ClosureOptionService, CommitRegionService, ContributionDispatcher, InputPlanService, LedgerService, MediationReadService, OperationalInstrumentService, PhaseProgressionService, PropositionMeasurementService, PropositionOptionService};
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommitRegionTest extends TestCase
{
    use RefreshDatabase;

    private Phase $history;
    private Participant $viewer;
    private Proposition $regionSeed;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'regions', 'title' => 'Regions', 'visibility' => 'public', 'configuration' => []])->save();
        $this->viewer = new Participant;
        $this->viewer->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'viewer', 'max_read_count' => 1])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->viewer->id, 'sequence' => 1])->save();
        $this->history = new Phase;
        $this->history->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
            'write_register' => 'signal', 'started_at' => now(), 'completed_at' => now()])->save();
        $this->regionSeed = $this->node('canon', null, 'committal');
        config()->set('schemas.options.limit', 1);
    }

    public function test_region_traverses_all_expressive_branches_but_stops_at_every_commit_in_the_same_horizon(): void
    {
        $left = $this->node('signal', $this->regionSeed);
        $right = $this->node('signal', $this->regionSeed);
        $a = $this->node('stance', $left, edge: 'reclassify');
        $b = $this->node('stance', $right);
        $merged = $this->node('concord', $a, edge: 'reconcile');
        $this->edge($b, $merged, 'reconcile');
        $next = $this->node('concord', $merged, 'committal', 'commit');
        $nextChild = $this->node('signal', $next);
        $unrelated = $this->node('canon', null, 'committal');
        $outsider = $this->node('stance', $unrelated);
        $regions = app(CommitRegionService::class);
        foreach ([$this->regionSeed, $left, $right, $a, $b, $merged] as $member) {
            $this->assertSame($this->regionSeed->id, $regions->seedFor($member)->id);
            $this->assertEqualsCanonicalizing([$left->id, $right->id, $a->id, $b->id, $merged->id],
                $regions->membersFor($member)->pluck('id')->all());
        }
        $this->assertSame($next->id, $regions->seedFor($next)->id);
        $this->assertSame([$nextChild->id], $regions->membersFor($next)->pluck('id')->all());
        $this->assertSame($unrelated->id, $regions->seedFor($outsider)->id);
        $this->assertSame($this->regionSeed->contextHorizonMembership->context_horizon_id,
            $nextChild->contextHorizonMembership->context_horizon_id);
    }

    public function test_conflicting_legacy_ancestry_does_not_join_two_regions(): void
    {
        $left = $this->node('stance', $this->regionSeed);
        $other = $this->node('canon', null, 'committal');
        $right = $this->node('stance', $other);
        $mixed = $this->node('concord', $left, edge: 'reconcile');
        $this->edge($right, $mixed, 'reconcile');
        $descendant = $this->node('concord', $mixed);
        $regions = app(CommitRegionService::class);
        $this->assertNull($regions->seedFor($mixed));
        $this->assertNull($regions->seedFor($descendant));
        $this->assertSame([$left->id], $regions->membersFor($this->regionSeed)->pluck('id')->all());
        $this->assertSame([$right->id], $regions->membersFor($other)->pluck('id')->all());
    }

    public static function domains(): array
    {
        return [['signal', 'stance', 'concord'], ['activity', 'frontier', 'canon']];
    }

    #[DataProvider('domains')]
    public function test_phase_positions_require_exact_registers_and_early_phases_stay_local(string $first, string $second, string $third): void
    {
        $left = $this->node($first, $this->regionSeed);
        $right = $this->node($first, $this->regionSeed);
        $middle = $this->node($second, $left);
        $end = $this->node($third, $middle);
        $parallel = $this->node($third, $right);
        $regions = app(CommitRegionService::class);
        foreach ([PhaseKind::Mediate, PhaseKind::Order] as $kind) {
            $phase = $this->phase($kind, $end, $kind === PhaseKind::Mediate ? $second : $third);
            $this->assertEqualsCanonicalizing([$end->id, $parallel->id], $regions->forPhase($phase)->pluck('id')->all());
        }
        $phase = $this->phase(PhaseKind::Bias, $middle, $third);
        $this->assertSame([$middle->id], $regions->forPhase($phase)->pluck('id')->all());
        $equivalent = $this->node($second === 'stance' ? 'frontier' : 'stance', $right);
        $this->assertSame([$middle->id], $regions->forPhase($phase)->pluck('id')->all());
        $this->assertSame(RegisterPosition::forRegister($second), RegisterPosition::forRegister($equivalent->register));
        $unvisited = $this->node($third, $middle);
        foreach ([PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse] as $kind) {
            $early = $this->phase($kind, $middle, $third);
            $this->assertCount(0, $regions->forPhase($early));
            $this->assertSame([$unvisited->id], app(PropositionOptionService::class)->writeOptionsFor($early)->pluck('id')->all());
        }
    }

    #[DataProvider('domains')]
    public function test_mediate_reads_cross_parent_branches_before_filtering_correspondence(string $first, string $second, string $third): void
    {
        $left = $this->node($second, $this->regionSeed);
        $right = $this->node($second, $this->regionSeed);
        $anchor = $this->node($third, $left);
        $matching = $this->node($third, $right);
        $opposite = $this->node($third, $right);
        $gate = $this->node($first, $anchor);
        $counterpart = $this->node($first, $matching);
        $this->node($first, $opposite);
        $otherSeed = $this->node($third, $right, 'committal');
        $otherRead = $this->node($third, $otherSeed);
        $otherGate = $this->node($first, $otherRead);
        $disperse = $this->phase(PhaseKind::Disperse, $anchor, $first);
        $phase = app(PhaseProgressionService::class)->advance($disperse, $gate);
        foreach ([1, -1] as $direction) {
            foreach ([$anchor, $matching, $otherRead] as $read) {
                app(PropositionMeasurementService::class)->project($read, $direction * 0.7, 0, 0);
            }
            app(PropositionMeasurementService::class)->project($opposite, -$direction * 0.7, 0, 0);
            app(PropositionMeasurementService::class)->project($counterpart, -$direction, 0, 0);
            $service = app(MediationReadService::class);
            $this->assertSame([$anchor->id, $matching->id], $service->readsFor($phase)->pluck('id')->all());
            $this->assertEqualsCanonicalizing([$gate->id, $counterpart->id], $service->gatesFor($phase)->pluck('id')->all());
            foreach ([$anchor, $matching, $opposite, $otherRead] as $read) {
                app(PropositionMeasurementService::class)->project($read, 0, 0, 0);
            }
            $this->assertSame([$anchor->id, $matching->id], $service->readsFor($phase)->pluck('id')->all());
            $this->assertEqualsCanonicalizing([$gate->id, $counterpart->id], $service->gatesFor($phase)->pluck('id')->all());
            $this->reject($phase, ['action' => 'proposition.reconcile', 'other_proposition_id' => $otherGate->id]);
        }
    }

    #[DataProvider('domains')]
    public function test_bias_filters_cross_branch_retained_directions_including_zero_attestations(string $first, string $second, string $third): void
    {
        $left = $this->node($first, $this->regionSeed);
        $right = $this->node($first, $this->regionSeed);
        $gate = $this->node($second, $left);
        $neutral = $this->node($second, $right);
        $negative = $this->node($second, $right);
        $this->attest($negative, 'refute');
        $regionDefault = $this->node($second, $negative);
        $boundary = $this->node($third, $right, 'committal');
        $outside = $this->node($second, $boundary);
        $this->attest($outside, 'support');
        $phase = $this->phase(PhaseKind::Bias, $gate, $third);
        $bias = app(BiasGateService::class);
        $this->assertEqualsCanonicalizing([$gate->id, $neutral->id, $negative->id, $regionDefault->id], $bias->populationFor($phase)->pluck('id')->all());
        $this->assertSame(-1.0, $bias->leanFor($phase));
        $this->assertSame([$negative->id], $bias->forPhase($phase)->pluck('id')->all());
        $this->assertCount(0, $regionDefault->attestations);
        $this->reject($phase, ['action' => 'proposition.bias', 'proposition_id' => $outside->id]);
        $this->attest($gate, 'support'); // Balanced aggregate retains its earlier negative direction.
        $this->assertSame([$negative->id], $bias->forPhase($phase)->pluck('id')->all());
    }

    #[DataProvider('domains')]
    public function test_closure_filters_each_cross_branch_write_and_revalidates_the_selected_source(string $first, string $second, string $third): void
    {
        $left = $this->node($second, $this->regionSeed);
        $right = $this->node($second, $this->regionSeed);
        $gate = $this->node($third, $left);
        $positive = $this->node($third, $right);
        $negative = $this->node($third, $right);
        $misaligned = $this->node($third, $right);
        $ungrounded = $this->node($third, $right);
        $wrongRegister = $this->node($first, $right);
        $boundary = $this->node($third, $right, 'committal');
        $outside = $this->node($third, $boundary);
        foreach ([$positive, $negative, $misaligned, $outside] as $candidate) {
            $this->attest($candidate, 'support');
            $this->verifiedReceiptFixture($candidate);
            app(OperationalInstrumentService::class)->execute($this->history, $candidate,
                ['kind' => 'hyperlink', 'reference' => 'https://example.test/'.$candidate->id]);
        }
        $phase = $this->phase(PhaseKind::Order, $gate, $third);
        $values = [$positive->id => [1, 1], $negative->id => [-1, -1], $ungrounded->id => [-1, -1]];
        $this->mock(ClosureGateService::class, function ($mock) use (&$values): void {
            $mock->shouldReceive('preview')->andReturnUsing(function (Proposition $candidate) use (&$values): array {
                return (new ClosureValues(...($values[$candidate->id] ?? [1, -1])))->resolve();
            });
            $mock->shouldReceive('forCommitment')->andReturn([
                ...(new ClosureValues(-1, -1))->resolve(),
                'history_source' => 'negative-default', 'history_proposition_id' => null,
            ]);
        });
        $options = app(ClosureOptionService::class);
        $this->assertEqualsCanonicalizing([$positive->id, $negative->id, $ungrounded->id], $options->forPhase($phase)->pluck('id')->all());
        $plan = app(InputPlanService::class)->forParticipant($this->viewer);
        $this->assertEqualsCanonicalizing([$positive->id, $negative->id, $ungrounded->id], array_column($plan['write_options'], 'id'));
        $this->assertEqualsCanonicalizing([$positive->id, $negative->id], $plan['schema']['$defs']['actions']['proposition.commit']['properties']['proposition_id']['enum']);
        foreach ([$outside, $misaligned, $wrongRegister, $ungrounded] as $invalid) {
            $this->reject($phase, ['action' => 'proposition.commit', 'proposition_id' => $invalid->id]);
        }
        $transport = app(\App\Services\OpenAIResponseSchema::class)->fromCommons($plan['schema']);
        $empty = array_fill_keys($transport['properties']['contribution']['anyOf'][0]['required'], null);
        foreach ([$negative, $ungrounded] as $candidate) {
            $wire = ['contribution' => array_replace($empty, ['action' => 'proposition.commit', 'proposition_id' => $candidate->id])];
            $valid = (new \Opis\JsonSchema\Validator)->validate(
                \Opis\JsonSchema\Helper::toJSON($wire), \Opis\JsonSchema\Helper::toJSON($transport))->isValid();
            $this->assertSame($candidate->is($negative), $valid);
        }
        $values[$positive->id] = [1, -1];
        $this->reject($phase, ['action' => 'proposition.commit', 'proposition_id' => $positive->id]);
        $this->assertSame([$negative->id], $options->committableFor($phase)->pluck('id')->all());
        $target = app(ContributionDispatcher::class)->dispatch($this->viewer, $phase,
            ['action' => 'proposition.commit', 'proposition_id' => $negative->id]);
        $this->assertSame([$negative->id], $target->commitSources()->pluck('propositions.id')->all());
        $this->assertSame($negative->statement, $target->statement);
        $state = app(\App\Services\PropositionStateService::class);
        $this->assertSame($state->attestations(collect([$negative]))->pluck('id')->all(),
            $state->attestations(collect([$target]))->pluck('id')->all());
        $this->assertSame($state->touchCount($negative), $state->touchCount($target));
        $this->assertSame($target->id, app(CommitRegionService::class)->seedFor($target)->id);
    }

    private function reject(Phase $phase, array $input): void
    {
        $before = [Proposition::count(), Supersession::count()];
        try {
            app(ContributionDispatcher::class)->dispatch($this->viewer, $phase, $input);
            $this->fail('Out-of-scope or stale candidate was accepted.');
        } catch (DomainException) {
            $this->assertSame($before, [Proposition::count(), Supersession::count()]);
        }
    }

    private function node(string $register, ?Proposition $parent, string $tier = 'expressive', string $edge = 'variant'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->history->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Region '.$register])->save();
        app(LedgerService::class)->record($node);
        if ($parent) $this->edge($parent, $node, $edge);
        return $node;
    }

    private function edge(Proposition $parent, Proposition $child, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->history->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $child->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    private function phase(PhaseKind $kind, Proposition $gate, string $register): Phase
    {
        $cycle = $this->viewer->cycles()->latest('sequence')->firstOrFail();
        $cycle->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
        if ($cycle->contextHorizon->domain->registerAt(RegisterPosition::forRegister($register)) !== $register) {
            $cycle->forceFill(['completed_at' => now()])->save();
            $horizon = $this->horizonForRegisterFixture($this->viewer, $register);
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $this->viewer->id, 'sequence' => $this->viewer->cycles()->max('sequence') + 1,
                'context_horizon_id' => $horizon->id])->save();
        }
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $gate->id,
            'write_register' => $register, 'started_at' => now()])->save();
        $this->viewer->forceFill(['current_proposition_id' => $gate->id])->save();
        return $phase;
    }

    private function attest(Proposition $node, string $direction): void
    {
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $this->history->id, 'proposition_id' => $node->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($attestation);
    }
}
