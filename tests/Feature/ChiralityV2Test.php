<?php

namespace Tests\Feature;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\ObservableDirection as Direction;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use App\Services\AttestationEvidenceService;
use App\Services\ClosureGateService;
use App\Services\CommitRegionPolarityService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContextHorizonProjector;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\CanonicalMeasurementService;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use App\Services\ReceiptContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class ChiralityV2Test extends TestCase
{
    use IsolatesClosureOutput;
    use CreatesHomologousPositions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    public function test_attested_origin_opens_settlement_and_freezes_components(): void
    {
        $this->createHomologyActor();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Explicit Origin evidence', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']]);
        $origin = Proposition::findOrFail($this->actor->fresh()->current_proposition_id);
        $snapshot = $origin->commitmentSnapshot;
        $this->assertSame(1, Attestation::count());
        $this->assertSame(0.0, $snapshot->orientation);
        $this->assertSame(-1.0, $snapshot->attestation_cadence);
        $this->assertSame(-0.5, $snapshot->chirality);
        $this->assertSame('negative', $snapshot->orientation_direction);
        $this->assertSame('negative', $snapshot->attestation_cadence_direction);
        $this->assertEqualsWithDelta(-1 / 6, $snapshot->tension, 1e-7);
        $this->assertSame('settlement', $snapshot->direction_after->value);
        $live = $this->actor->cycles()->latest('sequence')->firstOrFail();
        $this->assertSame('settlement', $live->contextHorizon->domain->value);
        $this->assertSame('signal', $live->phases()->sole()->write_register);
        $before = $snapshot->getAttributes();
        $this->emptyCycle($origin);
        app(PropositionMeasurementService::class)->recalculate($origin);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
        $this->assertSame(-0.5, $origin->fresh()->measurement->chirality);

        // Preview reads the selected source, so Origin retains its constitutional
        // state; it cannot be treated as a new ordinary expressive candidate.
        $phase = $this->phase($origin, $origin, PhaseKind::Order);
        $preview = app(ClosureGateService::class)->preview($origin, $phase);
        $this->assertTrue($preview['admissible']);
        $this->assertSame(-1.0, $preview['depth']);
        $this->assertSame(-1.0, $preview['cadence']);
        $grounding = app(CommitmentAdmissibilityService::class)->grounding($origin);
        $this->assertFalse($grounding['admissible']);
    }

    public static function orientations(): array
    {
        return [['support', 'support', 1.0], ['refute', 'refute', 1.0],
            ['support', 'refute', -1.0], ['refute', 'support', -1.0], [null, 'support', 0.0]];
    }

    #[DataProvider('orientations')]
    public function test_orientation_uses_effective_equal_weight_evidence_in_negative_support_frames(?string $a, string $b, float $expected): void
    {
        [$comparison, $current, $previous] = $this->pair();
        if ($a !== null) {
            $this->attest($current, $a);
        }
        $this->attest($previous, $b);
        $this->assertSame($expected, app(ChiralityCalculator::class)->components($comparison)->orientation);
        if ($a !== null) {
            $this->assertSame($a === 'support' ? -1.0 : 1.0,
                app(AttestationEvidenceService::class)->valueFor(collect([$current])));
        }
    }

    public function test_entry_population_includes_attest_only_empty_unfinished_and_rested_cycles_but_not_siblings(): void
    {
        [$comparison, $current, $previous, $seed] = $this->pair();
        $initial = app(ChiralityCalculator::class)->components($comparison);
        $this->assertSame(1, $initial->currentCycles); // A: creates the current Proposition.
        $b = $this->emptyCycle($seed);
        $phase = (new Phase)->forceFill(['cycle_id' => $b->id, 'kind' => PhaseKind::Distinguish,
            'gate_proposition_id' => $current->id, 'write_register' => $current->register, 'started_at' => now()]);
        $phase->save();
        $this->attest($current, 'support', phase: $phase); // B: Attests only.
        $c = $this->emptyCycle($seed); // C: neither, remains unfinished.
        $d = $this->emptyCycle($seed);
        $d->forceFill(['completed_at' => now(), 'rested_at' => now()])->save(); // D: Rested.
        $this->assertNull($c->completed_at);
        $this->assertSame(0, $c->phases()->count());
        $values = app(ChiralityCalculator::class)->components($comparison);
        $this->assertSame(4, $values->currentCycles);
        $this->assertSame(1, $values->currentActs);
        $this->assertSame(-1.0, $values->cadence); // .25 versus observed zero.
        $sibling = $this->committedCoordinate($seed, 'emergence');
        $values = app(ChiralityCalculator::class)->components($comparison);
        $this->assertSame(5, $values->currentCycles);
        $this->emptyCycle($sibling); // E: same Horizon, unrelated entry.
        $other = $this->position($sibling);
        for ($i = 0; $i < 8; $i++) {
            $this->emptyCycle($sibling);
            $this->attest($other, 'support');
        }
        $this->assertEquals($values, app(ChiralityCalculator::class)->components($comparison));
    }

    public function test_replacement_changes_orientation_but_both_acts_remain_in_cadence(): void
    {
        [$comparison, $current, $previous] = $this->pair();
        $old = $this->attest($current, 'support');
        $this->attest($previous, 'support');
        $this->assertSame(1.0, app(ChiralityCalculator::class)->components($comparison)->orientation);
        $this->attest($current, 'refute', $old);
        $values = app(ChiralityCalculator::class)->components($comparison);
        $this->assertSame(-1.0, $values->orientation);
        $this->assertSame(2, $values->currentActs);
        $this->assertSame(1, $values->previousActs);
        $this->assertSame(0.0, $values->cadence);
    }

    public function test_live_entry_counts_match_participation_context_without_any_new_contributions(): void
    {
        [$comparison, $current, $previous, $seed] = $this->pair();
        $this->attest($current, 'support');
        $this->attest($previous, 'support');
        $calculator = app(ChiralityCalculator::class);
        $before = $calculator->components($comparison);
        $ledger = SubstrateLedgerEntry::count();
        $this->emptyCycle($seed);
        $after = $calculator->components($comparison);
        $this->assertSame($before->currentCycles + 1, $after->currentCycles);
        $this->assertSame($before->currentActs, $after->currentActs);
        $this->assertSame($ledger, SubstrateLedgerEntry::count());
        $context = new ParticipationContext($current->phase, collect([$current->id, $previous->id]));
        $side = fn ($lineage) => new DomainLineage($lineage->horizon, $context->nodes($lineage->propositions->pluck('id')), $lineage->supersessions, $context);
        $this->assertEquals($after, $calculator->components(new LineageComparison($side($comparison->current), $side($comparison->previous))));
    }

    public function test_a_represented_same_domain_committal_counts_its_idle_entrants_before_any_descendant_exists(): void
    {
        [$comparison, $current, $previous, $seed] = $this->pair();
        $commit = $this->committedCoordinate($seed, 'emergence');
        $lineage = app(ContextLineageComparisonService::class)->for($commit);
        $calculator = app(ChiralityCalculator::class);
        $before = $calculator->components($lineage);
        $this->emptyCycle($commit);
        $after = $calculator->components($lineage);
        $this->assertSame($before->currentCycles + 1, $after->currentCycles);
        $this->assertSame($before->previousCycles, $after->previousCycles);
        $this->assertSame($before->currentActs, $after->currentActs);
        $this->assertSame(0, $commit->children()->count());
    }

    public function test_uneven_receipt_load_and_clear_leave_both_components_and_directions_unchanged(): void
    {
        [$comparison, $current, $previous] = $this->pair();
        $attestation = $this->attest($current, 'support');
        $this->attest($previous, 'support');
        $receipt = $this->loadedReceiptFixture($current, $attestation);
        // Establish the observer Cycle before measuring: entry itself is participation.
        $phase = $this->phase($current->phase->cycle->entryCommittal, $current, PhaseKind::Order);
        $calculator = app(ChiralityCalculator::class);
        $before = $calculator->components($comparison);
        $counts = app(ReinforcementService::class);
        $this->assertSame(1, $counts->count($current));
        app(ReceiptContextService::class)->clear($this->actor, $phase, $receipt->id);
        $this->assertSame(0, $counts->count($current));
        $after = $calculator->components($comparison);
        $this->assertEquals($before, $after);
        $this->assertSame($before->average(), $after->average());
        $this->assertSame($before->resolveDirections(Direction::Negative, Direction::Positive),
            $after->resolveDirections(Direction::Negative, Direction::Positive));
    }

    public function test_component_directions_retain_independently_and_calculation_is_read_only(): void
    {
        [$comparison, $current] = $this->pair();
        $service = app(PropositionMeasurementService::class);
        $service->project($current, 0, 0, 0,
            chiralityComponents: new ChiralityValues(-.8, .8));
        $measurement = $service->project($current, 0, 0, 0,
            chiralityComponents: new ChiralityValues(0, 0));
        $this->assertSame('negative', $measurement->orientation_direction);
        $this->assertSame('positive', $measurement->attestation_cadence_direction);
        $tables = ['attestations', 'cycles', 'supersessions', 'context_horizons', 'context_horizon_memberships',
            'participants', 'participant_receipt_contexts', 'receipt_context_events', 'receipts', 'proposition_measurements'];
        $state = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
        $before = $state();
        app(ChiralityCalculator::class)->components($comparison);
        $this->assertSame($before, $state());
    }

    public function test_non_origin_grounding_still_requires_both_attestation_and_receipt(): void
    {
        [, $current] = $this->pair();
        $service = app(CommitmentAdmissibilityService::class);
        $this->assertFalse($service->grounding($current)['admissible']);
        $this->attest($current, 'support');
        $this->assertTrue($service->grounding($current)['requirements']['attestation']);
        $this->assertFalse($service->grounding($current)['requirements']['receipt']);
        $this->assertFalse($service->grounding($current)['admissible']);
        $this->receiptFixture($current);
        $this->assertTrue($service->grounding($current)['admissible']);
    }

    private function pair(): array
    {
        $this->createHomologyActor();
        $origin = $this->committedCoordinate(null, 'settlement');
        app(CommitRegionPolarityService::class)->establishOrigin($origin, 'refute');
        $previous = $this->position($origin);
        $seed = $this->committedCoordinate($origin, 'emergence', ['cadence_interval' => 1]);
        app(CommitRegionPolarityService::class)->establishCommit($seed, Direction::Negative);
        $current = $this->position($seed);
        foreach ([[$origin, $previous], [$seed, $current]] as [$parent, $child]) {
            $edge = (new Supersession)->forceFill(['phase_id' => $child->phase_id,
                'source_proposition_id' => $parent->id, 'target_proposition_id' => $child->id, 'type' => 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
        }
        // Explicit selected single-branch comparison isolates the component populations.
        $side = fn ($node) => new DomainLineage($node->phase->cycle->contextHorizon, collect([$node]), collect());

        return [new LineageComparison($side($current), $side($previous)), $current, $previous, $seed];
    }

    private function emptyCycle(Proposition $entry): Cycle
    {
        $actor = (new Participant)->forceFill(['substrate_id' => $this->actor->substrate_id,
            'callsign' => 'observer-'.Participant::count()]);
        $actor->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $actor->id, 'sequence' => 1,
            'entry_committal_id' => $entry->id,
            'context_horizon_id' => app(ContextHorizonProjector::class)->forEntry($entry)->id]);
        $cycle->save();

        return $cycle;
    }

    private function attest(Proposition $node, string $direction, ?Attestation $supersedes = null, ?Phase $phase = null): Attestation
    {
        $phase ??= $this->phase($node->phase->cycle->entryCommittal, $node, PhaseKind::Distinguish);
        $attestation = (new Attestation)->forceFill(['phase_id' => $phase->id,
            'proposition_id' => $node->id, 'direction' => $direction, 'supersedes_attestation_id' => $supersedes?->id]);
        $attestation->save();
        app(LedgerService::class)->record($attestation);

        return $attestation;
    }
}
