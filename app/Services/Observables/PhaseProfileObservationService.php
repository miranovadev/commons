<?php

namespace App\Services\Observables;

use App\Enums\LedgerEntryKind;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Models\SubstrateLedgerEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

class PhaseProfileObservationService
{
    /**
     * Called only for a new ledger birth, under the existing substrate lock.
     * The dispatcher supplies the already-authorised causal source: the actual
     * source for unary writes, the local Gate for Reconcile, never its operand.
     * Edges follow in the same transaction. No query of incomplete edges or recency.
     */
    public function recordBirth(Proposition $proposition, SubstrateLedgerEntry $entry, ?Proposition $source): void
    {
        if (! $entry->wasRecentlyCreated || DB::transactionLevel() === 0
            || $entry->kind !== LedgerEntryKind::Proposition || $entry->ledgerable_id !== $proposition->id) {
            throw new DomainException('Phase Profile observations require a new Proposition ledger birth.');
        }
        $cycle = $proposition->phase->cycle;
        // Origin's profile is constitutional. Do not create empirical Origin intervals.
        if ($proposition->tier === 'committal' && $cycle->entry_committal_id === null) {
            return;
        }
        $cycles = Cycle::where('entry_committal_id', $cycle->entry_committal_id)
            ->where('context_horizon_id', $cycle->context_horizon_id);
        // This existing immutable marker records entry after S and before S+1.
        // Only its event order is used; Chirality state/cursors are never inputs.
        $coordinate = $cycle->entry_committal_id === null || (clone $cycles)->whereNull('chirality_entry_after_sequence')->exists()
            ? null : (clone $cycles)->where('chirality_entry_after_sequence', '<', $entry->sequence)->count();
        [$referenceId, $reason] = $this->reference($proposition, $source);
        $reference = $referenceId === null ? null : $this->stored($referenceId);
        $interval = null;
        if ($coordinate === null) {
            $reason = 'missing_cycle_history';
        } elseif ($referenceId !== null) {
            if ($reference?->birth_cycle_coordinate === null) {
                $reason = 'missing_reference_history';
            } elseif ($coordinate < (int) $reference->birth_cycle_coordinate) {
                $reason = 'invalid_coordinate_order';
            } else {
                $interval = $coordinate - (int) $reference->birth_cycle_coordinate;
                $reason = null;
            }
        }
        DB::table('phase_profile_observations')->insert([
            'proposition_id' => $proposition->id,
            'birth_cycle_coordinate' => $coordinate,
            'preceding_phase_proposition_id' => $referenceId,
            'cycle_interval' => $interval,
            'unavailable_reason' => $reason,
        ]);
    }

    public function stored(string $propositionId): ?object
    {
        return DB::table('phase_profile_observations')->where('proposition_id', $propositionId)->first();
    }

    /** @return array{?string, ?string} */
    private function reference(Proposition $target, ?Proposition $source): array
    {
        if ($source === null) {
            // Direct/legacy ledger callers did not supply authorised operation context.
            // Do not guess from the acting Gate, which need not be a causal parent.
            return [null, 'missing_source_context'];
        }
        if ($source->tier === 'committal') {
            return [null, 'first_expressive_observation'];
        }
        $cycle = $target->phase->cycle;
        if ($source->phase->cycle->entry_committal_id !== $cycle->entry_committal_id
            || $source->phase->cycle->context_horizon_id !== $cycle->context_horizon_id) {
            return [null, 'no_local_predecessor'];
        }
        $phase = $target->phase->kind->value;
        if ($source->phase->kind->value === $phase) {
            // Same-Phase Revision retains its original preceding-Phase reference.
            $prior = $this->stored($source->id);
            return $prior === null
                ? [null, 'missing_reference_history']
                : [$prior->preceding_phase_proposition_id, $prior->unavailable_reason];
        }
        return $source->phase->kind->value === $phase - 1
            ? [$source->id, null]
            : [null, 'no_preceding_phase_observation'];
    }
}
