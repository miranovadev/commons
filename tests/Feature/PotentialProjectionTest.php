<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\CycleEntryService;
use App\Services\FieldMutationService;
use App\Services\LedgerService;
use App\Services\Observables\ObservableService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\ReferencePotentialChargeService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipationScaleFixture;
use Tests\TestCase;

final class PotentialProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_incremental_projection_matches_full_derivation_after_writes_and_evidence_changes(): void
    {
        [$participant, $origin] = $this->fixture();
        $service = app(ParticipationService::class);
        $phase = $participant->cycles()->whereNull('completed_at')->sole()->currentPhase;
        $service->contribute($participant, $phase->id, ['action' => 'proposition.variant', 'statement' => 'A continuation']);
        $first = $participant->fresh()->position;
        $this->assertProjectionMatches($participant, $first);
        $other = $this->participant($participant->substrate);
        app(CycleEntryService::class)->enter($other);
        $phase = $other->cycles()->whereNull('completed_at')->sole()->currentPhase;
        $service->contribute($other, $phase->id, ['action' => 'proposition.variant', 'statement' => 'A sibling']);
        $sibling = $other->fresh()->position;
        $attestation = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $first->id, 'direction' => 'refute']);
        $attestation->save();
        app(LedgerService::class)->record($attestation);
        $this->assertTrue(app(PotentialProjectionService::class)->refresh($participant->substrate_id));
        $this->assertProjectionMatches($participant, $first);
        $this->assertProjectionMatches($other, $sibling);
        $this->loadedReceiptFixture($first);
        $this->assertTrue(app(PotentialProjectionService::class)->refresh($participant->substrate_id));
        $this->assertProjectionMatches($participant, $first);
        $before = DB::table('potential_entries')->orderBy('proposition_id')->get()->toJson();
        $this->assertTrue(app(PotentialProjectionService::class)->rebuild($participant->substrate_id));
        $this->assertSame($before, DB::table('potential_entries')->orderBy('proposition_id')->get()->toJson());
    }

    public function test_missing_or_inconsistent_projection_returns_explicit_state_without_rebuilding(): void
    {
        [$participant] = $this->fixture();
        $certificate = DB::table('potential_projection_states')->where('substrate_id', $participant->substrate_id)->first();
        DB::table('potential_projection_states')->where('substrate_id', $participant->substrate_id)->delete();
        $this->assertSame('potential_projection_pending', app(ParticipationService::class)->stateFor($participant)['reason']['code']);
        DB::table('potential_projection_states')->insert((array) $certificate);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $cycle->currentPhase->forceFill(['completed_at' => now()])->save();
        $cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        DB::table('potential_projection_states')->where('substrate_id', $participant->substrate_id)->delete();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $state = app(ParticipationService::class)->stateFor($participant);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame('potential_projection_pending', $state['reason']['code']);
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update|supersessions/i', $query['query']);
        }
        $this->assertFalse(app(PotentialProjectionService::class)->refresh($participant->substrate_id));
        $this->artisan('commons:potential-rebuild', ['substrate' => $participant->substrate_id])->assertSuccessful();
        app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id);
        Substrate::whereKey($participant->substrate_id)->increment('field_version');
        $this->assertFalse(app(PotentialProjectionService::class)->refresh($participant->substrate_id));
    }

    public function test_rest_recovery_queries_do_not_grow_with_unrelated_historical_propositions(): void
    {
        [$participant, $origin] = $this->fixture();
        $phase = $participant->cycles()->whereNull('completed_at')->sole()->currentPhase;
        app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'proposition.variant', 'statement' => 'Exhausted position']);
        $position = $participant->fresh()->position;
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        $cycle->currentPhase->forceFill(['completed_at' => now()])->save();
        $cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        $counts = [];
        foreach ([0, 1000] as $size) {
            if ($size) {
                ParticipationScaleFixture::addHistory($origin, $size);
                // Fixture inserts bypass write services; explicitly rebuild its terrain.
                app(PotentialProjectionService::class)->rebuild($participant->substrate_id, false);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $start = hrtime(true);
            app(ParticipationService::class)->stateFor($participant->fresh());
            $log = DB::getQueryLog();
            DB::disableQueryLog();
            $counts[] = count($log);
            fwrite(STDERR, sprintf("\nRECOVERY history=%d queries=%d wall=%.2fms\n", $size, count($log), (hrtime(true) - $start) / 1e6));
            foreach ($log as $query) {
                $this->assertDoesNotMatchRegularExpression('/from ["`]supersessions|insert |update |delete |for update/i', $query['query']);
            }
            foreach (['PROJECTED' => PotentialChargeService::class, 'REFERENCE' => ReferencePotentialChargeService::class] as $label => $calculator) {
                $freshPosition = $position->fresh();
                DB::enableQueryLog();
                DB::flushQueryLog();
                $referenceStart = hrtime(true);
                $selection = app($calculator)->inspectRecoveryFrom($freshPosition, $cycle->contextHorizon)['selection'];
                $referenceQueries = count(DB::getQueryLog());
                DB::disableQueryLog();
                $selections[$label] = $selection['proposition']->id ?? null;
                fwrite(STDERR, sprintf("\n%s RECOVERY history=%d queries=%d wall=%.2fms\n",
                    $label, $size, $referenceQueries, (hrtime(true) - $referenceStart) / 1e6));
            }
            $this->assertSame($selections['REFERENCE'], $selections['PROJECTED']);
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThan(100, $counts[1]);
    }

    public function test_version_race_discards_prepared_projection_and_retries_without_calculation_under_the_publish_lock(): void
    {
        [$participant] = $this->fixture();
        $phase = $participant->cycles()->whereNull('completed_at')->sole()->currentPhase;
        app(FieldMutationService::class)->touch($phase, [$phase->gate_proposition_id]);
        $outer = DB::transactionLevel();
        $raced = false;
        $calculationLevels = [];
        DB::listen(function (QueryExecuted $query) use (&$raced, &$calculationLevels, $phase): void {
            if (str_contains($query->sql, '/* participation working set */')) {
                $calculationLevels[] = DB::transactionLevel();
                if (! $raced) {
                    $raced = true;
                    app(FieldMutationService::class)->touch($phase, [$phase->gate_proposition_id]);
                }
            }
        });
        $this->assertTrue(app(PotentialProjectionService::class)->refresh($participant->substrate_id));
        $this->assertTrue($raced);
        $this->assertGreaterThanOrEqual(2, count($calculationLevels));
        $this->assertSame([$outer], array_values(array_unique($calculationLevels)));
        $this->assertSame((int) $participant->substrate->fresh()->field_version,
            app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id));
    }

    public function test_repeated_contention_leaves_durable_work_for_the_next_sweep_without_releasing_the_job(): void
    {
        [$participant] = $this->fixture();
        $phase = $participant->cycles()->whereNull('completed_at')->sole()->currentPhase;
        app(FieldMutationService::class)->touch($phase, [$phase->gate_proposition_id]);
        $contended = true;
        $attempts = 0;
        $horizonId = $phase->cycle->context_horizon_id;
        DB::listen(function (QueryExecuted $query) use (&$contended, &$attempts, $phase, $horizonId): void {
            // Exactly one competing write per attempt, even when another
            // affected region also constructs a calculation context.
            if ($contended && str_contains($query->sql, '/* participation working set */')
                && in_array($horizonId, $query->bindings, true)) {
                $attempts++;
                app(FieldMutationService::class)->touch($phase, [$phase->gate_proposition_id]);
            }
        });
        $queueJob = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldNotReceive('release');
        $job = new \App\Jobs\RefreshPotentialProjection($participant->substrate_id);
        $job->setJob($queueJob);
        $job->handle(app(PotentialProjectionService::class));
        $this->assertSame(3, $attempts);
        $this->assertDatabaseHas('potential_dirty_horizons', ['substrate_id' => $participant->substrate_id]);
        $this->assertSame('potential_projection_pending', app(ParticipationService::class)->stateFor($participant)['reason']['code']);

        \Illuminate\Support\Facades\Queue::fake();
        $this->artisan('commons:potential-refresh')->assertSuccessful();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RefreshPotentialProjection::class,
            fn ($next) => $next->substrateId === $participant->substrate_id);
        $contended = false;
        $job->handle(app(PotentialProjectionService::class));
        $this->assertDatabaseMissing('potential_dirty_horizons', ['substrate_id' => $participant->substrate_id]);
        app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id);
    }

    private function assertProjectionMatches(Participant $participant, Proposition $position): void
    {
        $projection = app(PotentialProjectionService::class);
        $projection->assertCurrent($participant->substrate_id);
        $horizon = $position->phase->cycle->contextHorizon;
        $actual = app(PotentialChargeService::class)->inspectRecoveryFrom($position, $horizon)['selection'];
        $reference = app(ReferencePotentialChargeService::class)->inspectRecoveryFrom($position, $horizon)['selection'];
        $this->assertSame($reference['proposition']->id ?? null, $actual['proposition']->id ?? null);
        $this->assertSame($reference['distance'] ?? null, $actual['distance'] ?? null);
        $this->assertEqualsWithDelta($reference['potential_charge'] ?? 0, $actual['potential_charge'] ?? 0, 1e-12);
        foreach (Proposition::active()->whereHas('phase.cycle', fn ($q) => $q->where('context_horizon_id', $horizon->id))->where('tier', 'expressive')->get() as $node) {
            $expected = app(ObservableService::class)->calculate($node);
            foreach (['correspondence', 'chirality', 'closure'] as $key) {
                $this->assertEqualsWithDelta($expected->$key, $node->measurement->$key, 1e-7);
            }
        }
    }

    private function participant(Substrate $substrate): Participant
    {
        $participant = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'projection-'.str()->ulid()]);
        $participant->save();
        return $participant;
    }

    private function fixture(): array
    {
        $substrate = (new Substrate)->forceFill(['slug' => 'projection', 'title' => 'Projection', 'visibility' => 'public', 'configuration' => []]);
        $substrate->save();
        $participant = $this->participant($substrate);
        app(CycleEntryService::class)->enter($participant, ['statement' => 'Projection Origin',
            'attestation' => ['direction' => 'support'], 'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']]);
        return [$participant, $participant->fresh()->position];
    }
}
