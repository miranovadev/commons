<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
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
use App\Services\ClosureOptionService;
use App\Services\CommitRegionService;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\MediationReadService;
use App\Services\OpenAIResponseSchema;
use App\Services\OperationalInstrumentService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use App\Services\PropositionStateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
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
            'write_register' => 'activity', 'started_at' => now(), 'completed_at' => now()])->save();
        $this->regionSeed = $this->node('concord', null, 'committal');
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
        $left = $this->node($first, $this->regionSeed, position: PhaseKind::Distinguish);
        $middle = $this->node($second, $left, position: PhaseKind::Mediate);
        $early = $this->node($third, $middle, position: PhaseKind::Constrain);
        $late = $this->node($third, $middle, position: PhaseKind::Bias);
        $regions = app(CommitRegionService::class);
        foreach ([[PhaseKind::Mediate, $second, $early], [PhaseKind::Bias, $third, $middle], [PhaseKind::Order, $third, $late]] as [$kind, $write, $expected]) {
            $phase = $this->phase($kind, $late, $write);
            $this->assertSame([$expected->id], $regions->forPhase($phase)->pluck('id')->all());
        }
        $phase = $this->phase(PhaseKind::Constrain, $middle, $third);
        $this->assertSame([$early->id], app(PropositionOptionService::class)->writeOptionsFor($phase)->pluck('id')->all());
        $this->assertSame(RegisterPosition::forRegister($early->register), RegisterPosition::forRegister($late->register));
        $this->assertNotSame($early->phase->kind, $late->phase->kind);
        $this->assertCount(0, $regions->forPhase($phase));
    }

    #[DataProvider('domains')]
    public function test_mediate_reads_cross_parent_branches_before_filtering_correspondence(string $first, string $second, string $third): void
    {
        $left = $this->node($second, $this->regionSeed, position: PhaseKind::Persist);
        $right = $this->node($second, $this->regionSeed, position: PhaseKind::Persist);
        $anchor = $this->node($third, $left, position: PhaseKind::Constrain);
        $matching = $this->node($third, $right, position: PhaseKind::Constrain);
        $opposite = $this->node($third, $right, position: PhaseKind::Constrain);
        $gate = $this->node($first, $anchor, position: PhaseKind::Disperse);
        $counterpart = $this->node($first, $matching, position: PhaseKind::Disperse);
        $this->node($first, $opposite, position: PhaseKind::Disperse);
        $otherSeed = $this->node($third, $right, 'committal', position: PhaseKind::Constrain);
        $otherRead = $this->node($third, $otherSeed, position: PhaseKind::Constrain);
        $otherGate = $this->node($first, $otherRead, position: PhaseKind::Disperse);
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
        $left = $this->node($first, $this->regionSeed, position: PhaseKind::Disperse);
        $right = $this->node($first, $this->regionSeed, position: PhaseKind::Disperse);
        $gate = $this->node($second, $left, position: PhaseKind::Mediate);
        $neutral = $this->node($second, $right, position: PhaseKind::Mediate);
        $negative = $this->node($second, $right, position: PhaseKind::Mediate);
        $this->attest($negative, 'refute');
        $regionDefault = $this->node($second, $negative, position: PhaseKind::Mediate);
        $boundary = $this->node($third, $right, 'committal', position: PhaseKind::Bias);
        $outside = $this->node($second, $boundary, position: PhaseKind::Mediate);
        $this->attest($outside, 'support');
        $phase = $this->phase(PhaseKind::Bias, $gate, $third);
        $bias = app(BiasGateService::class);
        $this->assertEqualsCanonicalizing([$gate->id, $neutral->id, $negative->id, $regionDefault->id], $bias->populationFor($phase)->pluck('id')->all());
        $this->assertSame(-1.0, $bias->leanFor($phase));
        $this->assertEqualsCanonicalizing([$gate->id, $neutral->id], $bias->forPhase($phase)->pluck('id')->all());
        $this->assertCount(0, $regionDefault->attestations);
        $this->reject($phase, ['action' => 'proposition.reclassify', 'proposition_id' => $outside->id]);
        $this->attest($gate, 'support'); // The regional aggregate does not change the head-specific comparison.
        $this->assertEqualsCanonicalizing([$gate->id, $neutral->id], $bias->forPhase($phase)->pluck('id')->all());
    }

    #[DataProvider('domains')]
    public function test_closure_filters_each_cross_branch_write_and_revalidates_the_selected_source(string $first, string $second, string $third): void
    {
        $left = $this->node($second, $this->regionSeed, position: PhaseKind::Mediate);
        $right = $this->node($second, $this->regionSeed, position: PhaseKind::Mediate);
        $gate = $this->node($third, $left, position: PhaseKind::Bias);
        $positive = $this->node($third, $right, position: PhaseKind::Bias);
        $negative = $this->node($third, $right, position: PhaseKind::Bias);
        $misaligned = $this->node($third, $right, position: PhaseKind::Bias);
        $ungrounded = $this->node($third, $right, position: PhaseKind::Bias);
        $wrongRegister = $this->node($first, $right, position: PhaseKind::Disperse);
        $boundary = $this->node($third, $right, 'committal', position: PhaseKind::Bias);
        $outside = $this->node($third, $boundary, position: PhaseKind::Bias);
        foreach ([$positive, $negative, $misaligned, $outside] as $candidate) {
            $this->attest($candidate, 'support');
            $this->loadedReceiptFixture($candidate);
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
        $this->assertSame([], $plan['write_options']);
        $this->assertEqualsCanonicalizing([$positive->id, $negative->id], array_column($plan['gate_options'], 'id'));
        $this->assertEqualsCanonicalizing([$positive->id, $negative->id], $plan['schema']['$defs']['actions']['proposition.commit']['properties']['proposition_id']['enum']);
        foreach ([$outside, $misaligned, $wrongRegister, $ungrounded] as $invalid) {
            $this->reject($phase, ['action' => 'proposition.commit', 'proposition_id' => $invalid->id]);
        }
        $transport = app(OpenAIResponseSchema::class)->fromCommons($plan['schema']);
        $empty = array_fill_keys(($transport['properties']['contribution']['anyOf'][0] ?? $transport['properties']['contribution'])['required'], null);
        foreach ([$negative, $ungrounded] as $candidate) {
            $wire = ['contribution' => array_replace($empty, ['action' => 'proposition.commit', 'proposition_id' => $candidate->id])];
            $valid = (new Validator)->validate(
                Helper::toJSON($wire), Helper::toJSON($transport))->isValid();
            $this->assertSame($candidate->is($negative), $valid);
        }
        $values[$positive->id] = [1, -1];
        $this->reject($phase, ['action' => 'proposition.commit', 'proposition_id' => $positive->id]);
        $this->assertSame([$negative->id], $options->committableFor($phase)->pluck('id')->all());
        $target = app(ContributionDispatcher::class)->dispatch($this->viewer, $phase,
            ['action' => 'proposition.commit', 'proposition_id' => $negative->id]);
        $this->assertSame([$negative->id], $target->commitSources()->pluck('propositions.id')->all());
        $this->assertSame($negative->statement, $target->statement);
        $state = app(PropositionStateService::class);
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

    private function node(string $register, ?Proposition $parent, string $tier = 'expressive', string $edge = 'variant', PhaseKind $position = PhaseKind::Distinguish): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->phaseForRegisterFixture($this->history, $register, $position)->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Region '.$register])->save();
        app(LedgerService::class)->record($node);
        if ($parent) {
            $this->edge($parent, $node, $edge);
        }

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
        $cycle = $this->viewer->cycles()->whereNull('completed_at')->latest('sequence')->first();
        $cycle?->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
        if ($cycle === null || $cycle->context_horizon_id !== $gate->phase->cycle->context_horizon_id) {
            $cycle?->forceFill(['completed_at' => now()])->save();
            $horizon = $gate->phase->cycle->contextHorizon;
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
