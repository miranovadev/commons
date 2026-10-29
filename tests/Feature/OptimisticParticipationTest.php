<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Substrate;
use App\Services\CycleEntryService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OptimisticParticipationTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_and_obsolete_measurements_are_not_rebuilt_by_observation(): void
    {
        [$participant, $phase] = $this->fixture();
        foreach ([false, true] as $missing) {
            if ($missing) {
                $phase->gate->measurement()->delete();
            } else {
                $phase->gate->measurement()->update(['calculator_version' => null]);
            }
            $before = $phase->gate->fresh()->getAttributes();
            $projection = $phase->gate->fresh()->measurement?->getAttributes();
            $transactions = 0;
            Event::listen(TransactionBeginning::class, function () use (&$transactions): void {
                $transactions++;
            });
            DB::enableQueryLog();
            DB::flushQueryLog();
            $service = app(ParticipationService::class);
            $state = $service->stateFor($participant);
            $workspace = $service->workspaceFor($participant);
            app(PotentialChargeService::class)->selectFor($participant->substrate);
            app(CycleEntryService::class)->prepare($participant);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertSame('ready', $state['status']);
            $this->assertSame($state['plan']['phase']['id'], $workspace['plan']['phase']['id']);
            $this->assertSame(isset($projection['correspondence']) ? (float) $projection['correspondence'] : null, $workspace['plan']['gate']['observables']['correspondence']);
            $this->assertSame(0, $transactions);
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/\b(for update|for share|insert into|update ["`]|delete from|savepoint)\b/i', $query['query']);
            }
            $this->assertSame($before, $phase->gate->fresh()->getAttributes());
            $this->assertSame($projection, $phase->gate->fresh()->measurement?->getAttributes());
        }
    }

    public function test_contribution_serializes_the_response_only_after_its_write_transaction(): void
    {
        [$participant, $phase] = $this->fixture();
        $this->instrumentGateFixture($phase);
        $outerLevel = DB::transactionLevel();
        $contexts = [];
        $transactionStart = null;
        $transactionMs = null;
        Event::listen(TransactionBeginning::class, function () use ($outerLevel, &$transactionStart): void {
            if (DB::transactionLevel() === $outerLevel + 1) {
                $transactionStart = hrtime(true);
            }
        });
        Event::listen(TransactionCommitted::class, function () use ($outerLevel, &$transactionStart, &$transactionMs): void {
            if (DB::transactionLevel() === $outerLevel && $transactionStart !== null) {
                $transactionMs = (hrtime(true) - $transactionStart) / 1e6;
            }
        });
        DB::listen(function (QueryExecuted $query) use (&$contexts): void {
            if (str_contains($query->sql, '/* participation working set */')) {
                $contexts[] = DB::transactionLevel();
            }
        });
        $started = hrtime(true);
        $result = app(ParticipationService::class)->contribute($participant, $phase->id,
            ['action' => 'proposition.variant', 'statement' => 'A fresh distinction']);
        fwrite(STDERR, sprintf("\nCONTRIBUTION transaction=%.2fms total=%.2fms context_transaction_levels=%s\n", $transactionMs, (hrtime(true) - $started) / 1e6, json_encode($contexts)));
        $this->assertNotContains($outerLevel + 1, $contexts, 'Admissibility and projection derivation run before/after guarded writes.');
        $this->assertSame($outerLevel, $contexts[array_key_last($contexts)], 'Response context is built after releasing write locks.');
        $this->assertSame(PhaseKind::Persist->value, $result['plan']['phase']['number']);
        $this->assertSame($outerLevel, DB::transactionLevel());
    }

    private function fixture(): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'optimistic', 'title' => 'Optimistic reads', 'configuration' => [], 'visibility' => 'public'])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
        app(CycleEntryService::class)->enter($participant, [
            'statement' => 'An observed Origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/optimistic'],
        ]);
        $phase = Phase::whereHas('cycle', fn ($q) => $q->where('participant_id', $participant->id))
            ->whereNull('completed_at')->sole();

        return [$participant, $phase];
    }
}
