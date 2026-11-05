<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ReconciliationCrossoverService
{
    public function __construct(private PhasePositionService $positions, private CommitRegionService $regions) {}

    /** The producing operand already has its ordinary local route. Discover only the other exact operand. */
    public function children(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Mediate || $phase->gate_proposition_id === null) {
            return collect();
        }

        $discover = fn () => $this->childrenFor(collect([$phase]))[$phase->id];

        return $context === null ? $discover() : $context->remember('crossover:'.$phase->id, $discover);
    }

    /** One structural inventory for actual or projected Mediate arrivals in the same substrate. */
    public function childrenFor(Collection $phases): array
    {
        if ($phases->isEmpty()) {
            return [];
        }
        $phases = (new Phase)->newCollection($phases->values()->all());
        $phases->loadMissing('cycle.participant');
        $result = $phases->mapWithKeys(fn ($phase) => [$phase->id => collect()])->all();
        $substrate = $phases->first()->cycle->participant->substrate_id;
        $edgeType = (new Supersession)->getMorphClass();
        $birthType = (new Proposition)->getMorphClass();
        $pairs = DB::table('supersessions as operand')
            ->join('propositions as child', 'child.id', '=', 'operand.target_proposition_id')
            ->join('phases as producer', 'producer.id', '=', 'child.phase_id')
            ->join('supersessions as partner', fn ($join) => $join->on('partner.target_proposition_id', '=', 'child.id')
                ->on('partner.phase_id', '=', 'producer.id')->on('partner.source_proposition_id', '=', 'producer.gate_proposition_id'))
            ->join('substrate_ledger_entries as birth', fn ($join) => $join->on('birth.ledgerable_id', '=', 'child.id')
                ->where('birth.ledgerable_type', $birthType)->where('birth.kind', 'proposition')->where('birth.substrate_id', $substrate))
            ->join('substrate_ledger_entries as left_entry', fn ($join) => $join->on('left_entry.ledgerable_id', '=', 'operand.id')
                ->where('left_entry.ledgerable_type', $edgeType)->where('left_entry.kind', 'supersession')->where('left_entry.substrate_id', $substrate))
            ->join('substrate_ledger_entries as right_entry', fn ($join) => $join->on('right_entry.ledgerable_id', '=', 'partner.id')
                ->where('right_entry.ledgerable_type', $edgeType)->where('right_entry.kind', 'supersession')->where('right_entry.substrate_id', $substrate))
            ->whereIn('operand.source_proposition_id', $phases->pluck('gate_proposition_id'))
            ->where('operand.type', 'reconcile')->where('partner.type', 'reconcile')
            ->whereColumn('operand.phase_id', 'producer.id')->whereColumn('operand.source_proposition_id', '!=', 'producer.gate_proposition_id')
            ->where('producer.kind', PhaseKind::Mediate->value)
            ->whereRaw('(select count(*) from supersessions inputs where inputs.target_proposition_id = child.id) = 2')
            ->get(['operand.source_proposition_id', 'child.id', 'producer.gate_proposition_id']);
        if ($pairs->isEmpty()) {
            return $result;
        }
        $phases->loadMissing('cycle.contextHorizon');
        $ids = $pairs->pluck('id')->merge($pairs->pluck('source_proposition_id'))->merge($pairs->pluck('gate_proposition_id'));
        $nodes = Proposition::whereKey($ids->unique())->with(['phase.cycle.contextHorizon', 'phase.cycle.participant'])->get()->keyBy('id');
        $owners = $this->regions->seedIdsFor($nodes, $nodes);
        $byOperand = $pairs->groupBy('source_proposition_id');
        foreach ($phases as $phase) {
            $gate = $nodes->get($phase->gate_proposition_id);
            $local = $phase->governance()->entry_committal_id;
            if ($gate === null || $phase->kind !== PhaseKind::Mediate || $local === null || $owners[$gate->id] !== $local
                || $phase->cycle->participant->substrate_id !== $substrate || $gate->phase->cycle->participant->substrate_id !== $substrate
                || ! $this->positions->acceptsGate($phase, $gate)) {
                continue;
            }
            foreach ($byOperand->get($gate->id, collect()) as $pair) {
                $child = $nodes[$pair->id];
                $parent = $nodes[$pair->gate_proposition_id];
                $producer = $child->phase;
                if ($owners[$child->id] !== null && $owners[$child->id] !== $local && $owners[$parent->id] === $owners[$child->id]
                    && $producer->cycle->participant->substrate_id === $substrate && $parent->phase->cycle->participant->substrate_id === $substrate
                    && $producer->governance()->contextHorizon->domain === $phase->governance()->contextHorizon->domain
                    && $this->positions->matches($child, $producer, PhaseKind::Mediate) && $this->positions->acceptsGate($producer, $parent)) {
                    $result[$phase->id]->push($child);
                }
            }
            $result[$phase->id] = $result[$phase->id]->sortBy('id')->values();
        }

        return $result;
    }

    /** Consecutive Phase Gates preserve the actual arrival independently of X's producing Gate. */
    public function arrival(Phase $phase): array
    {
        $gates = Phase::where('cycle_id', $phase->cycle_id)->whereIn('kind', [PhaseKind::Mediate->value, PhaseKind::Bias->value])
            ->pluck('gate_proposition_id', 'kind');

        return ['operand_id' => $gates[PhaseKind::Mediate->value], 'child_id' => $gates[PhaseKind::Bias->value],
            'governing_committal_id' => $phase->governance()->entry_committal_id,
            'context_horizon_id' => $phase->governance()->context_horizon_id];
    }

    /** Validate the immutable scope witness against consecutive Phases in the actual Participant Cycle. */
    public function assertArrival(Phase $next): void
    {
        $previous = Phase::where('cycle_id', $next->cycle_id)->where('kind', $next->kind->value - 1)
            ->whereNotNull('completed_at')->latest('id')->first();
        if ($previous !== null && $next->kind === PhaseKind::Bias) {
            $child = $this->children($previous)->firstWhere('id', $next->gate_proposition_id);
            if ($child !== null && $child->phase->cycle_id === $next->crossover_cycle_id) {
                return;
            }
        }
        if ($previous !== null && $next->kind === PhaseKind::Order && $previous->crossover_cycle_id === $next->crossover_cycle_id
            && $this->regions->seedFor($next->gate)?->id === $previous->governance()->entry_committal_id
            && $this->positions->matches($next->gate, $previous, PhaseKind::Bias)
            && (app(CausalWriteService::class)->classifiedIn($next->gate, $previous) || app(CausalWriteService::class)->forPhase($previous)->contains('id', $next->gate_proposition_id))) {
            return;
        }

        throw new DomainException('A crossover requires the recorded consecutive arrival in this Cycle.');
    }
}
