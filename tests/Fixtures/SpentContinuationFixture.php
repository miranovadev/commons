<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\EvidenceExecutionService;
use App\Services\LedgerService;
use App\Services\PotentialChargeService;

trait SpentContinuationFixture
{
    use ExhaustionFixture;

    private function testedLineage(bool $separateObservation = false): array
    {
        $distinguish = $this->phase($this->origin, PhaseKind::Distinguish);
        $signal = $this->node($distinguish, $this->origin);
        $observed = $separateObservation
            ? $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin) : $signal;
        $observation = $this->phase($observed, PhaseKind::Persist);
        $instruments = app(EvidenceExecutionService::class);
        $instruments->complete($instruments->start($observation, $observed, 'citation', $this->origin->id), $observed);
        $this->judgeContinuation($signal);
        $persist = $this->node($this->phase($signal, PhaseKind::Persist), $signal);
        $constrain = $this->node($this->phase($persist, PhaseKind::Constrain), $persist);
        $activity = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        $this->judgeContinuation($activity);
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
        $this->measure($activity, 0);
        $this->assertTrue(app(PotentialChargeService::class)->hasRemainingInHorizon($terminal));

        return [$signal, $activity, $terminal, $return];
    }

    private function judgeContinuation(Proposition $node): void
    {
        $vote = (new Attestation)->forceFill([
            'phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'refute',
        ]);
        $vote->save();
        app(LedgerService::class)->record($vote);
    }
}
