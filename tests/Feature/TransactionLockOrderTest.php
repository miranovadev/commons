<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\User;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TransactionLockOrderTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_graph_and_detail_finish_while_measurement_update_holds_shared_write_locks(): void
    {
        [$substrate, , $phase] = $this->fixture();
        User::factory()->create();
        $node = $phase->gate;
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
            $this->assertSame(-0.75, $node->fresh()->measurement->correspondence);
            $this->assertSame('negative', $node->fresh()->correspondence_direction);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_standalone_plan_loads_state_after_waiting_for_a_measurement_writer(): void
    {
        [$substrate, $participant, $phase] = $this->fixture();
        $phase->gate->measurement()->update(['calculator_version' => null]);
        $this->whileWorkerWaits($substrate, [
            'operation' => 'plan', 'participant_id' => $participant->id,
        ], function () use ($phase): void {
            app(PropositionMeasurementService::class)->project($phase->gate, -0.75, 0.25, 0.5);
        });
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
