<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitRegionPolarityService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipationSnapshotTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_evidence_does_not_split_a_plan_and_the_next_plan_sees_it(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires two PostgreSQL connections and MVCC.');
        }
        $this->freezeTime();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'snapshot', 'title' => 'Snapshot', 'configuration' => [], 'visibility' => 'public'])->save();
        $viewer = new Participant;
        $viewer->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'reader'])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $viewer->id, 'sequence' => 1])->save();
        $history = new Phase;
        $history->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish, 'write_register' => 'signal', 'started_at' => now(), 'completed_at' => now()])->save();
        $origin = new Proposition;
        $origin->forceFill(['phase_id' => $history->id, 'register' => 'canon', 'tier' => 'committal', 'statement' => 'Origin'])->save();
        app(LedgerService::class)->record($origin);
        app(CommitRegionPolarityService::class)->establishOrigin($origin, 'support');
        $candidate = new Proposition;
        $candidate->forceFill(['phase_id' => $history->id, 'register' => 'stance', 'tier' => 'expressive', 'statement' => 'Candidate'])->save();
        app(LedgerService::class)->record($candidate);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $history->id, 'source_proposition_id' => $origin->id, 'target_proposition_id' => $candidate->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Bias, 'write_register' => 'concord', 'gate_proposition_id' => $candidate->id, 'started_at' => now()])->save();
        $plans = app(InputPlanService::class);
        $before = $plans->forParticipant($viewer);

        config()->set('database.connections.performance_writer', config('database.connections.'.config('database.default')));
        $writer = DB::connection('performance_writer');
        $inserted = false;
        $armed = true;
        DB::listen(function (QueryExecuted $query) use (&$armed, &$inserted, $writer, $phase, $candidate, $substrate): void {
            if (! $armed || $query->connectionName === 'performance_writer' || ! str_contains($query->sql, 'from "supersessions"')) {
                return;
            }
            $armed = false;
            $writer->transaction(function () use ($writer, $phase, $candidate, $substrate): void {
                $id = (string) str()->ulid();
                $writer->table('attestations')->insert(['id' => $id, 'phase_id' => $phase->id, 'proposition_id' => $candidate->id,
                    'direction' => 'refute', 'created_at' => now(), 'updated_at' => now()]);
                $writer->table('substrate_ledger_entries')->insert(['id' => (string) str()->ulid(), 'substrate_id' => $substrate->id,
                    'sequence' => $writer->table('substrate_ledger_entries')->where('substrate_id', $substrate->id)->max('sequence') + 1,
                    'kind' => 'attestation', 'ledgerable_type' => (new Attestation)->getMorphClass(), 'ledgerable_id' => $id, 'recorded_at' => now()]);
                $writer->table('proposition_measurements')->where('proposition_id', $candidate->id)->update(['calculator_version' => null]);
            });
            $inserted = true;
        });
        try {
            $during = $plans->forParticipant($viewer);
            $this->assertTrue($inserted);
            $this->assertSame(json_encode($before), json_encode($during));
            $after = $plans->forParticipant($viewer);
            $this->assertSame(0, $before['gate']['attestation_counts']['refute']);
            $this->assertSame(1, $after['gate']['attestation_counts']['refute']);
            $this->assertSame(0, DB::transactionLevel());
        } finally {
            $armed = false;
            DB::purge('performance_writer');
        }
    }
}
