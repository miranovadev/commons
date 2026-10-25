<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\ClosureGateService;
use App\Services\ContributionDispatcher;
use App\Services\EvidenceExecutionService;
use App\Services\LedgerService;
use App\Services\PhaseGenerationService;
use App\Services\ReceiptContextService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

#[Group('load')]
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
        $instruments = app(EvidenceExecutionService::class);
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
        $secondPhase = $this->phase($this->origin, PhaseKind::Distinguish);
        foreach ([$firstPhase, $secondPhase] as $phase) {
            $this->assertTrue(app(PhaseGenerationService::class)->available($phase, 'proposition.variant'));
        }
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
        $this->assertFalse(app(PhaseGenerationService::class)->available($secondPhase, 'proposition.variant'));
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_concurrent_identical_gate_desired_states_emit_only_one_transition(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $receipt = $this->observeAndAttest($source);
        $phase = $this->phase($source, PhaseKind::Constrain);
        foreach (['load', 'unload'] as $operation) {
            $options = [$operation => [$receipt->id]];
            $this->whileWorkerWaits($this->substrate, [
                'operation' => 'receipt-desired', 'participant_id' => $phase->cycle->participant_id,
                'phase_id' => $phase->id, 'receipt_options' => $options,
            ], fn () => app(ReceiptContextService::class)->desired($phase->cycle->participant, $phase, $options));
            $this->assertDatabaseCount('participant_receipt_contexts', 1);
            $this->assertDatabaseCount('receipt_context_events', $operation === 'load' ? 1 : 2);
            $this->assertSame($operation === 'load' ? 1 : 0, ParticipantReceiptContext::active()->count());
        }
    }

    public function test_basin_completion_rejects_a_stale_commit_and_retry_freezes_the_same_preview(): void
    {
        $gate = $this->node($this->phase($this->origin, PhaseKind::Bias), $this->origin);
        $this->observeAndAttest($gate);
        $producer = $this->phase($gate, PhaseKind::Order);
        $other = $this->phase($this->origin);
        $unresolved = $this->phase($this->origin);
        $this->publishPotentialFixture($this->substrate);
        $service = app(ClosureGateService::class);
        $before = $service->preview($gate, $producer);
        $this->assertTrue($before['admissible']);
        $this->assertSame(1, $before['cadence_interval']);
        $this->whileWorkerWaits($this->substrate, [
            'operation' => 'closure-contribute-conflict', 'participant_id' => $producer->cycle->participant_id,
            'phase_id' => $producer->id,
            'contribution' => ['action' => 'proposition.commit', 'proposition_id' => $gate->id],
        ], fn () => $other->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save());
        $this->assertNull($producer->fresh()->completed_at);
        $this->assertNull($unresolved->cycle->fresh()->completed_at);
        $this->publishPotentialFixture($this->substrate);
        $preview = $service->preview($gate, $producer);
        $this->assertSame(2, $preview['cadence_interval']);
        $commit = app(ContributionDispatcher::class)->dispatch($producer->cycle->participant, $producer,
            ['action' => 'proposition.commit', 'proposition_id' => $gate->id]);
        $snapshot = $commit->commitmentSnapshot;
        foreach (['depth', 'cadence', 'closure', 'cadence_interval'] as $component) {
            $this->assertSame($preview[$component], $snapshot->{$component});
        }
        $this->assertSame($preview['depth_direction'], $snapshot->depth_direction->value);
        $this->assertSame($preview['cadence_direction'], $snapshot->cadence_direction->value);
        $producer->cycle->forceFill(['completed_at' => now()])->save();
        $unresolved->cycle->forceFill(['completed_at' => now()])->save();
        $this->assertSame(2, $snapshot->fresh()->cadence_interval);
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
