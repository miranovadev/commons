<?php

namespace Tests\Feature\Api;

use App\Models\CanonicalMeasurement;
use App\Models\Participant;
use App\Models\Proposition;
use App\Services\BackwardRecoveryLineage;
use App\Services\CrossoverMarginService;
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

    public function test_closed_crossover_alone_does_not_authorize_catastrophe_on_an_untested_continuation(): void
    {
        [$actor, , $initial, $committed] = $this->persistCommitWithObserver('tragic-explorer', 'open-entrant');

        // C2 loads the ordinary observation at its actual Persist acquisition.
        foreach (['r1', 'r2', 'rightPreparation'] as $observation) {
            $ordinary = DB::table('receipts')->where('id', $this->persistJourneyIds[$observation])->sole();
            $load = DB::table('participant_receipt_contexts')->where('participant_id', $actor)->where('receipt_id', $ordinary->id)->sole();
            $this->assertNull($load->cleared_at);
            $this->assertDatabaseHas('receipt_context_events', ['participant_receipt_context_id' => $load->id,
                'phase_id' => $ordinary->phase_id, 'action' => 'loaded']);
        }
        $classification = Proposition::findOrFail($this->persistJourneyIds['classification']);
        $this->assertSame(1, app(ReinforcementService::class)->count($classification));
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $this->persistJourneyIds['rightPreparation'], 'proposition_id' => $classification->id]);
        $boundary = $committed['last_commitment'];
        // This lawful skeptical history remains in Settlement; instrumentation
        // changes the chronology, not the domain-transition or margin equations.
        $this->assertFalse($boundary['closure_boundary']);
        $this->assertEqualsWithDelta(0, $boundary['depth'], 0.0000001);
        $this->assertEqualsWithDelta(0, $boundary['cadence'], 0.0000001);
        // Twelve author Cycles at Origin and three still-open observer Cycles.
        $this->assertSame(15, (int) DB::table('commitment_snapshots')
            ->where('proposition_id', $boundary['proposition_id'])->value('cadence_interval'));
        $this->discoverGate($actor);
        $observing = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Develop the completed region']);
        $observed = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $observing['plan']['gate_id'],
            'instrument' => ['kind' => 'citation', 'reference' => $boundary['proposition_id']]]);
        $this->assertSame($observing['plan']['phase'], $observed['plan']['phase']);
        $beforeRest = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Test its fresh continuation']);
        $participant = Participant::findOrFail($actor);
        $cycle = $participant->cycles()->latest('sequence')->firstOrFail();
        $origin = $initial['current_proposition_id'];
        $seed = Proposition::findOrFail($boundary['proposition_id']);
        $entries = DB::table('cycles')->where('entry_committal_id', $origin)
            ->where('context_horizon_id', $seed->phase->cycle->context_horizon_id);
        $this->assertSame(15, $entries->count());
        $this->assertTrue($entries->whereNull('completed_at')->exists());
        $diagnostic = app(CrossoverMarginService::class)->inspect($cycle);
        $this->assertReceiptNeutralChiralityChain($participant->position);
        $this->assertSame($boundary['tension_after'], $seed->commitmentSnapshot->tension);
        $this->assertSame($seed->id, $diagnostic['governing_committal_id']);
        $this->assertSame('settlement', $diagnostic['domain']);
        // Check this transcript's frozen values and independently project its margin below.
        foreach (['correspondence' => -0.2962244, 'chirality' => -0.118394,
            'prospective_depth' => 0, 'prospective_committed_cardinal_position' => 0, 'admissible_cadence_bound' => 0,
            'tension_bound' => -0.1382062,
            'crossover_margin' => -0.1382062] as $name => $value) {
            $this->assertEqualsWithDelta($value, $diagnostic[$name], 1e-7, $name.' '.json_encode($diagnostic));
        }
        // Settlement's negative retained Cardinal direction admits a zero
        // cadence bound. Independently project the three bounded observables.
        $values = [$seed->commitmentSnapshot->correspondence, $seed->commitmentSnapshot->chirality, 0.0];
        $prominence = round(array_sum(array_map(fn ($x) => (1 + $x) / 2, $values)) / 3, 7);
        $turbulence = round(array_sum(array_map(fn ($x) => (1 - $x) / 2, $values)) / 3, 7);
        $this->assertSame(round($prominence - $turbulence, 7), $diagnostic['crossover_margin']);
        $this->assertSame('negative', $diagnostic['prospective_cardinal_direction']);
        $this->assertTrue($diagnostic['closed']);
        $path = app(BackwardRecoveryLineage::class)->distances($participant->position, $participant,
            fn ($id, $horizon) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
        $committals = Proposition::whereIn('id', array_keys($path))->where('tier', 'committal')->pluck('id')->all();
        $this->assertSame([$seed->id, $origin], array_values(array_intersect(array_keys($path), $committals)));
        $this->assertSame(2, $path[$seed->id]);
        $this->assertSame(9, $path[$origin]);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($origin));
        $this->assertSame('no_available_move', $beforeRest['plan']['rest_reason']);
        $this->assertSame(['participant.rest'], array_keys($beforeRest['plan']['schema']['$defs']['actions']));

        $history = fn () => collect(['tragedies' => 'id', 'receipts' => 'id', 'receipt_consumptions' => 'receipt_id',
            'commit_region_exhaustions' => 'governing_committal_id', 'exhausted_region_memberships' => 'proposition_id',
            'commitment_snapshots' => 'proposition_id', 'closure_births' => 'proposition_id', 'context_horizons' => 'id'])
            ->map(fn ($key, $table) => DB::table($table)->orderBy($key)->get()->toJson())->all();
        $beforeHistory = $history();
        $state = $this->contribute($actor, ['action' => 'participant.rest'], $beforeRest);
        $this->assertSame($beforeHistory, $history());
        $this->assertNotNull($cycle->fresh()->rested_at);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertSame(0, DB::table('receipts')->where('type', 'tragedy')->count());
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        // Closed crossover skips this non-Origin governor on this Rest's
        // backward path; it does not exhaust the shared Region.
        $this->assertSame($origin, $state['current_proposition_id']);
        $this->assertSame('settlement', $state['domain']);
        $this->assertSame($beforeRest['horizon_sequence'], $state['horizon_sequence']);
        $this->assertSame($beforeRest['context_horizon_id'], $state['context_horizon_id']);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($seed));
        // A geometric bound alone is not proof of genuine continuation failure.
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
                $canonical = CanonicalMeasurement::findOrFail($position->id);
                $expected = $canonical->chirality;
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
