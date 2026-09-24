<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;

/** Real Rest and Reclassify transitions for nested Horizon proof integration tests. */
trait HorizonProofFixture
{
    use ExhaustionFixture;

    protected function firstFailure(): array
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, boundary: true);
        $head = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->explore($head);
        $phase = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);
        $this->boundaryTragedyFixture($head);
        $this->rest($phase);

        return [$opening, $phase->cycle->contextHorizon, Tragedy::sole()];
    }

    protected function exercise($opening, $tragedy): array
    {
        $source = $this->node($this->phase($opening, PhaseKind::Persist), $opening);
        $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $phase = $this->phase($source, PhaseKind::Constrain);
        $result = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $tragedy->receipt_id]);
        $this->explore($result);
        $terminal = $this->phase($result);
        // Spent history stays in the measured field. This fixture asserts a
        // genuinely zero-Potential final attempt, including the original probe.
        foreach (Proposition::whereHas('phase.cycle', fn ($query) => $query
            ->where('context_horizon_id', $terminal->cycle->context_horizon_id))->where('tier', 'expressive')->get() as $node) {
            $this->measure($node, 1);
        }
        $this->measure($source, 1);
        $this->measure($result, 1);

        return [$terminal, $result];
    }
}
