<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Read-only look-ahead through personally authorised Traverse hops in this Cycle. */
final readonly class GenerativeTraversalService
{
    public function __construct(
        private ContinuationOpportunityService $opportunities,
        private AcquisitionAttemptService $acquisition,
        private PhasePositionService $positions,
    ) {}

    /** @return Collection<string, int> base Traverse destinations with a meaningful onward action */
    public function destinations(Phase $from, Collection $candidates, ParticipationContext $context): Collection
    {
        $probes = $this->arrivals($from, $candidates, $context);

        return $this->reachable($probes, $context)->pluck('gate_proposition_id')->flip();
    }

    private function arrivals(Phase $from, Collection $candidates, ParticipationContext $context): Collection
    {
        if ($from->kind === PhaseKind::Order) {
            return collect(); // A Committal ends this excursion.
        }
        $next = PhaseKind::from($from->kind->value + 1);
        $seeds = $context->seedIds();

        $crossings = app(CausalCrossoverService::class)->children($from, $context)->keyBy('id');

        return $candidates->filter(fn (Proposition $node) => $crossings->has($node->id)
            || ($this->positions->matches($node, $from, $from->kind)
                && ($seeds[$node->id] ?? null) === $from->governance()->entry_committal_id))
            ->mapWithKeys(function (Proposition $gate) use ($from, $next, $context, $crossings) {
                $crossed = $crossings->has($gate->id) ? $gate->phase->governance() : $from->crossoverCycle;
                $phase = $this->positions->prospectiveArrival($from, $gate, $next, $crossed, $context->propositions->get($from->gate_proposition_id));

                return [$phase->id => $phase];
            });
    }

    /** Each layer advances exactly one Phase. Shared arrivals are evaluated once per layer. */
    private function reachable(Collection $probes, ParticipationContext $context): Collection
    {
        if ($probes->isEmpty()) {
            return collect();
        }
        $evaluation = $this->opportunities->comparisonContext($context->phase, $probes, $context);
        $direct = $probes->filter(function (Phase $phase) use ($evaluation): bool {
            // A projected Phase has no execution. Use the same pre-acquisition
            // decision as a real arrival, without reserving an attempt.
            $options = app(PropositionOptionService::class);
            $judgment = $options->attestationOptionsFor($phase, $evaluation)->isNotEmpty();

            return $judgment || $this->acquisition->state($phase, $evaluation)['status'] === 'required'
                || $this->opportunities->nonVariantGenerationAvailable($phase, $evaluation)
                || (in_array('proposition.revise', config('phases.'.$phase->kind->value.'.actions', []), true)
                    && $options->revisionOptionsFor($phase, $phase->cycle->participant, $evaluation)->isNotEmpty())
                || ($phase->kind === PhaseKind::Order && $options->traversalOptionsFor($phase, $evaluation, base: true)->isNotEmpty());
        });
        $children = collect();
        $paths = [];
        foreach ($probes->diffKeys($direct) as $id => $phase) {
            if (! in_array('proposition.traverse', config('phases.'.$phase->kind->value.'.actions', []), true)
                || $phase->kind === PhaseKind::Order) {
                continue;
            }
            $base = app(PropositionOptionService::class)->traversalOptionsFor($phase, $evaluation, base: true);
            $arrivals = $this->arrivals($phase, $base, $evaluation);
            $paths[$id] = $arrivals->keys();
            $children = $children->union($arrivals);
        }
        $reachable = $this->reachable($children, $context);

        return $direct->union($probes->filter(fn ($phase, $id) => isset($paths[$id]) && $paths[$id]->contains(fn ($child) => $reachable->has($child))));
    }
}
