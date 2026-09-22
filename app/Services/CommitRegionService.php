<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CommitRegionService
{
    public function __construct(private readonly PhasePositionService $positions) {}

    /**
     * Persisted Cycle governance is the only live ownership authority. Parents
     * remain an accepted argument for callers carrying a graph, but are not read.
     * One bounded join resolves all supplied positions, independently of topology.
     */
    public function seedIdsFor(Collection $positions, Collection $nodes, ?Collection $parents = null): array
    {
        $ids = $positions->filter(fn ($node) => $node->exists)->pluck('id');
        $result = $positions->mapWithKeys(fn ($node) => [$node->id => null])->all();
        if ($ids->isEmpty()) {
            return $result;
        }
        $rows = DB::table('propositions as p')
            ->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->join('cycles as c', 'c.id', '=', 'ph.cycle_id')
            ->join('participants as participant', 'participant.id', '=', 'c.participant_id')
            ->leftJoin('context_horizons as h', 'h.id', '=', 'c.context_horizon_id')
            ->leftJoin('propositions as g', 'g.id', '=', 'c.governing_committal_id')
            ->leftJoin('phases as gp', 'gp.id', '=', 'g.phase_id')
            ->leftJoin('cycles as gc', 'gc.id', '=', 'gp.cycle_id')
            ->leftJoin('participants as governor_participant', 'governor_participant.id', '=', 'gc.participant_id')
            ->leftJoin('context_horizons as gh', 'gh.id', '=', 'gc.context_horizon_id')
            ->leftJoin('commitment_snapshots as snapshot', 'snapshot.proposition_id', '=', 'g.id')
            ->leftJoin('context_horizons as child', 'child.opened_by_proposition_id', '=', 'g.id')
            ->whereIn('p.id', $ids)
            ->selectRaw(<<<'SQL'
                p.id,
                CASE
                    WHEN p.tier = 'committal' THEN p.id
                    WHEN p.tier = 'expressive' AND g.tier = 'committal'
                        AND participant.substrate_id = governor_participant.substrate_id
                        AND participant.substrate_id = h.substrate_id
                        AND participant.substrate_id = gh.substrate_id
                        AND participant.substrate_id = snapshot.substrate_id
                        AND snapshot.direction_before = gh.domain
                        AND (
                            (snapshot.direction_before = snapshot.direction_after
                                AND child.id IS NULL AND h.id = gh.id)
                            OR (snapshot.direction_before <> snapshot.direction_after
                                AND child.id = h.id AND child.parent_context_horizon_id = gh.id
                                AND child.domain = snapshot.direction_after)
                        )
                    THEN g.id
                    ELSE NULL
                END AS seed_id
                SQL)->get();
        foreach ($rows as $row) {
            $result[$row->id] = $row->seed_id;
        }

        return $result;
    }

    /** Committals own themselves; expressive Propositions use their creating Cycle. */
    public function seedFor(Proposition $position, ?ParticipationContext $context = null): ?Proposition
    {
        if (! $position->exists) {
            return null;
        }
        if ($context !== null) {
            return $context->seed($position);
        }
        if ($position->tier === 'committal') {
            return $position;
        }
        $seed = $this->seedIdsFor(collect([$position]), collect([$position]))[$position->id];

        return $seed === null ? null : Proposition::find($seed);
    }

    /** Active expressive members owned by this governor; causal topology is independent. */
    public function membersFor(Proposition $position, ?ParticipationContext $context = null): Collection
    {
        $seed = $this->seedFor($position, $context);
        if ($seed === null) {
            return collect();
        }
        if ($context !== null) {
            return $context->members($seed);
        }
        $members = Proposition::query()->active()->where('tier', 'expressive')
            ->whereHas('phase.cycle', fn ($q) => $q->where('governing_committal_id', $seed->id))
            ->orderBy('id')->get();
        $seeds = $this->seedIdsFor($members, $members);

        return $members->filter(fn ($node) => $seeds[$node->id] === $seed->id)->values();
    }

    /** Regional calculation population; membership alone never authorizes an operation. */
    public function forPhase(Phase $phase, ?Proposition $position = null, ?ParticipationContext $context = null): Collection
    {
        return $context === null ? $this->phaseCandidates($phase, $position)
            : $context->remember('phase-candidates:'.$phase->id.':'.($position?->id ?? $phase->gate_proposition_id),
                fn () => $this->phaseCandidates($phase, $position, $context));
    }

    private function phaseCandidates(Phase $phase, ?Proposition $position = null, ?ParticipationContext $context = null): Collection
    {
        $positionKind = $this->positions->candidatePosition($phase->kind);
        if ($positionKind === null || $phase->write_register === null) {
            return collect();
        }

        return $this->atPhasePosition($phase, $position ?? ($context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->firstOrFail()), $positionKind, $context, includeSuperseded: $phase->kind === PhaseKind::Mediate);
    }

    /** Direct admissibility checks need one source and its ancestry, not a region scan per candidate. */
    public function isCandidateFor(Proposition $node, Phase $phase, ?ParticipationContext $context = null): bool
    {
        $position = $this->positions->candidatePosition($phase->kind);
        if ($position === null || ! $this->positions->matches($node, $phase, $position)) {
            return false;
        }
        if ($context !== null) {
            return $this->forPhase($phase, context: $context)->contains('id', $node->id);
        }
        if (! Proposition::query()->active()->whereKey($node->id)
            ->when($phase->kind !== PhaseKind::Mediate, fn ($query) => $query->whereDoesntHave('children',
                fn ($query) => $query->where('supersessions.type', '!=', 'variant')))->exists()) {
            return false;
        }
        $gate = $phase->gate()->first();
        $seed = $gate === null ? null : $this->seedFor($gate);

        return $seed !== null && $this->seedFor($node)?->id === $seed->id;
    }

    public function atPhasePosition(Phase $phase, Proposition $position, PhaseKind $positionKind, ?ParticipationContext $context = null, bool $includeSuperseded = false): Collection
    {
        if ($position->phase->cycle->participant->substrate_id !== $phase->cycle->participant->substrate_id) {
            return collect();
        }

        // A direct Commit consumes the source for everyone; Variants preserve alternatives.
        $nonConsuming = ['variant'];
        if ($context !== null) {
            $members = $this->positions->filter($this->membersFor($position, $context), $phase, $positionKind);

            return $includeSuperseded ? $members->values()
                : $members->filter(fn ($node) => $context->children->get($node->id, collect())->whereNotIn('type', $nonConsuming)->isEmpty())->values();
        }

        return $this->positions->scope(Proposition::query()->whereKey($this->membersFor($position)->pluck('id')), $phase, $positionKind)
            ->when(! $includeSuperseded, fn ($query) => $query->whereDoesntHave('children', fn ($query) => $query->whereNotIn('supersessions.type', $nonConsuming)))
            ->orderBy('id')->get();
    }
}

