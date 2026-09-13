<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\CycleEntryService;
use App\Services\InstrumentExecutionService;
use App\Services\ParticipationService;
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

    /** Hold the root, observe a real lock wait, exercise the competing operation, release. */
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
