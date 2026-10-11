<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\ProspectiveClosureService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProspectiveClosureStateTest extends TestCase
{
    use RefreshDatabase;

    private Participant $actor;

    private Proposition $origin;

    private Cycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = (new Substrate)->forceFill(['slug' => 'prospective-closure', 'title' => 'Closure', 'visibility' => 'private', 'configuration' => []]);
        $substrate->save();
        $this->actor = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer']);
        $this->actor->save();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
        $this->origin = $this->actor->fresh()->position;
        $this->cycle = $this->actor->cycles()->latest('sequence')->firstOrFail();
        DB::transaction(fn () => $this->service()->initialize($this->origin));
    }

    private function service(): ProspectiveClosureService
    {
        return app(ProspectiveClosureService::class);
    }

    private function born(PhaseKind $kind, Proposition $source, string $type = 'variant', ?Proposition $other = null): Proposition
    {
        return DB::transaction(function () use ($kind, $source, $type, $other) {
            $phase = $this->cycle->phases()->where('kind', $kind->value)->first();
            if ($phase === null) {
                $phase = (new Phase)->forceFill(['cycle_id' => $this->cycle->id, 'kind' => $kind,
                    'gate_proposition_id' => $source->id, 'write_register' => $this->cycle->contextHorizon->domain->registerFor($kind), 'started_at' => now()]);
                $phase->save();
            }
            $node = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
                'register' => $phase->write_register, 'statement' => 'Selected observation']);
            $node->save();
            app(LedgerService::class)->record($node, $source);
            foreach (array_filter([$source, $other]) as $parent) {
                $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id,
                    'target_proposition_id' => $node->id, 'type' => $type]);
                $edge->save();
                app(LedgerService::class)->record($edge);
            }
            $this->service()->initialize($node);

            return $node;
        });
    }

    private function enter(): Cycle
    {
        return DB::transaction(function () {
            $cycle = (new Cycle)->forceFill(['participant_id' => $this->actor->id,
                'sequence' => (int) $this->actor->cycles()->max('sequence') + 1,
                'context_horizon_id' => $this->cycle->context_horizon_id, 'entry_committal_id' => $this->origin->id]);
            $cycle->save();
            $this->service()->entered($cycle);

            return $cycle;
        });
    }

    public function test_origin_has_seven_available_equal_weight_intervals_without_empirical_births(): void
    {
        $state = $this->service()->stored($this->origin);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0], array_column($state['phase_observations'], 'interval'));
        $this->assertSame([], $state['orientation_observations']);
        $this->assertDatabaseMissing('phase_profile_observations', ['proposition_id' => $this->origin->id]);
        $this->enter();
        $this->assertSame($state, $this->service()->stored($this->origin));
    }

    public function test_constitutional_state_is_database_immutable(): void
    {
        $this->expectException(QueryException::class);
        DB::table('prospective_closure_states')->where('proposition_id', $this->origin->id)->update(['frozen' => false]);
    }

    public function test_cycle_updates_superseded_expressives_but_structural_progression_does_not(): void
    {
        $first = $this->born(PhaseKind::Distinguish, $this->origin);
        $before = $this->service()->stored($first);
        $second = $this->born(PhaseKind::Persist, $first, 'reclassify');
        $this->assertSame($before, $this->service()->stored($first));
        $entry = $this->enter();
        foreach ([$first, $second] as $head) {
            $this->assertSame(['kind' => 'cycle_entry', 'id' => $entry->id], $this->service()->stored($head)['trigger']);
        }
        $state = $this->service()->stored($first);
        $entry->completed_at = now();
        $entry->save();
        $this->assertSame($state, $this->service()->stored($first));
    }

    public function test_attestation_is_observed_only_at_next_cycle_and_reads_do_not_remeasure(): void
    {
        $head = $this->born(PhaseKind::Distinguish, $this->origin);
        $before = $this->service()->stored($head);
        $attestation = (new Attestation)->forceFill(['phase_id' => $head->phase_id, 'proposition_id' => $head->id, 'direction' => 'support']);
        $attestation->save();
        app(LedgerService::class)->record($attestation);
        $this->assertSame($before, $this->service()->stored($head));
        $this->enter();
        $after = $this->service()->stored($head);
        $this->assertSame(.5, $after['historical_orientation']);
        $this->assertSame($attestation->id, $after['orientation_observations'][$head->id]['last_attestation_id']);
    }

    public function test_temporal_profile_excludes_reconcile_operand_while_orientation_uses_active_causal_population(): void
    {
        $first = $this->born(PhaseKind::Distinguish, $this->origin);
        $second = $this->born(PhaseKind::Persist, $first);
        $third = $this->born(PhaseKind::Constrain, $second, 'reclassify');
        $gate = $this->born(PhaseKind::Disperse, $third);
        $other = $this->born(PhaseKind::Disperse, $third);
        $merged = $this->born(PhaseKind::Mediate, $gate, 'reconcile', $other);
        $state = $this->service()->stored($merged);
        $this->assertArrayNotHasKey($other->id, $state['phase_observations']);
        $this->assertArrayHasKey($gate->id, $state['phase_observations']);
        $this->assertArrayNotHasKey($gate->id, $state['orientation_observations']);
        $this->assertArrayNotHasKey($other->id, $state['orientation_observations']);
        $this->assertArrayHasKey($merged->id, $state['orientation_observations']);
        $this->assertSame([1, 1, 1, 1, 1, 1, 1], $state['domain_profiles']['emergence']['sample_counts']);
    }
}
