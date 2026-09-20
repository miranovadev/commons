<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\BiasGateService;
use App\Services\ClosureGateService;
use App\Services\ClosureOptionService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\CommitRegionPolarityService;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipantContextSerializer;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseGenerationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CausalOperationalScopeTest extends TestCase
{
    use RefreshDatabase;

    private Participant $author;

    private Participant $reader;

    private array $closureValues = [];

    public static function domains(): array
    {
        return [['settlement'], ['emergence']];
    }

    #[DataProvider('domains')]
    public function test_bias_calculates_regionally_but_does_not_expose_or_follow_another_route(string $domain): void
    {
        $seed = $this->fixture($domain);
        $a = $this->node($seed, PhaseKind::Mediate);
        $b = $this->node($seed, PhaseKind::Mediate);
        $this->groundFixture($a);
        $this->groundFixture($b);
        $bias = $this->phase($this->reader, PhaseKind::Bias, $b);
        foreach ([null, new ParticipationContext($bias)] as $context) {
            $this->assertEqualsCanonicalizing([$a->id, $b->id], app(BiasGateService::class)->forPhase($bias, $context)->pluck('id')->all());
            $this->assertSame([$b->id], app(PropositionOptionService::class)->gateOptionsFor($bias, $context)->pluck('id')->all());
        }
        $writeA = $this->node($a, PhaseKind::Bias, 'reclassify');
        $commitA = $this->commit($writeA);
        // Same Horizon, region, register and a successful route still supply no edge from B.
        foreach ([null, new ParticipationContext($bias)] as $context) {
            $options = app(PropositionOptionService::class);
            $this->assertSame([], $options->writeOptionsFor($bias, $context)->pluck('id')->all());
            $this->assertSame([], $options->traversalOptionsFor($bias, $context)->pluck('id')->all());
            $this->assertSame([], app(PhaseGenerationService::class)->committedWrites($bias, $context)->pluck('id')->all());
        }
        $plan = app(InputPlanService::class)->forPhase($this->reader, $bias, workspace: true);
        $this->assertOperationallyAbsent($plan, [$a, $writeA, $commitA]);
        $this->reject(fn () => app(ContributionDispatcher::class)->dispatch($this->reader, $bias,
            ['action' => 'proposition.traverse', 'proposition_id' => $writeA->id]));
        $this->reject(fn () => app(ContributionDispatcher::class)->dispatch($this->reader, $bias,
            $this->reclassificationInputFixture($bias, $a)));
        $this->reject(fn () => app(PhaseProgressionService::class)->advance($bias, $writeA));
        $this->assertSame($b->id, $this->reader->fresh()->current_proposition_id);
        $this->assertNull($bias->fresh()->completed_at);
    }

    #[DataProvider('domains')]
    public function test_bias_revisit_follows_its_frozen_classification_then_only_its_direct_commit(string $domain): void
    {
        $seed = $this->fixture($domain);
        $a = $this->node($seed, PhaseKind::Mediate);
        $b = $this->node($seed, PhaseKind::Mediate);
        $writeA = $this->node($a, PhaseKind::Bias, 'reclassify');
        $writeB = $this->node($b, PhaseKind::Bias, 'reclassify');
        $commitA = $this->commit($writeA);
        $commitB = $this->commit($writeB);
        $bias = $this->phase($this->reader, PhaseKind::Bias, $a);
        foreach ([null, new ParticipationContext($bias)] as $context) {
            $this->assertSame([$writeA->id], app(PropositionOptionService::class)->traversalOptionsFor($bias, $context)->pluck('id')->all());
        }
        $target = app(ContributionDispatcher::class)->dispatch($this->reader, $bias,
            ['action' => 'proposition.traverse', 'proposition_id' => $writeA->id]);
        $order = app(PhaseProgressionService::class)->advance($bias, $target);
        $this->assertSame($writeA->id, $order->gate_proposition_id);
        foreach ([null, new ParticipationContext($order)] as $context) {
            $this->assertSame([$commitA->id], app(PropositionOptionService::class)->traversalOptionsFor($order, $context)->pluck('id')->all());
        }
        $plan = app(InputPlanService::class)->forPhase($this->reader, $order, workspace: true);
        $this->assertOperationallyAbsent($plan, [$b, $writeB, $commitB]);
        $this->reject(fn () => app(ContributionDispatcher::class)->dispatch($this->reader, $order,
            ['action' => 'proposition.traverse', 'proposition_id' => $commitB->id]));
        $this->reject(fn () => app(PhaseProgressionService::class)->advance($order, $commitB));
        $target = app(ContributionDispatcher::class)->dispatch($this->reader, $order,
            ['action' => 'proposition.traverse', 'proposition_id' => $commitA->id]);
        app(PhaseProgressionService::class)->advance($order, $target);
        $this->assertSame($commitA->id, $this->reader->fresh()->current_proposition_id);
    }

    #[DataProvider('domains')]
    public function test_order_keeps_every_causal_expressive_write_and_separates_visibility_commit_and_traverse(string $domain): void
    {
        $seed = $this->fixture($domain);
        $stance = $this->node($seed, PhaseKind::Mediate);
        $selected = $this->node($stance, PhaseKind::Bias, 'reclassify');
        // Existing same-position revision edges are causal; sibling branches are not.
        $aligned = $this->node($selected, PhaseKind::Bias, 'revise');
        $divergent = $this->node($selected, PhaseKind::Bias, 'revise');
        $frozen = $this->node($selected, PhaseKind::Bias, 'revise');
        $foreign = $this->node($stance, PhaseKind::Bias, 'reclassify');
        $foreignFrozen = $this->node($stance, PhaseKind::Bias, 'reclassify');
        foreach ([$aligned, $divergent, $foreign] as $write) {
            $this->groundFixture($write);
        }
        $commit = $this->commit($frozen);
        $foreignCommit = $this->commit($foreignFrozen);
        $this->closureValues[$divergent->id] = [1, -1];
        $order = $this->phase($this->reader, PhaseKind::Order, $selected);
        $local = [$selected->id, $aligned->id, $divergent->id, $frozen->id];
        foreach ([null, new ParticipationContext($order)] as $context) {
            $options = app(PropositionOptionService::class);
            $this->assertEqualsCanonicalizing($local, $options->writeOptionsFor($order, $context)->pluck('id')->all());
            $this->assertSame([$aligned->id], app(ClosureOptionService::class)->committableFor($order, $context)->pluck('id')->all());
            $this->assertSame([$commit->id], $options->traversalOptionsFor($order, $context)->pluck('id')->all());
            $this->assertFalse(app(CommitmentAdmissibilityService::class)->evaluate($foreign, $order, $context)['requirements']['causal_write']);
        }
        $plan = app(InputPlanService::class)->forPhase($this->reader, $order, workspace: true);
        $compact = app(ParticipantContextSerializer::class)->plan($plan);
        $this->assertEqualsCanonicalizing([...$local, $commit->id], array_column($compact['write_options'], 'id'));
        $this->assertSame([$aligned->id], $plan['schema']['$defs']['actions']['proposition.commit']['properties']['proposition_id']['enum']);
        $this->assertFalse($plan['commitment_options'][$divergent->id]['admissible']);
        $this->assertOperationallyAbsent($plan, [$foreign, $foreignFrozen, $foreignCommit]);
        foreach ([$foreign, $divergent] as $invalid) {
            $this->reject(fn () => app(ContributionDispatcher::class)->dispatch($this->reader, $order,
                ['action' => 'proposition.commit', 'proposition_id' => $invalid->id]));
        }
        // Closure refusal concerns Commit, not visibility or a Write's own
        // existing Committal route. It must not be treated as global inertness.
        $this->closureValues[$frozen->id] = [1, -1];
        $this->assertSame([$commit->id], app(PropositionOptionService::class)->traversalOptionsFor($order)->pluck('id')->all());
    }

    #[DataProvider('domains')]
    public function test_mediate_counterpart_is_an_operand_and_reconciliation_creates_both_causal_edges(string $domain): void
    {
        $seed = $this->fixture($domain);
        $readA = $this->node($seed, PhaseKind::Constrain);
        $readB = $this->node($seed, PhaseKind::Constrain);
        $gateA = $this->node($readA, PhaseKind::Disperse);
        $gateB = $this->node($readB, PhaseKind::Disperse);
        $disperse = $this->phase($this->reader, PhaseKind::Disperse, $readA);
        $mediate = app(PhaseProgressionService::class)->advance($disperse, $gateA);
        foreach ([null, new ParticipationContext($mediate)] as $context) {
            $options = app(PropositionOptionService::class);
            $this->assertSame([$gateB->id], $options->gateOptionsFor($mediate, $context)->pluck('id')->all());
            $this->assertSame([], $options->traversalOptionsFor($mediate, $context)->pluck('id')->all());
        }
        $plan = app(InputPlanService::class)->forPhase($this->reader, $mediate);
        $this->assertSame([$gateB->id], $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
        $this->assertArrayNotHasKey('proposition.traverse', $plan['schema']['$defs']['actions']);
        $this->reject(fn () => app(ContributionDispatcher::class)->dispatch($this->reader, $mediate,
            ['action' => 'proposition.traverse', 'proposition_id' => $gateB->id]));
        $result = app(ContributionDispatcher::class)->dispatch($this->reader, $mediate,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $gateB->id]);
        $this->assertEqualsCanonicalizing([$gateA->id, $gateB->id], $result->parents()->where('supersessions.type', 'reconcile')->pluck('propositions.id')->all());
        $this->assertSame($gateA->id, $this->reader->fresh()->current_proposition_id);
        app(PhaseProgressionService::class)->advance($mediate, $result);
        $this->assertSame($result->id, $this->reader->fresh()->current_proposition_id);
    }

    private function assertOperationallyAbsent(array $plan, array $nodes): void
    {
        $compact = app(ParticipantContextSerializer::class)->plan($plan);
        foreach ($nodes as $node) {
            foreach (['read_options', 'gate_options', 'write_options', 'traversal_options', 'attestation_options', 'instrument_options', 'revision_options'] as $key) {
                $this->assertNotContains($node->id, array_column($plan[$key] ?? [], 'id'), $key);
                $this->assertNotContains($node->id, array_column($compact[$key] ?? [], 'id'), 'compact '.$key);
            }
            $this->assertStringNotContainsString($node->id, json_encode($plan['schema']['$defs']['actions']));
        }
    }

    private function reject(callable $action): void
    {
        $before = [Proposition::count(), Supersession::count()];
        try {
            $action();
            $this->fail('A missing causal edge must not authorize an operation.');
        } catch (DomainException) {
            $this->assertSame($before, [Proposition::count(), Supersession::count()]);
        }
    }

    private function fixture(string $domain): Proposition
    {
        $substrate = (new Substrate)->forceFill(['slug' => 'causal-options', 'title' => 'Causal options', 'visibility' => 'private', 'configuration' => []]);
        $substrate->save();
        foreach (['author', 'reader'] as $callsign) {
            $this->{$callsign} = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign]);
            $this->{$callsign}->save();
        }
        $horizon = $this->horizonForRegisterFixture($this->author, ContextDomain::from($domain)->registerFor(PhaseKind::Distinguish));
        $phase = $this->phase($this->author, PhaseKind::Order, horizonId: $horizon->id);
        $seed = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => 'committal', 'statement' => 'Origin']);
        $seed->save();
        app(LedgerService::class)->record($seed);
        app(CommitRegionPolarityService::class)->establishOrigin($seed, 'support');
        $this->mock(ClosureGateService::class, function ($mock): void {
            $mock->shouldReceive('preview')->andReturnUsing(fn (Proposition $node) => [
                ...(new ClosureValues(...($this->closureValues[$node->id] ?? [1, 1])))->resolve(),
                'history_proposition_id' => null, 'history_source' => 'negative-default',
            ]);
            $mock->shouldReceive('forCommitment')->andReturn([
                ...(new ClosureValues(1, 1))->resolve(), 'history_proposition_id' => null, 'history_source' => 'negative-default',
            ]);
        });

        return $seed;
    }

    private function phase(Participant $participant, PhaseKind $kind, ?Proposition $gate = null, ?string $horizonId = null): Phase
    {
        $participant->cycles()->whereNull('completed_at')->update(['completed_at' => now()]);
        $cycle = (new Cycle)->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizonId ?? $gate->phase->cycle->context_horizon_id]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $cycle->contextHorizon->domain->registerFor($kind),
            'gate_proposition_id' => $gate?->id, 'started_at' => now()]);
        $phase->save();
        $participant->forceFill(['current_proposition_id' => $gate?->id])->save();

        return $phase;
    }

    private function node(Proposition $parent, PhaseKind $position, string $type = 'variant'): Proposition
    {
        $phase = $this->phase($this->author, $position, $parent);
        $node = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => 'expressive', 'statement' => $position->name]);
        $node->save();
        app(LedgerService::class)->record($node);
        $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $node->id, 'type' => $type]);
        $edge->save();
        app(LedgerService::class)->record($edge);

        return $node;
    }

    private function commit(Proposition $source): Proposition
    {
        $this->groundFixture($source);
        $phase = $this->phase($this->author, PhaseKind::Order, $source);

        return app(ContributionDispatcher::class)->dispatch($this->author, $phase,
            ['action' => 'proposition.commit', 'proposition_id' => $source->id]);
    }
}
