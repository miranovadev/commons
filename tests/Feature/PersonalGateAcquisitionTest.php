<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\CycleEntryService;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceAddress;
use App\Services\EvidenceExecutionService;
use App\Services\OperationalInstrumentService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PersonalAcquisitionService;
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

class PersonalGateAcquisitionTest extends TestCase
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

    public function test_distinguish_is_personal_exact_gate_and_persists_across_cycles(): void
    {
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $phase = $this->at($f, PhaseKind::Distinguish, $f->seed);
        $service = app(PersonalAcquisitionService::class);
        $generation = app(PhaseGenerationService::class);
        $this->assertSame('required', $service->state($phase)['status']);
        $this->assertFalse($generation->epistemicActionAvailable($phase, 'proposition.variant'));
        $this->assertTrue($generation->epistemicActionAvailable($phase, 'proposition.traverse'));
        $receipt = $this->instrument($phase);
        $this->assertSame($f->seed->id, $receipt->evidenceExecution->proposition_id);
        $this->assertSame('completed', $service->state($phase)['status']);
        $this->assertTrue($generation->epistemicActionAvailable($phase, 'proposition.variant'));
        $this->assertDatabaseCount('receipt_consumptions', 0);
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'independent']);
        $other->save();
        $second = $this->visit($phase, $other);
        $this->assertSame('required', $service->state($second)['status']);
        $this->assertFalse($generation->epistemicActionAvailable($second, 'proposition.variant'));
        $this->assertNotSame($receipt->id, $this->instrument($second)->id);
        $return = $this->visit($phase, $f->viewer);
        $this->assertSame('completed', $service->state($return)['status']);
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($return));
        $this->expectException(ConflictHttpException::class);
        $this->instrument($return);
    }

    public function test_new_gates_and_roles_do_not_inherit_completion(): void
    {
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $phase = $this->at($f, PhaseKind::Distinguish, $f->seed);
        $this->instrument($phase);
        $different = DB::transaction(fn () => $f->node($f->gate, 'canon', 'commit', 'committal'));
        $phase->forceFill(['gate_proposition_id' => $different->id])->save();
        $this->assertSame('required', app(PersonalAcquisitionService::class)->state($phase)['status']);
        $phase->forceFill(['gate_proposition_id' => $f->seed->id])->save();
        // Isolated identity probe: another role at the same ID is independent,
        // even when structural gate checks would reject this role/position pair.
        $new = $this->visit($phase, $f->viewer);
        $new->forceFill(['kind' => PhaseKind::Disperse])->save();
        $this->assertSame('required', app(PersonalAcquisitionService::class)->state($new)['status']);
    }

    public function test_disperse_choice_ignores_shared_unconsumed_evidence_and_instrument_excludes_preparation(): void
    {
        $fixture = new PreparationFixture(true);
        $f = $fixture->context;
        $prep = app(PreparationService::class);
        $this->assertTrue($prep->eligible($f->phase));
        $this->assertNotEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
        $this->assertFalse(app(PhaseGenerationService::class)->epistemicActionAvailable($f->phase, 'proposition.variant'));
        $receipt = $this->instrument($f->phase);
        $this->assertFalse($prep->eligible($f->phase));
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
        $fixture->consume($receipt);
        $return = $this->visit($f->phase, $f->viewer);
        $this->assertSame('completed', app(PersonalAcquisitionService::class)->state($return)['status']);
        $this->assertFalse($prep->eligible($return));
    }

    public function test_persist_requires_preparation_and_deferment_does_not_advance_or_generate(): void
    {
        $fixture = new PreparationFixture(true);
        $f = $fixture->context;
        $gate = DB::transaction(fn () => $f->node($f->seed, 'activity', position: PhaseKind::Distinguish));
        $phase = $this->at($f, PhaseKind::Persist, $gate);
        $service = app(PreparationService::class);
        $this->assertTrue($service->eligible($phase));
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($phase));
        $execution = $service->start($f->viewer, $phase, $fixture->input());
        $this->assertSame('pending', app(PersonalAcquisitionService::class)->state($phase)['status']);
        $this->assertSame(['preparation.defer'], array_keys($service->resolutionSchema($f->viewer, $phase, $execution)['$defs']['actions']));
        $delivery = $service->delivery($f->viewer, $execution, 'inline-text');
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $count = Proposition::count();
        $receipt = $service->defer($f->viewer, $execution, 'Reading completed; structural contribution follows.');
        $this->assertSame($receipt->id, $service->defer($f->viewer, $execution, 'Reading completed; structural contribution follows.')->id);
        $this->assertSame($count, Proposition::count());
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertSame('completed', app(PersonalAcquisitionService::class)->state($phase)['status']);
        $this->assertTrue(app(PhaseGenerationService::class)->epistemicActionAvailable($phase, 'proposition.variant'));
        $this->assertSame($gate->id, $receipt->receiptable_id);
        $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($phase));
        $this->assertFalse($service->eligible($this->visit($phase, $f->viewer)));
    }

    public static function failures(): array
    {
        return [['read', 'reader_failure', true], ['delivery', 'delivery_failure', true],
            ['stale', 'stale', false], ['expired', 'expired', false], ['read', 'invalid_parameters', false]];
    }

    #[DataProvider('failures')]
    public function test_meaningful_failures_complete_but_reservations_can_be_retried(string $stage, string $classification, bool $counts): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $this->assertFalse($service->eligible($f->phase));
        $service->fail($f->viewer, $execution, 'reader', '1', $stage, 'Recorded failure', ['classification' => $classification]);
        $this->assertSame($counts ? 'completed' : 'required', app(PersonalAcquisitionService::class)->state($f->phase)['status']);
        $this->assertSame($counts, app(PersonalAcquisitionService::class)->completedInContext($f->phase, new ParticipationContext($f->phase))->has('4:'.$f->gate->id),
            'Exploration uses the same failure completion contract');
        $this->assertSame($counts, $execution->fresh()->receipt !== null);
        if ($counts) {
            $this->assertFalse($execution->fresh()->receipt->metadata['content_verified']);
        } else {
            $retry = $service->start($f->viewer, $f->phase, $fixture->input());
            $this->assertNotSame($retry->id, $execution->id);
        }
    }

    public function test_failed_instrument_is_resolved_non_verifying_and_completion_is_idempotent(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('Unavailable', 503)]);
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $phase = $this->at($f, PhaseKind::Persist, DB::transaction(fn () => $f->node($f->seed, 'activity', position: PhaseKind::Distinguish)));
        $receipt = $this->instrument($phase);
        $this->assertFalse($receipt->metadata['content_verified']);
        $this->assertNull($receipt->signature);
        $this->assertSame('completed', app(PersonalAcquisitionService::class)->state($phase)['status']);
        $this->assertSame($receipt->id, app(EvidenceExecutionService::class)->complete($receipt->evidenceExecution, $phase->gate)->id);
    }

    public function test_real_origin_distinguish_and_persist_reading_use_normal_contributions(): void
    {
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        // Separate fresh substrate: the fixture above supplies no acquisition or route.
        $substrate = $f->substrate->replicate();
        $substrate->slug = 'natural-acquisition';
        $substrate->save();
        $actor = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'natural']);
        $actor->save();
        app(CycleEntryService::class)->enter($actor, ['statement' => 'Investigate evidence together.', 'attestation' => ['direction' => 'support']]);
        $service = app(ParticipationService::class);
        $state = $service->stateFor($actor);
        $gate = $state['plan']['gate_id'];
        $this->assertArrayNotHasKey('proposition.variant', $state['plan']['schema']['$defs']['actions']);
        $state = $service->contribute($actor, $state['plan']['phase']['id'], ['action' => 'instrument.execute',
            'proposition_id' => $gate, 'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/natural']]);
        $this->assertSame(1, $state['plan']['phase']['number']);
        $this->assertSame('completed', $state['plan']['acquisition']['status']);
        $state = $service->contribute($actor, $state['plan']['phase']['id'], ['action' => 'proposition.variant', 'statement' => 'A distinction from the recorded source.']);
        $this->assertSame(2, $state['plan']['phase']['number']);
        $this->assertArrayHasKey('preparation.start', $state['plan']['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('instrument.execute', $state['plan']['schema']['$defs']['actions']);
        $option = $state['plan']['preparation_options'][0];
        $state = $service->contribute($actor, $state['plan']['phase']['id'], ['action' => 'preparation.start',
            'proposition_id' => $state['plan']['gate_id'], 'purpose' => 'Examine the distinction', 'artifacts' => [[
                'receipt_id' => $option['receipt_id'], 'artifact_id' => $option['artifact_id'], 'read_type' => $option['capabilities'][0]['read_type']]]]);
        $execution = EvidenceExecution::findOrFail($state['plan']['preparation']['execution_id']);
        $prep = app(PreparationService::class);
        $delivery = $prep->delivery($actor, $execution, 'inline-text');
        $prep->acknowledge($actor, $execution, $delivery['presentation_token']);
        $state = $service->contribute($actor, $execution->phase_id, ['action' => 'preparation.defer', 'execution_id' => $execution->id, 'reason' => 'Examined; continue the distinction.']);
        $this->assertSame(2, $state['plan']['phase']['number']);
        $state = $service->contribute($actor, $state['plan']['phase']['id'], ['action' => 'proposition.variant', 'statement' => 'A developed distinction.']);
        $this->assertSame(3, $state['plan']['phase']['number']);
        $this->assertDatabaseCount('receipt_consumptions', 0);
        $this->assertSame(['instrument', 'prepare'], EvidenceExecution::whereHas('cycle', fn ($q) => $q->where('participant_id', $actor->id))->orderBy('id')->pluck('type')->all());
        $human = $service->workspaceFor($actor);
        $this->assertSame($state['plan']['schema'], $human['plan']['schema']);
    }

    public function test_superseded_open_phase_cannot_acquire_while_a_later_visit_is_reserved(): void
    {
        $fixture = new PreparationFixture(true);
        $f = $fixture->context;
        $old = $f->phase;
        $current = $this->visit($old, $f->viewer);
        $preparation = app(PreparationService::class)->start($f->viewer, $current, $fixture->input());
        $this->assertSame('pending', app(PersonalAcquisitionService::class)->state($current)['status']);
        $this->assertSame('required', app(PersonalAcquisitionService::class)->state($old)['status']);
        try {
            $this->instrument($old);
            $this->fail('An obsolete open Phase must not bypass the current reservation.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('no longer active', $exception->getMessage());
        }
        $this->assertSame('pending', $preparation->fresh()->status);
        $this->assertSame(1, EvidenceExecution::whereIn('phase_id', [$old->id, $current->id])->count());
    }

    #[Group('query')]
    public function test_abandoned_history_lookup_is_bounded_and_downgrade_preserves_it(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $phase = $f->phase->load('cycle');
        $preparation = app(PreparationService::class);
        $acquisition = app(PersonalAcquisitionService::class);
        $counts = [];
        for ($i = 0; $i < 12; $i++) {
            $execution = $preparation->start($f->viewer, $phase, $fixture->input());
            $preparation->fail($f->viewer, $execution, 'reader', '1', 'stale', 'Reservation abandoned', ['classification' => 'stale']);
            if (in_array($i, [0, 11], true)) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $this->assertSame('required', $acquisition->state($phase)['status']);
                $queries = DB::getQueryLog();
                DB::disableQueryLog();
                $counts[] = count($queries);
                foreach ($queries as $query) {
                    $this->assertStringContainsString('limit 1', strtolower($query['query']));
                }
            }
        }
        $this->assertSame([2, 2], $counts);
        $context = new ParticipationContext($phase);
        $acquisition->state($phase, $context);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame('required', $acquisition->state($phase, $context)['status']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(12, EvidenceExecution::where('type', 'prepare')->count());
        $migration = require database_path('migrations/2026_10_29_000000_scope_acquisition_to_personal_gates.php');
        try {
            $migration->down();
            $this->fail('Downgrade must not discard acquisition history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('without losing acquisition history', $exception->getMessage());
        }
        $this->assertSame(12, EvidenceExecution::where('type', 'prepare')->count());
        $this->assertSame('pending', $preparation->start($f->viewer, $phase, $fixture->input())->status);
        fwrite(STDERR, "\nACQUISITION history queries (1 -> 12 abandoned attempts): 2 -> 2; memoized: 0\n");
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
        $cycle->forceFill(['participant_id' => $actor->id, 'sequence' => (int) $actor->cycles()->max('sequence') + 1])->save();
        $next = $phase->replicate();
        $next->cycle_id = $cycle->id;
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
