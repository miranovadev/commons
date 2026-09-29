<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Structural opportunity is shared; Participant count is never basin capacity. */
final readonly class PhaseGenerationService
{
    public function __construct(
        private CommitRegionService $regions,
        private PropositionStateService $state,
        private PropositionOwnershipService $ownership,
        private PropositionAvailabilityService $availability,
        private PhasePositionService $positions,
        private CausalWriteService $writes,
    ) {}

    public function available(Phase $phase, string $action, ?ParticipationContext $context = null, ?Proposition $position = null): bool
    {
        $exhausted = app(ExhaustedContinuationService::class)->ids($phase, $context);
        if ($exhausted->has($phase->gate_proposition_id) || ($position !== null && $exhausted->has($position->id))) {
            return false;
        }
        if ($phase->gate_proposition_id !== null
            && ($context !== null ? ! $context->activeIds->has($phase->gate_proposition_id) : app(ExhaustionService::class)->unavailable($phase->gate_proposition_id))) {
            return false;
        }
        if ($action === 'proposition.commit') {
            // A preview with no selected source must not suppress other eligible local Writes.
            return $phase->kind === PhaseKind::Order && ($position === null
                || ($this->positions->matches($position, $phase, PhaseKind::Bias) && $this->availability->mutable($position, $context)));
        }
        if ($action === 'proposition.reclassify' && $phase->kind === PhaseKind::Bias && $position === null) {
            return app(BiasGateService::class)->operationalFor($phase, $context)
                ->contains(fn (Proposition $candidate) => $this->available($phase, $action, $context, $candidate));
        }
        if (! in_array($action, ['proposition.variant', 'proposition.reclassify', 'proposition.commit'], true)) {
            // Mediate owns a directed relationship, rather than a personal quota.
            return true;
        }
        // Standalone evidence previews may supply a candidate before a Phase
        // has a Gate. Operational plans always use the Phase's recorded Gate.
        $gate = $position ?? $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->first();
        if ($gate === null) {
            return false;
        }
        if ($action === 'proposition.reclassify' && $phase->kind === PhaseKind::Bias
            && ! app(ChiralityGateService::class)->evaluate($gate, $context)['admissible']) {
            return false;
        }
        $additionalBias = $action === 'proposition.reclassify' && $phase->kind === PhaseKind::Bias
            && $gate->id !== $phase->gate_proposition_id;
        if ($phase->kind === PhaseKind::Bias && $gate->id !== $phase->gate_proposition_id && ! $additionalBias) {
            return false;
        }
        if ($additionalBias) {
            $mediated = app(MediatedIncomingBiasService::class)->forPhase($phase, $context)->contains('id', $gate->id);
            $homologous = app(HomologousBiasService::class)->forPhase($phase, $context)->contains('id', $gate->id);
            if (! $mediated && ! $homologous) {
                return false;
            }
        } elseif (! $this->positions->acceptsGate($phase, $gate)) {
            return false;
        }
        if ($this->availability->committed($gate, $context)
            || ($action !== 'proposition.variant' && $gate->tier !== 'expressive')) {
            return false;
        }
        if ($action === 'proposition.reclassify') {
            $existing = $context !== null
                ? $context->classifications->where('source_proposition_id', $gate->id)->where('register', $phase->write_register)->isNotEmpty()
                : DB::table('reclassification_results')->where('source_proposition_id', $gate->id)->where('register', $phase->write_register)->exists();
            if ($existing || ($phase->kind === PhaseKind::Bias
                ? app(BiasReceiptAuthorityService::class)->receiptsFor($phase, $gate, $context)
                : app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context))->isEmpty()
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
        $viable = Proposition::query()->active()
            ->whereNotIn('id', $exhausted->keys())
            ->where('register', $phase->write_register)
            ->where('tier', $phase->kind === PhaseKind::Order ? 'committal' : 'expressive')
            ->whereHas('phase', fn ($query) => $query->where('kind', $phase->kind->value)
                ->whereHas('cycle', fn ($query) => $query->where('context_horizon_id', $phase->cycle->context_horizon_id)))
            ->whereIn('id', $results);
        $check = function () use ($viable, $phase, $action, $context): bool {
            $existing = $viable->pluck('id');
            if ($existing->isEmpty()) {
                return true;
            }
            if ($action !== 'proposition.variant') {
                return false;
            }
            $spent = app(ExplorationEpochService::class)->failuresFor($phase, $existing, $context)->keys();
            if ($existing->diff($spent)->isNotEmpty()) {
                // Evidence alone does not prove downstream failure. Keep using
                // healthy continuations; do not assign Variant a priority.
                return false;
            }
            if (in_array($phase->kind, [PhaseKind::Distinguish, PhaseKind::Disperse], true)) {
                $observed = app(HorizonReceiptService::class)->instrumentedIds($phase, $existing);
                $attested = Attestation::query()->effective()
                    ->whereIn('proposition_id', $existing)->distinct()->pluck('proposition_id');
                if ($existing->diff($observed)->isNotEmpty() || $existing->diff($attested)->isNotEmpty()) {
                    return false;
                }
            }

            // Potential describes demonstrated tension reduction, not whether
            // this structurally eligible position can express another Variant.
            return true;
        };

        // Reuse this calculation's structural query, never a cross-action cache.
        return $context === null ? $check() : $context->remember(
            'generation-history:'.$phase->id.':'.$action.':'.$phase->write_register.':'.implode(',', $sources->sort()->all()),
            $check,
        );
    }

    /** Only the direct Committal children of causally local Bias Writes preserve a route. */
    public function committedWrites(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if (! in_array($phase->kind, [PhaseKind::Bias, PhaseKind::Order], true)) {
            return collect();
        }
        $sources = $this->writes->forPhase($phase, $context)->pluck('id');

        return $this->positions->scope(Proposition::query()->active(), $phase, PhaseKind::Order, 'committal')->whereHas('commitmentSnapshot')
            ->whereHas('phase', fn ($query) => $query->where('kind', PhaseKind::Order->value)
                ->whereHas('cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id)))
            ->whereIn('id', Supersession::query()->select('target_proposition_id')
                ->whereIn('source_proposition_id', $sources)->where('type', 'commit'))
            ->orderBy('id')->get();
    }
}
