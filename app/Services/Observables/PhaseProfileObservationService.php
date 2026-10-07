<?php

namespace App\Services\Observables;

use App\Enums\LedgerEntryKind;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Models\SubstrateLedgerEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Raw structural observations, independent of Chirality's Attestation intervals. */
class PhaseProfileObservationService
{
    /** @internal Only LedgerService's new-entry branch, under its substrate lock. */
    public function recordBirth(Proposition $proposition, SubstrateLedgerEntry $entry): void
    {
        if (! $entry->wasRecentlyCreated || DB::transactionLevel() === 0
            || $entry->kind !== LedgerEntryKind::Proposition
            || $entry->ledgerable_id !== $proposition->id) {
            throw new DomainException('Phase Profile observations require a new proposition ledger birth.');
        }
        $horizonId = $proposition->phase->cycle->context_horizon_id;
        $cycles = Cycle::where('context_horizon_id', $horizonId);
        // This existing immutable boundary says Cycle entry occurred after S
        // and before S+1. Read it only; never reuse Chirality's interval state.
        $position = (clone $cycles)->whereNull('chirality_entry_after_sequence')->exists()
            ? null
            : (clone $cycles)->where('chirality_entry_after_sequence', '<', $entry->sequence)->count();

        $previous = DB::table('substrate_ledger_entries as l')
            ->join('propositions as p', 'p.id', '=', 'l.ledgerable_id')
            ->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->join('cycles as c', 'c.id', '=', 'ph.cycle_id')
            ->leftJoin('phase_profile_observations as o', 'o.proposition_id', '=', 'p.id')
            ->where('l.substrate_id', $entry->substrate_id)
            ->where('l.ledgerable_type', $proposition->getMorphClass())
            ->where('l.kind', LedgerEntryKind::Proposition->value)
            ->where('c.context_horizon_id', $horizonId)
            ->where('l.sequence', '<', $entry->sequence)
            ->orderByDesc('l.sequence')->first(['o.developmental_cycle_position']);
        $priorPosition = $previous === null ? 0 : $previous->developmental_cycle_position;

        // A legacy gap is explicit diagnostic unavailability, never a fabricated
        // first interval or a new production actionability restriction.
        DB::table('phase_profile_observations')->insert([
            'proposition_id' => $proposition->id,
            'developmental_cycle_position' => $position,
            'cycle_interval' => $position === null || $priorPosition === null ? null : $position - (int) $priorPosition,
        ]);
    }
}
