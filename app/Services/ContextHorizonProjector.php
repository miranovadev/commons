<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Enums\LedgerEntryKind;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ContextHorizonProjector
{
    public function currentFor(Substrate $substrate): ContextHorizon
    {
        return DB::transaction(function () use ($substrate): ContextHorizon {
            $locked = $this->lockSubstrate($substrate);

            return $this->currentLocked($locked);
        });
    }

    public function include(
        SubstrateLedgerEntry $entry,
    ): ContextHorizonMembership {
        if ($entry->kind !== LedgerEntryKind::Proposition) {
            throw new DomainException(
                'Only proposition ledger entries belong to a Context Horizon.'
            );
        }

        return DB::transaction(function () use (
            $entry,
        ): ContextHorizonMembership {
            $substrate = $this->lockSubstrate($entry->substrate);
            $horizon = $this->currentLocked($substrate);

            return $this->includeLocked($entry, $horizon);
        });
    }

    public function apply(CommitmentSnapshot $snapshot): ContextHorizon
    {
        return DB::transaction(function () use ($snapshot): ContextHorizon {
            $substrate = $this->lockSubstrate($snapshot->substrate);
            $horizon = $this->currentLocked($substrate);

            return $this->transitionLocked($snapshot, $horizon);
        });
    }

    public function rebuild(Substrate $substrate): int
    {
        return DB::transaction(function () use ($substrate): int {
            $locked = $this->lockSubstrate($substrate);

            $this->rebuildLocked($locked);

            return ContextHorizon::query()
                ->whereBelongsTo($locked)
                ->count();
        });
    }

    private function rebuildLocked(Substrate $substrate): ContextHorizon
    {
        ContextHorizon::query()
            ->whereBelongsTo($substrate)
            ->delete();

        $horizon = $this->createInitial($substrate);
        $snapshots = CommitmentSnapshot::query()
            ->whereBelongsTo($substrate)
            ->get()
            ->keyBy('proposition_id');

        $entries = SubstrateLedgerEntry::query()
            ->whereBelongsTo($substrate)
            ->where('kind', LedgerEntryKind::Proposition->value)
            ->oldest('sequence')
            ->get();

        foreach ($entries as $entry) {
            $this->includeLocked($entry, $horizon);

            $snapshot = $snapshots->get($entry->ledgerable_id);

            if ($snapshot !== null) {
                $horizon = $this->transitionLocked(
                    $snapshot,
                    $horizon,
                );
            }
        }

        return $horizon;
    }

    private function includeLocked(
        SubstrateLedgerEntry $entry,
        ContextHorizon $horizon,
    ): ContextHorizonMembership {
        $proposition = Proposition::query()
            ->findOrFail($entry->ledgerable_id);
        $membership = ContextHorizonMembership::query()
            ->find($proposition->getKey());

        if ($membership !== null) {
            if (
                $membership->context_horizon_id !== $horizon->getKey()
                || $membership->ledger_entry_id !== $entry->getKey()
            ) {
                throw new DomainException(
                    'The proposition already belongs to another Context Horizon.'
                );
            }

            return $membership;
        }

        $membership = new ContextHorizonMembership;
        $membership->forceFill([
            'proposition_id' => $proposition->getKey(),
            'context_horizon_id' => $horizon->getKey(),
            'ledger_entry_id' => $entry->getKey(),
            'projected_at' => now(),
        ]);
        $membership->save();

        return $membership;
    }

    private function transitionLocked(
        CommitmentSnapshot $snapshot,
        ContextHorizon $horizon,
    ): ContextHorizon {
        if ($snapshot->direction_before !== $horizon->domain) {
            throw new DomainException(
                'The commitment direction does not match the active Context Horizon.'
            );
        }

        if (! $snapshot->isClosureBoundary()) {
            return $horizon;
        }

        $horizon->closed_by_proposition_id = $snapshot->proposition_id;
        $horizon->closed_at = $snapshot->evaluated_at;
        $horizon->save();

        $next = new ContextHorizon;
        $next->forceFill([
            'substrate_id' => $horizon->substrate_id,
            'sequence' => $horizon->sequence + 1,
            'domain' => $snapshot->direction_after,
            'opened_by_proposition_id' => $snapshot->proposition_id,
            'opened_at' => $snapshot->evaluated_at,
        ]);
        $next->save();

        return $next;
    }

    private function currentLocked(Substrate $substrate): ContextHorizon
    {
        $horizon = ContextHorizon::query()
            ->whereBelongsTo($substrate)
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();

        if ($horizon !== null) {
            return $horizon;
        }

        $hasLedgerHistory = SubstrateLedgerEntry::query()
            ->whereBelongsTo($substrate)
            ->exists();

        return $hasLedgerHistory
            ? $this->rebuildLocked($substrate)
            : $this->createInitial($substrate);
    }

    private function createInitial(Substrate $substrate): ContextHorizon
    {
        $horizon = new ContextHorizon;
        $horizon->forceFill([
            'substrate_id' => $substrate->getKey(),
            'sequence' => 1,
            'domain' => ContextDomain::Emergence,
            'opened_at' => $substrate->created_at ?? now(),
        ]);
        $horizon->save();

        return $horizon;
    }

    private function lockSubstrate(Substrate $substrate): Substrate
    {
        return Substrate::query()
            ->lockForUpdate()
            ->findOrFail($substrate->getKey());
    }
}
