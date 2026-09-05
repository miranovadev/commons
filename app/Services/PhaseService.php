<?php

namespace App\Services;

use App\Enums\Phase;
use App\Models\Participant;
use DomainException;

final readonly class PhaseService
{
    public function __construct(
        private CycleService $cycles,
    ) {}

    public function currentFor(Participant $participant): Phase
    {
        return $this->cycles->currentFor($participant)->current_phase;
    }

    /**
     * @return array{
     *     name: string,
     *     gate: string,
     *     write_registers: array<string, string>,
     *     actions: list<string>,
     *     constraints?: array<string, string>
     * }
     */
    public function plan(Phase $phase): array
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
