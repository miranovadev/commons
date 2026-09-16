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
use App\Models\Tragedy;
use App\Models\User;
use App\Services\ContextHorizonProjector;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\OriginBranchService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\PropositionMeasurementService;
use App\Services\TragedyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class BranchTragedyTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    private Proposition $origin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->substrate = (new Substrate)->forceFill(['slug' => 'tragedy', 'title' => 'Tragedy', 'visibility' => 'public', 'configuration' => []]);
        $this->substrate->save();
        $phase = $this->phase();
        $this->origin = $this->node($phase, null, 'committal');
        $this->snapshot($this->origin, .8, 'origin-bootstrap-v1');
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now()])->save();
    }

    public function test_complete_exhaustion_prunes_after_rest_without_moving_other_participants(): void
    {
        [$root, $first, $second] = $this->exhaustedBranch();
        $before = $second->cycle->participant->fresh()->getAttributes();
        $historical = $this->history();
        $state = app(ParticipationService::class)->contribute($first->cycle->participant, $first->id, ['action' => 'participant.rest']);
        $this->assertSame(1, Tragedy::count());
        $this->assertNotNull(DB::table('origin_branches')->where('root_proposition_id', $root->id)->value('pruned_at'));
        $this->assertNotNull($first->cycle->fresh()->rested_at);
        $this->assertSame($before, $second->cycle->participant->fresh()->getAttributes());
        $this->assertNull($second->fresh()->completed_at);
        $this->assertSame('ready', $state['status']);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
        $this->assertSame($historical, $this->history());
        $plan = app(InputPlanService::class)->forParticipant($second->cycle->participant);
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $packet = app(ParticipationService::class)->stateFor($second->cycle->participant)['plan'];
        foreach (['lineage', 'read_options', 'gate_options', 'write_options', 'receipts'] as $key) {
            $this->assertSame([], $packet[$key]);
        }
        // The second Rest is idempotent with respect to retirement and recovers normally.
        $state = app(ParticipationService::class)->contribute($second->cycle->participant, $second->id, ['action' => 'participant.rest']);
        $this->assertSame(1, Tragedy::count());
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
    }

    public function test_zero_measured_potential_renews_origin_and_new_distinction_does_not_resurrect_history(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $participant = $phase->cycle->participant;
        app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        $recovery = app(PotentialChargeService::class)->recoverFrom($root);
        $this->assertSame('origin_renewal', $recovery['reason']);
        $this->assertSame(0.0, $recovery['raw_potential_charge']);
        $live = $participant->cycles()->whereNull('completed_at')->sole()->currentPhase;
        app(ParticipationService::class)->contribute($participant, $live->id,
            ['action' => 'proposition.variant', 'statement' => 'Fresh possibility after Tragedy']);
        $new = $participant->fresh()->position;
        $this->assertNotSame($root->id, $new->id);
        $this->assertFalse(app(OriginBranchService::class)->pruned($new));
        $this->assertTrue(app(OriginBranchService::class)->pruned($root));
        $this->assertSame(2, DB::table('origin_branches')->count());
    }

    public function test_origin_renewal_reopens_a_participants_previous_distinguish_opportunity(): void
    {
        $first = $this->phase($this->origin, PhaseKind::Distinguish);
        $participant = $first->cycle->participant;
        app(ParticipationService::class)->contribute($participant, $first->id,
            ['action' => 'proposition.variant', 'statement' => 'The first attempt']);
        $old = $participant->fresh()->position;
        $first->cycle->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
        $terminal = (new Phase)->forceFill(['cycle_id' => $first->cycle_id, 'gate_proposition_id' => $old->id,
            'kind' => PhaseKind::Order, 'write_register' => 'canon', 'started_at' => now()]);
        $terminal->save();
        $this->measure($old, 1);
        $state = app(ParticipationService::class)->contribute($participant, $terminal->id, ['action' => 'participant.rest']);
        $this->assertArrayHasKey('proposition.variant', $state['plan']['schema']['$defs']['actions']);
        app(ParticipationService::class)->contribute($participant, $state['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A genuinely new attempt']);
        $this->assertNotSame($old->id, $participant->fresh()->current_proposition_id);
        $this->assertTrue(app(OriginBranchService::class)->pruned($old));
    }

    public function test_viable_sibling_prevents_branch_exhaustion_even_if_the_resting_path_is_exhausted(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $terminal = $this->node($this->phase($root), $root);
        $phase = $this->phase($terminal);
        $sibling = $this->node($this->phase($root), $root);
        $this->measure($root, 1);
        $this->measure($terminal, 1);
        $this->measure($sibling, 0);
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertFalse($proof['branches'][0]['exhausted']);
        $this->assertSame($this->origin->id, app(PotentialChargeService::class)->recoverFrom($terminal)['proposition']->id ?? null);
        $this->assertSame(0, Tragedy::count());
    }

    public function test_an_independent_live_origin_branch_is_preserved_and_selected_before_renewal(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $other = $this->node($this->phase($this->origin), $this->origin, 'committal');
        $this->snapshot($other, .5);
        $opportunity = $this->node($this->phase($other), $other);
        $this->measure($root, 1);
        $this->measure($opportunity, 0);
        $state = app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertTrue(app(OriginBranchService::class)->pruned($root));
        $this->assertFalse(app(OriginBranchService::class)->pruned($other));
        $this->assertSame($other->id, $state['current_proposition_id']);
    }

    public function test_stale_exhaustion_proof_cannot_retire_new_viable_work(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $before = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($before['branches'][0]['exhausted']);
        $commit = $this->node($this->phase($root), $root, 'committal');
        $this->snapshot($commit, .5);
        $opportunity = $this->node($this->phase($commit), $commit);
        $this->measure($root, 1);
        $this->measure($opportunity, 0);
        $this->assertNotSame($before['field_version'], (int) $this->substrate->fresh()->field_version);
        DB::transaction(function () use ($phase, $before): void {
            Substrate::whereKey($this->substrate->id)->lockForUpdate()->firstOrFail();
            app(TragedyService::class)->retire($phase, $before);
        });
        $this->assertSame(0, Tragedy::count());
        $after = app(TragedyService::class)->inspect($phase);
        // This descendant region must be checked even though the exhausted path
        // from Origin passes through the resting node.
        $this->assertFalse($after['branches'][0]['exhausted']);
    }

    public function test_potential_on_the_exhausted_rest_path_does_not_leave_a_healthy_branch_stranded(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $this->measure($root, 0);
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($proof['branches'][0]['exhausted']);
        $state = app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame(1, Tragedy::count());
        $this->assertSame('ready', $state['status']);
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
    }

    public function test_a_formerly_valid_action_is_rejected_after_pruning_then_only_rest_is_exposed(): void
    {
        [$root, $first] = $this->exhaustedBranch();
        $other = $this->phase($root, PhaseKind::Distinguish);
        $this->measure($root, 1);
        $schema = app(InputPlanService::class)->forParticipant($other->cycle->participant)['schema'];
        $this->assertArrayHasKey('proposition.variant', $schema['$defs']['actions']);
        app(ParticipationService::class)->contribute($first->cycle->participant, $first->id, ['action' => 'participant.rest']);
        try {
            app(ParticipationService::class)->contribute($other->cycle->participant, $other->id,
                ['action' => 'proposition.variant', 'statement' => 'Stale proposal'], $schema);
            $this->fail('A stale contribution must fail.');
        } catch (ConflictHttpException) {
            $this->assertNull($other->fresh()->completed_at);
            $this->assertSame($root->id, $other->cycle->participant->fresh()->current_proposition_id);
        }
    }

    public function test_active_graph_hides_retired_branch_while_history_and_direct_inspection_keep_it(): void
    {
        User::factory()->create();
        [$root, $phase] = $this->exhaustedBranch();
        app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $url = '/api/substrates/'.$this->substrate->slug.'/propositions';
        $active = $this->getJson($url)->assertOk()->json('data');
        $this->assertNotContains($root->id, array_column($active, 'id'));
        $history = $this->getJson($url.'?history=1')->assertOk()->json('data');
        $this->assertContains($root->id, array_column($history, 'id'));
        $this->getJson($url.'/'.$root->id)->assertOk()->assertJsonPath('data.id', $root->id);
    }

    public function test_diagnostics_are_read_only_and_retirement_is_constant_work_inside_the_write_lock(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($proof['stable']);
        foreach ($sql as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b|for update/i', $query);
        }
        $sql = [];
        DB::transaction(function () use ($phase, $proof): void {
            Substrate::whereKey($this->substrate->id)->lockForUpdate()->firstOrFail();
            app(TragedyService::class)->retire($phase, $proof);
        });
        $this->assertLessThanOrEqual(12, count($sql));
        $this->assertSame(1, Tragedy::count());
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
    }

    public function test_shared_descendants_remain_active_and_membership_backfill_preserves_causal_branches(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $other = $this->node($this->phase($this->origin), $this->origin);
        $shared = $this->node($this->phase($root), $root);
        $edge = (new Supersession)->forceFill(['phase_id' => $shared->phase_id,
            'source_proposition_id' => $other->id, 'target_proposition_id' => $shared->id, 'type' => 'reconcile']);
        $edge->save();
        app(LedgerService::class)->record($edge);
        $descendant = $this->node($this->phase($shared), $shared);
        $before = DB::table('origin_branch_memberships')->orderBy('root_proposition_id')->orderBy('proposition_id')->get()->toJson();
        $migration = require database_path('migrations/2026_09_16_020000_create_origin_branches.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, DB::table('origin_branch_memberships')->orderBy('root_proposition_id')->orderBy('proposition_id')->get()->toJson());
        foreach ([$root, $other, $shared, $descendant] as $node) {
            $this->measure($node, 1);
        }
        app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertTrue(app(OriginBranchService::class)->pruned($root));
        foreach ([$other, $shared, $descendant] as $node) {
            $this->assertFalse(app(OriginBranchService::class)->pruned($node));
        }
        $this->assertSame(1, Tragedy::count());
        $continued = $this->node($this->phase($descendant), $descendant);
        $this->assertSame([$other->id], DB::table('origin_branch_memberships')
            ->where('proposition_id', $continued->id)->pluck('root_proposition_id')->all());
    }

    public function test_missing_branch_projections_are_observed_in_a_batched_read_without_repairs(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        for ($i = 0; $i < 25; $i++) {
            $this->node($this->phase($root), $root);
        }
        DB::table('proposition_measurements')->update(['calculator_version' => null]);
        app(\App\Services\PotentialProjectionService::class)->rebuild($this->substrate->id, false);
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($proof['stable']);
        $this->assertLessThan(300, count($sql), 'Branch observation must share one batched measurement context.');
        foreach ($sql as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b|for update/i', $query);
        }
    }

    private function exhaustedBranch(): array
    {
        $root = $this->node($this->phase($this->origin), $this->origin);
        $first = $this->phase($root);
        $second = $this->phase($root);
        $this->measure($root, 1);

        return [$root, $first, $second];
    }

    private function phase(?Proposition $gate = null, PhaseKind $kind = PhaseKind::Order): Phase
    {
        $participant = (new Participant)->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => 'p-'.str()->ulid(), 'current_proposition_id' => $gate?->id]);
        $participant->save();
        $horizon = $gate === null ? app(ContextHorizonProjector::class)->currentFor($this->substrate)
            : ($gate->tier === 'committal' ? app(ContextHorizonProjector::class)->forEntry($gate) : $gate->phase->cycle->contextHorizon);
        $cycle = (new Cycle)->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id, 'sequence' => 1]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'gate_proposition_id' => $gate?->id,
            'kind' => $kind, 'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()]);
        $phase->save();

        return $phase;
    }

    private function node(Phase $phase, ?Proposition $parent, string $tier = 'expressive'): Proposition
    {
        $node = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => $tier, 'statement' => 'Branch fixture']);
        $node->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
        }

        return $node;
    }

    private function snapshot(Proposition $node, float $tension, string $version = 'tragedy-fixture'): void
    {
        (new CommitmentSnapshot)->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
            'ledger_entry_id' => $node->ledgerEntry->id, 'correspondence' => $tension, 'chirality' => $tension, 'closure' => $tension,
            'prominence' => (1 + $tension) / 2, 'turbulence' => (1 - $tension) / 2, 'tension' => $tension,
            'direction_before' => $node->phase->cycle->contextHorizon->domain,
            'direction_after' => $node->phase->cycle->contextHorizon->domain,
            'calculator_version' => $version, 'evaluated_at' => now()])->save();
    }

    private function measure(Proposition $node, float $value): void
    {
        app(PropositionMeasurementService::class)->project($node, $value, $value, $value);
        app(\App\Services\PotentialProjectionService::class)->rebuild($this->substrate->id, false);
    }

    private function history(): array
    {
        return collect(['propositions', 'supersessions', 'attestations', 'receipts', 'commitment_snapshots', 'context_horizons', 'substrate_ledger_entries'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->map(fn ($row) => (array) $row)->all()])->all();
    }
}
