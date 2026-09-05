<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Proposition;
use DomainException;

final readonly class GateService
{
    public function __construct(
        private CycleService $cycles,
    ) {}

    public function currentFor(Participant $participant): Proposition
    {
        $cycle = $this->cycles->currentFor($participant);
        $gate = $cycle->gate()->first();

        if ($gate === null) {
            throw new DomainException(
                'The initial gate awaits Potential Charge selection.'
            );
        }

        return $gate;
    }

    public function advance(Cycle $cycle, Proposition $proposition): void
    {
        $cycle->update([
            'gate_proposition_id' => $proposition->getKey(),
        ]);
    }
}
