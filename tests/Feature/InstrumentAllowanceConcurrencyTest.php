<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\PotentialProjectionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class InstrumentAllowanceConcurrencyTest extends TestCase
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

    public function test_two_independent_connections_cannot_complete_the_same_cycle_allowance(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires independent PostgreSQL connections and row locks.');
        }
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $gate = DB::transaction(fn () => $f->node($f->seed, 'activity', position: PhaseKind::Distinguish));
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $gate->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $gate->id])->save();
        $input = ['action' => 'instrument.execute', 'proposition_id' => $gate->id,
            'instrument' => ['kind' => 'citation', 'reference' => $f->seed->id]];
        $before = Receipt::count();
        $this->whileWorkerWaits($f->substrate, ['operation' => 'instrument-conflict',
            'participant_id' => $f->viewer->id, 'phase_id' => $f->phase->id, 'contribution' => $input],
            fn () => app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $input));
        $this->assertSame($before + 1, Receipt::count());
        $this->assertSame(1, InstrumentExecution::where('phase_id', $f->phase->id)->where('status', 'completed')->count());
        $this->assertNull($f->phase->fresh()->completed_at);
        $receipt = InstrumentExecution::where('phase_id', $f->phase->id)->sole()->receipt;
        $this->assertSame(1, DB::table('participant_receipt_contexts')->where('receipt_id', $receipt->id)->count());
    }

    public static function generationBoundaries(): array
    {
        return [['receipt-generation-conflict'], ['generation-conflict']];
    }

    #[DataProvider('generationBoundaries')]
    public function test_consumption_of_the_last_shared_receipt_invalidates_waiting_generation(string $operation): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires independent PostgreSQL connections and row locks.');
        }
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        [$gate, $source, $target] = DB::transaction(function () use ($f): array {
            $gate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
            $sibling = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
            $source = $f->node($sibling, 'frontier', position: PhaseKind::Persist);
            $consumer = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'concurrent-consumer']);
            $consumer->save();
            $target = $f->node($source, 'canon', 'reclassify', position: PhaseKind::Constrain, participant: $consumer);
            $this->assertNotSame($f->viewer->id, $target->phase->cycle->participant_id);

            return [$gate, $source, $target];
        });
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $gate->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $gate->id])->save();
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, ['action' => 'instrument.execute',
            'proposition_id' => $gate->id, 'instrument' => ['kind' => 'citation', 'reference' => $f->seed->id]]);
        $receipt = app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->sole();
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $this->assertArrayHasKey('proposition.variant', app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']['$defs']['actions']);
        $before = Proposition::count();
        $this->whileWorkerWaits($f->substrate, ['operation' => $operation,
            'participant_id' => $f->viewer->id, 'phase_id' => $f->phase->id,
            'contribution' => ['action' => 'proposition.variant', 'statement' => 'Validated before consumption'],
            'expected_message' => 'generation opportunity is unavailable'],
            fn () => app(HorizonReceiptService::class)->consume($target->phase, $source, $target, $receipt->id));
        $this->assertSame($before, Proposition::count());
        $this->assertNull($f->phase->fresh()->completed_at);
        $this->assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase));
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id, 'proposition_id' => $target->id]);
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
}
