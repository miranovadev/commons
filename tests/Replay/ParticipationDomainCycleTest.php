<?php

namespace Tests\Replay;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('manual-replay')]
class ParticipationDomainCycleTest extends ScriptedParticipationTestCase
{
    public function test_one_participant_completes_settlement_emergence_settlement_through_http(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/Fixtures/solo-domain-cycle.json');
        $this->assertParticipationScriptComplete();
        $this->assertDomainRoundTrip($journey);
        $this->assertDatabaseCount('participants', 1);
    }

    public function test_interleaved_participants_complete_settlement_emergence_settlement_through_http(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/Fixtures/collective-domain-cycle.json');
        $this->assertParticipationScriptComplete();
        $this->assertDomainRoundTrip($journey);
        $this->assertDatabaseCount('participants', 3);
        $authors = DB::table('propositions as proposition')
            ->join('phases as phase', 'phase.id', '=', 'proposition.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->distinct()->pluck('cycle.participant_id')->all();
        $this->assertGreaterThanOrEqual(2, count($authors), 'The collective route has more than one structural author.');
        $crossAttestation = DB::table('attestations as a')->join('phases as ap', 'ap.id', '=', 'a.phase_id')
            ->join('cycles as ac', 'ac.id', '=', 'ap.cycle_id')->join('propositions as p', 'p.id', '=', 'a.proposition_id')
            ->join('phases as pp', 'pp.id', '=', 'p.phase_id')->join('cycles as pc', 'pc.id', '=', 'pp.cycle_id')
            ->whereColumn('ac.participant_id', '!=', 'pc.participant_id')->exists();
        $this->assertTrue($crossAttestation, "Participants attest another participant's actual work.");
        $shared = DB::table('receipt_consumptions as attachment')
            ->join('phases as consuming', 'consuming.id', '=', 'attachment.phase_id')
            ->join('cycles as consumer', 'consumer.id', '=', 'consuming.cycle_id')
            ->join('receipts as receipt', 'receipt.id', '=', 'attachment.receipt_id')
            ->join('phases as generating', 'generating.id', '=', 'receipt.phase_id')
            ->join('cycles as producer', 'producer.id', '=', 'generating.cycle_id')
            ->whereColumn('producer.participant_id', '!=', 'consumer.participant_id')->exists();
        $this->assertTrue($shared, 'Receipt consumption crosses participant ownership through the same API.');
        foreach ($journey['participants'] as $participant) {
            $this->assertTrue(DB::table('cycles')->where('participant_id', $participant)->whereNotNull('rest_reason')->exists(),
                'Each participant exercises actual Rest and recovery.');
        }
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
            $current = $this->readParticipation($participant);
            if (count($journey['participants']) === 1) {
                $this->assertSame($journey['states'][$name], $current);
            }
            // Other participants may change visible evidence, never this position.
            foreach (['current_proposition_id', 'context_horizon_id', 'domain'] as $key) {
                $this->assertSame($journey['states'][$name][$key], $current[$key]);
            }
            $this->assertSame($journey['states'][$name]['plan']['phase']['id'], $current['plan']['phase']['id']);
            $this->assertSame($journey['states'][$name]['cycle']['id'], $current['cycle']['id']);
        }
    }
}
