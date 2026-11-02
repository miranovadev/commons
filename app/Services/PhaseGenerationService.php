<?php

namespace App\Services;

use App\Enums\PhaseKind;
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

    /** Personal completion and shared structural evidence are independent prerequisites. */
    public function epistemicActionAvailable(Phase $phase, string $action, ?ParticipationContext $context = null): bool
    {
        $acquisition = app(AcquisitionAttemptService::class);
        if (in_array($action, ['instrument.execute', 'preparation.start'], true)) {
            return $acquisition->allows($phase, $action, $context);
        }
        if ($action === 'proposition.revise' && in_array($phase->kind, [PhaseKind::Persist, PhaseKind::Disperse], true)) {
            return app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context)->isNotEmpty();
        }
        if (! in_array($action, ['proposition.variant'], true)
            || $acquisition->role($phase) === null) {
            return true;
        }
        if (! $acquisition->ready($phase, $context)) {
            return false;
        }

        return $phase->kind === PhaseKind::Distinguish
            || app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context)->isNotEmpty();
    }

    public function available(Phase $phase, string $action, ?ParticipationContext $context = null, ?Proposition $position = null, bool $prepared = false): bool
    {
        if (! $prepared && ! $this->epistemicActionAvailable($phase, $action, $context)) {
            return false;
        }
        if ($action === 'preparation.start' && ! app(PreparationService::class)->eligible($phase, $context)) {
            return false;
        }
        if (in_array($action, ['proposition.variant', 'proposition.revise'], true)) {
            $terminalRegion = fn () => DB::table('tragedies')->where('governing_committal_id', $phase->cycle->entry_committal_id)->exists();
            if ($context === null ? $terminalRegion() : $context->remember('generation-terminal-region:'.$phase->cycle->entry_committal_id, $terminalRegion)) {
                return false;
            }
        }
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

        if ($action === 'proposition.variant' && ($context !== null
            ? $context->children->get($gate->id, collect())->contains(fn ($edge) => $edge->type !== 'variant')
            : $gate->children()->where('supersessions.type', '!=', 'variant')->exists())) {
            return false;
        }

        // Every fresh Variant probe, including resolution of an admitted attempt,
        // uses the governing Cycle's geometry. Unavailable history cannot close it.
        if ($action === 'proposition.variant') {
            $closed = fn () => app(CrossoverMarginService::class)->inspect($phase->cycle)['closed'];
            if ($context === null ? $closed() : $context->remember('variant-crossover:'.$phase->cycle_id, $closed)) {
                return false;
            }
        }

        // Admission bypasses only later competing Variants, never hard
        // supersession or the terminal and canonical safety checks above.
        if ($action === 'proposition.variant' && app(AcquisitionAttemptService::class)->admitted($phase, $context)) {
            return true;
        }

        $sources = in_array($phase->kind, [PhaseKind::Bias, PhaseKind::Order], true)
            ? $this->regions->membersFor($gate, $context)->pluck('id')
            : collect([$gate->id]);

        $check = function () use ($sources, $phase, $action, $context, $exhausted): bool {
            // Projected arrivals at the same Phase position share the SQL selection
            // over the request's working set. Retain the ordinary source-local query otherwise.
            $batch = ! $phase->exists && $context !== null;
            $load = fn () => Proposition::query()->active()
                ->join('supersessions as result', 'result.target_proposition_id', '=', 'propositions.id')
                ->whereIn('result.source_proposition_id', $batch ? $context->propositions->keys() : $sources)
                ->whereIn('result.type', ['variant', 'reclassify', 'commit'])
                ->whereNotIn('propositions.id', $exhausted->keys())
                ->where('propositions.register', $phase->write_register)
                ->where('propositions.tier', $phase->kind === PhaseKind::Order ? 'committal' : 'expressive')
                ->whereHas('phase', fn ($query) => $query->where('kind', $phase->kind->value)
                    ->whereHas('cycle', fn ($query) => $query->where('context_horizon_id', $phase->cycle->context_horizon_id)))
                ->toBase()->get(['result.source_proposition_id', 'propositions.id'])->groupBy('source_proposition_id');
            $writes = $batch ? $context->remember('prospective-writes:'.$phase->cycle_id.':'.$phase->kind->value.':'.$phase->write_register, $load) : $load();
            $existing = $sources->flatMap(fn ($id) => $writes->get($id, collect())->pluck('id'))->unique();
            if ($existing->isEmpty()) {
                return true;
            }
            $context ??= new ParticipationContext($phase);
            // Personal completion belongs to this Cycle, including during a
            // projected arrival. Reuse its actual Phase's batched encounter probes.
            $acting = $batch && $context->phase->cycle_id === $phase->cycle_id ? $context->phase : $phase;
            $existing = $existing->filter(fn ($id) => app(ParticipantExplorationService::class)->heads($id, $acting, $context)->isNotEmpty());
            if ($existing->isEmpty()) {
                return true;
            }
            if ($action !== 'proposition.variant') {
                return false;
            }
            $explored = app(ParticipantExplorationService::class)->exploredIds($acting, $existing, $context)->keys();
            if ($existing->diff($explored)->isNotEmpty()) {
                // Every existing reachable active head must be personally explored
                // before considering fresh generation. Variant is not its own prerequisite.
                return false;
            }
            if (in_array($phase->kind, [PhaseKind::Distinguish, PhaseKind::Disperse], true)) {
                $shared = app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context)->isNotEmpty();
                $evidenced = $shared ? $existing : $context->nodes($existing)
                    ->filter(fn (Proposition $node) => $this->state->evidenceReceipts($node, $context)->isNotEmpty())->pluck('id');
                $attested = $context->effectiveAttestations($existing)->pluck('proposition_id');
                if ($existing->diff($evidenced)->isNotEmpty() || $existing->diff($attested)->isNotEmpty()) {
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
        if ($context !== null) {
            // Outgoing hard edges are complete even when the target lies outside the working set.
            $sources = $sources->filter(fn ($id) => $context->children->get($id, collect())->contains('type', 'commit'));
        }
        if ($sources->isEmpty()) {
            return collect();
        }

        return $this->positions->scope(Proposition::query()->active(), $phase, PhaseKind::Order, 'committal')->whereHas('commitmentSnapshot')
            ->whereHas('phase', fn ($query) => $query->where('kind', PhaseKind::Order->value)
                ->whereHas('cycle.participant', fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id)))
            ->whereIn('id', Supersession::query()->select('target_proposition_id')
                ->whereIn('source_proposition_id', $sources)->where('type', 'commit'))
            ->orderBy('id')->get();
    }
}
