<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Recorded other-operand -> Reconcile result. No homology, movement or Receipt scope. */
final readonly class MediatedIncomingBiasService
{
    public function __construct(private PhasePositionService $positions, private CommitRegionService $regions,
        private PropositionAvailabilityService $availability,
        private ChiralityCalculator $chirality, private AttestationEvidenceService $evidence) {}

    /** Safe before context hydration: only exact directed causal provenance is discovered. */
    public function populationFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $discover = function () use ($phase): Collection {
            $gate = $phase->gate;
            if ($phase->kind !== PhaseKind::Bias || $gate === null
                || ! $this->positions->acceptsGate($phase, $gate)
                || $this->regions->seedFor($gate)?->id !== $phase->cycle->entry_committal_id) {
                return collect();
            }
            // Bias keeps its real Mediate-produced Gate. Resolve only that
            // producer's exact Gate, never arbitrary ancestors or homologues.
            $operand = $gate->phase->gate;
            if ($operand === null || ! $this->positions->acceptsGate($gate->phase, $operand)
                || ! DB::table('supersessions')->where('target_proposition_id', $gate->id)
                    ->where('source_proposition_id', $operand->id)->where('phase_id', $gate->phase_id)
                    ->where('type', 'reconcile')->exists()) {
                return collect();
            }
            $ids = DB::table('supersessions as operand')
                ->join('propositions as result', 'result.id', '=', 'operand.target_proposition_id')
                ->join('phases as producer', 'producer.id', '=', 'result.phase_id')
                ->join('cycles as cycle', 'cycle.id', '=', 'producer.cycle_id')
                ->join('participants as author', 'author.id', '=', 'cycle.participant_id')
                ->join('supersessions as dominant', fn ($join) => $join
                    ->on('dominant.target_proposition_id', '=', 'result.id')
                    ->on('dominant.source_proposition_id', '=', 'producer.gate_proposition_id')
                    ->on('dominant.phase_id', '=', 'producer.id'))
                ->where('operand.source_proposition_id', $operand->id)->where('operand.type', 'reconcile')
                ->whereColumn('operand.phase_id', 'producer.id')->where('dominant.type', 'reconcile')
                ->where('producer.kind', PhaseKind::Mediate->value)
                ->where('producer.gate_proposition_id', '!=', $operand->id)
                ->where('result.tier', 'expressive')->whereColumn('result.register', 'producer.write_register')
                ->where('author.substrate_id', $phase->cycle->participant->substrate_id)
                ->whereRaw('(select count(*) from supersessions inputs where inputs.target_proposition_id = result.id) = 2')
                ->pluck('result.id');

            $nodes = Proposition::whereKey($ids)->with('phase.cycle.contextHorizon')->orderBy('id')->get();
            $seeds = $this->regions->seedIdsFor($nodes, $nodes);

            return $nodes->filter(fn ($node) => $node->register === $node->phase->cycle->contextHorizon->domain->registerFor(PhaseKind::Mediate)
                && ($seeds[$node->id] ?? null) !== null)->values();
        };

        return $context === null ? $discover() : $context->remember('mediated-bias-structure:'.$phase->id, $discover);
    }

    /** Discover exact directed relationships once for a complete personal head frontier. */
    public function populationsFor(Collection $phases, ParticipationContext $context): array
    {
        if ($phases->isEmpty()) {
            return [];
        }
        $operands = $phases->mapWithKeys(function ($phase) use ($context): array {
            $gate = $context->propositions->get($phase->gate_proposition_id);
            $operand = $context->propositions->get($gate?->phase->gate_proposition_id);
            $valid = $gate !== null && $operand !== null && $phase->kind === PhaseKind::Bias
                && $this->positions->acceptsGate($phase, $gate)
                && $this->regions->seedFor($gate, $context)?->id === $phase->cycle->entry_committal_id
                && $this->positions->acceptsGate($gate->phase, $operand)
                && $context->parents->get($gate->id, collect())->contains(fn ($edge) => $edge->source_proposition_id === $operand->id
                    && $edge->phase_id === $gate->phase_id && $edge->type === 'reconcile');

            return [$phase->id => $valid ? $operand->id : null];
        });
        $pairs = DB::table('supersessions as operand')
            ->join('propositions as result', 'result.id', '=', 'operand.target_proposition_id')
            ->join('phases as producer', 'producer.id', '=', 'result.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'producer.cycle_id')
            ->join('participants as author', 'author.id', '=', 'cycle.participant_id')
            ->join('supersessions as dominant', fn ($join) => $join
                ->on('dominant.target_proposition_id', '=', 'result.id')
                ->on('dominant.source_proposition_id', '=', 'producer.gate_proposition_id')
                ->on('dominant.phase_id', '=', 'producer.id'))
            ->whereIn('operand.source_proposition_id', $operands->filter()->unique())
            ->where('operand.type', 'reconcile')->whereColumn('operand.phase_id', 'producer.id')
            ->where('dominant.type', 'reconcile')->where('producer.kind', PhaseKind::Mediate->value)
            ->whereColumn('producer.gate_proposition_id', '!=', 'operand.source_proposition_id')
            ->where('result.tier', 'expressive')->whereColumn('result.register', 'producer.write_register')
            ->where('author.substrate_id', $phases->first()->cycle->participant->substrate_id)
            ->whereRaw('(select count(*) from supersessions inputs where inputs.target_proposition_id = result.id) = 2')
            ->get(['operand.source_proposition_id', 'result.id']);
        $nodes = Proposition::whereKey($pairs->pluck('id'))->with('phase.cycle.contextHorizon')->orderBy('id')->get();
        $seeds = $this->regions->seedIdsFor($nodes, $nodes);
        $nodes = $nodes->filter(fn ($node) => $node->register === $node->phase->cycle->contextHorizon->domain->registerFor(PhaseKind::Mediate)
            && ($seeds[$node->id] ?? null) !== null);
        $result = [];
        foreach ($phases as $phase) {
            $ids = $pairs->where('source_proposition_id', $operands[$phase->id])->pluck('id');
            $result['mediated-bias-structure:'.$phase->id] = $nodes->whereIn('id', $ids)->values();
        }

        return $result;
    }

    /** Ordinary source protections; no population direction or Tragedy requirement. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Bias) {
            return collect();
        }
        $context ??= new ParticipationContext($phase);

        return $context->remember('mediated-bias:'.$phase->id, function () use ($phase, $context): Collection {
            $gate = $context->propositions->get($phase->gate_proposition_id);
            if ($gate === null || ! $this->availability->mutable($gate, $context)) {
                return collect();
            }
            $candidates = $context->nodes($this->populationFor($phase, $context)->pluck('id'))
                ->filter(fn ($node) => $this->availability->mutable($node, $context)
                    && $this->chirality->directionFor($node, $context) === $this->evidence->lineageDirectionFor($node, $context));
            if ($candidates->isEmpty()) {
                return collect();
            }
            $horizons = $candidates->map(fn ($node) => $node->phase->cycle->context_horizon_id)
                ->push($phase->cycle->context_horizon_id)->unique()->sort()->values();
            $exhausted = $context->remember('foreign-terminal-continuations:'.$horizons->implode(','),
                fn () => app(ExhaustedContinuationService::class)->idsForHorizons($horizons));

            return $exhausted->has($gate->id) ? collect()
                : $candidates->reject(fn ($node) => $exhausted->has($node->id))->values();
        });
    }

    public function foreignGateIdsFor(Phase $phase, ParticipationContext $context): Collection
    {
        $seeds = $context->seedIds();

        return $this->forPhase($phase, $context)
            ->filter(fn ($node) => ($seeds[$node->id] ?? null) !== $phase->cycle->entry_committal_id)->pluck('id');
    }
}
