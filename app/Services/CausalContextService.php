<?php

namespace App\Services;

use App\Enums\RegisterPosition;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use DomainException;

/** Validate persisted ownership, independently of projections and action schemas. */
final class CausalContextService
{
    public function assertGate(Proposition $gate, ContextHorizon $horizon): void
    {
        $owner = $gate->phase()->firstOrFail()->cycle()->firstOrFail()->contextHorizon()->firstOrFail();
        if ($owner->substrate_id !== $horizon->substrate_id) {
            throw new DomainException('A Gate cannot cross Context Horizon substrates.');
        }
        if ($owner->id === $horizon->id) {
            return;
        }
        if ($gate->tier !== 'committal' || $horizon->opened_by_proposition_id !== $gate->id
            || $horizon->parent_context_horizon_id !== $owner->id) {
            throw new DomainException('A Gate must belong to the Cycle Context Horizon or explicitly open it.');
        }
        $horizon->predecessorCommitment();
    }

    public function assertRegister(string $register, ContextHorizon $horizon): void
    {
        $registers = array_map($horizon->domain->registerAt(...), RegisterPosition::cases());
        if (! in_array($register, $registers, true)) {
            throw new DomainException('The register must belong to the creating Cycle Context Horizon Domain.');
        }
    }

    public function assertPhase(Phase $phase): ContextHorizon
    {
        // Query ownership afresh: a stale/injected Eloquent relation cannot select Domain.
        $horizon = $phase->cycle()->firstOrFail()->contextHorizon()->firstOrFail();
        if ($phase->write_register !== null) {
            $this->assertRegister($phase->write_register, $horizon);
        }
        if ($phase->gate_proposition_id !== null) {
            $this->assertGate($phase->gate()->firstOrFail(), $horizon);
        }

        return $horizon;
    }
}
