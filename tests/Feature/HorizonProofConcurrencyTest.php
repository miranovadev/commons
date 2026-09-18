<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\ContributionDispatcher;
use App\Services\ExplorationEpochService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class HorizonProofConcurrencyTest extends TestCase
{
    use DatabaseMigrations, ExhaustionFixture;

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
        $this->initializeExhaustionFixture();
    }

    private function observeAndAttest(Proposition $source): Receipt
    {
        $instruments = app(InstrumentExecutionService::class);
        $receipt = $instruments->complete($instruments->start($source->phase, $source, 'citation', $this->origin->id), $source);
        $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);

        return $receipt;
    }

    public function test_two_sources_competing_for_one_receipt_produce_one_classification_and_one_attachment(): void
    {
        $first = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $receipt = $this->observeAndAttest($first);
        $second = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        // A spare Receipt keeps the structural action available to the loser;
        // its chosen consumed Receipt must still fail atomically under the lock.
        $spare = $this->observeAndAttest($second);
        $winnerPhase = $this->phase($first, PhaseKind::Constrain);
        $loserPhase = $this->phase($second, PhaseKind::Constrain);
        $before = Proposition::count();
        $this->whileWorkerWaits($this->substrate, [
            'operation' => 'dispatch-rejected', 'participant_id' => $loserPhase->cycle->participant_id,
            'phase_id' => $loserPhase->id, 'expected_message' => 'unconsumed Receipt',
            'contribution' => ['action' => 'proposition.reclassify', 'proposition_id' => $second->id, 'receipt_id' => $receipt->id],
        ], function () use ($winnerPhase, $first, $receipt): void {
            app(ContributionDispatcher::class)->dispatch($winnerPhase->cycle->participant, $winnerPhase,
                ['action' => 'proposition.reclassify', 'proposition_id' => $first->id, 'receipt_id' => $receipt->id]);
        });
        $this->assertSame($before + 1, Proposition::count());
        $this->assertDatabaseCount('receipt_consumptions', 1);
        $this->assertDatabaseCount('proposition_receipt_links', 1);
        $this->assertDatabaseCount('reclassification_results', 1);
        $this->assertDatabaseMissing('reclassification_results', ['source_proposition_id' => $second->id]);
        $this->assertNull($spare->fresh()->consumption);
        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }

    public function test_competing_variants_recheck_new_unobserved_opportunity_after_the_winner_commits(): void
    {
        $firstPhase = $this->phase($this->origin, PhaseKind::Distinguish);
        $previous = $this->node($firstPhase, $this->origin);
        $this->observeAndAttest($previous);
        $secondPhase = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->measure($previous, 0);
        DB::table('continuation_failures')->insert(['proposition_id' => $previous->id,
            'context_horizon_id' => $firstPhase->cycle->context_horizon_id, 'structural_position' => 1,
            'exploration_epoch' => 0, 'phase_id' => $firstPhase->id, 'failed_at' => now()]);
        $before = Proposition::count();
        $input = ['action' => 'proposition.variant', 'statement' => 'Next opportunity'];
        $this->whileWorkerWaits($this->substrate, [
            'operation' => 'dispatch-rejected', 'participant_id' => $secondPhase->cycle->participant_id,
            'phase_id' => $secondPhase->id, 'expected_message' => 'generation opportunity is unavailable',
            'contribution' => $input,
        ], function () use ($firstPhase, $input): void {
            app(ContributionDispatcher::class)->dispatch($firstPhase->cycle->participant, $firstPhase, $input);
        });
        $this->assertSame($before + 1, Proposition::count());
        $this->assertSame(1, app(ExplorationEpochService::class)->current($firstPhase->cycle->context_horizon_id, PhaseKind::Distinguish));
        $this->assertDatabaseCount('continuation_failures', 1);
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
