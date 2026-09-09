<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PropositionOptionService
{
    public function __construct(private readonly LineageService $lineage) {}

    /** @return Collection<int, string> */
    public function localIds(Phase $phase): Collection
    {
        $gate = $phase->gate()->firstOrFail();

        return $this->lineage->neighbourIds($gate)->push($gate->getKey());
    }

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
            ->where('tier', 'expressive')
            ->latest('created_at')
            ->limit($this->limit())
            ->get();
    }

    /** Attestations target local expressive propositions, not just active heads. */
    public function attestationOptionsFor(Phase $phase): Collection
    {
        if (! in_array($phase->kind, [PhaseKind::Persist, PhaseKind::Constrain], true)) {
            return collect();
        }

        return Proposition::query()->whereKey($this->localIds($phase))
            ->where('register', $phase->write_register)->where('tier', 'expressive')
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where(
                'substrate_id', $phase->cycle->participant->substrate_id,
            ))
            ->orderBy('id')->get();
    }

    /**
     * Revision is an ownership-preserving move within a register. It cannot
     * target the Gate merely because the Gate belongs to the Participant.
     *
     * @return Collection<int, Proposition>
     */
    public function revisionOptionsFor(
        Phase $phase,
        Participant $participant,
    ): Collection {
        if ($phase->write_register === null) {
            return collect();
        }

        return $this->activeHeads($phase)
            ->where('register', $phase->write_register)
            ->where('tier', 'expressive')
            ->whereKeyNot($phase->gate_proposition_id)
            ->whereHas(
                'phase.cycle',
                fn (Builder $query) => $query->whereBelongsTo($participant),
            )
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
            ->whereKey($this->localIds($phase))
            ->whereHas(
                'phase.cycle.participant',
                fn (Builder $query) => $query->where(
                    'substrate_id',
                    $phase->cycle->participant->substrate_id,
                ),
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
