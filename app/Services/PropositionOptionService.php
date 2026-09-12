<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PropositionOptionService
{
    public function __construct(
        private readonly LineageService $lineage,
        private readonly ReconciliationOptionService $reconciliation,
        private readonly BiasGateService $bias,
        private readonly ClosureOptionService $closure,
    ) {}

    /** @return Collection<int, string> */
    public function localIds(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $gate = ($context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->firstOrFail());

        return ($context !== null ? $context->parents->get($gate->id, collect())->pluck('source_proposition_id')
            ->merge($context->children->get($gate->id, collect())->pluck('target_proposition_id'))
            ->reject(fn ($id) => $id === $gate->id)->unique()->values() : $this->lineage->neighbourIds($gate))->push($gate->getKey());
    }

    /** An effective personal Attestation establishes the forward traversal path. */
    public function traversalOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind->value > PhaseKind::Constrain->value) {
            return collect();
        }

        return $this->writeDescendants($phase, $context)
            ->whereHas('effectiveAttestations.phase.cycle', fn ($query) => $query
                ->whereBelongsTo($phase->cycle->participant))
            ->orderBy('id')->get();
    }

    private function writeDescendants(Phase $phase, ?ParticipationContext $context = null): Builder
    {
        return Proposition::query()
            ->whereKey($this->descendantIds($phase, $context))
            ->where('register', $phase->write_register)
            ->where('tier', 'expressive')
            ->whereNotIn('id', $phase->cycle->phases()
                ->whereNotNull('completed_at')->pluck('gate_proposition_id')->filter())
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where(
                'substrate_id', $phase->cycle->participant->substrate_id,
            ));
    }

    /** Bias can ground existing Concord/Canon nodes even after a later Supersession. */
    public function instrumentOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Bias) {
            return $this->activeHeads($phase, $context)
                ->where('register', $phase->write_register)->where('tier', 'expressive')
                ->latest('created_at')->limit($this->limit())->get();
        }

        return $this->localExpressive($phase, $context)->orderBy('id')->get();
    }

    private function localExpressive(Phase $phase, ?ParticipationContext $context = null): Builder
    {
        return Proposition::query()->whereKey($this->localIds($phase, $context))
            ->where('register', $phase->write_register)->where('tier', 'expressive')
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where(
                'substrate_id', $phase->cycle->participant->substrate_id,
            ));
    }

    /**
     * @return Collection<int, Proposition>
     */
    public function writeOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->write_register === null) {
            return collect();
        }

        return $phase->kind === PhaseKind::Order
            ? $this->closure->forPhase($phase, $context)
            : $this->writeDescendants($phase, $context)->orderBy('id')->get();
    }

    /** Every valid Write descendant offers the as-yet-unattested direction(s). */
    public function attestationOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $this->writeOptionsFor($phase, $context);
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
        ?ParticipationContext $context = null,
    ): Collection {
        if ($phase->write_register === null) {
            return collect();
        }

        return $this->activeHeads($phase, $context)
            ->whereKey($this->descendantIds($phase, $context))
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
    public function gateOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $phase->kind === PhaseKind::Bias
            ? $this->bias->forPhase($phase, $context)
            : $this->reconciliation->forPhase($phase, $context);
    }

    private function activeHeads(Phase $phase, ?ParticipationContext $context = null): Builder
    {
        // Context Horizon membership, participant visibility, homeostatic
        // admissibility and predicted Tension reduction belong here once
        // their measurements are available.
        return Proposition::query()
            ->whereKey($this->localIds($phase, $context))
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

    private function descendantIds(Phase $phase, ?ParticipationContext $context): Collection
    {
        return $context !== null
            ? $context->children->get($phase->gate_proposition_id, collect())->pluck('target_proposition_id')
                ->reject(fn ($id) => $id === $phase->gate_proposition_id)->unique()->values()
            : $this->lineage->immediateDescendantIds($phase->gate()->firstOrFail());
    }

    private function limit(): int
    {
        return max(1, (int) config('schemas.options.limit', 10));
    }
}
