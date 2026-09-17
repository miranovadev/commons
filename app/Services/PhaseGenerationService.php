<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Generative opportunities survive Cycle completion and revision of their results. */
final readonly class PhaseGenerationService
{
    public function __construct(
        private CommitRegionService $regions,
        private PropositionStateService $state,
        private PropositionOwnershipService $ownership,
        private PropositionAvailabilityService $availability,
        private PhasePositionService $positions,
    ) {}

    public function available(Phase $phase, string $action, ?ParticipationContext $context = null, ?Proposition $position = null): bool
    {
        if ($phase->gate_proposition_id !== null
            && ($context !== null ? ! $context->activeIds->has($phase->gate_proposition_id) : app(ExhaustionService::class)->unavailable($phase->gate_proposition_id))) {
            return false;
        }
        if ($action === 'proposition.commit') {
            // A preview with no selected source must not suppress other eligible Gates.
            return $phase->kind === PhaseKind::Order && ($position === null
                || ($this->positions->matches($position, $phase, PhaseKind::Bias) && $this->availability->mutable($position, $context)));
        }
        if ($action === 'proposition.reclassify' && $phase->kind === PhaseKind::Bias && $position === null) {
            return app(BiasGateService::class)->forPhase($phase, $context)
                ->contains(fn (Proposition $candidate) => $this->available($phase, $action, $context, $candidate));
        }
        if (! in_array($action, ['proposition.variant', 'proposition.reclassify', 'proposition.bias', 'proposition.commit'], true)) {
            // Mediate owns a directed relationship, rather than a personal quota.
            return true;
        }
        // Standalone evidence previews may supply a candidate before a Phase
        // has a Gate. Operational plans always use the Phase's recorded Gate.
        $gate = $position ?? $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->first();
        if ($gate === null || ! $this->positions->acceptsGate($phase, $gate)) {
            return false;
        }
        if (($action !== 'proposition.bias' || $position !== null)
            && ($this->availability->committed($gate, $context)
                || ($action !== 'proposition.variant' && $gate->tier !== 'expressive'))) {
            return false;
        }
        if ($phase->kind === PhaseKind::Persist && $action === 'proposition.variant'
            && $this->ownership->ownedBy($gate, $phase->cycle->participant)) {
            return false;
        }
        if ($action === 'proposition.reclassify' || ($action === 'proposition.bias' && $position !== null)) {
            $existing = $context !== null
                ? $context->classifications->where('source_proposition_id', $gate->id)->where('register', $phase->write_register)->isNotEmpty()
                : DB::table('reclassification_results')->where('source_proposition_id', $gate->id)->where('register', $phase->write_register)->exists();
            if ($existing || app(HorizonReceiptService::class)->available($phase, $context)->isEmpty()
                || $this->state->attestations(collect([$gate]), $context)->isEmpty()) {
                return false;
            }

            return true; // Shared structural classification has no personal quota.
        }

        $sources = in_array($phase->kind, [PhaseKind::Bias, PhaseKind::Order], true)
            ? $this->regions->membersFor($gate, $context)->pluck('id')
            : collect([$gate->id]);

        // Sources are already resolved Propositions. Filtering the edge directly
        // avoids a redundant parent join and global scans under stale statistics.
        $results = Supersession::query()->select('target_proposition_id')
            ->whereIn('source_proposition_id', $sources)
            ->whereIn('type', ['variant', 'reclassify', 'commit']);
        $check = fn () => ! Proposition::query()->active()
            // Persist Variant belongs to the Participant/Gate, even if a later
            // Context Horizon presents that Gate with different register labels.
            ->when($phase->kind !== PhaseKind::Persist || $action !== 'proposition.variant',
                fn ($query) => $query->where('register', $phase->write_register))
            ->where('tier', $phase->kind === PhaseKind::Order ? 'committal' : 'expressive')
            ->whereHas('phase', fn ($query) => $query->where('kind', $phase->kind->value)
                ->whereHas('cycle', fn ($query) => $query->where('participant_id', $phase->cycle->participant_id)))
            ->whereIn('id', $results)
            ->exists();

        // Bias evaluates multiple Gates in one region. Its personal history is
        // identical for that source set, but is never cached between calculations.
        return $context === null ? $check() : $context->remember(
            'generation-history:'.$phase->id.':'.$action.':'.$phase->write_register.':'.implode(',', $sources->sort()->all()),
            $check,
        );
    }

    /** Commits end the region, so its Write row is just outside expressive membership. */
    public function committedWrites(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $sources = $this->positions->filter($this->regions->membersFor($context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate, $context), $phase, PhaseKind::Bias)->pluck('id');

        return $this->positions->scope(Proposition::query()->active(), $phase, PhaseKind::Order, 'committal')->whereHas('commitmentSnapshot')
            ->whereHas('phase', fn ($query) => $query->where('kind', PhaseKind::Order->value)
                ->whereHas('cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id)))
            ->whereIn('id', Supersession::query()->select('target_proposition_id')
                ->whereIn('source_proposition_id', $sources)->where('type', 'commit'))
            ->orderBy('id')->get();
    }
}
