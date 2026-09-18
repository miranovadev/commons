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
        private readonly EvidenceEligibilityService $evidence,
        private readonly PropositionAvailabilityService $availability,
        private readonly PhasePositionService $positions,
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
        if ($phase->kind === PhaseKind::Order) {
            return $this->generation->committedWrites($phase, $context);
        }

        if (! in_array($phase->kind, [PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse, PhaseKind::Bias], true)) {
            return collect();
        }

        // Receipt consumption never erases the path established by an earlier vote.
        $options = $this->writeOptionsFor($phase, $context);
        if ($context !== null) {
            $attested = $context->effectiveAttestations($options->pluck('id'))
                ->filter(fn ($attestation) => $attestation->phase->cycle->participant_id === $phase->cycle->participant_id
                    && $attestation->phase->cycle_id !== $phase->cycle_id)
                ->pluck('proposition_id')->flip();

            return $options->filter(fn ($node) => $attested->has($node->id))->sortBy('id')->values();
        }

        return Proposition::query()->active()->whereKey($options->pluck('id'))
            ->whereHas('effectiveAttestations.phase.cycle', fn ($query) => $query
                ->whereBelongsTo($phase->cycle->participant)->whereKeyNot($phase->cycle_id))
            ->orderBy('id')->get();
    }

    private function writeDescendants(Phase $phase, ?ParticipationContext $context = null): Builder
    {
        return Proposition::query()->active()
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
            return collect();
        }

        $writes = $this->writeOptionsFor($phase, $context);
        if (in_array($phase->kind, [PhaseKind::Distinguish, PhaseKind::Disperse], true)) {
            $receipts = app(HorizonReceiptService::class);
            $instrumented = $receipts->instrumentedIds($phase, $writes->pluck('id'));
            $shared = $receipts->available($phase, $context)->isNotEmpty();

            return $writes->whereNotIn('id', $this->traversalOptionsFor($phase, $context)->pluck('id'))
                ->filter(fn ($node) => ! $shared || ! $instrumented->contains($node->id))->values();
        }

        return $writes;
    }

    /**
     * @return Collection<int, Proposition>
     */
    public function writeOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $this->resolveWrites($phase, $context)->filter(fn (Proposition $node): bool => ($phase->kind === PhaseKind::Order && $node->tier === 'committal') || $this->availability->mutable($node, $context))->values();
    }

    private function resolveWrites(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->write_register === null) {
            return collect();
        }

        if ($phase->kind === PhaseKind::Order) {
            return $this->generation->committedWrites($phase, $context);
        }
        if ($phase->kind === PhaseKind::Bias) {
            // A Bias source can be any Gate in the region; its Write must stay
            // discoverable when the next Cycle arrives through another source.
            $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate;

            return $this->positions->filter($this->regions->membersFor($gate, $context), $phase, PhaseKind::Bias)
                ->concat($this->writeDescendants($phase, $context)->orderBy('id')->get())->unique('id')->values();
        }

        return $this->writeDescendants($phase, $context)->orderBy('id')->get();
    }

    /** Every valid Write descendant offers the as-yet-unattested direction(s). */
    public function attestationOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind === PhaseKind::Order) {
            return collect();
        }

        $shared = app(HorizonReceiptService::class)->available($phase, $context)->isNotEmpty();
        $expressive = in_array($phase->kind, [PhaseKind::Distinguish, PhaseKind::Disperse], true);

        $writes = $this->writeOptionsFor($phase, $context);
        $instrumented = $expressive ? app(HorizonReceiptService::class)->instrumentedIds($phase, $writes->pluck('id')) : collect();

        return $writes->filter(fn (Proposition $node): bool => $this->availability->mutable($node, $context)
            && ($expressive ? ($shared && $instrumented->contains($node->id))
                : ($shared || $this->evidence->canAttest($node, $context))))->values();
    }

    /**
     * Revision is an ownership-preserving move within a producing phase position. It cannot
     * target the Gate merely because the Gate belongs to the Participant.
     * The exact Write must have an effective self-Refute from an earlier Cycle;
     * a revision's ancestry never supplies its personal revision eligibility.
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

        $query = Proposition::query()->active()->whereDoesntHave('children')
            ->whereKey($this->descendantIds($phase, $context))
            ->where('register', $phase->write_register)
            ->where('tier', 'expressive')
            ->whereKeyNot($phase->gate_proposition_id)
            ->whereHas(
                'phase.cycle',
                fn (Builder $query) => $query->whereBelongsTo($participant),
            );
        if ($context !== null) {
            $refuted = $context->effectiveAttestations($context->propositions->keys())
                ->filter(fn ($attestation) => $attestation->direction === 'refute'
                    && $attestation->phase->cycle->participant_id === $participant->id
                    && $attestation->phase->cycle->sequence < $phase->cycle->sequence)
                ->pluck('proposition_id');
            $query->whereKey($refuted);
        } else {
            $query->whereHas('effectiveAttestations', fn (Builder $query) => $query
                ->where('direction', 'refute')
                ->whereHas('phase.cycle', fn (Builder $query) => $query
                    ->whereBelongsTo($participant)
                    ->where('sequence', '<', $phase->cycle->sequence)));
        }

        return $query->latest('created_at')
            ->limit($this->limit())
            ->get();
    }

    /**
     * @return Collection<int, Proposition>
     */
    public function gateOptionsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind === PhaseKind::Order) {
            return $this->closure->committableFor($phase, $context);
        }

        return $phase->kind === PhaseKind::Bias
            ? $this->bias->forPhase($phase, $context)->filter(fn (Proposition $node): bool => $this->generation->available($phase, 'proposition.reclassify', $context, $node))->values()
            : $this->reconciliation->forPhase($phase, $context);
    }

    public function orderReadsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Order) {
            return collect();
        }
        $frontier = collect([$context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->firstOrFail()]);
        $seen = [];
        $reads = collect();
        while ($frontier->isNotEmpty()) {
            $next = collect();
            foreach ($frontier as $node) {
                if (isset($seen[$node->id]) || $node->tier === 'committal') {
                    continue;
                }
                $seen[$node->id] = true;
                if ($this->positions->matches($node, $phase, PhaseKind::Mediate)) {
                    $reads->push($node);

                    continue;
                }
                $parents = $context !== null ? $context->parentNodes($node) : $node->parents()->get();
                $next = $next->concat($parents->filter(fn ($parent) => $parent->phase->cycle->participant->substrate_id === $phase->cycle->participant->substrate_id));
            }
            $frontier = $next->unique('id');
        }

        return $reads->unique('id')->sortBy('id')->values();
    }

    /** Follow revisions within the exact producing Phase position, not merely its label. */
    private function descendantIds(Phase $phase, ?ParticipationContext $context): Collection
    {
        $ids = $context !== null
            ? $context->children->get($phase->gate_proposition_id, collect())->pluck('target_proposition_id')
                ->reject(fn ($id) => $id === $phase->gate_proposition_id)->unique()->values()
            : $this->lineage->immediateDescendantIds($phase->gate()->firstOrFail());
        $ids = $this->atWritePosition($ids, $phase, $context);
        $frontier = $ids;
        while ($frontier->isNotEmpty()) {
            $next = $context !== null
                ? $frontier->flatMap(fn ($id) => $context->children->get($id, collect())->where('type', 'revise')->pluck('target_proposition_id'))
                : Supersession::query()->whereIn('source_proposition_id', $frontier)
                    ->where('type', 'revise')->whereHas('target', fn ($query) => $query->where('register', $phase->write_register))
                    ->pluck('target_proposition_id');
            $frontier = $this->atWritePosition($next, $phase, $context)->diff($ids)->unique()->values();
            $ids = $ids->merge($frontier)->unique()->values();
        }

        return $ids;
    }

    private function atWritePosition(Collection $ids, Phase $phase, ?ParticipationContext $context): Collection
    {
        return $context !== null
            ? $this->positions->filter($context->nodes($ids), $phase, $phase->kind)->pluck('id')
            : $this->positions->scope(Proposition::query()->active()->whereKey($ids), $phase, $phase->kind)
                ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id))
                ->pluck('id');
    }

    private function limit(): int
    {
        return max(1, (int) config('schemas.options.limit', 10));
    }
}
