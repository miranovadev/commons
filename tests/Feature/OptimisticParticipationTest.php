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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OptimisticParticipationTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_and_obsolete_measurements_are_observed_without_mutations_or_transactions(): void
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
            $this->assertNotNull($workspace['plan']['gate']['observables']['correspondence']);
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
        $outerLevel = DB::transactionLevel();
        $contexts = [];
        DB::listen(function (QueryExecuted $query) use (&$contexts): void {
            if (str_contains($query->sql, 'WITH RECURSIVE dependencies')) {
                $contexts[] = DB::transactionLevel();
            }
        });
        $started = hrtime(true);
        $result = app(ParticipationService::class)->contribute($participant, $phase->id,
            ['action' => 'proposition.variant', 'statement' => 'A fresh distinction']);
        fwrite(STDERR, sprintf("\nCONTRIBUTION total=%.2fms context_transaction_levels=%s\n", (hrtime(true) - $started) / 1e6, json_encode($contexts)));
        $this->assertContains($outerLevel + 1, $contexts, 'Admissibility must still be validated in the write transaction.');
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
