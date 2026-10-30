<?php

namespace Tests\Diagnostics;

use App\Models\Proposition;
use App\Services\CorrespondenceGateService;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

/** Isolate the population assumption in the positive single-Participant Commit proof. */
class SoloCommitFrontierTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;

    public function test_two_personally_completed_solo_branches_reach_the_same_incoherent_mediate_frontier(): void
    {
        [$actor] = $this->beginPersistJourney('solo-frontier');
        foreach ($this->persistJourneySteps as $index => $step) {
            // Run the author's same real actions, with no other Participant entering.
            unset($this->persistJourneySteps[$index]['entrant']);
            $state = $this->executePersistJourneyStep($actor, $index);
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
        }
        $this->assertActionAvailable($state, 'proposition.variant');
        $this->assertActionUnavailable($state, 'proposition.traverse');
        $this->assertActionUnavailable($state, 'attestation.create');
        $this->assertDatabaseMissing('supersessions', ['type' => 'reconcile']);
    }
}
