<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use DomainException;

final readonly class GateService
{
    public function __construct(
        private PhaseService $phases,
    ) {}

    public function currentFor(Participant $participant): Proposition
    {
        return $this->forPhase($this->phases->currentFor($participant));
    }

    public function forPhase(Phase $phase): Proposition
    {
        $gate = $phase->gate()->first();

        if ($gate === null) {
            throw new DomainException(
                'The initial gate awaits Potential Charge selection.'
            );
        }

        return $gate;
    }

    public function advance(Phase $phase, Proposition $proposition): void
    {
        $phase->gate_proposition_id = $proposition->getKey();
        $phase->save();
    }
}
