<?php

namespace Tests\Feature;

use App\Models\EvidenceExecution;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\PreparationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Fixtures\PreparationFixture;
use Tests\TestCase;

class PreparationConcurrencyTest extends TestCase
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

    public function test_competing_starts_reserve_only_one_cycle_allowance(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires independent PostgreSQL row locks.');
        }
        Storage::fake('artifacts');
        $fixture = DB::transaction(fn () => new PreparationFixture);
        $f = $fixture->context;
        app(PreparationService::class)->options($f->phase);
        $this->whileWorkerWaits($f->substrate, ['operation' => 'preparation-start-conflict',
            'participant_id' => $f->viewer->id, 'phase_id' => $f->phase->id, 'preparation' => $fixture->input()],
            fn () => app(PreparationService::class)->start($f->viewer, $f->phase, $fixture->input()));
        $this->assertSame(1, EvidenceExecution::where('type', 'prepare')->count());
        $this->assertSame(0, Receipt::where('type', 'preparation')->count());
    }

    public function test_competing_contributed_and_deferred_outcomes_cannot_both_complete(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires independent PostgreSQL row locks.');
        }
        Storage::fake('artifacts');
        $fixture = DB::transaction(fn () => new PreparationFixture);
        $f = $fixture->context;
        PreparationFixture::registerReader();
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->present($f->viewer, $execution, $fixture->presentation());
        $this->whileWorkerWaits($f->substrate, ['operation' => 'preparation-defer-conflict',
            'participant_id' => $f->viewer->id, 'execution_id' => $execution->id],
            fn () => $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'Concurrent contribution wins.']));
        $this->assertSame(1, Receipt::where('type', 'preparation')->count());
        $this->assertSame('contributed', $execution->fresh()->outcome);
        $this->assertNotNull($execution->fresh()->resulting_proposition_id);
    }

    private function whileWorkerWaits(Substrate $substrate, array $input, callable $competing): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-commons-worker.php')], base_path(), null,
            json_encode([...$input, 'artifact_root' => Storage::disk('artifacts')->path(''), 'database' => config('database.connections.'.DB::getDefaultConnection())], JSON_THROW_ON_ERROR), 15);
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
}
