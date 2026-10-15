<?php

namespace Tests\Feature;

use App\Domain\Observables\ObservableValues;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Http\Resources\PropositionResource;
use App\Models\Attestation;
use App\Models\CanonicalMeasurement;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use App\Services\ChiralityGateService;
use App\Services\ClosureGateService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;
use App\Services\Observables\CanonicalMeasurementService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ObservableService;
use App\Services\Participation\ParticipationContext;
use App\Services\PotentialProjectionService;
use App\Services\ProjectionService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\AssertsLatePhaseContract;
use Tests\TestCase;

class CanonicalCommitMeasurementTest extends TestCase
{
    use AssertsLatePhaseContract;
    use RefreshDatabase;

    private Participant $participant;

    private Proposition $origin;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('schemas.cache.store', 'array');
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'closure', 'title' => 'Closure', 'visibility' => 'private', 'configuration' => []])->save();
        $this->participant = new Participant;
        $this->participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer', 'max_read_count' => 25])->save();
    }

    private function committed(): array
    {
        $this->bootstrap();
        [$phase, $source] = $this->candidate([2]);
        $resolved = app(PropositionMeasurementService::class)->recalculate($source);
        $preview = app(LineageMeasurementService::class)->prospectiveCommit($source, $phase);
        $before = DB::table('local_chirality_states')->where('proposition_id', $source->id)->first();
        $target = app(ContributionDispatcher::class)->dispatch($this->participant, $phase,
            ['action' => 'proposition.commit', 'proposition_id' => $source->id]);
        $this->assertEquals($before, DB::table('local_chirality_states')->where('proposition_id', $source->id)->first());

        return [$source, $target, $resolved, $preview];
    }

    public function test_commit_preserves_independent_resolved_scalars_and_all_component_metadata(): void
    {
        [$source, $target, $resolved, $preview] = $this->committed();
        $canonical = CanonicalMeasurement::findOrFail($target->id);
        $this->assertSame($source->id, $canonical->source_proposition_id);
        foreach (['correspondence', 'chirality', 'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction', 'chirality_direction', 'shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction'] as $key) {
            $this->assertSame($resolved->$key, $canonical->$key, $key);
            $this->assertSame($resolved->$key, $target->fresh()->measurement->$key, $key);
            $this->assertSame($resolved->$key, $target->fresh()->commitmentSnapshot->$key, $key);
        }
        $this->assertNotSame($canonical->chirality, $canonical->chiralityComponents()->average());
        $this->assertSame($resolved->correspondence, $preview->correspondence);
        $this->assertSame($resolved->chirality, $preview->chirality);
    }

    public function test_commit_has_evidence_references_but_no_local_expressive_state(): void
    {
        [$source, $target] = $this->committed();
        $this->assertSame(0, $target->attestations()->count());
        $this->assertSame(0, Attestation::effective()->where('proposition_id', $target->id)->count());
        $this->assertFalse(DB::table('local_chirality_states')->where('proposition_id', $target->id)->exists());
        $this->assertSame([$source->id], $target->commitSources()->pluck('propositions.id')->all());
        $this->assertTrue(app(CommitmentAdmissibilityService::class)->grounding($target)['admissible']);
        $this->assertSame(1, $source->attestations()->count());
    }

    public function test_canonical_read_paths_never_run_expressive_calculators_or_rebuild_scalar(): void
    {
        [, $target, $resolved] = $this->committed();
        // Poison the old expressive read path: following it now must fail explicitly.
        DB::table('local_chirality_states')->where('proposition_id', $resolved->proposition_id)->delete();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $lineage = app(LineageMeasurementService::class);
        $local = app(LocalMeasurementService::class)->calculate($target);
        $this->assertSame($resolved->chirality, $local->chirality);
        $this->assertSame($resolved->correspondence, $lineage->calculate($target)->correspondence);
        $this->assertSame($resolved->chirality, $lineage->chirality($target)[0]);
        $this->assertSame($resolved->chirality, app(ObservableService::class)->calculate($target)->chirality);
        $this->assertSame($resolved->chirality, app(PropositionMeasurementService::class)->observeChirality($target));
        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'local_chirality_states') || str_contains($sql, 'attestations'))));
        $context = new ParticipationContext($target->phase, collect([$target->id]));
        $this->assertSame($resolved->chirality, $lineage->calculate($target, context: $context)->chirality);
        $this->assertSame($resolved->chirality, $lineage->chirality($target, $context)[0]);
    }

    public function test_closure_is_source_preserved_and_snapshot_combines_it_with_preserved_scalars(): void
    {
        [$source, $target, $resolved, $preview] = $this->committed();
        $birth = app(ProspectiveClosureService::class)->stored($target);
        $this->assertSame(app(ProspectiveClosureService::class)->stored($source), $birth);
        $snapshot = $target->fresh()->commitmentSnapshot;
        $this->assertSame($birth['closure'], $snapshot->closure);
        $this->assertSame($preview->closure()->average(), $snapshot->closure);
        $projection = app(ProjectionService::class)->from(new ObservableValues($resolved->correspondence, $resolved->chirality, $birth['closure']));
        $this->assertSame($projection['prominence'], $snapshot->prominence);
        $this->assertSame($projection['turbulence'], $snapshot->turbulence);
        $this->assertEquals($snapshot->closure_observation, $birth);
        $this->assertArrayNotHasKey('closure', CanonicalMeasurement::find($target->id)->getAttributes());
    }

    public function test_first_expressive_child_is_fresh_and_attenuates_only_the_canonical_baseline(): void
    {
        [$source, $target, $resolved] = $this->committed();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->participant->id, 'sequence' => 3,
            'entry_committal_id' => $target->id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
            'write_register' => $cycle->contextHorizon->domain->registerFor(PhaseKind::Distinguish), 'gate_proposition_id' => $target->id, 'started_at' => now()])->save();
        $child = $this->node($phase, 'expressive', $phase->write_register);
        $this->edge($phase, $target, $child, 'variant');
        $state = DB::table('local_chirality_states')->where('proposition_id', $child->id)->first();
        $this->assertSame([], json_decode($state->intervals, true));
        $this->assertEquals(0, $state->chirality);
        $this->assertEquals(0, $state->orientation);
        $this->assertSame(0, $child->attestations()->count());
        $lineage = app(LineageMeasurementService::class);
        $positions = $lineage->positions($child)->map(fn ($p) => $p[0]->id)->all();
        $this->assertSame([$child->id, $target->id], $positions);
        $this->assertNotContains($source->id, $positions);
        $local = app(LocalMeasurementService::class)->calculate($child);
        $values = $lineage->calculate($child);
        $this->assertSame(round($resolved->chirality / 5, 7), $values->chirality);
        $this->assertSame(round(($local->correspondence + $resolved->correspondence / 4) / 1.25, 7), $values->correspondence);
        $this->assertSame($values->chirality, $lineage->chirality($child)[0]);
    }

    public function test_zero_component_directions_are_preserved_even_after_projection_loss(): void
    {
        $this->bootstrap();
        [$phase, $source] = $this->candidate([2]);
        // A resolved zero retains event-born hysteresis, independently of entry defaults.
        DB::table('local_chirality_states')->where('proposition_id', $source->id)->update([
            'orientation' => 0, 'attestation_cadence' => 0, 'chirality' => 0,
            'orientation_direction' => 'positive', 'attestation_cadence_direction' => 'negative']);
        $target = app(ContributionDispatcher::class)->dispatch($this->participant, $phase,
            ['action' => 'proposition.commit', 'proposition_id' => $source->id]);
        DB::table('proposition_measurements')->where('proposition_id', $target->id)->delete();
        DB::table('chirality_observations')->where('proposition_id', $target->id)->delete();
        $target = $target->fresh();
        $gate = app(ChiralityGateService::class)->evaluate($target);
        $this->assertSame('positive', $gate['orientation_direction']);
        $this->assertSame('negative', $gate['attestation_cadence_direction']);
        $this->assertFalse($gate['admissible']);
        $components = app(LocalMeasurementService::class)->chiralityComponents($target);
        $this->assertSame(['orientation_direction' => 'positive', 'attestation_cadence_direction' => 'negative'], $components->resolveDirections());
        $canonical = CanonicalMeasurement::find($target->id);
        $projected = app(PropositionMeasurementService::class)->project($target, 1, $components->average(), 1);
        $this->assertSame($canonical->chirality, $projected->chirality);
        $this->assertSame($canonical->correspondence, $projected->correspondence);
    }

    public function test_canonical_record_is_immutable(): void
    {
        [, $target] = $this->committed();
        $canonical = CanonicalMeasurement::findOrFail($target->id);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('immutable');
        $canonical->forceFill(['chirality' => 1])->save();
    }

    public function test_source_closure_is_preserved_and_commit_closure_remains_frozen(): void
    {
        $this->bootstrap();
        [$phase, $source] = $this->candidate([2]);
        $expected = app(ClosureGateService::class)->preview($source, $phase);
        $target = app(ContributionDispatcher::class)->dispatch($this->participant, $phase,
            ['action' => 'proposition.commit', 'proposition_id' => $source->id]);
        $this->assertSame(app(ProspectiveClosureService::class)->stored($source), $expected);
        $this->assertSame($expected['closure'], $target->fresh()->commitmentSnapshot->closure);
        $before = app(ProspectiveClosureService::class)->stored($target);
        (new Cycle)->forceFill(['participant_id' => $this->participant->id, 'sequence' => 3,
            'entry_committal_id' => $target->id])->save();
        app(PropositionMeasurementService::class)->recalculate($target);
        $this->assertSame($before, app(ProspectiveClosureService::class)->stored($target));
        $this->assertSame($expected['closure'], $target->measurement->closure);
    }

    public function test_rejected_commit_rolls_back_canonical_record_and_source_projection(): void
    {
        $this->bootstrap();
        [$phase, $source] = $this->candidate([2]);
        $preview = app(ClosureGateService::class)->preview($source, $phase);
        $before = $this->counts();
        $state = DB::table('local_chirality_states')->where('proposition_id', $source->id)->first();
        $this->mock(ClosureGateService::class, function ($mock) use ($preview) {
            $mock->shouldReceive('preview')->andReturn($preview);
            $mock->shouldReceive('forCommitment')->once()->andReturn([...$preview, 'admissible' => false]);
        });
        try {
            app(ContributionDispatcher::class)->dispatch($this->participant, $phase,
                ['action' => 'proposition.commit', 'proposition_id' => $source->id]);
            $this->fail('The actual Closure rejection must roll back the canonical boundary.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('aligned Historical Extent/Rhythm', $error->getMessage());
        }
        $this->assertSame($before, $this->counts());
        $this->assertSame(0, CanonicalMeasurement::count());
        $this->assertEquals($state, DB::table('local_chirality_states')->where('proposition_id', $source->id)->first());
    }

    public function test_resource_and_repaired_projection_publish_canonical_scalars(): void
    {
        [, $target, $resolved] = $this->committed();
        DB::table('proposition_measurements')->where('proposition_id', $target->id)->delete();
        $target = $target->fresh();
        app(PropositionMeasurementService::class)->recalculate($target);
        $resource = (new PropositionResource($target))->resolve();
        $this->assertSame($resolved->correspondence, $resource['observables']['correspondence']);
        $this->assertSame($resolved->chirality, $resource['observables']['chirality']);
        $this->assertSame('current', $resource['observables']['status']);
        $this->assertSame($resolved->orientation_direction, $target->measurement->orientation_direction);
        $this->assertSame($resolved->attestation_cadence_direction, $target->measurement->attestation_cadence_direction);
    }

    public function test_background_projection_rebuild_preserves_all_canonical_metadata(): void
    {
        [, $target, $resolved] = $this->committed();
        $canonical = CanonicalMeasurement::findOrFail($target->id);
        DB::table('proposition_measurements')->where('proposition_id', $target->id)->delete();
        app(PotentialProjectionService::class)->rebuild($this->participant->substrate_id);
        $projected = $target->fresh()->measurement;
        foreach ($canonical->metadata() as $key => $value) {
            $this->assertSame($value, $projected->$key, $key);
        }
        $this->assertSame($resolved->chirality, $projected->chirality);
        $this->assertSame($resolved->correspondence, $projected->correspondence);
        $this->assertFalse(DB::table('local_chirality_states')->where('proposition_id', $target->id)->exists());
    }

    public function test_canonical_observation_before_snapshot_does_not_write_expressive_history(): void
    {
        $this->bootstrap();
        [$phase, $source] = $this->candidate([2]);
        $measurements = app(PropositionMeasurementService::class);
        $resolved = $measurements->recalculate($source);
        $target = $this->node($phase, 'committal', $phase->write_register);
        app(CanonicalMeasurementService::class)->preserve($target, $source, $resolved,
            $measurements->correspondenceDirectionFor($source)->value);
        $this->assertFalse(CommitmentSnapshot::whereKey($target->id)->exists());
        $this->assertSame($resolved->chirality, $measurements->observeChirality($target));
        $this->assertFalse(DB::table('chirality_observations')->where('proposition_id', $target->id)->exists());
    }

    private function bootstrap(): void
    {
        $phase = $this->phase(1, 'canon');
        // Origin is the unique parentless Committal; no expressive pre-Origin history.
        $this->origin = $this->node($phase, 'committal', 'canon');
        app(PropositionMeasurementService::class)->recalculate($this->origin);
        // Pre-upgrade immutable history is intentionally not retroactively reinforced.
        $snapshot = new CommitmentSnapshot;
        $horizon = $this->origin->contextHorizonMembership->horizon;
        $snapshot->forceFill(['proposition_id' => $this->origin->id, 'substrate_id' => $this->participant->substrate_id,
            'ledger_entry_id' => $this->origin->ledgerEntry->id, 'correspondence' => -1, 'chirality' => -1, 'closure' => -1,
            'depth' => -1, 'cadence' => -1, 'depth_direction' => 'negative', 'cadence_direction' => 'negative',
            'prominence' => 0, 'turbulence' => 1, 'tension' => -1, 'tension_before' => 0,
            'direction_before' => 'emergence', 'direction_after' => 'settlement',
            'calculator_version' => 'fixture-bootstrap', 'evaluated_at' => now()])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);
        $this->assertSame(ObservableDirection::Negative, $snapshot->depth_direction);
        $this->assertSame(ObservableDirection::Negative, $snapshot->cadence_direction);
        $this->finish($phase);
    }

    /** @param list<int> $cycles @return array{Phase, Proposition} */
    private function candidate(array $cycles, string $writeRegister = 'concord', string $gateRegister = 'signal', string $direction = 'support'): array
    {
        $previous = $this->origin;
        foreach ($cycles as $sequence) {
            $phase = $this->phase($sequence, $writeRegister);
            $gate = $this->node($this->phaseAtPositionFixture($phase, PhaseKind::Bias), 'expressive', $sequence === end($cycles) ? $writeRegister : $gateRegister);
            $this->edge($phase, $previous, $gate, 'variant');
            $phase->gate_proposition_id = $gate->id;
            $phase->save();
            if ($sequence !== end($cycles)) {
                $this->finish($phase);
            }
            $previous = $gate;
        }
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $phase->id, 'proposition_id' => $gate->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($attestation);
        $this->loadedReceiptFixture($gate);
        $this->participant->current_proposition_id = $gate->id;
        $this->participant->save();

        $this->publishPotentialFixture($this->participant);

        return [$phase, $gate];
    }

    private function phase(int $sequence, string $register): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->participant->id, 'sequence' => $sequence,
            'entry_committal_id' => isset($this->origin) ? $this->origin->id : null])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order, 'write_register' => $register, 'started_at' => now()])->save();

        return $phase;
    }

    private function node(Phase $phase, string $tier, string $register): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => $tier, 'register' => $register, 'statement' => 'Fixture proposition.'])->save();
        app(LedgerService::class)->record($node);

        return $node;
    }

    private function edge(Phase $phase, Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $source->id, 'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
        if ($target->tier === 'expressive') {
            app(ProspectiveClosureService::class)->initialize($target);
        }
    }

    private function finish(Phase $phase): void
    {
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();
    }

    /** @return list<int> */
    private function counts(): array
    {
        return [Proposition::count(), Supersession::count(), CommitmentSnapshot::count(), ContextHorizon::count(), SubstrateLedgerEntry::count()];
    }
}
