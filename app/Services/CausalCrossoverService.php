<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CausalCrossoverService
{
    public function __construct(private PhasePositionService $positions, private CommitRegionService $regions) {}

    /** Only a ledgered foreign child at the next Write position may extend the local inventory. */
    public function children(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if (! in_array($phase->kind, [PhaseKind::Mediate, PhaseKind::Bias], true) || $phase->gate_proposition_id === null) {
            return collect();
        }

        $discover = fn () => $this->childrenFor(collect([$phase]))[$phase->id];

        return $context === null ? $discover() : $context->remember('crossover:'.$phase->id, $discover);
    }

    /** One structural inventory for actual or projected arrivals in the same substrate. */
    public function childrenFor(Collection $phases): array
    {
        if ($phases->isEmpty()) {
            return [];
        }
        $phases = (new Phase)->newCollection($phases->values()->all());
        $phases->loadMissing('cycle.participant');
        $result = $phases->mapWithKeys(fn ($phase) => [$phase->id => collect()])->all();
        $substrate = $phases->first()->cycle->participant->substrate_id;
        $records = collect();
        foreach ([PhaseKind::Mediate, PhaseKind::Bias] as $kind) {
            $sources = $phases->where('kind', $kind)->pluck('gate_proposition_id')->filter()->unique();
            if ($sources->isNotEmpty()) {
                $records = $records->concat($this->recordedChildren($kind, $sources, $substrate));
            }
        }
        if ($records->isEmpty()) {
            return $result;
        }
        $phases->loadMissing('cycle.contextHorizon', 'crossoverCycle.contextHorizon');
        $ids = $records->pluck('id')->merge($records->pluck('source_proposition_id'))->merge($records->pluck('gate_proposition_id'));
        $nodes = Proposition::whereKey($ids->unique())->with(['phase.cycle.contextHorizon', 'phase.cycle.participant', 'phase.crossoverCycle.contextHorizon'])->get()->keyBy('id');
        $owners = $this->regions->seedIdsFor($nodes, $nodes);
        $bySource = $records->groupBy('source_proposition_id');
        foreach ($phases as $phase) {
            $gate = $nodes->get($phase->gate_proposition_id);
            $local = $phase->governance()->entry_committal_id;
            if ($gate === null || $local === null || $owners[$gate->id] !== $local
                || $phase->cycle->participant->substrate_id !== $substrate || $gate->phase->cycle->participant->substrate_id !== $substrate
                || ! $this->positions->acceptsGate($phase, $gate)) {
                continue;
            }
            foreach ($bySource->get($gate->id, collect()) as $record) {
                $child = $nodes[$record->id];
                $producingGate = $nodes[$record->gate_proposition_id];
                $producer = $child->phase;
                if ($owners[$child->id] !== null && $owners[$child->id] !== $local && $owners[$producingGate->id] === $owners[$child->id]
                    && $producer->cycle->participant->substrate_id === $substrate && $producingGate->phase->cycle->participant->substrate_id === $substrate
                    && $producer->governance()->contextHorizon->domain === $phase->governance()->contextHorizon->domain
                    && $this->positions->matches($child, $producer, $phase->kind) && $this->positions->acceptsGate($producer, $producingGate)) {
                    $result[$phase->id]->push($child);
                }
            }
            $result[$phase->id] = $result[$phase->id]->sortBy('id')->values();
        }

        return $result;
    }

    private function recordedChildren(PhaseKind $kind, Collection $sources, string $substrate): Collection
    {
        $query = DB::table('supersessions as operand')
            ->join('propositions as child', 'child.id', '=', 'operand.target_proposition_id')
            ->join('phases as producer', 'producer.id', '=', 'child.phase_id')
            ->whereIn('operand.source_proposition_id', $sources)
            ->whereColumn('operand.phase_id', 'producer.id')
            ->whereColumn('operand.source_proposition_id', '!=', 'producer.gate_proposition_id')
            ->where('producer.kind', $kind->value);
        $this->ledgered($query, 'child', 'birth', 'proposition', (new Proposition)->getMorphClass(), $substrate);
        $edgeType = (new Supersession)->getMorphClass();
        $this->ledgered($query, 'operand', 'source_entry', 'supersession', $edgeType, $substrate);
        if ($kind === PhaseKind::Mediate) {
            $query->join('supersessions as partner', fn ($join) => $join->on('partner.target_proposition_id', '=', 'child.id')
                ->on('partner.phase_id', '=', 'producer.id')->on('partner.source_proposition_id', '=', 'producer.gate_proposition_id'))
                ->where('operand.type', 'reconcile')->where('partner.type', 'reconcile');
            $this->ledgered($query, 'partner', 'partner_entry', 'supersession', $edgeType, $substrate);
            $inputs = 2;
        } else {
            $query->join('reclassification_results as result', fn ($join) => $join->on('result.target_proposition_id', '=', 'child.id')
                ->on('result.source_proposition_id', '=', 'operand.source_proposition_id')->on('result.register', '=', 'child.register'))
                ->where('operand.type', 'reclassify');
            $inputs = 1;
        }

        return $query->whereRaw('(select count(*) from supersessions inputs where inputs.target_proposition_id = child.id) = ?', [$inputs])
            ->get(['operand.source_proposition_id', 'child.id', 'producer.gate_proposition_id']);
    }

    private function ledgered(Builder $query, string $table, string $alias, string $kind, string $type, string $substrate): void
    {
        $query->join('substrate_ledger_entries as '.$alias, fn ($join) => $join->on($alias.'.ledgerable_id', '=', $table.'.id')
            ->where($alias.'.ledgerable_type', $type)->where($alias.'.kind', $kind)->where($alias.'.substrate_id', $substrate));
    }

    /** Report the most recent transition, including an Order that inherits its Bias context. */
    public function arrival(Phase $phase): array
    {
        $phases = Phase::where('cycle_id', $phase->cycle_id)
            ->whereBetween('kind', [PhaseKind::Mediate->value, $phase->kind->value])
            ->get(['kind', 'gate_proposition_id', 'crossover_cycle_id'])->keyBy(fn ($row) => $row->kind->value);
        $bias = $phases[PhaseKind::Bias->value];
        $arrivedAtOrder = $phase->kind === PhaseKind::Order && $phase->crossover_cycle_id !== $bias->crossover_cycle_id;
        $from = $arrivedAtOrder ? $bias : $phases[PhaseKind::Mediate->value];
        $to = $arrivedAtOrder ? $phase : $bias;

        return ['operand_id' => $from->gate_proposition_id, 'child_id' => $to->gate_proposition_id,
            'governing_committal_id' => $phase->governance()->entry_committal_id,
            'context_horizon_id' => $phase->governance()->context_horizon_id];
    }

    /** Validate the immutable scope witness against consecutive Phases in the actual Participant Cycle. */
    public function assertArrival(Phase $next): void
    {
        if ($next->write_register !== $next->governance()->contextHorizon->domain->registerFor($next->kind)) {
            throw new DomainException('A crossover requires the destination Phase register.');
        }
        $previous = Phase::where('cycle_id', $next->cycle_id)->where('kind', $next->kind->value - 1)
            ->whereNotNull('completed_at')->latest('id')->first();
        if ($previous !== null && in_array($next->kind, [PhaseKind::Bias, PhaseKind::Order], true)
            && $previous->crossover_cycle_id !== $next->crossover_cycle_id) {
            $child = $this->children($previous)->firstWhere('id', $next->gate_proposition_id);
            if ($child !== null && $child->phase->operating_cycle_id === $next->crossover_cycle_id) {
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
