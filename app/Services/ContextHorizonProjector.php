<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Enums\LedgerEntryKind;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ContextHorizonProjector
{
    /** Default for initial/unpositioned entry only; never a Cycle's current Domain. */
    public function currentFor(Substrate $substrate): ContextHorizon
    {
        $horizon = ContextHorizon::whereBelongsTo($substrate)->whereNull('closed_at')->latest('sequence')->first();
        if ($horizon !== null) {
            return $horizon;
        }

        return DB::transaction(function () use ($substrate): ContextHorizon {
            $locked = $this->lockSubstrate($substrate);
            $horizon = ContextHorizon::whereBelongsTo($locked)->whereNull('closed_at')->latest('sequence')->first();
            if ($horizon !== null) {
                return $horizon;
            }
            if (ContextHorizon::whereBelongsTo($locked)->exists()) {
                throw new DomainException('The substrate has no open Context Horizon for entry.');
            }

            return $this->createInitial($locked);
        });
    }

    /** A Committal enters its own Horizon, or the direct child it explicitly opened. */
    public function forEntry(Proposition $gate, ?ContextHorizon $expected = null): ContextHorizon
    {
        $gate = $gate->fresh(['phase.cycle.contextHorizon', 'commitmentSnapshot']);
        $owner = $gate?->phase?->cycle?->contextHorizon;
        if ($owner === null || $gate->tier !== 'committal' || $gate->commitmentSnapshot === null) {
            throw new DomainException('Cycle entry requires a persisted Committal with a Context Horizon and commitment snapshot.');
        }
        $snapshot = $gate->commitmentSnapshot;
        if ($snapshot->substrate_id !== $owner->substrate_id || $snapshot->direction_before !== $owner->domain) {
            throw new DomainException('The selected Committal must retain its creating Horizon direction.');
        }
        $child = ContextHorizon::where('opened_by_proposition_id', $gate->id)->first();
        if ($snapshot->isClosureBoundary()) {
            if ($child === null || $child->parent_context_horizon_id !== $owner->id) {
                throw new DomainException('A boundary Committal must explicitly open its direct child Context Horizon.');
            }
            $child->predecessorCommitment();
        } elseif ($child !== null) {
            throw new DomainException('A non-boundary Committal cannot open a Context Horizon.');
        }
        $horizon = $child ?? $owner;
        if ($expected !== null && $expected->id !== $horizon->id) {
            throw new DomainException('The selected Gate does not determine the requested entry Context Horizon.');
        }

        app(CausalContextService::class)->assertGate($gate, $horizon);

        return $horizon;
    }

    public function include(SubstrateLedgerEntry $entry): ContextHorizonMembership
    {
        if ($entry->kind !== LedgerEntryKind::Proposition) {
            throw new DomainException('Only proposition ledger entries belong to a Context Horizon.');
        }

        return DB::transaction(function () use ($entry): ContextHorizonMembership {
            $this->lockSubstrate($entry->substrate);

            return $this->includeLocked($entry);
        });
    }

    public function apply(CommitmentSnapshot $snapshot): ContextHorizon
    {
        return DB::transaction(function () use ($snapshot): ContextHorizon {
            $this->lockSubstrate($snapshot->substrate);
            $ownerId = $snapshot->proposition->phase->cycle->context_horizon_id;
            $horizon = ContextHorizon::query()->lockForUpdate()->findOrFail($ownerId);
            if ($horizon->substrate_id !== $snapshot->substrate_id) {
                throw new DomainException('A commitment cannot cross Context Horizon substrates.');
            }

            return $this->transitionLocked($snapshot, $horizon);
        });
    }

    /** Membership is disposable; Horizon IDs and Cycle ownership are durable. */
    public function rebuild(Substrate $substrate): int
    {
        return DB::transaction(function () use ($substrate): int {
            $locked = $this->lockSubstrate($substrate);
            app(FieldMutationService::class)->mark($locked->id, ContextHorizon::whereBelongsTo($locked)->pluck('id')->all());
            ContextHorizonMembership::whereIn('context_horizon_id', ContextHorizon::whereBelongsTo($locked)->select('id'))->delete();
            $snapshots = CommitmentSnapshot::whereBelongsTo($locked)->get()->keyBy('proposition_id');
            foreach (SubstrateLedgerEntry::whereBelongsTo($locked)->where('kind', LedgerEntryKind::Proposition->value)->oldest('sequence')->get() as $entry) {
                $this->includeLocked($entry);
                if ($snapshot = $snapshots->get($entry->ledgerable_id)) {
                    $this->apply($snapshot);
                }
            }
            if (! ContextHorizon::whereBelongsTo($locked)->exists()) {
                $this->createInitial($locked);
            }

            PropositionMeasurement::whereHas('proposition.phase.cycle.contextHorizon', fn ($q) => $q->where('substrate_id', $locked->id))
                ->update(['calculator_version' => null]);

            return ContextHorizon::whereBelongsTo($locked)->count();
        });
    }

    private function includeLocked(SubstrateLedgerEntry $entry): ContextHorizonMembership
    {
        $proposition = Proposition::with('phase.cycle.contextHorizon')->findOrFail($entry->ledgerable_id);
        $horizon = $proposition->phase->cycle->contextHorizon;
        if ($horizon === null || $horizon->substrate_id !== $entry->substrate_id) {
            throw new DomainException('A proposition must belong to its creating Cycle Context Horizon.');
        }
        $membership = ContextHorizonMembership::find($proposition->id);
        if ($membership !== null) {
            if ($membership->context_horizon_id !== $horizon->id || $membership->ledger_entry_id !== $entry->id) {
                throw new DomainException('The proposition already belongs to another Context Horizon.');
            }

            return $membership;
        }
        $membership = new ContextHorizonMembership;
        $membership->forceFill([
            'proposition_id' => $proposition->id,
            'context_horizon_id' => $horizon->id,
            'ledger_entry_id' => $entry->id,
            'projected_at' => now(),
        ])->save();

        return $membership;
    }

    private function transitionLocked(CommitmentSnapshot $snapshot, ContextHorizon $horizon): ContextHorizon
    {
        if ($snapshot->direction_before !== $horizon->domain) {
            throw new DomainException('The commitment direction does not match its Cycle Context Horizon.');
        }
        if (! $snapshot->isClosureBoundary()) {
            return $horizon;
        }
        if ($snapshot->direction_after !== $horizon->domain->opposite()) {
            throw new DomainException('A boundary must alternate Domain relative to its parent Horizon.');
        }
        $existing = ContextHorizon::where('opened_by_proposition_id', $snapshot->proposition_id)->first();
        if ($existing !== null) {
            if ($existing->parent_context_horizon_id !== $horizon->id) {
                throw new DomainException('The boundary already opens a different Horizon lineage.');
            }

            return $existing;
        }
        // Closure records a boundary, not a ban on causal backtracking. Retain the first closing Commit; all later
        // boundaries are represented by independently opened child Horizons.
        if ($horizon->closed_at === null) {
            $horizon->closed_by_proposition_id = $snapshot->proposition_id;
            $horizon->closed_at = $snapshot->evaluated_at;
            $horizon->save();
        }
        $next = new ContextHorizon;
        $next->forceFill([
            'substrate_id' => $horizon->substrate_id,
            'parent_context_horizon_id' => $horizon->id,
            // Sequence is a display/order identifier, never an ancestry relation.
            'sequence' => (int) ContextHorizon::where('substrate_id', $horizon->substrate_id)->max('sequence') + 1,
            'domain' => $horizon->domain->opposite(),
            'opened_by_proposition_id' => $snapshot->proposition_id,
            'opened_at' => $snapshot->evaluated_at,
        ])->save();

        app(FieldMutationService::class)->mark($horizon->substrate_id, [$horizon->id, $next->id], [$snapshot->proposition_id]);

        return $next;
    }

    private function createInitial(Substrate $substrate): ContextHorizon
    {
        $horizon = new ContextHorizon;
        $horizon->forceFill([
            'substrate_id' => $substrate->id, 'sequence' => 1,
            'domain' => ContextDomain::Emergence,
            'opened_at' => $substrate->created_at ?? now(),
        ])->save();

        DB::table('potential_projection_states')->insertOrIgnore(['substrate_id' => $substrate->id]);
        app(FieldMutationService::class)->mark($substrate->id, [$horizon->id]);

        return $horizon;
    }

    private function lockSubstrate(Substrate $substrate): Substrate
    {
        return Substrate::query()->lockForUpdate()->findOrFail($substrate->id);
    }
}
