<?php

namespace Tests\Feature;

use App\Contracts\ParticipantInference;
use App\Contracts\WebSearchProvider;
use App\Domain\Evidence\SearchObservation;
use App\Domain\Evidence\VerifiedHyperlink;
use App\Domain\Observables\ClosureValues;
use App\Enums\PhaseKind;
use App\Jobs\WakeParticipant;
use App\Models\Connection;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptContextEvent;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Models\User;
use App\Services\ClosureGateService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\ParticipantActivityService;
use App\Services\ParticipantConnectionTriggerService;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TransactionLockOrderTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        // Each test starts with migrate:fresh and independent connections.
        // Rolling fixtures back through old schemas would correctly refuse
        // persistent Receipt loads and histories with repeated Phase kinds.
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires independent PostgreSQL backends and row locks.');
        }
        DB::statement("SET lock_timeout = '5s'");
        DB::statement("SET statement_timeout = '10s'");
    }

    public function test_cycle_entry_can_lock_the_participant_while_a_contribution_waits_for_the_substrate(): void
    {
        [$substrate, $participant, $phase] = $this->fixture();
        $this->whileWorkerWaits($substrate, [
            'operation' => 'contribute', 'participant_id' => $participant->id, 'phase_id' => $phase->id,
        ], function () use ($participant): void {
            // The active Cycle is deliberately still open: entry must reach its
            // normal conflict check, rather than deadlock on the Participant.
            try {
                app(CycleEntryService::class)->enter($participant);
                $this->fail('Expected the existing active Cycle to reject entry.');
            } catch (ConflictHttpException $exception) {
                $this->assertSame('The Participant already has an active Cycle.', $exception->getMessage());
            }
        });
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->assertSame(1, $participant->cycles()->whereNull('completed_at')->count());
    }

    public function test_two_participants_can_contribute_to_the_same_substrate_without_a_lock_cycle(): void
    {
        [$substrate, $first, $firstPhase] = $this->fixture();
        $second = new Participant;
        $second->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'second'])->save();
        app(CycleEntryService::class)->enter($second);
        $secondPhase = $this->activePhase($second);
        $this->whileWorkerWaits($substrate, [
            'operation' => 'contribute', 'participant_id' => $first->id, 'phase_id' => $firstPhase->id,
        ], function () use ($second, $secondPhase): void {
            app(ParticipationService::class)->contribute($second, $secondPhase->id, [
                'action' => 'proposition.variant', 'statement' => 'Another distinction',
            ]);
        });
        foreach ([$firstPhase, $secondPhase] as $phase) {
            $this->assertNotNull($phase->fresh()->completed_at);
            $this->assertSame(1, Phase::where('cycle_id', $phase->cycle_id)->whereNull('completed_at')->count());
        }
        $sequences = DB::table('substrate_ledger_entries')->where('substrate_id', $substrate->id)
            ->orderBy('sequence')->pluck('sequence')->all();
        $this->assertSame(range(1, count($sequences)), $sequences);
        $this->assertSame(2, Proposition::where('tier', 'expressive')->count());
    }

    public function test_standalone_instrument_completion_does_not_hold_the_execution_while_waiting_for_the_root(): void
    {
        [$substrate, , $phase] = $this->fixture();
        $execution = app(InstrumentExecutionService::class)->start($phase, $phase->gate, 'citation', $phase->gate_proposition_id);
        $this->whileWorkerWaits($substrate, [
            'operation' => 'complete', 'execution_id' => $execution->id, 'proposition_id' => $phase->gate_proposition_id,
        ], function () use ($execution): void {
            $this->assertNotNull($execution->newQuery()->lockForUpdate()->find($execution->id));
        });
        $this->assertSame('completed', $execution->fresh()->status);
        $this->assertSame(1, $execution->receipt()->count());
    }

    public function test_standalone_measurement_projection_waits_before_locking_the_proposition(): void
    {
        [$substrate, , $phase] = $this->fixture();
        $this->whileWorkerWaits($substrate, [
            'operation' => 'project', 'proposition_id' => $phase->gate_proposition_id,
        ], function () use ($phase): void {
            $this->assertNotNull(Proposition::query()->lockForUpdate()->find($phase->gate_proposition_id));
        });
    }

    public static function projectionKinds(): array
    {
        return ['mutable expressive' => [true], 'frozen committal' => [false]];
    }

    #[DataProvider('projectionKinds')]
    public function test_graph_and_detail_finish_while_measurement_update_holds_shared_write_locks(bool $expressive): void
    {
        [$substrate, $participant, $phase] = $this->fixture();
        User::factory()->create();
        $node = $phase->gate;
        if ($expressive) {
            app(ParticipationService::class)->contribute($participant, $phase->id,
                ['action' => 'proposition.variant', 'statement' => 'Mutable projection subject']);
            $node = $participant->fresh()->position;
        }
        $expected = $expressive ? -0.75 : $node->commitmentSnapshot->correspondence;
        $node->measurement()->delete();
        DB::beginTransaction();
        try {
            app(PropositionMeasurementService::class)->project($node, -0.75, 0.25, 0.5);
            foreach (["/api/substrates/{$substrate->slug}/propositions", "/api/substrates/{$substrate->slug}/propositions/{$node->id}"] as $url) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-commons-worker.php')], base_path(), null,
                    json_encode(['operation' => 'read', 'url' => $url,
                        'database' => config('database.connections.'.DB::getDefaultConnection())], JSON_THROW_ON_ERROR), 5);
                // Writer remains uncommitted throughout the independent HTTP request.
                // The old GET repair would wait on our root/node locks and time out.
                $process->run();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertStringContainsString('"status":"missing"', $process->getOutput());
                $this->assertStringContainsString('completed', $process->getOutput());
            }
            DB::commit();
            $this->assertSame($expected, $node->fresh()->measurement->correspondence);
            $this->assertSame('negative', $node->fresh()->correspondence_direction);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_plan_state_workspace_and_active_prepare_complete_concurrently_while_a_writer_holds_the_root(): void
    {
        [$substrate, $participant, $phase] = $this->fixture();
        $phase->gate->measurement()->delete(); // The read-only fallback must not repair under a lock.
        $processes = [];
        DB::beginTransaction();
        try {
            Substrate::query()->lockForUpdate()->findOrFail($substrate->id);
            Participant::query()->lockForUpdate()->findOrFail($participant->id);
            Phase::query()->lockForUpdate()->findOrFail($phase->id);
            foreach (['plan', 'plan', 'state', 'workspace', 'prepare'] as $operation) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-commons-worker.php')], base_path(), null,
                    json_encode(['operation' => $operation, 'participant_id' => $participant->id,
                        'database' => config('database.connections.'.DB::getDefaultConnection())], JSON_THROW_ON_ERROR), 5);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $process->wait(); // All reads finish before the writer commits.
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertStringContainsString('completed', $process->getOutput());
            }
            $this->assertNull($phase->gate->fresh()->measurement);
        } finally {
            DB::rollBack();
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }
    }

    /** Hold the root, observe a real lock wait, exercise the competing operation, release. */
    public function test_waiting_generation_rechecks_personal_history_after_the_first_writer_commits(): void
    {
        [$substrate, $participant, $phase] = $this->fixture();
        $input = ['action' => 'proposition.variant', 'statement' => 'One distinction'];
        $this->whileWorkerWaits($substrate, [
            'operation' => 'dispatch-rejected', 'participant_id' => $participant->id, 'phase_id' => $phase->id,
            'contribution' => $input, 'expected_message' => 'generation opportunity is unavailable',
        ], function () use ($participant, $phase, $input): void {
            app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
        });
        $this->assertSame(1, Proposition::where('tier', 'expressive')->count());
    }

    public function test_waiting_reconciliation_rechecks_the_directed_pair_across_participants(): void
    {
        [$substrate, $first, $phase] = $this->fixture();
        $seed = $phase->gate;
        $node = function (string $register, Proposition $parent) use ($phase): Proposition {
            $node = new Proposition;
            $node->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => 'expressive', 'statement' => 'Shared structure'])->save();
            app(LedgerService::class)->record($node);
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id,
                'target_proposition_id' => $node->id, 'type' => 'variant'])->save();
            app(LedgerService::class)->record($edge);

            return $node;
        };
        $read = $node('concord', $seed);
        $a = $node('signal', $read);
        $b = $node('signal', $read);
        $second = new Participant;
        $second->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'second'])->save();
        $phases = [];
        foreach ([$first, $second] as $participant) {
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
                'context_horizon_id' => $phase->cycle->context_horizon_id])->save();
            $disperse = new Phase;
            $disperse->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Disperse, 'write_register' => 'signal',
                'gate_proposition_id' => $read->id, 'started_at' => now()])->save();
            $phases[] = app(PhaseProgressionService::class)->advance($disperse, $a);
        }
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $b->id];
        $before = Proposition::count();
        $this->whileWorkerWaits($substrate, [
            'operation' => 'dispatch-rejected', 'participant_id' => $second->id, 'phase_id' => $phases[1]->id,
            'contribution' => $input, 'expected_message' => 'admissible Gate counterpart',
        ], function () use ($first, $phases, $input): void {
            app(ContributionDispatcher::class)->dispatch($first, $phases[0], $input);
        });
        $this->assertSame($before + 1, Proposition::count());
        $this->assertSame(1, $a->children()->wherePivot('type', 'reconcile')->count());
    }

    public function test_waiting_reclassification_reuses_the_first_participants_result(): void
    {
        [$substrate, $first, $initial] = $this->fixture();
        app(ParticipationService::class)->contribute($first, $initial->id, ['action' => 'proposition.variant', 'statement' => 'Shared classification source']);
        $source = $first->fresh()->position;
        $this->classificationEvidenceFixture($source);
        $firstPhase = $first->cycles()->whereNull('completed_at')->sole()->currentPhase;
        $second = new Participant;
        $second->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'second-classifier'])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $second->id, 'sequence' => 1])->save();
        $phase = $firstPhase->replicate();
        $phase->cycle_id = $cycle->id;
        $phase->save();
        $input = ['action' => 'proposition.reclassify'];
        $before = Proposition::count();
        $this->whileWorkerWaits($substrate, [
            'operation' => 'dispatch-rejected', 'participant_id' => $second->id, 'phase_id' => $phase->id,
            'contribution' => $input, 'expected_message' => 'generation opportunity is unavailable',
        ], function () use ($first, $firstPhase, $input): void {
            app(ContributionDispatcher::class)->dispatch($first, $firstPhase, $input);
        });
        $this->assertSame($before + 1, Proposition::count());
        $this->assertSame(1, DB::table('reclassification_results')->where('source_proposition_id', $source->id)->count());
    }

    public function test_waiting_commit_rechecks_global_source_consumption(): void
    {
        [$substrate, $first, $initial] = $this->fixture();
        $source = new Proposition;
        $source->forceFill(['phase_id' => $initial->id, 'register' => 'concord', 'tier' => 'expressive', 'statement' => 'Shared commitment source'])->save();
        app(LedgerService::class)->record($source);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $initial->id, 'source_proposition_id' => $initial->gate_proposition_id,
            'target_proposition_id' => $source->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        $this->classificationEvidenceFixture($source);
        $closure = [...(new ClosureValues(1, 1))->resolve(), 'history_proposition_id' => null, 'history_source' => 'negative-default'];
        $this->mock(ClosureGateService::class, fn ($mock) => $mock->shouldReceive('preview', 'forCommitment')->andReturn($closure));
        $initial->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'concord', 'gate_proposition_id' => $source->id])->save();
        $second = new Participant;
        $second->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'second-committer'])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $second->id, 'sequence' => 1])->save();
        $phase = $initial->replicate();
        $phase->cycle_id = $cycle->id;
        $phase->save();
        $input = ['action' => 'proposition.commit', 'proposition_id' => $source->id];
        $before = Proposition::count();
        $this->whileWorkerWaits($substrate, [
            'operation' => 'dispatch-rejected', 'participant_id' => $second->id, 'phase_id' => $phase->id,
            'contribution' => $input, 'expected_message' => 'active Closure candidate',
        ], function () use ($first, $initial, $input): void {
            app(ContributionDispatcher::class)->dispatch($first, $initial->fresh(), $input);
        });
        $this->assertSame($before + 1, Proposition::count());
        $this->assertSame(1, DB::table('commit_results')->where('source_proposition_id', $source->id)->count());
    }

    public function test_another_participant_can_commit_an_action_during_inference_between_steps_of_one_wake(): void
    {
        config()->set('participation.max_actions_per_wake', 2);
        [$substrate, $first, $phase] = $this->fixture();
        $second = new Participant;
        $second->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'between-actions'])->save();
        app(CycleEntryService::class)->enter($second);
        $secondPhase = $this->activePhase($second);
        $connection = new Connection;
        $connection->forceFill(['provider' => 'openai', 'name' => 'Wake concurrency',
            'configuration' => ['model' => 'test'], 'credentials' => ['api_key' => 'test']])->save();
        $link = new ParticipantConnection;
        $link->forceFill(['participant_id' => $first->id, 'connection_id' => $connection->id,
            'is_enabled' => true, 'configuration' => []])->save();
        $calls = 0;
        $inference = \Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->twice()->andReturnUsing(function ($connection, $state) use (&$calls, $phase, $second, $secondPhase): array {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame($phase->cycle_id, $state['cycle']['id']);
            if (++$calls === 1) {
                return ['action' => 'proposition.variant', 'statement' => 'First wake action'];
            }
            $this->assertSame('persist', $state['plan']['phase']['kind']);
            $this->assertNotNull($phase->fresh()->completed_at);
            $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-commons-worker.php')], base_path(), null,
                json_encode(['operation' => 'contribute', 'participant_id' => $second->id, 'phase_id' => $secondPhase->id,
                    'database' => config('database.connections.'.DB::getDefaultConnection())], JSON_THROW_ON_ERROR), 15);
            $process->mustRun(); // Would time out if this wake retained a database/root lock.
            $this->assertStringContainsString('completed', $process->getOutput());
            $this->assertNotNull($secondPhase->fresh()->completed_at);

            $receiptIds = $state['plan']['schema']['$defs']['actions']['receipt.read']['properties']['receipt_id']['enum'];
            $this->assertNotEmpty($receiptIds);

            return ['action' => 'receipt.read', 'receipt_id' => $receiptIds[0]];
        });
        (new WakeParticipant($link->id))->handle($inference, app(ParticipationService::class), app(ParticipantConnectionTriggerService::class), app(ParticipantActivityService::class));
        $this->assertSame(2, $calls);
        $this->assertNull($phase->cycle->fresh()->completed_at);
        $this->assertNull($phase->cycle->fresh()->rested_at);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_fresh_admissibility_rejects_a_previously_valid_schema_as_a_retryable_conflict(): void
    {
        [, $first, $initial] = $this->fixture();
        app(ParticipationService::class)->contribute($first, $initial->id, ['action' => 'proposition.variant', 'statement' => 'Source']);
        $source = $first->fresh()->position;
        $this->classificationEvidenceFixture($source);
        $phase = $first->cycles()->whereNull('completed_at')->sole()->currentPhase;
        $state = app(ParticipationService::class)->stateFor($first);
        $input = ['action' => 'proposition.reclassify'];
        app(ContributionDispatcher::class)->dispatch($first, $phase, $input);
        try {
            app(ParticipationService::class)->contribute($first, $phase->id, $input, $state['plan']['schema']);
            $this->fail('A stale generation action was applied.');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertNull($phase->fresh()->completed_at);
            $this->assertSame(1, DB::table('reclassification_results')->where('source_proposition_id', $source->id)->count());
        }
    }

    public static function externalInstruments(): array
    {
        return [['hyperlink'], ['web_search']];
    }

    #[DataProvider('externalInstruments')]
    public function test_external_instrument_observation_allows_another_writer_and_discards_a_stale_result(string $kind): void
    {
        [$substrate, $first, $initial] = $this->fixture();
        $target = app(ContributionDispatcher::class)->dispatch($first, $initial,
            ['action' => 'proposition.variant', 'statement' => 'Instrument subject']);
        $this->receiptFixture($target); // Allow the competing attestation to advance this Phase.
        app(\App\Services\PotentialProjectionService::class)->refresh($substrate->id);
        $second = new Participant;
        $second->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'during-observation'])->save();
        app(CycleEntryService::class)->enter($second);
        $secondPhase = $this->activePhase($second);
        $input = ['action' => 'instrument.execute', 'proposition_id' => $target->id,
            'instrument' => $kind === 'hyperlink' ? ['kind' => $kind, 'reference' => 'https://example.test/outside-lock']
                : ['kind' => $kind, 'query' => 'Observe outside the write lock']];
        $before = InstrumentExecution::count();
        $observed = false;
        $this->mock($kind === 'hyperlink' ? HyperlinkVerifier::class : WebSearchProvider::class, function ($mock) use ($kind, &$observed, $second, $secondPhase, $first, $initial, $target): void {
            $mock->shouldReceive($kind === 'hyperlink' ? 'verify' : 'search')->once()->andReturnUsing(function ($reference) use ($kind, &$observed, $second, $secondPhase, $first, $initial, $target) {
                $observed = DB::transactionLevel() === 0;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-commons-worker.php')], base_path(), null,
                    json_encode(['operation' => 'contribute', 'participant_id' => $second->id, 'phase_id' => $secondPhase->id,
                        'database' => config('database.connections.'.DB::getDefaultConnection())], JSON_THROW_ON_ERROR), 5);
                $process->mustRun();
                // Another actor also advances this Participant while observation is in flight.
                app(ParticipationService::class)->contribute($first, $initial->id,
                    ['action' => 'attestation.create', 'proposition_id' => $target->id, 'direction' => 'support']);

                return $kind === 'hyperlink' ? new VerifiedHyperlink($reference, hash('sha256', $reference), CarbonImmutable::now())
                    : new SearchObservation($reference, CarbonImmutable::now(), [], 'fixture');
            });
        });
        try {
            app(ParticipationService::class)->contribute($first, $initial->id, $input);
            $this->fail('A stale instrument result advanced the old Phase.');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertTrue($observed, 'External observation must run without a transaction.');
        $this->assertNotNull($secondPhase->fresh()->completed_at);
        $this->assertSame($before, InstrumentExecution::count(), 'Discarded observation persists no execution or Receipt.');
    }

    public function test_competing_receipt_reads_keep_one_load_and_one_event(): void
    {
        [$substrate, $participant, $phase] = $this->fixture();
        $receiptId = app(ParticipationService::class)->stateFor($participant)['plan']['schema']['$defs']['actions']['receipt.read']['properties']['receipt_id']['enum'][0];
        $this->whileWorkerWaits($substrate, ['operation' => 'receipt-read', 'participant_id' => $participant->id,
            'phase_id' => $phase->id, 'receipt_id' => $receiptId], function () use ($participant, $phase, $receiptId): void {
                app(ReceiptContextService::class)->read($participant, $phase, $receiptId);
            });
        $load = ParticipantReceiptContext::where('participant_id', $participant->id)->where('receipt_id', $receiptId)->sole();
        $this->assertSame(1, ReceiptContextEvent::where('participant_receipt_context_id', $load->id)->count());
    }

    public function test_competing_boundary_projection_reuses_the_same_child_horizon(): void
    {
        [$substrate, , $phase] = $this->fixture();
        $snapshot = $phase->gate->commitmentSnapshot;
        $before = ContextHorizon::where('substrate_id', $substrate->id)->count();
        $this->whileWorkerWaits($substrate, ['operation' => 'horizon', 'proposition_id' => $snapshot->proposition_id], function () use ($snapshot): void {
            app(ContextHorizonProjector::class)->apply($snapshot);
        });
        $this->assertSame($before, ContextHorizon::where('substrate_id', $substrate->id)->count());
        $this->assertSame(1, ContextHorizon::where('opened_by_proposition_id', $snapshot->proposition_id)->count());
    }

    public function test_concurrent_rests_record_one_tragedy_and_leave_both_participants_with_valid_entry(): void
    {
        [$substrate, $first, $phase] = $this->fixture();
        app(ParticipationService::class)->contribute($first, $phase->id, ['action' => 'proposition.variant', 'statement' => 'A finite attempt']);
        $root = $first->fresh()->position;
        $firstRest = $this->tragicPhase($first, $root);
        $second = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'second-rest']);
        $second->save();
        $secondRest = $this->tragicPhase($second, $root);
        app(PropositionMeasurementService::class)->project($root, 1.0, 1.0, 1.0);
        app(\App\Services\PotentialProjectionService::class)->rebuild($substrate->id, false);
        $this->whileWorkerWaits($substrate, ['operation' => 'rest', 'participant_id' => $second->id, 'phase_id' => $secondRest->id],
            function () use ($first, $firstRest): void {
                app(ParticipationService::class)->contribute($first, $firstRest->id, ['action' => 'participant.rest']);
            });
        $this->assertSame(1, Tragedy::count());
        $this->assertSame(1, DB::table('origin_branches')->whereNotNull('pruned_at')->count());
        foreach ([$first, $second] as $participant) {
            $this->assertSame(1, $participant->cycles()->whereNull('completed_at')->count());
            $this->assertSame('ready', app(ParticipationService::class)->stateFor($participant)['status']);
        }
    }

    private function tragicPhase(Participant $participant, Proposition $root): Phase
    {
        foreach ($participant->cycles()->whereNull('completed_at')->get() as $cycle) {
            $cycle->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
            $cycle->forceFill(['completed_at' => now()])->save();
        }
        $cycle = (new Cycle)->forceFill(['participant_id' => $participant->id,
            'context_horizon_id' => $root->phase->cycle->context_horizon_id,
            'sequence' => (int) $participant->cycles()->max('sequence') + 1]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
            'write_register' => $cycle->contextHorizon->domain->registerFor(PhaseKind::Order),
            'gate_proposition_id' => $root->id, 'started_at' => now()]);
        $phase->save();
        $participant->forceFill(['current_proposition_id' => $root->id])->save();

        return $phase;
    }

    private function whileWorkerWaits(Substrate $substrate, array $input, callable $competing): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-commons-worker.php')], base_path(), null,
            json_encode([...$input, 'database' => config('database.connections.'.DB::getDefaultConnection())], JSON_THROW_ON_ERROR), 15);
        DB::beginTransaction();
        try {
            Substrate::query()->lockForUpdate()->findOrFail($substrate->id);
            $process->start();
            $deadline = microtime(true) + 5;
            $waiting = null;
            do {
                $pid = (int) strtok($process->getOutput(), "\n");
                if ($pid > 0) {
                    DB::select('select pg_stat_clear_snapshot()');
                    $waiting = DB::selectOne("select query from pg_stat_activity where pid = ? and wait_event_type = 'Lock'", [$pid]);
                }
                if ($waiting !== null || ! $process->isRunning()) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);

            $this->assertNotNull($waiting, 'Worker did not reach a lock wait: '.$process->getOutput().$process->getErrorOutput());
            $this->assertStringContainsString('"substrates"', $waiting->query);
            $competing();
            DB::commit();
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('completed', $process->getOutput());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process->isRunning()) {
                $process->stop(0);
            }
        }
    }

    private function fixture(): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'lock-order', 'title' => 'Lock order', 'configuration' => [], 'visibility' => 'public'])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'first'])->save();
        app(CycleEntryService::class)->enter($participant, [
            'statement' => 'A grounded origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/lock-order'],
        ]);

        return [$substrate, $participant, $this->activePhase($participant)];
    }

    private function activePhase(Participant $participant): Phase
    {
        $cycle = Cycle::where('participant_id', $participant->id)->whereNull('completed_at')->sole();

        return Phase::where('cycle_id', $cycle->id)->whereNull('completed_at')->where('kind', PhaseKind::Distinguish)->sole();
    }
}
