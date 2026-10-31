<?php

namespace Tests\Diagnostics;

use App\Models\EvidenceExecution;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\CorrespondenceGateService;
use App\Services\Observables\PhaseProfileService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

/** Bounded update of the former solo Mediate diagnostic; not a search for Commit. */
class SoloCommitFrontierTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;

    public static function soloChoices(): array
    {
        return [
            'Refute and contributed Preparation' => [null, 'contribute'],
            'Support, Traverse and deferred Preparation' => ['support', 'defer'],
            'Refute, Traverse and Instrument' => ['refute', 'instrument'],
            'Support, Traverse and recorded unsuccessful Instrument' => ['support', 'failed_instrument'],
        ];
    }

    #[DataProvider('soloChoices')]
    public function test_phase_scoped_attempts_distinguish_immediate_and_deliberately_deferred_births(?string $judgment, string $disperse): void
    {
        [$actor] = $this->beginPersistJourney('solo-frontier');
        // Use the same bounded first branch, without the population fixture's
        // voluntary Persist Defer. Distinguish and Persist acquire in one Cycle.
        $steps = array_slice($this->persistJourneySteps, 0, 19);
        unset($steps[2], $steps[3], $steps[4]);
        $steps[8]['input'] = ['action' => 'attestation.create',
            'proposition_id' => ['ref' => 'leftDistinction'], 'direction' => 'refute'];
        foreach ($steps as &$entry) {
            if ($entry['cycle'] >= 3) {
                $entry['cycle']--;
            }
        }
        unset($entry);
        $this->persistJourneySteps = array_values($steps);
        $extraCycle = 0;
        foreach (range(0, 15) as $index) {
            $step = &$this->persistJourneySteps[$index];
            unset($step['entrant'], $step['entrant_judgment']);
            $step['cycle'] += $extraCycle;
            if ($step['input']['action'] === 'attestation.create' && $judgment !== null) {
                $step['input']['direction'] = $judgment;
            }
            if ($index === 8 && in_array($disperse, ['instrument', 'failed_instrument'], true)) {
                $reference = 'https://evidence.example.test/solo/disperse';
                if ($disperse === 'failed_instrument') {
                    Http::swap(new Factory);
                    Http::preventStrayRequests();
                    Http::fake([$reference => Http::response('Unavailable', 503)]);
                }
                $step['input'] = ['action' => 'instrument.execute', 'proposition_id' => ['ref' => 'leftCanon'],
                    'instrument' => ['kind' => 'hyperlink', 'reference' => $reference]];
                $step['receipt'] = $this->persistJourneySteps[9]['receipt'];
                unset($step['execution'], $this->persistJourneySteps[9]['receipt'],
                    $this->persistJourneySteps[9]['input']['execution_id']);
            }
            if ($index === 9 && $disperse === 'defer') {
                $id = $this->persistJourneyIds['execution10'];
                $this->contribute($actor, ['action' => 'preparation.defer', 'execution_id' => $id,
                    'reason' => 'Continue reading in a separate generative attempt.']);
                $this->assertDatabaseHas('cycles', ['id' => EvidenceExecution::findOrFail($id)->cycle_id, 'rested_at' => null]);
                $this->assertDatabaseCount('exploration_events', 0);
                foreach (['leftDistinction', 'leftPersist'] as $ref) {
                    $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $this->persistJourneyIds[$ref]]);
                }
                $this->contribute($actor, ['action' => 'attestation.create',
                    'proposition_id' => $this->persistJourneyIds['leftCanon'], 'direction' => $judgment]);
                $start = $this->contribute($actor, $this->persistJourneyInput(8));
                $this->persistJourneyIds['execution10'] = $start['plan']['preparation']['execution_id'];
                $this->assertNotSame($id, $this->persistJourneyIds['execution10']);
                $this->readAndAcknowledgePreparation($actor, $this->persistJourneyIds['execution10']);
                $extraCycle = 1;
                $step['cycle']++;
                $this->persistJourneySteps[13]['input'] = ['action' => 'proposition.traverse',
                    'proposition_id' => ['ref' => 'leftCanon']];
            }
            $state = $this->executePersistJourneyStep($actor, $index);
            if ($index === 8 && $disperse === 'failed_instrument') {
                $receipt = Receipt::findOrFail($this->persistJourneyIds['leftPreparation']);
                $this->assertFalse($receipt->metadata['content_verified']);
                $this->assertNull($receipt->signature);
            }
            unset($step);
        }
        $this->assertDatabaseCount('participants', 1);
        $ids = array_map(fn ($ref) => $this->persistJourneyIds[$ref], ['leftPersist', 'leftCanon', 'leftSignal']);
        $intervals = DB::table('phase_profile_observations')->whereIn('proposition_id', $ids)->pluck('cycle_interval', 'proposition_id');
        $this->assertSame([0, 1, $extraCycle], array_map(fn ($id) => (int) $intervals[$id], $ids));
        $head = Proposition::findOrFail($ids[2]);
        $gate = app(CorrespondenceGateService::class)->evaluate($head);
        $this->assertSame('negative', $gate['shape_topology_direction']);
        $this->assertSame($extraCycle ? 'negative' : 'positive', $gate['phase_profile_direction']);
        $this->assertSame((bool) $extraCycle, $gate['admissible']);
        $profile = app(PhaseProfileService::class)->inspect($head);
        $this->assertSame([0.0, 1.0, -1.0, $extraCycle ? -1.0 : 1.0, 0.0, 0.0, 0.0], $profile['contributions']);
        $this->assertSame(($extraCycle ? -1 : 1) / 7, $profile['phase_profile']);
        $this->assertDatabaseHas('exploration_events', ['proposition_id' => $head->id]);
        $this->assertActionAvailable($state, 'instrument.execute');
        $this->assertActionUnavailable($state, 'proposition.variant');
        $this->assertActionUnavailable($state, 'proposition.traverse');
        $this->assertActionUnavailable($state, 'attestation.create');
        $this->assertDatabaseMissing('supersessions', ['type' => 'reconcile']);
    }
}
