<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Observables\CommittalLineageService;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Raw causal hops, including revisions and historical nodes, never display ancestry. */
final class BackwardRecoveryLineage
{
    /** Rest at the entry itself requires a backward hop. Rest at an expressive Gate does not exclude its governor. */
    public function restedAtEntry(Proposition $position, ContextHorizon $horizon, ?Participant $observer): bool
    {
        if ($position->tier !== 'committal' || $observer?->current_proposition_id !== $position->id) {
            return false;
        }
        $latest = $observer->cycles()->latest('sequence')->first();

        return $latest?->context_horizon_id === $horizon->id && $latest->restedEntryPhase($position) !== null;
    }

    /**
     * Consecutive participant Phases disambiguate arrivals at shared Writes.
     * At inherited history, resume the creating participant's recorded provenance.
     * The cursor only moves backward: a later revisit cannot rewrite an earlier hop.
     *
     * @param  Closure(string, string): array  $parentsFor  target ID, producing Horizon ID
     * @return array<string, int> Proposition ID => actual backward distance
     */
    public function distances(Proposition $position, ?Participant $observer, Closure $parentsFor, bool $forRecovery = false): array
    {
        $cursor = $observer === null ? null : DB::table('phases as phase')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('cycle.participant_id', $observer->id)
            ->orderByDesc('cycle.sequence')->orderByDesc('phase.kind')
            ->first(['cycle.participant_id', 'cycle.sequence', 'phase.kind', 'phase.gate_proposition_id', 'phase.crossover_cycle_id']);
        $substrateId = $position->phase->cycle->participant->substrate_id;
        if ($observer !== null && $observer->substrate_id !== $substrateId) {
            throw new DomainException('Recovery lineage cannot cross substrates.');
        }
        // Within a crossed Cycle, a selected foreign Reclassify source can be
        // real causal evidence without being a route back to its Region.
        $crossed = $forRecovery && $cursor?->crossover_cycle_id !== null && $cursor->gate_proposition_id === $position->id;
        $governor = $crossed ? $position->phase->governance()->entry_committal_id : null;
        $hierarchy = $crossed ? app(CommittalLineageService::class)->recordsForIds($substrateId, collect([$governor])) : null;
        $distances = [];
        $id = $position->id;
        while ($id !== null) {
            if (isset($distances[$id])) {
                throw new DomainException('Recovery requires acyclic proposition lineage.');
            }
            $node = DB::table('propositions as proposition')
                ->join('phases as phase', 'phase.id', '=', 'proposition.phase_id')
                ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->join('participants as participant', 'participant.id', '=', 'cycle.participant_id')
                ->join('cycles as scope', 'scope.id', '=', 'phase.operating_cycle_id')
                ->where('proposition.id', $id)->first(['phase.gate_proposition_id', 'phase.kind',
                    'cycle.participant_id', 'cycle.sequence', 'scope.context_horizon_id', 'participant.substrate_id']);
            if ($node === null || $node->substrate_id !== $substrateId) {
                throw new DomainException('Recovery requires intact same-substrate lineage.');
            }
            $distances[$id] = count($distances);
            $parents = array_values(array_unique($parentsFor($id, $node->context_horizon_id)));
            if ($governor === $id) {
                $governor = $hierarchy->get($id)?->entry_committal_id;
            }
            if ($governor !== null && (int) $node->kind === PhaseKind::Bias->value
                && ! in_array($node->gate_proposition_id, $parents, true)) {
                // A registered foreign Bias source supplies evidence. Its local
                // producing Gate supplies recovery in the operating Region.
                $target = Proposition::findOrFail($id);
                $edges = Supersession::where('target_proposition_id', $id)->get();
                $source = app(RecordedBiasSourceService::class)->recordedSourceFor($target, $edges);
                if ($source !== null && app(CommitRegionService::class)->seedFor($source)?->id !== $governor
                    && app(CommitRegionService::class)->seedFor($target->phase->gate)?->id === $governor) {
                    $parents = [$node->gate_proposition_id];
                }
            }
            if ($parents === []) {
                break;
            }
            $arrival = $cursor === null || $governor !== null ? null : $this->arrival($id, $cursor);
            // The recorded foreign operand authorised arrival. At an exact crossover,
            // the local producing route leads to the destination's recovery hierarchy.
            $cursor = $forRecovery && ($arrival->crossed ?? false) ? $node : ($arrival ?? $node);
            if (count($parents) === 1) {
                // Selected Reclassify sources and revisions use the actual edge;
                // the incidental Phase Gate need not be that structural source.
                $id = $parents[0];
            } elseif (in_array($cursor->gate_proposition_id, $parents, true)) {
                $id = $cursor->gate_proposition_id;
            } else {
                throw new DomainException('Recovery requires a recorded arrival Gate for multi-input lineage.');
            }
        }

        // The recovery selectors need only reachable entries and boundary openers.
        // Search and personal history retain the actual arrival and all raw hops.
        return $crossed ? array_intersect_key($distances, $hierarchy->all()) : $distances;
    }

    private function arrival(string $id, object $cursor): ?object
    {
        $arrivals = DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('cycle.participant_id', $cursor->participant_id)->where('phase.gate_proposition_id', $id)
            ->where(fn ($q) => $q->where('cycle.sequence', '<', $cursor->sequence)
                ->orWhere(fn ($q) => $q->where('cycle.sequence', $cursor->sequence)->where('phase.kind', '<=', $cursor->kind)))
            ->orderByDesc('cycle.sequence')->orderByDesc('phase.kind')
            ->get(['phase.cycle_id', 'phase.kind', 'phase.crossover_cycle_id', 'cycle.sequence']);
        foreach ($arrivals as $arrival) {
            $previous = DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->where('cycle.participant_id', $cursor->participant_id)->whereNotNull('phase.completed_at');
            if ((int) $arrival->kind === PhaseKind::Distinguish->value) {
                // Normal completed Order rolls into the next Distinguish.
                // A Rest/recovery entry is not a forward traversal witness.
                $previous->where('cycle.sequence', $arrival->sequence - 1)->whereNull('cycle.rested_at')
                    ->whereNotNull('cycle.completed_at')->where('phase.kind', PhaseKind::Order->value);
            } else {
                $previous->where('phase.cycle_id', $arrival->cycle_id)->where('phase.kind', $arrival->kind - 1);
            }
            $phase = $previous->first(['cycle.participant_id', 'cycle.sequence', 'phase.kind', 'phase.gate_proposition_id']);
            if ($phase !== null) {
                $phase->crossed = (int) $arrival->kind === PhaseKind::Bias->value && $arrival->crossover_cycle_id !== null;

                return $phase;
            }
        }

        return null;
    }
}
