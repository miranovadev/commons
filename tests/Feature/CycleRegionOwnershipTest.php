<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ActiveParticipantLineage;
use App\Services\CommitRegionService;
use App\Services\ContextHorizonProjector;
use App\Services\GraphProjectionService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use App\Services\PotentialProjectionService;
use App\Services\ReceiptRegionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class CycleRegionOwnershipTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    private Participant $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'cycle-regions', 'title' => 'Cycle regions', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
    }

    public function test_direct_context_and_batch_ownership_agree_without_parent_inference(): void
    {
        $a = $this->commit(null, 'emergence');
        $left = $this->expressive($a, $a);
        $b = $this->commit($a, 'emergence');
        $right = $this->expressive($b, $b);
        $r = $this->expressive($a, $left);
        $this->edge($right, $r);
        $context = new ParticipationContext($r->phase);
        $regions = app(CommitRegionService::class);
        $nodes = collect([$a, $left, $b, $right, $r]);
        $batch = $regions->seedIdsFor($nodes, $nodes->keyBy('id'), collect());
        foreach ([$a->id => $a, $left->id => $a, $b->id => $b, $right->id => $b, $r->id => $a] as $id => $owner) {
            $node = $nodes->firstWhere('id', $id);
            $this->assertSame($owner->id, $regions->seedFor($node)?->id);
            $this->assertSame($owner->id, $context->seed($node)?->id);
            $this->assertSame($owner->id, $batch[$id]);
        }
        $this->assertEqualsCanonicalizing([$left->id, $r->id], $regions->membersFor($a)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$left->id, $r->id], $context->members($a)->pluck('id')->all());
        $this->assertSame([$right->id], $regions->membersFor($b)->pluck('id')->all());
        $this->assertSame($a->id, app(ReceiptRegionService::class)->seeds($context)[$r->id]);
        $this->assertSame($b->id, app(ReceiptRegionService::class)->seeds($context)[$right->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $regions->seedIdsFor($nodes, $nodes);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_cross_horizon_inputs_preserve_owner_horizon_and_only_the_producing_gate_route(): void
    {
        $a = $this->commit(null, 'emergence');
        $boundary = $this->commit($a, 'settlement');
        $b = $this->commit($boundary, 'emergence');
        $left = $this->expressive($a, $a);
        $right = $this->expressive($b, $b);
        $r = $this->expressive($a, $left);
        $this->edge($right, $r);
        $horizonA = $r->phase->cycle->context_horizon_id;
        $horizonB = $right->phase->cycle->context_horizon_id;
        $this->assertNotSame($horizonA, $horizonB);
        $regions = app(CommitRegionService::class);
        $this->assertSame($a->id, $regions->seedFor($r)?->id);
        $this->assertEqualsCanonicalizing([$left->id, $right->id], $r->parents()->pluck('propositions.id')->all());
        $context = new ParticipationContext($r->phase);
        foreach ([null, $context] as $readModel) {
            $this->assertSame($a->id, $regions->seedFor($r, $readModel)?->id);
            $this->assertSame([$a->id, $left->id, $r->id], app(ActiveParticipantLineage::class)->endingAt($r, $readModel)->pluck('id')->all());
        }
        $this->assertSame($horizonA, $r->contextHorizonMembership->context_horizon_id);
        app(ContextHorizonProjector::class)->rebuild($this->actor->substrate);
        $this->assertSame($horizonA, $r->fresh()->contextHorizonMembership->context_horizon_id);
        $this->assertSame($horizonB, $right->fresh()->contextHorizonMembership->context_horizon_id);
        $before = Supersession::orderBy('id')->get()->map->getAttributes()->all();
        $this->publishPotentialFixture($this->actor->substrate);
        $local = app(PotentialProjectionService::class)->region($horizonA)['local'];
        $this->assertSame($a->id, $local['nodes'][$r->id]['seed_id']);
        $this->assertContains([$right->id, $r->id, 'variant'], $local['foreign_inputs']);
        $this->assertNotContains([$right->id, $r->id, 'variant'], $local['edges']);
        app(GraphProjectionService::class)->load($this->actor->substrate, collect([$r]));
        $this->assertSame($a->id, $r->getAttribute('field')['committal_proposition_id']);
        $this->assertSame($before, Supersession::orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_missing_governance_fails_closed_even_with_a_single_valid_parent(): void
    {
        $a = $this->commit(null, 'emergence');
        $r = $this->expressive(null, $a);
        $regions = app(CommitRegionService::class);
        $this->assertNull($regions->seedFor($r));
        $context = new ParticipationContext($r->phase);
        $this->assertNull($context->seed($r));
        $this->assertNull($regions->seedIdsFor(collect([$r]), collect([$a, $r]))[$r->id]);
        $this->assertNull(app(ReceiptRegionService::class)->seeds($context)[$r->id]);
        $this->assertTrue($regions->membersFor($a)->isEmpty());
    }

    public function test_an_expressive_governor_fails_closed_in_all_resolvers(): void
    {
        $a = $this->commit(null, 'emergence');
        $left = $this->expressive($a, $a);
        // Import malformed governance explicitly; normal Cycle creation rejects it.
        $r = $this->expressive($left, $left, malformed: true);
        $this->assertNull(app(CommitRegionService::class)->seedFor($r));
        $this->assertNull((new ParticipationContext($r->phase))->seed($r));
        $this->assertNull(app(CommitRegionService::class)->seedIdsFor(collect([$r]), collect([$r]))[$r->id]);
    }

    public function test_invalid_snapshot_horizon_and_foreign_substrate_governance_fail_closed(): void
    {
        $a = $this->commit(null, 'emergence');
        $boundary = $this->commit($a, 'settlement');
        $right = $this->expressive($boundary, $boundary);
        $wrongHorizon = $this->expressive($a, $right, malformed: true);

        $missingSnapshot = new Proposition;
        $missingSnapshot->forceFill(['phase_id' => $a->phase_id, 'tier' => 'committal',
            'register' => $a->register, 'statement' => 'Imported seed without a snapshot'])->save();
        app(LedgerService::class)->record($missingSnapshot);
        $withoutSnapshot = $this->expressive($missingSnapshot, $a, malformed: true);

        $localActor = $this->actor;
        $foreign = new Substrate;
        $foreign->forceFill(['slug' => 'foreign-owner', 'title' => 'Foreign owner',
            'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $foreign->id, 'callsign' => 'foreign'])->save();
        $foreignSeed = $this->commit(null, 'emergence');
        $this->actor = $localActor;
        $foreignOwner = $this->expressive($foreignSeed, $a, malformed: true);

        $regions = app(CommitRegionService::class);
        foreach ([$wrongHorizon, $withoutSnapshot, $foreignOwner] as $invalid) {
            $context = new ParticipationContext($invalid->phase);
            $this->assertNull($regions->seedFor($invalid));
            $this->assertNull($context->seed($invalid));
            $this->assertNull($regions->seedIdsFor(collect([$invalid]), collect())[$invalid->id]);
            $this->assertNull(app(ReceiptRegionService::class)->seeds($context)[$invalid->id]);
        }
        $this->assertSame($missingSnapshot->id, $regions->seedFor($missingSnapshot)?->id);
    }

    public function test_hard_mixed_region_inputs_retain_governance_and_gate_lineage(): void
    {
        $a = $this->commit(null, 'emergence');
        $b = $this->commit($a, 'emergence');
        $left = $this->expressive($a, $a);
        $right = $this->expressive($b, $b);
        $phase = $this->phase($a, $left, PhaseKind::Mediate);
        $result = new Proposition;
        $result->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $phase->write_register, 'statement' => 'Synthetic mixed-input result'])->save();
        app(LedgerService::class)->record($result);
        foreach ([$left, $right] as $input) {
            $this->edge($input, $result, 'reconcile');
        }
        $context = new ParticipationContext($phase);
        foreach ([null, $context] as $readModel) {
            $this->assertSame($a->id, app(CommitRegionService::class)->seedFor($result, $readModel)?->id);
            $this->assertSame([$a->id, $result->id], app(ActiveParticipantLineage::class)->endingAt($result, $readModel)->pluck('id')->all());
        }
        $this->assertEqualsCanonicalizing([$left->id, $right->id], $result->parents()->pluck('propositions.id')->all());
        $this->assertEqualsCanonicalizing([$left->id, $result->id], app(CommitRegionService::class)->membersFor($a)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$left->id, $result->id], $context->members($a)->pluck('id')->all());
    }

    private function commit(?Proposition $governor, string $after): Proposition
    {
        $phase = $this->phase($governor, $governor, PhaseKind::Order);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'committal', 'register' => $phase->write_register, 'statement' => 'Region seed'])->save();
        $entry = app(LedgerService::class)->record($node);
        $horizon = $phase->cycle->contextHorizon;
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $entry->id, 'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
            'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $horizon->domain, 'direction_after' => $after,
            'calculator_version' => $governor === null ? 'origin-bootstrap-v1' : 'ownership-fixture', 'evaluated_at' => now()])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);

        return $node;
    }

    private function expressive(?Proposition $governor, Proposition $gate, bool $malformed = false): Proposition
    {
        $phase = $this->phase($governor, $gate, PhaseKind::Distinguish, $malformed);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive', 'register' => $phase->write_register, 'statement' => 'Owned expression']);
        if ($malformed) {
            // Imported corrupt records deliberately bypass the birth invariant.
            Proposition::withoutEvents(fn () => $node->save());
        } else {
            $node->save();
        }
        app(LedgerService::class)->record($node);
        $this->edge($gate, $node);

        return $node;
    }

    private function phase(?Proposition $governor, ?Proposition $gate, PhaseKind $kind, bool $malformed = false): Phase
    {
        $horizon = $governor === null ? app(ContextHorizonProjector::class)->currentFor($this->actor->substrate)
            : ($malformed ? $gate->phase->cycle->contextHorizon : app(ContextHorizonProjector::class)->forEntry($governor));
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->actor->id, 'sequence' => (int) $this->actor->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id, 'entry_committal_id' => $governor?->id]);
        if ($malformed) {
            Cycle::withoutEvents(fn () => $cycle->save());
        } else {
            $cycle->save();
        }
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $gate?->id,
            'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()])->save();

        return $phase;
    }

    private function edge(Proposition $source, Proposition $target, string $type = 'variant'): void
    {
        // Representation fixture only: no Mediate candidate or write policy is widened.
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }
}
