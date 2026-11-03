<?php

namespace Tests\Feature\Api;

use App\Models\CanonicalMeasurement;
use App\Models\Participant;
use App\Models\Proposition;
use App\Services\BackwardRecoveryLineage;
use App\Services\ExhaustionService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ReinforcementService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationTragedyLifecycleTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;

    public function test_new_committal_with_immature_cadence_offers_distinguish_acquisition(): void
    {
        [$actor, , $initial, $committed] = $this->persistCommitWithObserver('tragic-explorer', 'open-entrant');

        // C2 loads the ordinary observation at its actual Persist acquisition.
        foreach (['r0', 'rightDiscovery'] as $observation) {
            $ordinary = DB::table('receipts')->where('id', $this->persistJourneyIds[$observation])->sole();
            $load = DB::table('participant_receipt_contexts')->where('participant_id', $actor)->where('receipt_id', $ordinary->id)->sole();
            $this->assertNull($load->cleared_at);
            $this->assertDatabaseHas('receipt_context_events', ['participant_receipt_context_id' => $load->id,
                'phase_id' => $ordinary->phase_id, 'action' => 'loaded']);
        }
        $classification = Proposition::findOrFail($this->persistJourneyIds['classification']);
        $this->assertSame(1, app(ReinforcementService::class)->count($classification));
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $this->persistJourneyIds['rightDiscovery'], 'proposition_id' => $classification->id]);
        $boundary = $committed['last_commitment'];
        // This lawful skeptical history remains in Settlement; instrumentation
        // changes the chronology, not the domain-transition equations.
        $this->assertFalse($boundary['closure_boundary']);
        $this->assertEqualsWithDelta(0, $boundary['depth'], 0.0000001);
        $this->assertEqualsWithDelta(0, $boundary['cadence'], 0.0000001);
        // Twelve author Cycles at Origin and three still-open observer Cycles.
        $this->assertSame(15, (int) DB::table('commitment_snapshots')
            ->where('proposition_id', $boundary['proposition_id'])->value('cadence_interval'));
        $this->assertSame(['instrument.execute'], array_keys($committed['plan']['schema']['$defs']['actions']));
        $this->assertNull($committed['plan']['rest_reason']);
        $participant = Participant::findOrFail($actor);
        $cycle = $participant->cycles()->latest('sequence')->firstOrFail();
        $origin = $initial['current_proposition_id'];
        $seed = Proposition::findOrFail($boundary['proposition_id']);
        $entries = DB::table('cycles')->where('entry_committal_id', $origin)
            ->where('context_horizon_id', $seed->phase->cycle->context_horizon_id);
        $this->assertSame(15, $entries->count());
        $this->assertTrue($entries->whereNull('completed_at')->exists());
        $this->assertReceiptNeutralChiralityChain($participant->position);
        $this->assertSame($boundary['tension_after'], $seed->commitmentSnapshot->tension);
        $this->assertFalse($seed->commitmentSnapshot->closure_observation['availability']['cadence']);
        $this->assertSame($seed->id, $cycle->entry_committal_id);
        $this->assertSame('settlement', $cycle->contextHorizon->domain->value);
        $this->assertEqualsWithDelta(-0.3980798, $seed->commitmentSnapshot->correspondence, 1e-7);
        $this->assertEqualsWithDelta(-0.0997720, $seed->commitmentSnapshot->chirality, 1e-7);
        $path = app(BackwardRecoveryLineage::class)->distances($participant->position, $participant,
            fn ($id, $horizon) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
        $committals = Proposition::whereIn('id', array_keys($path))->where('tier', 'committal')->pluck('id')->all();
        $this->assertSame([$seed->id, $origin], array_values(array_intersect(array_keys($path), $committals)));
        $this->assertSame(0, $path[$seed->id]);
        $this->assertSame(7, $path[$origin]);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($origin));
        $this->assertNull($cycle->fresh()->rested_at);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($seed));
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseMissing('evidence_executions', ['phase_id' => $cycle->currentPhase->id]);
    }

    public function test_unjudged_expressive_descendants_preserve_receipt_neutral_chirality_in_an_open_region(): void
    {
        ['actor' => $actor] = $this->developPersist('open-region');
        $this->assertReceiptNeutralChiralityChain(Participant::findOrFail($actor)->position);
    }

    /** Audit fresh local neutrality and attenuation at the frozen Commit boundary. */
    private function assertReceiptNeutralChiralityChain(Proposition $seed): void
    {
        $lineages = app(ContextLineageComparisonService::class);
        $measurements = app(LineageMeasurementService::class);
        $local = app(LocalMeasurementService::class);
        $total = $mass = 0.0;
        foreach ($measurements->positions($seed) as [$position, $distance]) {
            $comparison = $lineages->for($position);
            if ($position->tier === 'committal') {
                // Canonical Commit is a frozen boundary, not a fresh evaluation
                // of the Cycle population after this descendant was entered.
                $expected = $position->phase->cycle->entry_committal_id === null
                    ? $position->commitmentSnapshot->chirality : CanonicalMeasurement::findOrFail($position->id)->chirality;
                $this->assertSame($position->commitmentSnapshot->chirality, $expected);
            } else {
                // These fresh descendants have no own Attestation event. Their
                // local state is neutral, not inherited population Chirality.
                $this->assertDatabaseMissing('attestations', ['proposition_id' => $position->id]);
                $components = $local->chiralityComponents($position);
                $this->assertSame(0.0, $components->orientation);
                $this->assertSame(0.0, $components->cadence);
                $expected = 0.0;
            }
            $this->assertSame($expected, $local->calculate($position, $comparison, false)->chirality);
            $weight = 1 / (1 + $distance) ** 2;
            $total += $expected * $weight;
            $mass += $weight;
        }
        $this->assertSame(round($total / $mass, 7), $measurements->calculate($seed)->chirality);
    }
}
