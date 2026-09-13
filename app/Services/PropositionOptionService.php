<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PropositionOptionService
{
    public function __construct(
        private readonly LineageService $lineage,
        private readonly ReconciliationOptionService $reconciliation,
        private readonly BiasGateService $bias,
        private readonly ClosureOptionService $closure,
        private readonly CommitRegionService $regions,
        private readonly PhaseGenerationService $generation,
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

    /** Instruments support Written propositions, never incidental Gate neighbours. */
    public function instrumentOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind === PhaseKind::Order) {
            // Grounding may repair evidence before Closure becomes admissible.
            $candidates = $this->regions->forPhase($phase, context: $context);

            return Proposition::query()->whereKey($candidates->pluck('id'))->whereDoesntHave('children')->orderBy('id')->get()
                ->concat($this->generation->committedWrites($phase, $context))->unique('id')->values();
        }

        return $this->writeOptionsFor($phase, $context);
    }

    /**
     * @return Collection<int, Proposition>
     */
    public function writeOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->write_register === null) {
            return collect();
        }

        if ($phase->kind === PhaseKind::Order) {
            // Order retains Bias's Write register. Existing Committals in this
            // row remain supportable after the personal Commit opportunity ends.
            return $this->closure->forPhase($phase, $context)
                ->concat($this->generation->committedWrites($phase, $context))->unique('id')->values();
        }
        if ($phase->kind === PhaseKind::Bias) {
            // A Bias source can be any Gate in the region; its Write must stay
            // discoverable when the next Cycle arrives through another source.
            $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate;

            return $this->regions->membersFor($gate, $context)->where('register', $phase->write_register)
                ->concat($this->writeDescendants($phase, $context)->orderBy('id')->get())->unique('id')->values();
        }

        return $this->writeDescendants($phase, $context)->orderBy('id')->get();
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

        return Proposition::query()->whereDoesntHave('children')
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

    /** Follow revisions within Write without crossing into another phase's register. */
    private function descendantIds(Phase $phase, ?ParticipationContext $context): Collection
    {
        $ids = $context !== null
            ? $context->children->get($phase->gate_proposition_id, collect())->pluck('target_proposition_id')
                ->reject(fn ($id) => $id === $phase->gate_proposition_id)->unique()->values()
            : $this->lineage->immediateDescendantIds($phase->gate()->firstOrFail());
        $ids = $this->inWriteRegister($ids, $phase, $context);
        $frontier = $ids;
        while ($frontier->isNotEmpty()) {
            $next = $context !== null
                ? $frontier->flatMap(fn ($id) => $context->children->get($id, collect())->where('type', 'revise')->pluck('target_proposition_id'))
                : Supersession::query()->whereIn('source_proposition_id', $frontier)
                    ->where('type', 'revise')->whereHas('target', fn ($query) => $query->where('register', $phase->write_register))
                    ->pluck('target_proposition_id');
            $frontier = $this->inWriteRegister($next, $phase, $context)->diff($ids)->unique()->values();
            $ids = $ids->merge($frontier)->unique()->values();
        }

        return $ids;
    }

    private function inWriteRegister(Collection $ids, Phase $phase, ?ParticipationContext $context): Collection
    {
        return $context !== null
            ? $context->nodes($ids)->where('register', $phase->write_register)->where('tier', 'expressive')->pluck('id')
            : Proposition::query()->whereKey($ids)->where('register', $phase->write_register)->where('tier', 'expressive')
                ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id))
                ->pluck('id');
    }

    private function limit(): int
    {
        return max(1, (int) config('schemas.options.limit', 10));
    }
}
