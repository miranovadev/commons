<?php

namespace App\Services\Observables;

use App\Models\Proposition;
use App\Models\Supersession;
use Illuminate\Support\Facades\DB;

/**
 * Select the last two genuine intervals from the active, locally selected causal
 * ancestry. Hard replacements skip the replaced node but retain its predecessors.
 * Never import a foreign Reconcile operand or cross a canonical Commit boundary.
 */
final readonly class ActiveCadenceLineageService
{
    public function predecessor(Proposition $node): ?Proposition
    {
        $seen = [];
        $current = $node;
        while (true) {
            if (isset($seen[$current->id])) {
                throw new \DomainException('Cyclic selected cadence ancestry.');
            }
            $seen[$current->id] = true;
            $edge = Supersession::query()->where('target_proposition_id', $current->id)
                ->with(['source', 'phase'])
                ->orderBy('id')->get()
                ->first(fn ($edge) => $edge->type !== 'reconcile'
                    || $edge->source_proposition_id === $edge->phase->gate_proposition_id);
            if ($edge === null) {
                return null;
            }
            $source = $edge->source;
            if ($source->tier === 'committal') {
                return null;
            }
            if (in_array($edge->type, ['reclassify', 'revise'], true)) {
                $current = $source;
                continue;
            }

            return $source;
        }
    }

    public function intervals(Proposition $node): array
    {
        $parts = [];
        $seen = [];
        $current = $this->predecessor($node);
        while ($current !== null && count($parts) < 2) {
            if (isset($seen[$current->id])) {
                throw new \DomainException('Cyclic selected cadence ancestry.');
            }
            $seen[$current->id] = true;
            $state = DB::table('local_chirality_states')->where('proposition_id', $current->id)->first();
            if ($state !== null) {
                $own = json_decode($state->intervals, true, flags: JSON_THROW_ON_ERROR);
                // A predecessor's own intervals precede those of earlier ancestors.
                array_unshift($parts, ...array_slice($own, - (2 - count($parts))));
            }
            $current = $this->predecessor($current);
        }

        return array_slice($parts, -2);
    }
}
