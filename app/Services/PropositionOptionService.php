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
    public function __construct(
        private readonly LineageService $lineage,
        private readonly ReconciliationOptionService $reconciliation,
    ) {}

    /** @return Collection<int, string> */
    public function localIds(Phase $phase): Collection
    {
        $gate = $phase->gate()->firstOrFail();

        return $this->lineage->neighbourIds($gate)->push($gate->getKey());
    }

    /** An effective personal Attestation establishes the forward traversal path. */
    public function traversalOptionsFor(Phase $phase): Collection
    {
        return $this->writeDescendants($phase)
            ->whereHas('effectiveAttestations.phase.cycle', fn ($query) => $query
                ->whereBelongsTo($phase->cycle->participant))
            ->orderBy('id')->get();
    }

    private function writeDescendants(Phase $phase): Builder
    {
        return Proposition::query()
            ->whereKey($this->lineage->immediateDescendantIds($phase->gate()->firstOrFail()))
            ->where('register', $phase->write_register)
            ->where('tier', 'expressive')
            ->whereNotIn('id', $phase->cycle->phases()
                ->whereNotNull('completed_at')->pluck('gate_proposition_id')->filter())
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where(
                'substrate_id', $phase->cycle->participant->substrate_id,
            ));
    }

    /** Bias can ground reconciled nodes even after a later Supersession. */
    public function instrumentOptionsFor(Phase $phase): Collection
    {
        if ($phase->kind !== PhaseKind::Bias) {
            return $this->activeHeads($phase)
                ->where('register', $phase->write_register)->where('tier', 'expressive')
                ->latest('created_at')->limit($this->limit())->get();
        }

        return $this->localExpressive($phase)->orderBy('id')->get();
    }

    private function localExpressive(Phase $phase): Builder
    {
        return Proposition::query()->whereKey($this->localIds($phase))
            ->where('register', $phase->write_register)->where('tier', 'expressive')
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where(
                'substrate_id', $phase->cycle->participant->substrate_id,
            ));
    }

    /**
     * @return Collection<int, Proposition>
     */
    public function writeOptionsFor(Phase $phase): Collection
    {
        if ($phase->write_register === null) {
            return collect();
        }

        return $this->writeDescendants($phase)->orderBy('id')->get();
    }

    /** Every valid Write descendant offers the as-yet-unattested direction(s). */
    public function attestationOptionsFor(Phase $phase): Collection
    {
        return $this->writeOptionsFor($phase);
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
            ->whereKey($this->lineage->immediateDescendantIds($phase->gate()->firstOrFail()))
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
        return $this->reconciliation->forPhase($phase);
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
