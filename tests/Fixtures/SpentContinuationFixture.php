<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Phase;
use App\Services\HorizonReceiptService;
use App\Services\PotentialChargeService;
use App\Services\ReceiptContextService;

trait SpentContinuationFixture
{
    use ExhaustionFixture;

    private function testedLineage(): array
    {
        $distinguish = $this->phase($this->origin, PhaseKind::Distinguish);
        $signal = $this->node($distinguish, $this->origin);
        $this->explore($signal);
        $persist = $this->node($this->phase($signal, PhaseKind::Persist), $signal);
        $constrain = $this->node($this->phase($persist, PhaseKind::Constrain), $persist);
        $activity = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        $this->explore($activity);
        $terminal = $this->phase($activity, PhaseKind::Mediate);
        $this->measure($activity, 0);
        $this->assertTrue(app(PotentialChargeService::class)->hasRemainingInHorizon($terminal));

        $distinguish->forceFill(['completed_at' => now()])->save();
        $distinguish->cycle->forceFill(['completed_at' => now()])->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $distinguish->cycle->participant_id,
            'context_horizon_id' => $distinguish->cycle->context_horizon_id, 'sequence' => 2]);
        $cycle->save();
        $return = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
            'gate_proposition_id' => $this->origin->id, 'write_register' => $distinguish->write_register, 'started_at' => now()]);
        $return->save();
        foreach (app(HorizonReceiptService::class)->available($terminal) as $receipt) {
            app(ReceiptContextService::class)->read($terminal->cycle->participant, $terminal, $receipt->id);
        }
        $this->measure($activity, 0);
        $this->assertTrue(app(PotentialChargeService::class)->hasRemainingInHorizon($terminal));

        return [$signal, $activity, $terminal, $return];
    }
}
