<?php

namespace Tests\Feature\Api;

use App\Enums\PhaseKind;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('constitutional')]
class ParticipationLocalEpochTest extends ScriptedParticipationTestCase
{
    public function test_a_third_distinguish_variant_reopens_only_its_own_slot_through_http(): void
    {
        $journey = $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/local-epochs.json', 2);
        $actor = $journey['participants']['critical-explorer'];
        $observer = $journey['participants']['origin-observer'];
        $origin = $journey['responses'][0]['current_proposition_id'];
        $variants = 0;
        for ($step = 2; $step <= 83; $step++) {
            $opening = in_array($step, [2, 20, 83], true);
            if ($opening) {
                $before = $this->readParticipation($observer)['plan']['write_options'];
                $history = $this->failures();
                $disperse = $this->disperseEpochs();
                foreach ($before as $option) {
                    $this->assertTrue($option['spent'], 'Every existing option must genuinely be spent before another Variant.');
                }
            }
            $this->continueParticipationScript(1);
            if ($opening) {
                $variants++;
                $after = $this->readParticipation($observer);
                $this->assertSame($origin, $after['plan']['gate_id']);
                foreach ($before as $option) {
                    $current = $this->visibleProposition($after, $option['id']);
                    $this->assertSame($option['exploration_epoch'] + 1, $current['exploration_epoch']);
                    $this->assertFalse($current['spent']);
                }
                $this->assertSame($history, $this->failures());
                $this->assertSame($disperse, $this->disperseEpochs(), 'Distinguish does not advance any Disperse slot.');
            }
        }
        $this->assertParticipationScriptComplete();
        $this->assertSame(3, $variants, $this->traceMessage($this->readParticipation($actor)));
        $this->assertNotSame('[]', $this->failures());
        $this->assertCount(3, $this->readParticipation($observer)['plan']['write_options']);
    }

    private function failures(): string
    {
        return DB::table('continuation_failures')->orderBy('context_horizon_id')->orderBy('structural_position')
            ->orderBy('exploration_epoch')->orderBy('proposition_id')->get()->toJson();
    }

    private function disperseEpochs(): string
    {
        return DB::table('horizon_exploration_states')->where('structural_position', PhaseKind::Disperse->value)
            ->orderBy('context_horizon_id')->orderBy('structural_source_id')->get()->toJson();
    }
}
