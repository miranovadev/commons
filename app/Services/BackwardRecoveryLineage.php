<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Proposition;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Raw causal hops, including revisions and historical nodes, never display ancestry. */
final class BackwardRecoveryLineage
{
    /**
     * Consecutive participant Phases disambiguate arrivals at shared Writes.
     * At inherited history, resume the creating participant's recorded provenance.
     * The cursor only moves backward: a later revisit cannot rewrite an earlier hop.
     *
     * @param  Closure(string, string): array  $parentsFor  target ID, producing Horizon ID
     * @return array<string, int> Proposition ID => actual backward distance
     */
    public function distances(Proposition $position, ?Participant $observer, Closure $parentsFor): array
    {
        $cursor = $observer === null ? null : DB::table('phases as phase')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('cycle.participant_id', $observer->id)
            ->orderByDesc('cycle.sequence')->orderByDesc('phase.kind')
            ->first(['cycle.participant_id', 'cycle.sequence', 'phase.kind']);
        $substrateId = $position->phase->cycle->participant->substrate_id;
        if ($observer !== null && $observer->substrate_id !== $substrateId) {
            throw new DomainException('Recovery lineage cannot cross substrates.');
        }
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
                ->where('proposition.id', $id)->first(['phase.gate_proposition_id', 'phase.kind',
                    'cycle.participant_id', 'cycle.sequence', 'cycle.context_horizon_id', 'participant.substrate_id']);
            if ($node === null || $node->substrate_id !== $substrateId) {
                throw new DomainException('Recovery requires intact same-substrate lineage.');
            }
            $distances[$id] = count($distances);
            $parents = array_values(array_unique($parentsFor($id, $node->context_horizon_id)));
            if ($parents === []) {
                break;
            }
            $arrival = $cursor === null ? null : $this->arrival($id, $cursor);
            $cursor = $arrival ?? $node;
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

        return $distances;
    }

    private function arrival(string $id, object $cursor): ?object
    {
        $arrivals = DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('cycle.participant_id', $cursor->participant_id)->where('phase.gate_proposition_id', $id)
            ->where(fn ($q) => $q->where('cycle.sequence', '<', $cursor->sequence)
                ->orWhere(fn ($q) => $q->where('cycle.sequence', $cursor->sequence)->where('phase.kind', '<=', $cursor->kind)))
            ->orderByDesc('cycle.sequence')->orderByDesc('phase.kind')
            ->get(['phase.cycle_id', 'phase.kind', 'cycle.sequence']);
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
                return $phase;
            }
        }

        return null;
    }
}
