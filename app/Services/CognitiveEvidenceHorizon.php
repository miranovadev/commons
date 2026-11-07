<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Ordered observation origins in the current lineage and its preceding Commit Region. */
final class CognitiveEvidenceHorizon
{
    public function propositionIds(array $plan): Collection
    {
        $gate = Proposition::with('phase')->findOrFail($plan['gate_id']);
        $phase = isset($plan['phase']['id']) ? Phase::findOrFail($plan['phase']['id']) : $gate->phase;

        return $this->origins($gate->id, new ParticipationContext($phase, collect([$gate->id]), includeUnavailableAdditional: true));
    }

    public function origins(string $gateId, ParticipationContext $context): Collection
    {
        return $context->remember('observation-origins:'.$gateId, function () use ($gateId, $context) {
            $selected = collect();
            $seeds = $context->seedIds();
            $governor = $seeds[$gateId] ?? null;
            $frontier = [$gateId];
            for ($region = 0; $region <= 1 && $frontier !== []; $region++) {
                $previous = [];
                for ($i = 0; $i < count($frontier); $i++) {
                    $id = $frontier[$i];
                    $node = $context->propositions->get($id);
                    if ($node === null || $selected->has($id)) {
                        continue;
                    }
                    $selected->put($id, true);
                    $parents = $context->parents->get($id, collect());
                    if ($node->tier === 'committal') {
                        $previous = $parents->where('type', 'commit')->pluck('source_proposition_id')->all();
                        continue;
                    }
                    $hasLocalParent = false;
                    foreach ($parents->sortBy('source_proposition_id') as $edge) {
                        $source = $edge->source_proposition_id;
                        if ($source === $governor || ($governor !== null && ($seeds[$source] ?? null) === $governor)) {
                            $frontier[] = $source;
                            $hasLocalParent = true;
                        }
                    }
                    // A crossover can have only a foreign causal source. Its
                    // producing Gate still supplies its local operating ancestry.
                    if (! $hasLocalParent && $governor !== null && $parents->contains('type', 'reclassify') && $node->phase->gate_proposition_id !== null
                        && ($seeds[$node->phase->gate_proposition_id] ?? null) === $governor) {
                        $frontier[] = $node->phase->gate_proposition_id;
                    }
                }
                if ($governor !== null && ! $selected->has($governor) && $context->propositions->has($governor)) {
                    $selected->put($governor, true);
                    $previous = $context->parents->get($governor, collect())->where('type', 'commit')->pluck('source_proposition_id')->all();
                }
                $frontier = $previous;
                $governor = $previous === [] ? null : ($seeds[$previous[0]] ?? null);
            }

            return $selected->keys();
        });
    }

    public function receipts(string $gateId, ParticipationContext $context): Collection
    {
        return $context->remember('observations:'.$gateId, function () use ($gateId, $context) {
            $byOrigin = $context->remember('observations-by-origin', fn () => $context->receipts
                ->filter(fn ($r) => $r->observationComplete())
                ->sortBy('id')->groupBy(fn ($r) => $r->evidenceExecution->proposition_id));

            return $this->origins($gateId, $context)->flatMap(fn ($id) => $byOrigin->get($id, collect()))->keyBy('id');
        });
    }
}
