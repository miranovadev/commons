<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Exceptions\DeferredChiralityCadenceBoundary;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class PropositionLocalChiralityTest extends TestCase
{
    use CreatesHomologousPositions, RefreshDatabase;

    private function setupPosition(): array
    {
        $this->createHomologyActor();
        $origin = $this->commit(null, 'settlement');

        $node = $this->position($origin);
        $this->edge($origin, $node, 'variant');

        return [$origin, $node];
    }

    private function act(Proposition $node, string $direction = 'support', ?Attestation $previous = null, ?Phase $phase = null): Attestation
    {
        return DB::transaction(function () use ($node, $direction, $previous, $phase) {
            if ($phase === null) {
                $phase = new Phase;
                $phase->forceFill(['cycle_id' => $node->phase->cycle_id, 'kind' => $node->phase->kind,
                    'write_register' => $node->register, 'gate_proposition_id' => $node->id, 'started_at' => now()])->save();
            }
            $act = new Attestation;
            $act->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id,
                'direction' => $direction, 'supersedes_attestation_id' => $previous?->id])->save();
            app(LedgerService::class)->record($act);

            return $act;
        });
    }

    private function state(Proposition $node): array
    {
        return (array) DB::table('local_chirality_states')->where('proposition_id', $node->id)->first();
    }

    private function components(Proposition $node): array
    {
        $v = app(LocalMeasurementService::class)->chiralityComponents($node->fresh());

        return [$v->orientation, $v->cadence, $v->average(), $v->resolveDirections()];
    }

    private function edge(Proposition $parent, Proposition $child, string $type = 'reclassify'): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $child->phase_id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $child->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    public function test_own_create_and_replacement_recalculate_and_record_only_own_intervals(): void
    {
        [$origin, $node] = $this->setupPosition();
        $first = $this->act($node);
        $this->assertSame([.5, 0.0, .25], array_slice($this->components($node), 0, 3));
        $this->phase($origin, $origin, PhaseKind::Distinguish);
        $second = $this->act($node, 'refute', $first);
        $this->assertSame([-.5, 0.0, -.25], array_slice($this->components($node), 0, 3));
        $this->assertSame([1], json_decode($this->state($node)['intervals'], true));
        $this->phase($origin, $origin, PhaseKind::Distinguish);
        $this->act($node, 'support', $second);
        $this->assertSame([.5, 1.0, .75], array_slice($this->components($node), 0, 3));
        $this->assertSame([1, 1], json_decode($this->state($node)['intervals'], true));
        $this->assertSame(1, Attestation::effective()->where('proposition_id', $node->id)->count());
    }

    public function test_cycles_unrelated_attestations_and_projection_reads_do_not_change_local_state(): void
    {
        [$origin, $node] = $this->setupPosition();
        $first = $this->act($node);
        $this->phase($origin, $origin, PhaseKind::Distinguish);
        $second = $this->act($node, 'support', $first);
        $this->phase($origin, $origin, PhaseKind::Distinguish);
        $this->act($node, 'support', $second);
        $before = $this->state($node);
        $this->phase($origin, $origin, PhaseKind::Distinguish);
        $unrelated = $this->position($origin);
        $this->edge($origin, $unrelated, 'variant');
        $this->act($unrelated, 'refute');
        app(PropositionMeasurementService::class)->recalculate($node);
        app(PropositionMeasurementService::class)->observeChirality($node);
        $context = new ParticipationContext($node->phase, collect([$node->id]));
        app(LineageMeasurementService::class)->chirality($node, $context);
        $this->assertSame($before, $this->state($node));
        $this->assertSame([.5, 1.0, .75], array_slice($this->components($node), 0, 3));
    }

    public function test_reclassification_and_descendants_do_not_mutate_ancestors_or_inherit_local_state(): void
    {
        [$origin, $parent] = $this->setupPosition();
        $this->act($parent);
        $before = $this->state($parent);
        $child = $this->position($origin, PhaseKind::Disperse);
        $this->edge($parent, $child);
        $grandchild = $this->position($origin, PhaseKind::Mediate);
        $this->edge($child, $grandchild, 'variant');
        foreach ([$child, $grandchild] as $fresh) {
            $this->assertSame([0.0, 0.0, 0.0], array_slice($this->components($fresh), 0, 3));
            $this->assertSame([], json_decode($this->state($fresh)['intervals'], true));
            $this->assertNull($this->state($fresh)['last_position']);
            $this->assertSame(0, Attestation::where('proposition_id', $fresh->id)->count());
            $this->assertSame(0, Attestation::effective()->where('proposition_id', $fresh->id)->count());
        }
        $this->act($grandchild, 'refute');
        $this->assertSame($before, $this->state($parent));
        $this->assertSame([], json_decode($this->state($grandchild)['intervals'], true));
        $this->assertSame([], json_decode($this->state($child)['intervals'], true));
    }

    public function test_parent_influence_remains_downstream_through_existing_attenuation(): void
    {
        [$origin, $parent] = $this->setupPosition();
        $this->act($parent);
        $child = $this->position($origin, PhaseKind::Disperse);
        $this->edge($parent, $child);
        [$resolved, $components] = app(LineageMeasurementService::class)->chirality($child);
        $this->assertSame(0.0, $components->average());
        $this->assertSame(round((.25 / 4 - .5 / 9) / (1 + 1 / 4 + 1 / 9), 7), $resolved);
    }

    public function test_zero_hysteresis_is_local_and_not_overwritten_by_projection(): void
    {
        [$origin, $node] = $this->setupPosition();
        $this->act($node);
        $other = new Participant;
        $other->forceFill(['substrate_id' => $this->actor->substrate_id, 'callsign' => 'second-voter'])->save();
        $this->actor = $other;
        $phase = $this->phase($origin, $node, PhaseKind::Constrain);
        $this->act($node, 'refute', phase: $phase); // Two participants cancel; one interval is immature.
        $this->assertSame([0.0, 0.0, 0.0], array_slice($this->components($node), 0, 3));
        $this->assertSame('positive', $this->components($node)[3]['orientation_direction']);
        app(PropositionMeasurementService::class)->recalculate($node);
        $this->assertSame('positive', $node->fresh()->measurement->orientation_direction);
    }

    public function test_zero_mean_boundary_rolls_back_the_attestation_and_measurement_atomically(): void
    {
        [, $node] = $this->setupPosition();
        $first = $this->act($node);
        $second = $this->act($node, 'refute', $first);
        $before = $this->state($node);
        $ledgerCount = DB::table('substrate_ledger_entries')->count();
        try {
            $this->act($node, 'support', $second);
            $this->fail('Expected the deferred Stage 2 boundary.');
        } catch (DeferredChiralityCadenceBoundary $error) {
            $this->assertSame([0, 0], $error->intervals);
            $this->assertSame($node->id, $error->propositionId);
        }
        $this->assertSame($before, $this->state($node));
        $this->assertSame(2, Attestation::where('proposition_id', $node->id)->count());
        $this->assertSame($ledgerCount, DB::table('substrate_ledger_entries')->count());
    }

    public function test_missing_historical_state_is_not_reconstructed_from_live_population(): void
    {
        [, $node] = $this->setupPosition();
        $this->act($node);
        DB::table('local_chirality_states')->where('proposition_id', $node->id)->delete();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('explicit historical migration is required');
        $this->components($node);
    }

    public function test_existing_unattested_proposition_can_start_at_its_first_genuine_act(): void
    {
        [, $node] = $this->setupPosition();
        DB::table('local_chirality_states')->where('proposition_id', $node->id)->delete();
        $this->assertSame([0.0, 0.0, 0.0], array_slice($this->components($node), 0, 3));
        $this->act($node);
        $this->assertSame([.5, 0.0, .25], array_slice($this->components($node), 0, 3));
        $this->assertSame([], json_decode($this->state($node)['intervals'], true));
    }

    public function test_duplicate_ledger_record_is_not_another_beat(): void
    {
        [, $node] = $this->setupPosition();
        $act = $this->act($node);
        $before = $this->state($node);
        app(LedgerService::class)->record($act);
        $this->assertSame($before, $this->state($node));
    }

    public function test_real_reclassify_preserves_parent_state_and_resets_the_result(): void
    {
        $this->createHomologyActor();
        $origin = $this->commit(null, 'settlement');
        $source = $this->position($origin, PhaseKind::Persist);
        $this->edge($origin, $source, 'variant');
        $this->act($source);
        $this->receiptFixture($source);
        $before = $this->state($source);
        $phase = $this->phase($origin, $source, PhaseKind::Constrain);
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $phase, $this->reclassificationInputFixture($phase, $source));
        $this->assertSame($before, $this->state($source));
        $this->assertSame([0.0, 0.0, 0.0], array_slice($this->components($target), 0, 3));
        $this->assertSame([], json_decode($this->state($target)['intervals'], true));
        $this->assertNull($this->state($target)['last_position']);
        $this->assertSame(0, $target->attestations()->count());
    }

    public function test_real_dispatch_creates_and_changes_only_the_target_local_measurement(): void
    {
        $this->createHomologyActor();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
        $cycle = $this->actor->cycles()->latest('sequence')->firstOrFail();
        $phase = $cycle->phases()->whereNull('completed_at')->sole();
        $dispatch = app(ContributionDispatcher::class);
        $node = $dispatch->dispatch($this->actor, $phase, ['action' => 'proposition.variant', 'statement' => 'Local source']);
        $dispatch->dispatch($this->actor, $phase, ['action' => 'instrument.execute', 'proposition_id' => $node->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/local-evidence']]);
        $dispatch->dispatch($this->actor, $phase, ['action' => 'attestation.create', 'proposition_id' => $node->id, 'direction' => 'support']);
        $this->assertSame([.5, 0.0, .25], array_slice($this->components($node), 0, 3));
        $phase->forceFill(['completed_at' => now()])->save();
        $next = new Phase;
        $next->forceFill(['cycle_id' => $cycle->id, 'kind' => $phase->kind, 'write_register' => $phase->write_register,
            'gate_proposition_id' => $phase->gate_proposition_id, 'started_at' => now()])->save();
        $dispatch->dispatch($this->actor, $next, ['action' => 'attestation.create', 'proposition_id' => $node->id, 'direction' => 'refute']);
        $this->assertSame([-.5, 0.0, -.25], array_slice($this->components($node), 0, 3));
        $this->assertSame([0], json_decode($this->state($node)['intervals'], true));
    }
}
