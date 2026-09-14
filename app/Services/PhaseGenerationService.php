<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
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
    ) {}

    public function available(Phase $phase, string $action, ?ParticipationContext $context = null, ?Proposition $position = null): bool
    {
        if (! in_array($action, ['proposition.variant', 'proposition.reclassify', 'proposition.bias', 'proposition.commit'], true)) {
            // Mediate owns a directed relationship, rather than a personal quota.
            return true;
        }
        // Standalone evidence previews may supply a candidate before a Phase
        // has a Gate. Operational plans always use the Phase's recorded Gate.
        $gate = $position ?? $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate;
        if ($gate === null) {
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
            if ($existing || $this->state->receipts($gate, $context)->isEmpty()
                || $this->state->attestations(collect([$gate]), $context)->isEmpty()) {
                return false;
            }
        }

        $sources = in_array($phase->kind, [PhaseKind::Bias, PhaseKind::Order], true)
            ? $this->regions->membersFor($gate, $context)->pluck('id')
            : collect([$gate->id]);

        return ! Proposition::query()
            // Persist Variant belongs to the Participant/Gate, even if a later
            // Context Horizon presents that Gate with different register labels.
            ->when($phase->kind !== PhaseKind::Persist || $action !== 'proposition.variant',
                fn ($query) => $query->where('register', $phase->write_register))
            ->where('tier', $phase->kind === PhaseKind::Order ? 'committal' : 'expressive')
            ->whereHas('phase', fn ($query) => $query->where('kind', $phase->kind->value)
                ->whereHas('cycle', fn ($query) => $query->where('participant_id', $phase->cycle->participant_id)))
            ->whereHas('parents', fn ($query) => $query->whereKey($sources)
                ->whereIn('supersessions.type', ['variant', 'reclassify', 'commit']))
            ->exists();
    }

    /** Commits end the region, so its Write row is just outside expressive membership. */
    public function committedWrites(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $sources = $this->regions->membersFor($context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate, $context)->pluck('id');

        return Proposition::query()->where('tier', 'committal')->where('register', $phase->write_register)
            ->whereHas('phase', fn ($query) => $query->where('kind', PhaseKind::Order->value)
                ->whereHas('cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id)))
            ->whereHas('parents', fn ($query) => $query->whereKey($sources)->where('supersessions.type', 'commit'))
            ->orderBy('id')->get();
    }
}
