<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

#[Group('load')]
class BiasReceiptConcurrencyTest extends TestCase
{
    use CreatesHomologousPositions, DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_two_foreign_sources_compete_for_one_current_region_tragedy_receipt(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires independent PostgreSQL connections and row locks.');
        }
        $this->createHomologyActor();
        $origin = $this->committedCoordinate(null, 'emergence');
        $a = $this->committedCoordinate($origin, 'settlement');
        $b = $this->committedCoordinate($origin, 'settlement');
        $c = $this->committedCoordinate($origin, 'settlement');
        $gates = [];
        foreach ([$a, $b, $c] as $entry) {
            $node = $this->position($entry, PhaseKind::Mediate);
            $edge = (new Supersession)->forceFill(['phase_id' => $node->phase_id,
                'source_proposition_id' => $entry->id, 'target_proposition_id' => $node->id, 'type' => 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
            $vote = (new Attestation)->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'support']);
            $vote->save();
            app(LedgerService::class)->record($vote);
            $gates[] = $node;
        }
        $first = $this->phase($a, $gates[0], PhaseKind::Bias);
        $second = $this->phase($a, $gates[0], PhaseKind::Bias);
        $tragedy = $this->boundaryTragedyFixture($gates[0]);
        foreach ($gates as $node) {
            app(PropositionMeasurementService::class)->project($node, 0, -.5, 0);
        }
        $this->resolveExpressiveFieldFixture(.8);
        $input = ['action' => 'proposition.reclassify', 'proposition_id' => $gates[1]->id, 'receipt_id' => $tragedy->receipt_id];
        $before = Proposition::count();
        $this->whileWorkerWaits($this->actor->substrate, ['operation' => 'dispatch-rejected',
            'participant_id' => $this->actor->id, 'phase_id' => $second->id,
            'contribution' => [...$input, 'proposition_id' => $gates[2]->id], 'expected_message' => 'generation opportunity is unavailable'],
            fn () => app(ContributionDispatcher::class)->dispatch($this->actor, $first, $input));
        $this->assertSame($before + 1, Proposition::count());
        $this->assertDatabaseCount('receipt_consumptions', 1);
        $this->assertDatabaseCount('reclassification_results', 1);
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id, 'source_proposition_id' => $gates[1]->id]);
        $this->assertDatabaseMissing('reclassification_results', ['source_proposition_id' => $gates[2]->id]);
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
