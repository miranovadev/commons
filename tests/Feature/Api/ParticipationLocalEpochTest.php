<?php

namespace Tests\Feature\Api;

use App\Enums\PhaseKind;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('http-participation')]
class ParticipationLocalEpochTest extends ParticipationHttpTestCase
{
    public function test_a_third_distinguish_variant_reopens_only_its_own_slot_through_http(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('critical-explorer');
        $origin = $this->enterParticipant($actor, true)['current_proposition_id'];
        $observer = $this->joinParticipant('origin-observer');
        $this->enterParticipant($observer);
        $variants = 0;
        for ($step = 0; $step < 140 && $variants < 3; $step++) {
            $state = $this->readParticipation($actor);
            $input = $this->chooseContribution($state);
            if ($state['plan']['phase']['kind'] === 'mediate' && $input['action'] === 'attestation.create') {
                $refute = collect($this->actionDefinitions($state)['attestation.create']['anyOf'])
                    ->first(fn ($choice) => $choice['properties']['direction']['enum'][0] === 'refute');
                if ($refute !== null) {
                    $input['direction'] = 'refute';
                    $input['proposition_id'] = $refute['properties']['proposition_id']['enum'][0];
                } elseif (isset($this->actionDefinitions($state)['instrument.execute'])) {
                    // Ground the refuted reconciliation itself before its natural Bias Rest.
                    $input = ['action' => 'instrument.execute',
                        'proposition_id' => $this->actionDefinitions($state)['instrument.execute']['properties']['proposition_id']['enum'][0],
                        'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/reconciliation/'.$step]];
                }
            }
            $opening = $state['plan']['phase']['kind'] === 'distinguish' && $state['plan']['gate_id'] === $origin
                && $input['action'] === 'proposition.variant';
            if ($opening) {
                $before = $this->readParticipation($observer)['plan']['write_options'];
                $history = $this->failures();
                $disperse = $this->disperseEpochs();
                foreach ($before as $option) {
                    $this->assertTrue($option['spent'], 'Every existing option must genuinely be spent before another Variant.');
                }
            }
            $this->contribute($actor, $input);
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
