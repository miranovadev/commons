<?php

namespace App\Services;

use App\Enums\Phase as PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use DomainException;

final readonly class PhaseService
{
    public function __construct(
        private CycleService $cycles,
    ) {}

    public function currentFor(Participant $participant): Phase
    {
        $cycle = $this->cycles->currentFor($participant);
        $phase = $cycle->phases()
            ->whereNull('completed_at')
            ->latest('position')
            ->first();

        if ($phase === null) {
            throw new DomainException(
                "Participant {$participant->getKey()} has no active phase."
            );
        }

        return $phase;
    }

    /**
     * @return array{
     *     name: string,
     *     gate: string,
     *     write_registers: array<string, string>,
     *     actions: list<string>,
     *     constraints?: array<string, list<string>>
     * }
     */
    public function plan(PhaseKind $phase): array
    {
        $plan = config("phases.{$phase->value}");

        if (! is_array($plan)) {
            throw new DomainException(
                "No input plan exists for phase {$phase->value}."
            );
        }

        return $plan;
    }
}
