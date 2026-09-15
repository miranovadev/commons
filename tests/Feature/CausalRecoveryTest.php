<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PotentialChargeService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class CausalRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    private ContextHorizon $root;

    private array $measurements = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'recovery', 'title' => 'Recovery', 'visibility' => 'public', 'configuration' => []])->save();
        $this->root = app(ContextHorizonProjector::class)->currentFor($this->substrate);
    }

    public function test_farther_ancestor_can_beat_a_positive_nearer_candidate_after_attenuation(): void
    {
        $far = $this->committal($this->root, .95);
        $near = $this->committal($this->root, .02, $far);
        $position = $this->expressive($this->root, $near);
        $this->opportunity($far);
        $this->opportunity($near);
        $selection = $this->recover($position);
        $this->assertSame($far->id, $selection['proposition']->id);
        $this->assertSame(2, $selection['distance']);
        $this->assertEqualsWithDelta(.95 / 9, $selection['potential_charge'], 1e-9);
        $this->assertSame(.95, $selection['raw_potential_charge']);
    }

    public function test_attenuation_can_make_the_nearer_ancestor_win_despite_lower_raw_potential(): void
    {
        $far = $this->committal($this->root, .95);
        $near = $this->committal($this->root, .5, $far);
        $position = $this->expressive($this->root, $near);
        $this->opportunity($far);
        $this->opportunity($near);
        $selection = $this->recover($position);
        $this->assertSame($near->id, $selection['proposition']->id);
        $this->assertSame(1, $selection['distance']);
        $this->assertEqualsWithDelta(.125, $selection['potential_charge'], 1e-9);
    }

    public function test_reconciliation_inspects_all_parent_paths_and_deduplicates_diamond_ancestors_at_minimum_distance(): void
    {
        $shared = $this->committal($this->root, .9);
        $weak = $this->committal($this->root, .01, $shared);
        $left = $this->expressive($this->root, $shared);
        $right = $this->expressive($this->root, $weak);
        $rest = $this->expressive($this->root, $right);
        $this->edge($left, $rest, 'reconcile');
        $this->opportunity($shared);
        $this->opportunity($weak);
        $selection = $this->recover($rest);
        $this->assertSame($shared->id, $selection['proposition']->id);
        $this->assertSame(2, $selection['distance']); // Other path is distance 3.
        $this->assertEqualsWithDelta(.9 / 9, $selection['potential_charge'], 1e-9);
        // Shared ancestry is not accumulated or counted twice.
        $this->assertSame(.9, $selection['raw_potential_charge']);
    }

    public function test_descendants_siblings_and_disconnected_regions_are_not_recovery_candidates(): void
    {
        $ancestor = $this->committal($this->root, .1);
        $position = $this->expressive($this->root, $ancestor);
        $descendant = $this->committal($this->root, 1, $position);
        $sibling = $this->committal($this->root, 1, $ancestor);
        $disconnected = $this->committal($this->root, 1);
        foreach ([$ancestor, $descendant, $sibling, $disconnected] as $candidate) {
            $this->opportunity($candidate);
        }
        $this->assertSame($ancestor->id, $this->recover($position)['proposition']->id);
    }

    public function test_recovery_cannot_borrow_potential_through_the_exhausted_position(): void
    {
        $ancestor = $this->committal($this->root, .9);
        $position = $this->expressive($this->root, $ancestor);
        $this->measurements[$position->id] = 0;
        $continuation = $this->expressive($this->root, $position);
        $this->measurements[$continuation->id] = 0;
        $this->assertNull($this->recover($position));
    }

    public function test_recovery_crosses_one_domain_boundary_and_writes_signal_in_the_selected_origins_direct_child(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h3, $commit);
        $this->opportunity($origin);
        $participant = $this->participant();
        $order = $this->phase($h3, $position, participant: $participant);
        $participant->forceFill(['current_proposition_id' => $position->id])->save();
        $this->refreshMeasurements();
        $rest = app(ParticipationService::class)->contribute($participant, $order->id, ['action' => 'participant.rest']);
        $this->assertSame('ready', $rest['status']);
        $this->assertSame('distinguish', $rest['plan']['phase']['kind']);
        $this->assertNotNull($order->cycle->fresh()->rested_at);
        $historicalOwners = DB::table('cycles')->pluck('context_horizon_id', 'id')->all();
        $selection = $this->recover($position);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame($h2->id, app(ContextHorizonProjector::class)->forEntry($origin)->id);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h2->id, $cycle->context_horizon_id);
        $this->assertSame('signal', $cycle->currentPhase->write_register);
        $state = app(ParticipationService::class)->contribute($participant, $cycle->currentPhase->id,
            ['action' => 'proposition.variant', 'statement' => 'A new Signal in the selected historical region.']);
        $write = $participant->fresh()->position;
        $this->assertSame('signal', $write->register);
        $this->assertSame('settlement', $state['domain']);
        $this->assertSame($h2->id, $write->contextHorizonMembership->context_horizon_id);
        foreach ($historicalOwners as $id => $owner) {
            $this->assertSame($owner, Cycle::findOrFail($id)->context_horizon_id);
        }
        $this->assertNotNull($h2->fresh()->closed_at);
        $this->assertSame($commit->id, $h2->fresh()->closed_by_proposition_id);
    }

    public function test_recovery_can_cross_multiple_domain_boundaries_without_rewriting_historical_ownership(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $later = $this->committal($h3, -.01, $commit, boundary: true);
        $h4 = app(ContextHorizonProjector::class)->forEntry($later);
        $position = $this->expressive($h4, $later);
        $this->opportunity($origin);
        $participant = $this->resting($h4, $position);
        $selection = $this->recover($position);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertSame(3, $selection['distance']);
        app(CycleEntryService::class)->enter($participant);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h2->id, $cycle->context_horizon_id);
        $this->assertSame($h3->id, $later->fresh()->phase->cycle->context_horizon_id);
        $this->assertSame($h3->id, $h4->fresh()->parent_context_horizon_id);
    }

    public function test_emergence_candidate_wins_and_next_cycle_creates_activity(): void
    {
        [$origin, , $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h3, $commit);
        $this->opportunity($commit);
        $participant = $this->resting($h3, $position);
        $this->assertSame($commit->id, $this->recover($position)['proposition']->id);
        app(CycleEntryService::class)->enter($participant);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $this->assertSame($h3->id, $cycle->context_horizon_id);
        $this->assertSame($commit->id, $cycle->currentPhase->gate_proposition_id);
        app(ParticipationService::class)->contribute($participant, $cycle->currentPhase->id,
            ['action' => 'proposition.variant', 'statement' => 'Activity follows the selected Emergence entry.']);
        $this->assertSame('activity', $participant->fresh()->position->register);
    }

    public function test_non_boundary_committal_stays_in_its_closed_own_horizon(): void
    {
        [, $h2, $boundary, $h3] = $this->boundaryChain();
        $sameDomain = $this->committal($h2, -.3);
        $this->assertSame($h2->id, app(ContextHorizonProjector::class)->forEntry($sameDomain)->id);
        $participant = $this->participant();
        $participant->forceFill(['current_proposition_id' => $sameDomain->id])->save();
        app(CycleEntryService::class)->enter($participant);
        $this->assertSame($h2->id, $participant->cycles()->sole()->context_horizon_id);
        $this->assertSame($h3->id, app(ContextHorizonProjector::class)->forEntry($boundary)->id);
    }

    public function test_newest_descendant_cannot_be_requested_for_an_old_gate(): void
    {
        [$origin, , , $h3] = $this->boundaryChain();
        $this->expectException(DomainException::class);
        app(ContextHorizonProjector::class)->forEntry($origin, $h3);
    }

    public function test_missing_direct_boundary_horizon_fails_instead_of_searching_other_descendants(): void
    {
        [$origin, $h2] = $this->boundaryChain();
        $gate = $this->committal($h2, .1, $origin, boundary: true, projectBoundary: false);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('must explicitly open its direct child');
        app(ContextHorizonProjector::class)->forEntry($gate);
    }

    public function test_multiple_resting_participants_independently_enter_the_same_valid_historical_region(): void
    {
        [$origin, $h2, $commit, $h3] = $this->boundaryChain();
        $position = $this->expressive($h3, $commit);
        $this->opportunity($origin);
        $participants = [$this->resting($h3, $position), $this->resting($h3, $position)];
        foreach ($participants as $participant) {
            $this->refreshMeasurements();
            app(CycleEntryService::class)->enter($participant);
            $cycle = $participant->cycles()->whereNull('completed_at')->sole();
            $this->assertSame($h2->id, $cycle->context_horizon_id);
            $this->assertSame($origin->id, $cycle->currentPhase->gate_proposition_id);
        }
    }

    public function test_no_positive_ancestral_potential_leaves_rest_and_position_intact_without_inventing_a_cycle(): void
    {
        $gate = $this->committal($this->root, 0);
        $position = $this->expressive($this->root, $gate);
        $participant = $this->resting($this->root, $position);
        $this->assertNull($this->recover($position));
        try {
            app(CycleEntryService::class)->enter($participant);
            $this->fail('Exhaustion must not create entry.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, $participant->cycles()->count());
            $this->assertSame($position->id, $participant->fresh()->current_proposition_id);
            $this->assertNotNull($participant->cycles()->sole()->rested_at);
        }
    }

    public function test_direct_phase_creation_cannot_pair_old_origin_with_newest_horizon(): void
    {
        [$origin, , , $h3] = $this->boundaryChain();
        $this->expectException(DomainException::class);
        $this->phase($h3, $origin, PhaseKind::Distinguish);
    }

    public function test_direct_phase_creation_rejects_the_opposite_domains_register(): void
    {
        $phase = $this->phase($this->root);
        $this->expectException(DomainException::class);
        $phase->forceFill(['write_register' => 'signal'])->save();
    }

    public function test_boundary_entry_cannot_choose_its_old_owner_instead_of_its_direct_child(): void
    {
        [$origin] = $this->boundaryChain();
        $this->expectException(DomainException::class);
        $this->phase($this->root, $origin, PhaseKind::Distinguish);
    }

    public function test_invalid_direct_progression_fails_before_changing_position_or_completing_the_phase(): void
    {
        [$origin, , $commit, $h3] = $this->boundaryChain();
        $phase = $this->phase($h3, $commit, PhaseKind::Distinguish);
        $participant = $phase->cycle->participant;
        $participant->forceFill(['current_proposition_id' => $commit->id])->save();
        try {
            app(PhaseProgressionService::class)->advance($phase, $origin);
            $this->fail('Invalid progression must reject before moving the Participant.');
        } catch (DomainException) {
            $this->assertSame($commit->id, $participant->fresh()->current_proposition_id);
            $this->assertNull($phase->fresh()->completed_at);
            $this->assertSame(1, $phase->cycle->phases()->count());
        }
    }

    public function test_direct_proposition_creation_rejects_the_opposite_domains_register(): void
    {
        $phase = $this->phase($this->root);
        $this->expectException(DomainException::class);
        (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => 'signal',
            'tier' => 'expressive', 'statement' => 'Invalid Domain'])->save();
    }

    public function test_direct_dispatch_rechecks_phase_vocabulary_even_when_the_in_memory_object_is_modified(): void
    {
        $gate = $this->committal($this->root, .2);
        $phase = $this->phase($this->root, $gate, PhaseKind::Distinguish);
        $phase->write_register = 'signal';
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
                ['action' => 'proposition.variant', 'statement' => 'Invalid Signal']);
            $this->fail('Direct dispatch must reject a mismatched Domain.');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
        }
    }

    public function test_phase_and_proposition_cannot_be_reparented_to_reassign_horizon_ownership(): void
    {
        $node = $this->expressive($this->root);
        $other = $this->phase($this->root);
        foreach ([$node, $node->phase] as $model) {
            $column = $model instanceof Proposition ? 'phase_id' : 'cycle_id';
            $original = $model->$column;
            try {
                $model->forceFill($model instanceof Proposition ? ['phase_id' => $other->id] : ['cycle_id' => $other->cycle_id])->save();
                $this->fail('Creating ownership must be durable.');
            } catch (DomainException) {
                $this->assertSame($original, $model->fresh()->$column);
            }
        }
    }

    public function test_bulk_updates_cannot_reassign_phase_proposition_or_horizon_ownership(): void
    {
        $node = $this->expressive($this->root);
        $other = $this->phase($this->root);
        foreach ([
            ['phases', $node->phase_id, 'cycle_id', $other->cycle_id],
            ['propositions', $node->id, 'phase_id', $other->id],
            ['context_horizons', $this->root->id, 'domain', 'settlement'],
        ] as [$table, $id, $column, $value]) {
            $original = DB::table($table)->where('id', $id)->value($column);
            try {
                DB::transaction(fn () => DB::table($table)->where('id', $id)->update([$column => $value]));
                $this->fail('Bulk SQL must preserve causal ownership.');
            } catch (QueryException) {
                $this->assertSame($original, DB::table($table)->where('id', $id)->value($column));
            }
        }
    }

    public function test_audit_reports_legacy_invalid_entries_without_relabelling_history(): void
    {
        [$origin, , $commit, $h3] = $this->boundaryChain();
        $phase = $this->phase($h3, $commit, PhaseKind::Distinguish);
        DB::table('phases')->where('id', $phase->id)->update(['gate_proposition_id' => $origin->id]);
        $before = $phase->fresh()->getAttributes();
        $this->artisan('commons:causal-audit', ['substrate' => $this->substrate->slug])
            ->expectsOutputToContain('Causal audit: 1 invalid relationships. No records changed.')
            ->assertExitCode(1);
        $this->assertSame($before, $phase->fresh()->getAttributes());
        $this->assertSame($h3->id, $phase->cycle->fresh()->context_horizon_id);
    }

    public function test_identity_migration_rolls_back_and_reapplies_without_changing_history(): void
    {
        $node = $this->expressive($this->root);
        $attributes = $node->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_09_15_120000_protect_causal_ownership.php');
        $migration->down();
        $migration->up();
        $this->assertSame($attributes, $node->fresh()->getAttributes());
    }

    private function boundaryChain(): array
    {
        $origin = $this->committal($this->root, -.9, boundary: true);
        $h2 = app(ContextHorizonProjector::class)->forEntry($origin);
        $commit = $this->committal($h2, .01, $origin, boundary: true);
        $h3 = app(ContextHorizonProjector::class)->forEntry($commit);
        $this->assertSame(ContextDomain::Settlement, $h2->domain);
        $this->assertSame(ContextDomain::Emergence, $h3->domain);

        return [$origin, $h2, $commit, $h3];
    }

    private function participant(): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => 'observer-'.str()->ulid()])->save();

        return $participant;
    }

    private function phase(ContextHorizon $horizon, ?Proposition $gate = null, PhaseKind $kind = PhaseKind::Order, ?Participant $participant = null): Phase
    {
        $participant ??= $this->participant();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id,
            'sequence' => (int) $participant->cycles()->max('sequence') + 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'gate_proposition_id' => $gate?->id, 'kind' => $kind,
            'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()])->save();

        return $phase;
    }

    private function expressive(ContextHorizon $horizon, ?Proposition $parent = null): Proposition
    {
        return $this->node($this->phase($horizon, $parent), $parent);
    }

    private function node(Phase $phase, ?Proposition $parent = null, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => $tier, 'statement' => 'Causal recovery fixture'])->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $this->edge($parent, $node);
        }

        return $node;
    }

    private function committal(ContextHorizon $horizon, float $tension, ?Proposition $parent = null, bool $boundary = false, bool $projectBoundary = true): Proposition
    {
        $node = $this->node($this->phase($horizon, $parent), $parent, 'committal');
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
            'ledger_entry_id' => $node->ledgerEntry->id, 'correspondence' => $tension, 'chirality' => $tension, 'closure' => $tension,
            'prominence' => (1 + $tension) / 2, 'turbulence' => (1 - $tension) / 2, 'tension' => $tension,
            'direction_before' => $horizon->domain, 'direction_after' => $boundary ? $horizon->domain->opposite() : $horizon->domain,
            'calculator_version' => 'controlled-routing-fixture', 'evaluated_at' => now()])->save();
        if ($boundary && $projectBoundary) {
            app(ContextHorizonProjector::class)->apply($snapshot);
        }

        return $node;
    }

    private function opportunity(Proposition $committal): void
    {
        $horizon = app(ContextHorizonProjector::class)->forEntry($committal);
        $node = $this->expressive($horizon, $committal);
        $this->measurements[$node->id] = 0;
    }

    private function edge(Proposition $source, Proposition $target, string $type = 'variant'): void
    {
        (new Supersession)->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
    }

    private function resting(ContextHorizon $horizon, Proposition $position): Participant
    {
        $participant = $this->participant();
        $phase = $this->phase($horizon, $position, participant: $participant);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $participant->forceFill(['current_proposition_id' => $position->id])->save();

        return $participant;
    }

    private function recover(Proposition $position): ?array
    {
        $this->refreshMeasurements();

        return app(PotentialChargeService::class)->recoverFrom($position);
    }

    /** Controlled values only for routing; Potential still evaluates the real continuation graph. */
    private function refreshMeasurements(): void
    {
        foreach ($this->measurements as $id => $value) {
            app(PropositionMeasurementService::class)->project(Proposition::findOrFail($id), $value, $value, $value);
        }
    }
}
