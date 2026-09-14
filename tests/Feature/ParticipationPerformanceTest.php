<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\AttestationEvidenceService;
use App\Services\CommitmentSnapshotService;
use App\Services\CommitRegionPolarityService;
use App\Services\CommitRegionService;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionStateService;
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

    /** Parent IDs describe a set; PostgreSQL does not promise relationship order. */
    private function comparablePlan(array $plan): string
    {
        $normalize = function (array &$values) use (&$normalize): void {
            foreach ($values as $key => &$value) {
                if (! is_array($value)) {
                    continue;
                }
                if ($key === 'parent_ids') {
                    sort($value);
                } else {
                    $normalize($value);
                }
            }
        };
        $normalize($plan);

        return json_encode($plan, JSON_THROW_ON_ERROR);
    }

    #[DataProvider('phases')]
    public function test_participation_query_budget(PhaseKind $kind): void
    {
        $this->freezeTime();
        [$viewer, $nodes] = $this->fixture($kind, 8);
        $service = app(InputPlanService::class);
        DB::beginTransaction();
        try {
            [$before, $baseline, $beforeMs] = $this->measure(fn () => (new \ReflectionMethod($service, 'build'))->invoke($service, $viewer->fresh(), app(PhaseService::class)->currentFor($viewer), null));
        } finally {
            DB::rollBack();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        $plan = app(InputPlanService::class)->forParticipant($viewer->fresh());
        $elapsed = (hrtime(true) - $start) / 1e6;
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        fwrite(STDERR, sprintf("\nPROFILE %s: %d propositions, %d queries, %.2f SQL ms, %.2f wall ms\n", $kind->name, count($nodes), count($log), array_sum(array_column($log, 'time')), $elapsed));
        fwrite(STDERR, sprintf("BASELINE %s: %d queries, %.2f SQL ms, %.2f wall ms\n", $kind->name, count($baseline), array_sum(array_column($baseline, 'time')), $beforeMs));
        $this->assertSame($this->comparablePlan($before), $this->comparablePlan($plan), 'Complete plan, measurements and action schema must match the unbatched calculation.');
        $this->assertNotEmpty($plan['gate']);
        $this->assertLessThan(500, count($log));
    }

    #[DataProvider('phases')]
    public function test_adding_candidates_does_not_multiply_database_work(PhaseKind $kind): void
    {
        $this->freezeTime();
        $counts = [];
        foreach ([4, 10] as $branches) {
            DB::beginTransaction();
            [$viewer] = $this->fixture($kind, $branches);
            [, $cold] = $this->measure(fn () => app(InputPlanService::class)->forParticipant($viewer->fresh()));
            [, $warm] = $this->measure(fn () => app(InputPlanService::class)->forParticipant($viewer->fresh()));
            $counts[] = [count($cold), count($warm)];
            DB::rollBack();
        }
        fwrite(STDERR, sprintf("GROWTH %s: cold %d -> %d; warm %d -> %d\n", $kind->name, $counts[0][0], $counts[1][0], $counts[0][1], $counts[1][1]));
        // More cold projections require writes, but warm candidate evaluation is batched.
        $this->assertLessThan(500, $counts[1][0]);
        $this->assertLessThan(200, $counts[1][1]);
        $this->assertLessThanOrEqual(125, $counts[1][0] - $counts[0][0]);
        $this->assertLessThanOrEqual(20, $counts[1][1] - $counts[0][1]);
    }

    public function test_scoped_history_and_evidence_match_and_a_new_calculation_sees_new_writes(): void
    {
        $this->freezeTime();
        [$viewer, $nodes] = $this->fixture(PhaseKind::Bias, 8);
        $phase = app(PhaseService::class)->currentFor($viewer);
        $context = new ParticipationContext($phase);
        $state = app(PropositionStateService::class);
        $evidence = app(AttestationEvidenceService::class);
        $regions = app(CommitRegionService::class);
        $measurements = app(PropositionMeasurementService::class);
        foreach (array_merge(array_slice($nodes, 0, 7), array_slice($nodes, -4)) as $node) {
            $this->assertEqualsCanonicalizing($state->sourceIds($node)->all(), $state->sourceIds($node, $context)->all());
            $this->assertSame($state->touchCount($node), $state->touchCount($node, $context));
            $this->assertSame($evidence->directionFor($node), $evidence->directionFor($node, context: $context));
            $this->assertSame($evidence->valueFor(collect([$node]), $viewer->substrate), $evidence->valueFor(collect([$node]), $viewer->substrate, $context));
            $this->assertSame($regions->seedFor($node)?->id, $regions->seedFor($node, $context)?->id);
            $this->assertEqualsCanonicalizing($regions->membersFor($node)->pluck('id')->all(), $regions->membersFor($node, $context)->pluck('id')->all());
            $this->assertSame($measurements->correspondenceDirectionFor($node), $measurements->correspondenceDirectionFor($node, $context));
        }
        $target = collect($nodes)->last(fn ($node) => $node->register === 'stance' && $context->attestationsFor([$node->id])->isNotEmpty());
        $old = $target->attestations()->firstOrFail();
        $replacement = new Attestation;
        $replacement->forceFill(['phase_id' => $phase->id, 'proposition_id' => $target->id,
            'supersedes_attestation_id' => $old->id, 'direction' => $old->direction === 'positive' ? 'negative' : 'positive'])->save();
        app(LedgerService::class)->record($replacement);
        $fresh = new ParticipationContext($phase->fresh());
        $this->assertSame($evidence->directionFor($target), $evidence->directionFor($target, context: $fresh));
        $this->assertNotSame($evidence->directionFor($target, context: $context), $evidence->directionFor($target, context: $fresh));
        // Old original IDs remain historical, but only the replacement is effective.
        $this->assertSame([$replacement->id], $fresh->effectiveAttestations([$target->id])->pluck('id')->all());
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
            $p->forceFill(['phase_id' => $history[count($nodes) % 3]->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Fixture proposition '.count($nodes), 'created_at' => now()->subSeconds(1000 - count($nodes))])->save();
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
        app(CommitRegionPolarityService::class)->establishOrigin($origin, 'positive');
        $prior = $node('signal', $origin);
        $prior = $node('stance', $prior, type: 'reclassify');
        $prior = $node('concord', $prior, type: 'reclassify');
        $boundary = $node('concord', $prior, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($boundary, ObservableDirection::Positive);
        $this->groundFixture($boundary);
        app(PropositionMeasurementService::class)->recalculate($boundary);
        app(CommitmentSnapshotService::class)->record($boundary, 0, 1,
            LineageMeasurementService::VERSION,
            Supersession::where('target_proposition_id', $boundary->id)->firstOrFail());
        $seed = $node('concord', $boundary, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($seed, ObservableDirection::Positive);
        $reads = $gates = $bias = $writes = [];
        for ($i = 0; $i < $branches; $i++) {
            $read = $reads[] = $node('concord', $seed);
            $gate = $gates[] = $node('signal', $read);
            $b = $bias[] = $node('stance', $gate);
            $write = $writes[] = $node('concord', $b);
            foreach ([$read, $b, $write] as $target) {
                $a = new Attestation;
                $a->forceFill(['phase_id' => $history[$i % 3]->id, 'proposition_id' => $target->id, 'direction' => $i % 3 === 0 ? 'negative' : 'positive'])->save();
                app(LedgerService::class)->record($a);
                $execution = app(InstrumentExecutionService::class)->start($a->phase, $target, 'hyperlink', 'https://example.test/'.$a->id);
                app(InstrumentExecutionService::class)->complete($execution, $a, 'fixture-'.$a->id);
            }
        }
        // Retired population evidence and fresh Revise/Reconcile history are
        // present alongside a negative-polarity neighbouring Commit Region.
        $retired = $node('stance', $gates[0]);
        $old = new Attestation;
        $old->forceFill(['phase_id' => $history[0]->id, 'proposition_id' => $retired->id, 'direction' => 'negative'])->save();
        app(LedgerService::class)->record($old);
        $node('stance', $retired, 'expressive', 'revise');
        $merged = $node('concord', $writes[2], 'expressive', 'reconcile');
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $merged->phase_id, 'source_proposition_id' => $writes[3]->id,
            'target_proposition_id' => $merged->id, 'type' => 'reconcile'])->save();
        app(LedgerService::class)->record($edge);
        $other = $node('concord', $writes[0], 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($other, ObservableDirection::Negative);
        $node('stance', $other);

        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participants[0]->id, 'sequence' => 2])->save();
        $disperse = new Phase;
        $disperse->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Disperse, 'write_register' => 'signal', 'gate_proposition_id' => $reads[0]->id, 'started_at' => now(), 'completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $kind === PhaseKind::Mediate ? 'stance' : 'concord', 'gate_proposition_id' => ($kind === PhaseKind::Mediate ? $gates[0] : $bias[0])->id, 'started_at' => now()])->save();
        $participants[0]->forceFill(['current_proposition_id' => $phase->gate_proposition_id])->save();
        $this->assertGreaterThanOrEqual(2, ContextHorizon::where('substrate_id', $substrate->id)->count());

        return [$participants[0], $nodes];
    }
}
