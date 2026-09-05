<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PropositionOptionService
{
    /**
     * @return Collection<int, Proposition>
     */
    public function writeOptionsFor(Phase $phase): Collection
    {
        if ($phase->write_register === null) {
            return collect();
        }

        return $this->activeHeads($phase)
            ->where('register', $phase->write_register)
            ->latest('created_at')
            ->limit($this->limit())
            ->get();
    }

    /**
     * @return Collection<int, Proposition>
     */
    public function gateOptionsFor(Phase $phase): Collection
    {
        if (! in_array(
            $phase->kind,
            [PhaseKind::Mediate, PhaseKind::Bias],
            true,
        )) {
            return collect();
        }

        return $this->activeHeads($phase)
            ->when(
                $phase->gate_proposition_id !== null,
                fn (Builder $query) => $query->whereKeyNot(
                    $phase->gate_proposition_id
                ),
            )
            ->latest('created_at')
            ->limit($this->limit())
            ->get();
    }

    private function activeHeads(Phase $phase): Builder
    {
        // Context Horizon membership, participant visibility, homeostatic
        // admissibility and predicted Tension reduction belong here once
        // their measurements are available.
        return Proposition::query()
            ->whereHas(
                'phase.cycle.participant',
                fn (Builder $query) => $query->where(
                    'substrate_id',
                    $phase->cycle->participant->substrate_id,
                ),
            )
            ->whereHas(
                'phase.cycle',
                fn (Builder $query) => $query->whereNull('completed_at'),
            )
            ->whereNotExists(
                fn ($query) => $query
                    ->select(DB::raw(1))
                    ->from('supersessions')
                    ->whereColumn(
                        'supersessions.source_proposition_id',
                        'propositions.id',
                    ),
            );
    }

    private function limit(): int
    {
        return max(1, (int) config('schemas.options.limit', 10));
    }
}
