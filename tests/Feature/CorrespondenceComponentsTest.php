<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Supersession;
use App\Services\CommitmentSnapshotService;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\CorrespondenceCalculator;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use App\Services\ReceiptContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class CorrespondenceComponentsTest extends TestCase
{
    use CreatesHomologousPositions;
    use RefreshDatabase;

    private const array FIELDS = ['shape_topology', 'cardinal_position', 'shape_topology_direction', 'cardinal_position_direction'];

    public function test_origin_has_the_unique_exact_identity_pair_and_freezes_it(): void
    {
        $origin = $this->origin();
        $components = app(CorrespondenceCalculator::class)->components(app(ContextLineageComparisonService::class)->for($origin));
        $this->assertSame(1.0, $components->shapeTopology);
        $this->assertSame(1.0, $components->cardinalPosition);
        $this->assertSame(1.0, $components->average());
        $snapshot = $origin->commitmentSnapshot;
        $this->assertSame(1.0, $snapshot->correspondence);
        $this->assertSame([1.0, 1.0, 'positive', 'positive'], array_values($snapshot->only(self::FIELDS)));
        $before = $snapshot->getAttributes();
        $this->child($origin, $origin);
        app(PropositionMeasurementService::class)->recalculate($origin);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
        $this->assertSame($snapshot->only(self::FIELDS), $origin->fresh()->measurement->only(self::FIELDS));
        $this->assertSame(-0.5, $snapshot->chirality);
        $this->assertSame(-1.0, $snapshot->closure);
        $this->assertSame('settlement', $snapshot->direction_after->value);
    }

    public function test_local_mean_and_attenuated_results_preserve_pre_refactor_values(): void
    {
        $origin = $this->origin();
        $head = $origin;
        // Captured from the unchanged pre-refactor calculator on main 4266d81a.
        // Each pair is [raw/local, structurally attenuated].
        $expected = [[-.2642978, -.0114382], [-.4742165, -.3153158],
            [-.4413933, -.3700546], [-.5350936, -.4609498]];
        $service = app(PropositionMeasurementService::class);
        foreach ([PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse] as $index => $kind) {
            $head = $this->child($origin, $head, $kind);
            $comparison = app(ContextLineageComparisonService::class)->for($head);
            $calculator = app(CorrespondenceCalculator::class);
            $components = $calculator->components($comparison);
            $local = app(LocalMeasurementService::class)->calculate($head);
            $resolved = app(LineageMeasurementService::class)->calculate($head);
            $measurement = $service->recalculate($head);
            $this->assertSame($expected[$index], [$local->correspondence, $resolved->correspondence]);
            $this->assertSame(round(($components->shapeTopology + $components->cardinalPosition) / 2, 7), $calculator->calculate($comparison));
            $this->assertSame($components->average(), $local->correspondence);
            $this->assertSame($components->shapeTopology, $measurement->shape_topology);
            $this->assertSame($components->cardinalPosition, $measurement->cardinal_position);
            $this->assertEquals($components, $resolved->correspondenceComponents);
            $this->assertSame($resolved->correspondence, $measurement->correspondence);
            $this->assertNotSame($local->correspondence, $resolved->correspondence);
        }
    }

    public static function directions(): array
    {
        return [
            'opposite signs' => [[-.8, .8], [.4, -.4], ['positive', 'negative']],
            'zero Shape' => [[-.8, -.8], [0, .4], ['negative', 'positive']],
            'zero Cardinal' => [[-.8, -.8], [.4, 0], ['positive', 'negative']],
            'both zero' => [[-.8, .8], [0, 0], ['negative', 'positive']],
        ];
    }

    #[DataProvider('directions')]
    public function test_component_directions_retain_independently_without_replacing_combined_direction(array $first, array $next, array $expected): void
    {
        $origin = $this->origin();
        $head = $this->child($origin, $origin);
        $service = app(PropositionMeasurementService::class);
        $service->project($head, -.2, 0, 0, correspondenceComponents: new CorrespondenceValues(...$first));
        $measurement = $service->project($head, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(...$next));
        $this->assertSame($expected, [$measurement->shape_topology_direction, $measurement->cardinal_position_direction]);
        $this->assertSame(0.0, $measurement->correspondence);
        $this->assertSame('negative', $head->fresh()->correspondence_direction);
    }

    public function test_missing_component_history_uses_entry_snapshot_then_positive_default(): void
    {
        $this->createHomologyActor();
        $entry = $this->commit(null, 'settlement', ['shape_topology_direction' => 'negative']);
        $head = $this->child($entry, $entry);
        $measurement = app(PropositionMeasurementService::class)->project($head, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(0, 0));
        $this->assertSame('negative', $measurement->shape_topology_direction);
        $this->assertSame('positive', $measurement->cardinal_position_direction);
        $this->assertNull($entry->fresh()->commitmentSnapshot->cardinal_position_direction);
    }

    public function test_historical_snapshots_survive_migration_and_projection_without_reconstruction(): void
    {
        $migration = require database_path('migrations/2026_09_30_000000_record_correspondence_components.php');
        $migration->down();
        $this->createHomologyActor();
        $origin = $this->commit(null, 'settlement', ['correspondence' => .1234567]);
        $snapshot = $origin->commitmentSnapshot;
        $before = $snapshot->getAttributes();
        $migration->up();
        $snapshot->refresh();
        $this->assertSame($before, array_diff_key($snapshot->getAttributes(), array_flip(self::FIELDS)));
        $this->assertSame([null, null, null, null], array_values($snapshot->only(self::FIELDS)));
        // Explicit projection cannot inject invented diagnostics into a frozen Committal.
        $measurement = app(PropositionMeasurementService::class)->project($origin, 1, 1, 1,
            correspondenceComponents: new CorrespondenceValues(1, 1));
        $this->assertSame(.1234567, $measurement->correspondence);
        $this->assertSame([null, null, null, null], array_values($measurement->only(self::FIELDS)));
        app(PropositionMeasurementService::class)->recalculate($origin);
        $this->assertSame($snapshot->getAttributes(), $snapshot->fresh()->getAttributes());
    }

    public function test_receipt_creation_loading_and_clearing_are_neutral_to_persisted_components(): void
    {
        $origin = $this->origin();
        $head = $this->child($origin, $origin);
        $service = app(PropositionMeasurementService::class);
        // Cardinal is exactly zero at the first developed row. Keep a negative history.
        $service->project($head, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(-1, -1));
        $before = $service->recalculate($head)->only(self::FIELDS);
        $attestation = (new Attestation)->forceFill(['phase_id' => $head->phase_id, 'proposition_id' => $head->id, 'direction' => 'support']);
        $attestation->save();
        app(LedgerService::class)->record($attestation);
        $receipt = $this->loadedReceiptFixture($head, $attestation);
        $this->assertSame($before, $service->recalculate($head)->only(self::FIELDS));
        $phase = $this->phase($origin, $head, PhaseKind::Order);
        app(ReceiptContextService::class)->clear($this->actor, $phase, $receipt->id);
        $this->assertSame($before, $service->recalculate($head)->only(self::FIELDS));
    }

    public function test_component_calculation_and_stale_observation_perform_no_writes_and_match_context(): void
    {
        $origin = $this->origin();
        $head = $this->child($origin, $origin);
        $service = app(PropositionMeasurementService::class);
        $service->project($head, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(-1, -1));
        DB::table('proposition_measurements')->where('proposition_id', $head->id)->update(['calculator_version' => 'old']);
        $head->unsetRelation('measurement');
        $context = new ParticipationContext($head->phase, collect([$head->id]));
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });
        $calculator = app(CorrespondenceCalculator::class);
        $plain = $calculator->components(app(ContextLineageComparisonService::class)->for($head));
        $cached = $calculator->components(app(ContextLineageComparisonService::class)->for($head, $context));
        $this->assertEquals($plain, $cached);
        $service->observeMissing(collect([$head]), $context);
        $observed = $head->measurement->only(self::FIELDS);
        $this->assertSame('negative', $observed['cardinal_position_direction']);
        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $sql))));
        $this->assertSame('old', PropositionMeasurement::find($head->id)->calculator_version);
        $this->assertSame($observed, $service->recalculate($head)->only(self::FIELDS));
    }

    public function test_new_ordinary_commitment_snapshot_freezes_local_values_and_retained_directions(): void
    {
        $origin = $this->origin();
        $gate = $this->child($origin, $origin);
        $phase = $this->phase($origin, $gate, PhaseKind::Order);
        $candidate = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'committal',
            'register' => $phase->write_register, 'statement' => 'Commit']);
        $candidate->save();
        app(LedgerService::class)->record($candidate);
        $edge = $this->commitAncestryFixture($candidate, $gate);
        $attestation = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $candidate->id, 'direction' => 'support']);
        $attestation->save();
        app(LedgerService::class)->record($attestation);
        $this->receiptFixture($candidate);
        $measurement = app(PropositionMeasurementService::class)->recalculate($candidate);
        $snapshot = app(CommitmentSnapshotService::class)->record($candidate, .5, .5, LineageMeasurementService::VERSION, $edge);
        $this->assertSame($measurement->only(self::FIELDS), $snapshot->only(self::FIELDS));
        $this->assertNotNull($snapshot->shape_topology);
        $before = $snapshot->fresh()->getAttributes();
        $this->child($origin, $gate);
        app(PropositionMeasurementService::class)->recalculate($candidate);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
        $this->assertSame($snapshot->only(self::FIELDS), $candidate->fresh()->measurement->only(self::FIELDS));
    }

    private function origin(): Proposition
    {
        $this->createHomologyActor();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']]);

        return Proposition::findOrFail($this->actor->fresh()->current_proposition_id);
    }

    private function child(Proposition $entry, Proposition $parent, PhaseKind $kind = PhaseKind::Distinguish): Proposition
    {
        $node = $this->position($entry, $kind);
        $edge = (new Supersession)->forceFill(['phase_id' => $node->phase_id,
            'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => 'variant']);
        $edge->save();
        app(LedgerService::class)->record($edge);

        return $node;
    }
}
