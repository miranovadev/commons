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

/** Isolate the population assumption in the positive single-Participant Commit proof. */
class SoloCommitFrontierTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;

    public static function soloChoices(): array
    {
        return [
            'original mixed judgments and contributed Preparation' => [null, 'contribute', false],
            'Support, Traverse and deferred Preparation' => ['support', 'defer', true],
            'Refute, reversals and Instrument' => ['refute', 'instrument', false],
            'Support, Traverse and recorded unsuccessful Instrument' => ['support', 'failed_instrument', true],
        ];
    }

    #[DataProvider('soloChoices')]
    public function test_two_personally_completed_solo_branches_reach_the_same_incoherent_mediate_frontier(?string $judgment, string $disperse, bool $traverse): void
    {
        [$actor] = $this->beginPersistJourney('solo-frontier');
        $judged = [];
        foreach (array_keys($this->persistJourneySteps) as $index) {
            $step = $this->persistJourneySteps[$index];
            $input = $step['input'];
            if ($input['action'] === 'attestation.create' && $judgment !== null) {
                $target = $input['proposition_id']['ref'];
                if (isset($judged[$target]) && $traverse) {
                    $input = ['action' => 'proposition.traverse', 'proposition_id' => ['ref' => $target]];
                } else {
                    $input['direction'] = isset($judged[$target])
                        ? ($judged[$target] === 'support' ? 'refute' : 'support') : $judgment;
                    $judged[$target] = $input['direction'];
                }
                $this->persistJourneySteps[$index]['input'] = $input;
            }
            if ($step['phase'] === 4 && $input['action'] === 'preparation.start'
                && in_array($disperse, ['instrument', 'failed_instrument'], true)) {
                $reference = 'https://evidence.example.test/solo/'.$step['gate'];
                if ($disperse === 'failed_instrument') {
                    Http::swap(new Factory);
                    Http::preventStrayRequests();
                    Http::fake([$reference => Http::response('Unavailable', 503)]);
                }
                $receipt = $this->persistJourneySteps[$index + 1]['receipt'];
                $this->persistJourneySteps[$index]['input'] = ['action' => 'instrument.execute',
                    'proposition_id' => ['ref' => $step['gate']],
                    'instrument' => ['kind' => 'hyperlink', 'reference' => $reference]];
                $this->persistJourneySteps[$index]['receipt'] = $receipt;
                unset($this->persistJourneySteps[$index]['execution']);
                unset($this->persistJourneySteps[$index + 1]['receipt'],
                    $this->persistJourneySteps[$index + 1]['input']['execution_id']);
            }
            if ($step['phase'] === 4 && $input['action'] === 'proposition.variant' && $disperse === 'defer') {
                $state = $this->contribute($actor, ['action' => 'preparation.defer',
                    'execution_id' => $this->persistJourneyIds[$input['execution_id']['ref']],
                    'reason' => 'Complete examination before choosing structural continuation.']);
                $receipt = EvidenceExecution::findOrFail($this->persistJourneyIds[$input['execution_id']['ref']])->receipt;
                $this->persistJourneyIds[$step['receipt']] = $receipt->id;
                unset($this->persistJourneySteps[$index]['receipt'],
                    $this->persistJourneySteps[$index]['input']['execution_id']);
                $this->assertSame(['proposition.variant'], array_keys($this->actionDefinitions($state)));
            }
            // Run the author's same real actions, with no other Participant entering.
            unset($this->persistJourneySteps[$index]['entrant']);
            $before = $this->readParticipation($actor);
            if (in_array($index, [3, 7, 19, 23], true)) {
                $expected = in_array($index, [3, 19], true) ? 'proposition.variant' : 'proposition.reclassify';
                $this->assertSame([$expected], array_keys($this->actionDefinitions($before)));
                $this->postContribution($actor, $before['plan']['phase']['id'], ['action' => 'participant.rest'])
                    ->assertUnprocessable();
            }
            $state = $this->executePersistJourneyStep($actor, $index);
            if ($disperse === 'failed_instrument' && $step['phase'] === 4 && $input['action'] === 'preparation.start') {
                $observation = Receipt::findOrFail($this->persistJourneyIds[$receipt]);
                $this->assertFalse($observation->metadata['content_verified']);
                $this->assertNull($observation->signature);
                $this->assertSame('completed', $observation->evidenceExecution->status);
            }
            if ($step['result'] === 'leftSignal' && $step['input']['action'] === 'attestation.create') {
                $state = $this->contribute($actor, ['action' => 'participant.rest'], $state);
                break;
            }
        }

        $this->assertDatabaseCount('participants', 1);
        foreach ([['p2', 'p3', 'p4'], ['leftPersist', 'leftCanon', 'leftSignal']] as $route) {
            $ids = array_map(fn ($ref) => $this->persistJourneyIds[$ref], $route);
            $intervals = DB::table('phase_profile_observations')->whereIn('proposition_id', $ids)
                ->pluck('cycle_interval', 'proposition_id');
            $this->assertSame([0, 1, 0], array_map(fn ($id) => (int) $intervals[$id], $ids));
            $gate = app(CorrespondenceGateService::class)->evaluate(Proposition::findOrFail($ids[2]));
            $this->assertSame('negative', $gate['shape_topology_direction']);
            $this->assertSame('positive', $gate['phase_profile_direction']);
            $this->assertSame('component_directions_disagree', $gate['reason']);
            $this->assertDatabaseHas('exploration_events', ['proposition_id' => $ids[2]]);
            $profile = app(PhaseProfileService::class)->inspect(Proposition::findOrFail($ids[2]));
            $this->assertSame([0.0, 1.0, -1.0, 1.0, 0.0, 0.0, 0.0], $profile['contributions']);
            $this->assertSame(1 / 7, $profile['phase_profile']);
        }
        $this->assertActionAvailable($state, 'proposition.variant');
        $this->assertActionUnavailable($state, 'proposition.traverse');
        $this->assertActionUnavailable($state, 'attestation.create');
        $this->assertDatabaseMissing('supersessions', ['type' => 'reconcile']);
    }
}
