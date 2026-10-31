<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\AcquisitionAttemptService;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceAddress;
use App\Services\EvidenceExecutionService;
use App\Services\OperationalInstrumentService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseGenerationService;
use App\Services\PreparationService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\Fixtures\PreparationFixture;
use Tests\TestCase;

class AcquisitionAttemptTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(HyperlinkVerifier::class, HyperlinkVerifier::class);
        $this->mock(PublicResourceAddress::class, fn ($mock) => $mock->shouldReceive('resolve')->andReturn('93.184.216.34'));
        Http::preventStrayRequests();
        Storage::fake('artifacts');
        Http::fake(['*' => Http::response('An independently acquired preserved observation.', 200, ['Content-Type' => 'text/plain'])]);
    }

    public function test_same_gate_can_be_acquired_in_later_cycles_and_other_participants_are_independent(): void
    {
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $phase = $this->at($f, PhaseKind::Distinguish, $f->seed);
        $service = app(AcquisitionAttemptService::class);
        $this->assertSame('required', $service->state($phase)['status']);
        $receipt = $this->instrument($phase);
        $this->assertSame('awaiting_resolution', $service->state($phase)['status']);
        $this->assertSame($f->seed->id, $receipt->evidenceExecution->proposition_id);
        $this->assertFalse($service->allows($phase, 'instrument.execute'));
        $service->resolve($f->viewer, $receipt->evidenceExecution, ['action' => 'acquisition.defer', 'execution_id' => $receipt->evidence_execution_id, 'reason' => 'Examine again next Cycle']);
        $return = $this->visit($phase, $f->viewer);
        $this->assertSame('required', $service->state($return)['status']);
        $this->assertNotSame($receipt->id, $this->instrument($return)->id);
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'independent']);
        $other->save();
        $second = $this->visit($phase, $other);
        $this->assertSame('required', $service->state($second)['status']);
        $this->assertNotSame($receipt->id, $this->instrument($second)->id);
        $this->assertDatabaseCount('receipt_consumptions', 0);
    }

    public function test_one_phase_reservation_covers_both_disperse_mechanisms(): void
    {
        $fixture = new PreparationFixture(true);
        $f = $fixture->context;
        $this->assertTrue(app(PreparationService::class)->eligible($f->phase));
        $receipt = $this->instrument($f->phase);
        $this->assertFalse(app(PreparationService::class)->eligible($f->phase));
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
        $this->assertSame('awaiting_resolution', app(AcquisitionAttemptService::class)->state($f->phase)['status']);
        $this->expectException(ConflictHttpException::class);
        app(PreparationService::class)->start($f->viewer, $f->phase, $fixture->input());
    }

    public static function failures(): array
    {
        return [['read', 'reader_failure', true], ['delivery', 'delivery_failure', true],
            ['stale', 'stale', false], ['expired', 'expired', false], ['read', 'invalid_parameters', false]];
    }

    #[DataProvider('failures')]
    public function test_failure_observations_are_distinct_from_abandonment_but_both_can_defer(string $stage, string $classification, bool $recorded): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->fail($f->viewer, $execution, 'reader', '1', $stage, 'Recorded failure', ['classification' => $classification]);
        $attempts = app(AcquisitionAttemptService::class);
        $this->assertSame('awaiting_resolution', $attempts->state($f->phase)['status']);
        $this->assertSame($recorded, $execution->fresh()->receipt !== null);
        $this->assertSame($recorded, $attempts->ready($f->phase));
        $this->assertFalse($service->eligible($f->phase));
        $count = Proposition::count();
        $input = ['action' => 'acquisition.defer', 'execution_id' => $execution->id, 'reason' => 'Stop this attempted reading'];
        $attempts->resolve($f->viewer, $execution, $input);
        $attempts->resolve($f->viewer, $execution, $input);
        $this->assertSame($count, Proposition::count());
        $this->assertNotNull($f->phase->cycle->fresh()->deferred_at);
        $this->assertDatabaseCount('exploration_events', 0);
        if ($recorded) {
            $this->assertFalse($execution->fresh()->receipt->metadata['content_verified']);
        }
    }

    public function test_failed_instrument_records_unverified_observation_and_can_defer(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('Unavailable', 503)]);
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $phase = $this->at($f, PhaseKind::Distinguish, $f->seed);
        $receipt = $this->instrument($phase);
        $this->assertFalse($receipt->metadata['content_verified']);
        $this->assertNull($receipt->signature);
        $this->assertSame('awaiting_resolution', app(AcquisitionAttemptService::class)->state($phase)['status']);
        $this->assertSame($receipt->id, app(EvidenceExecutionService::class)->complete($receipt->evidenceExecution, $phase->gate)->id);
    }

    public function test_an_obsolete_open_phase_cannot_reserve_before_external_io(): void
    {
        $fixture = new PreparationFixture(true);
        $f = $fixture->context;
        $old = $f->phase;
        $current = $this->visit($old, $f->viewer);
        $preparation = app(PreparationService::class)->start($f->viewer, $current, $fixture->input());
        try {
            $this->instrument($old);
            $this->fail('An obsolete Phase must not reserve an opportunity.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('no longer active', $exception->getMessage());
        }
        $this->assertSame('pending', $preparation->fresh()->status);
        $this->assertSame(1, EvidenceExecution::whereIn('phase_id', [$old->id, $current->id])->count());
    }

    #[Group('query')]
    public function test_attempt_lookup_is_phase_scoped_and_memoized_and_downgrade_preserves_history(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $phase = $f->phase->load('cycle');
        $service = app(AcquisitionAttemptService::class);
        app(PreparationService::class)->start($f->viewer, $phase, $fixture->input());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame('pending', $service->state($phase)['status']);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('phase_id', $queries[0]['query']);
        $context = new ParticipationContext($phase);
        $service->state($phase, $context);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame('pending', $service->state($phase, $context)['status']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $migration = require database_path('migrations/2026_10_31_000000_record_generative_attempt_resolution.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_migration_preserves_multiple_historical_observations_without_retroactive_admission(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $this->observationFixture($f->phase, $f->gate, ['kind' => 'citation', 'reference' => $f->seed->id]);
        $before = EvidenceExecution::orderBy('id')->get()->map->getAttributes()->all();
        $receipts = Receipt::orderBy('id')->get()->map->getAttributes()->all();
        $this->assertGreaterThanOrEqual(2, count($before));
        $migration = require database_path('migrations/2026_10_31_000000_record_generative_attempt_resolution.php');
        $migration->down();
        $migration->up();
        $this->assertEquals($before, EvidenceExecution::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertEquals($receipts, Receipt::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame('required', app(AcquisitionAttemptService::class)->state($f->phase)['status']);
        $execution = app(PreparationService::class)->start($f->viewer, $f->phase, $fixture->input());
        $this->assertNotNull($execution->attempt_admitted_at);
        $this->assertSame(1, EvidenceExecution::whereNotNull('attempt_admitted_at')->count());
    }

    public function test_pending_instrument_cannot_be_replaced_or_used_and_expiry_allows_only_defer(): void
    {
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $phase = $this->at($f, PhaseKind::Distinguish, $f->seed);
        $instrument = app(OperationalInstrumentService::class);
        $instrument->observeFor($phase, $phase->gate, ['kind' => 'citation', 'reference' => $f->seed->id]);
        $attempts = app(AcquisitionAttemptService::class);
        $execution = $attempts->execution($phase);
        $this->assertSame('pending', $attempts->state($phase)['status']);
        $this->assertFalse($attempts->ready($phase));
        $this->assertNull($execution->receipt);
        try {
            $this->instrument($phase);
            $this->fail('A pending reservation must exclude another acquisition.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, EvidenceExecution::whereNotNull('attempt_admitted_at')->count());
        }
        $this->travel((int) config('preparation.unresolved_seconds') + 1)->seconds();
        $attempts->recover($phase);
        $this->assertSame(['acquisition.defer'], array_keys($attempts->schema($phase, $execution->fresh())['$defs']['actions']));
        $this->assertNull($execution->fresh()->receipt);
        $attempts->resolve($f->viewer, $execution, ['action' => 'acquisition.defer', 'execution_id' => $execution->id, 'reason' => 'Reservation expired']);
        $this->assertNotNull($phase->cycle->fresh()->deferred_at);
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_reserved_invocation_identity_survives_database_json_key_order(): void
    {
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $phase = $this->at($f, PhaseKind::Distinguish, $f->seed);
        $instrument = app(OperationalInstrumentService::class);
        $input = ['kind' => 'proposition_search', 'query' => 'Existing evidence', 'limit' => 2];
        $observed = $instrument->observeFor($phase, $phase->gate, $input);
        $execution = app(AcquisitionAttemptService::class)->execution($phase);
        // PostgreSQL JSONB orders object keys independently of the submitted
        // PHP array. Simulate that loss of order on SQLite as well.
        DB::table('evidence_executions')->where('id', $execution->id)
            ->update(['input' => json_encode(array_reverse($execution->input, true), JSON_THROW_ON_ERROR)]);
        $receipt = $instrument->execute($phase, $phase->gate, $input, observed: $observed);
        $this->assertSame($execution->id, $receipt->evidence_execution_id);
        $this->assertSame($phase->gate_proposition_id, $receipt->evidenceExecution->proposition_id);
        $this->assertSame($input['query'], $receipt->evidenceExecution->input['query']);
        $this->assertSame(1, EvidenceExecution::whereNotNull('attempt_admitted_at')->count());
    }

    public function test_independently_admitted_preparations_survive_a_competing_variant_but_cannot_resolve_twice(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'other-reader']);
        $other->save();
        $second = $this->visit($f->phase, $other);
        $service = app(PreparationService::class);
        $a = $service->start($f->viewer, $f->phase, $fixture->input());
        $b = $service->start($other, $second, $fixture->input());
        foreach ([[$f->viewer, $a], [$other, $b]] as [$actor, $execution]) {
            $service->present($actor, $execution, $fixture->presentation());
            $receipt = $service->contribute($actor, $execution, ['action' => 'proposition.variant', 'statement' => 'Independent considered continuation']);
            $this->assertSame($f->gate->id, $receipt->evidenceExecution->proposition_id);
            $this->assertSame('variant', $execution->fresh()->attempt_resolution['action']);
            $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $receipt->id]);
        }
        $this->assertNotSame($a->fresh()->resulting_proposition_id, $b->fresh()->resulting_proposition_id);
        $count = Proposition::count();
        $service->contribute($other, $b, ['action' => 'proposition.variant', 'statement' => 'Independent considered continuation']);
        $this->assertSame($count, Proposition::count());
        $this->expectException(ConflictHttpException::class);
        $service->defer($other, $b, 'Cannot change a contributed decision');
    }

    public function test_closed_margin_blocks_preparation_admission_and_a_presented_variant_but_preserves_defer(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->present($f->viewer, $execution, $fixture->presentation());
        $this->variantMarginFixture($f->phase, closed: true);
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'later-reader']);
        $other->save();
        $phase = $this->visit($f->phase, $other);
        try {
            $service->start($other, $phase, $fixture->input());
            $this->fail('Closed Crossover cannot reserve a readable Preparation.');
        } catch (ConflictHttpException|\DomainException) {
            $this->assertSame(1, EvidenceExecution::where('type', 'prepare')->whereNotNull('attempt_admitted_at')->count());
        }
        $this->assertSame(['preparation.defer'], array_keys($service->resolutionSchema($f->viewer, $f->phase, $execution)['$defs']['actions']));
        $count = Proposition::count();
        try {
            $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'A now-prohibited Variant']);
            $this->fail('Closed Crossover cannot resolve through a Variant.');
        } catch (\InvalidArgumentException) {
            $this->assertNull($execution->fresh()->attempt_resolution);
        }
        $receipt = $service->defer($f->viewer, $execution, 'Keep the reading at its observed Gate');
        $this->assertSame($execution->id, $receipt->evidence_execution_id);
        $this->assertSame($count, Proposition::count());
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_hard_supersession_invalidates_variant_but_retains_safe_defer_and_observation(): void
    {
        $fixture = new PreparationFixture(true);
        $f = $fixture->context;
        $receipt = $this->instrument($f->phase);
        $attempts = app(AcquisitionAttemptService::class);
        $before = $receipt->fresh()->getAttributes();
        // Isolated structural invalidation fixture, not an authored natural Revision.
        DB::transaction(fn () => $f->node($f->gate, 'canon', 'revise', position: PhaseKind::Constrain));
        $this->assertFalse(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant'));
        $this->assertSame(['acquisition.defer'], array_keys($attempts->schema($f->phase, $receipt->evidenceExecution)['$defs']['actions']));
        $attempts->resolve($f->viewer, $receipt->evidenceExecution, ['action' => 'acquisition.defer', 'execution_id' => $receipt->evidence_execution_id, 'reason' => 'The Gate is now historical']);
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $this->assertDatabaseCount('exploration_events', 0);
    }

    private function at(ParticipantContextFixture $f, PhaseKind $kind, Proposition $gate): Phase
    {
        $f->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id,
            'write_register' => $f->phase->cycle->contextHorizon->domain->registerFor($kind)])->save();
        $f->viewer->forceFill(['current_proposition_id' => $gate->id])->save();

        return $f->phase->fresh();
    }

    private function visit(Phase $phase, Participant $actor): Phase
    {
        $cycle = $phase->cycle->replicate();
        $cycle->forceFill(['completed_at' => null, 'deferred_at' => null, 'participant_id' => $actor->id, 'sequence' => (int) $actor->cycles()->max('sequence') + 1])->save();
        $next = $phase->replicate();
        $next->cycle_id = $cycle->id;
        $next->completed_at = null;
        $next->save();
        $actor->forceFill(['current_proposition_id' => $next->gate_proposition_id])->save();

        return $next->fresh();
    }

    private function instrument(Phase $phase): Receipt
    {
        return app(OperationalInstrumentService::class)->execute($phase, $phase->gate,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/observation/'.str()->ulid()]);
    }
}
