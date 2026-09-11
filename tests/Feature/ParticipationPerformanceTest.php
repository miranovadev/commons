<?php

namespace Tests\Feature;

use App\Enums\{ContextDomain, PhaseKind};
use App\Models\{Attestation, ContextHorizon, Cycle, Participant, Phase, Proposition, Substrate, Supersession};
use App\Services\{CommitRegionPolarityService, InputPlanService, InstrumentExecutionService, LedgerService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParticipationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public static function phases(): array
    {
        return [[PhaseKind::Mediate], [PhaseKind::Bias], [PhaseKind::Order]];
    }

    #[DataProvider('phases')]
    public function test_participation_query_budget(PhaseKind $kind): void
    {
        $this->freezeTime();
        [$viewer, $nodes] = $this->fixture($kind, 8);
        $service = app(InputPlanService::class);
        $phase = app(\App\Services\PhaseService::class)->currentFor($viewer);
        DB::beginTransaction();
        [$before, $baseline, $beforeMs] = $this->measure(fn () =>
            (new \ReflectionMethod($service, 'build'))->invoke($service, $viewer->fresh(), $phase->fresh(), null));
        DB::rollBack();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        $plan = app(InputPlanService::class)->forParticipant($viewer->fresh());
        $elapsed = (hrtime(true) - $start) / 1e6;
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        fwrite(STDERR, sprintf("\nPROFILE %s: %d propositions, %d queries, %.2f SQL ms, %.2f wall ms\n", $kind->name, count($nodes), count($log), array_sum(array_column($log, 'time')), $elapsed));
        fwrite(STDERR, sprintf("BASELINE %s: %d queries, %.2f SQL ms, %.2f wall ms\n", $kind->name, count($baseline), array_sum(array_column($baseline, 'time')), $beforeMs));
        $this->assertSame(json_encode($before), json_encode($plan), 'Complete plan, measurements and action schema must match the unbatched calculation.');
        $this->assertNotEmpty($plan['gate']);
        $this->assertLessThan(500, count($log));
    }

    private function measure(callable $calculate): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        $result = $calculate();
        $elapsed = (hrtime(true) - $start) / 1e6;
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        return [$result, $log, $elapsed];
    }

    /** Two Horizons, Commit-only history, branching and all three late-phase candidate registers. */
    private function fixture(PhaseKind $kind, int $branches): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'performance', 'title' => 'Performance', 'visibility' => 'public', 'configuration' => []])->save();
        $participants = collect(range(1, 3))->map(function ($i) use ($substrate) {
            $participant = new Participant;
            $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'participant-'.$i, 'max_read_count' => 25])->save();
            return $participant;
        });
        $history = $participants->map(function ($participant) {
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1, 'completed_at' => now()])->save();
            $phase = new Phase;
            $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish, 'write_register' => 'signal', 'started_at' => now(), 'completed_at' => now()])->save();
            return $phase;
        });
        $nodes = [];
        $node = function ($register, $parent = null, $tier = 'expressive', $type = 'variant') use (&$nodes, $history) {
            $p = new Proposition;
            $p->forceFill(['phase_id' => $history[count($nodes) % 3]->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Fixture proposition '.count($nodes)])->save();
            app(LedgerService::class)->record($p);
            if ($parent) {
                $edge = new Supersession;
                $edge->forceFill(['phase_id' => $p->phase_id, 'source_proposition_id' => $parent->id, 'target_proposition_id' => $p->id, 'type' => $type])->save();
                app(LedgerService::class)->record($edge);
            }
            $nodes[] = $p;
            return $p;
        };
        $origin = $node('canon', tier: 'committal');
        app(CommitRegionPolarityService::class)->establishOrigin($origin, 'support');
        $prior = $node('signal', $origin);
        $prior = $node('stance', $prior, type: 'reclassify');
        $prior = $node('concord', $prior, type: 'reclassify');
        $boundary = $node('concord', $prior, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($boundary, \App\Enums\ObservableDirection::Positive);
        app(\App\Services\PropositionMeasurementService::class)->recalculate($boundary);
        app(\App\Services\CommitmentSnapshotService::class)->record($boundary, 1, 0,
            \App\Services\Observables\LineageMeasurementService::VERSION,
            Supersession::where('target_proposition_id', $boundary->id)->firstOrFail());
        $seed = $node('concord', $boundary, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($seed, \App\Enums\ObservableDirection::Positive);
        $reads = $gates = $bias = $writes = [];
        for ($i = 0; $i < $branches; $i++) {
            $read = $reads[] = $node('canon', $seed);
            $gate = $gates[] = $node('activity', $read);
            $b = $bias[] = $node('frontier', $gate);
            $write = $writes[] = $node('canon', $b);
            foreach ([$read, $b, $write] as $target) {
                $a = new Attestation;
                $a->forceFill(['phase_id' => $history[$i % 3]->id, 'proposition_id' => $target->id, 'direction' => $i % 3 === 0 ? 'refute' : 'support'])->save();
                app(LedgerService::class)->record($a);
                $execution = app(InstrumentExecutionService::class)->start($a->phase, $target, 'hyperlink', 'https://example.test/'.$a->id);
                app(InstrumentExecutionService::class)->complete($execution, $a, 'fixture-'.$a->id);
            }
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participants[0]->id, 'sequence' => 2])->save();
        $disperse = new Phase;
        $disperse->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Disperse, 'write_register' => 'activity', 'gate_proposition_id' => $reads[0]->id, 'started_at' => now(), 'completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $kind === PhaseKind::Mediate ? 'frontier' : 'canon', 'gate_proposition_id' => ($kind === PhaseKind::Mediate ? $gates[0] : $bias[0])->id, 'started_at' => now()])->save();
        $participants[0]->forceFill(['current_proposition_id' => $phase->gate_proposition_id])->save();
        return [$participants[0], $nodes];
    }
}
