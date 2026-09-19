<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('constitutional')]
class ParticipationDomainCycleTest extends ScriptedParticipationTestCase
{
    public function test_one_participant_completes_settlement_emergence_settlement_through_http(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/solo-domain-cycle.json');
        $this->assertParticipationScriptComplete();
        $this->assertDomainRoundTrip($journey);
        $this->assertDatabaseCount('participants', 1);
    }

    private function assertDomainRoundTrip(array $journey): void
    {
        $phases = [];
        $horizons = [];
        foreach ($journey['responses'] as $state) {
            $horizon = $state['context_horizon_id'];
            $phases[$horizon][$state['plan']['phase']['kind']] = true;
            $horizons[$horizon] = $state['domain'];
        }
        $this->assertSame(['settlement', 'emergence', 'settlement'], array_values($horizons));
        foreach (array_slice(array_keys($horizons), 0, 2) as $horizon) {
            foreach (['distinguish', 'persist', 'constrain', 'disperse', 'mediate', 'bias', 'order'] as $phase) {
                $this->assertArrayHasKey($phase, $phases[$horizon]);
            }
        }
        $chain = array_keys($horizons);
        foreach ([1, 2] as $index) {
            $horizon = DB::table('context_horizons')->where('id', $chain[$index])->sole();
            $this->assertSame($chain[$index - 1], $horizon->parent_context_horizon_id);
            $this->assertDatabaseHas('commitment_snapshots', [
                'proposition_id' => $horizon->opened_by_proposition_id,
                'direction_before' => $horizons[$chain[$index - 1]],
                'direction_after' => $horizons[$chain[$index]],
            ]);
            $opened = collect($journey['responses'])->firstWhere('context_horizon_id', $horizon->id);
            $this->assertSame($horizon->opened_by_proposition_id, $opened['current_proposition_id']);
            $this->assertSame($opened['current_proposition_id'], $opened['plan']['gate_id']);
            $this->assertSame('distinguish', $opened['plan']['phase']['kind']);
        }
        foreach ($journey['participants'] as $name => $participant) {
            $this->assertSame($journey['states'][$name], $this->readParticipation($participant));
        }
    }
}
