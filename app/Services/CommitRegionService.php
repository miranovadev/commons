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

    /** Batched read-only equivalent of seedFor, using a substrate-scoped graph. */
    public function seedIdsFor(Collection $positions, Collection $nodes, Collection $parents): array
    {
        $children = $pendingParents = $seeds = [];
        foreach ($nodes as $node) {
            $incoming = $node->tier === 'committal' ? collect() : $parents->get($node->id, collect())
                ->filter(fn ($edge) => $nodes->has($edge->source_proposition_id));
            $pendingParents[$node->id] = $incoming->count();
            $seeds[$node->id] = $node->tier === 'committal' ? $node->id : null;
            foreach ($incoming as $edge) {
                $children[$edge->source_proposition_id][] = $node->id;
            }
        }
        $ready = array_keys(array_filter($pendingParents, fn ($count) => $count === 0));
        $processed = [];
        while ($ready !== []) {
            $id = array_pop($ready);
            $processed[$id] = true;
            foreach ($children[$id] ?? [] as $child) {
                if ($seeds[$id] !== null) {
                    // false means multiple governing seeds; that ambiguity
                    // persists through expressive descendants, until a Commit.
                    $seeds[$child] = $seeds[$child] === null ? $seeds[$id]
                        : ($seeds[$child] === $seeds[$id] ? $seeds[$child] : false);
                }
                if (--$pendingParents[$child] === 0) {
                    $ready[] = $child;
                }
            }
        }
        $result = [];
        foreach ($positions as $position) {
            if (isset($processed[$position->id])) {
                $result[$position->id] = is_string($seeds[$position->id]) ? $seeds[$position->id] : null;

                continue;
            }
            // Legacy malformed cycles still terminate and obey seedFor's UNION
            // semantics; normal acyclic substrate graphs take the linear path.
            $seen = $found = [];
            $pending = [$position->id];
            while ($pending !== []) {
                $id = array_pop($pending);
                if (isset($seen[$id]) || ! $nodes->has($id)) {
                    continue;
                }
                $seen[$id] = true;
                if ($nodes[$id]->tier === 'committal') {
                    $found[$id] = true;
                } else {
                    foreach ($parents->get($id, collect()) as $edge) {
                        $pending[] = $edge->source_proposition_id;
                    }
                }
            }
            $result[$position->id] = count($found) === 1 ? array_key_first($found) : null;
        }

        return $result;
    }

    /** Walk every parent path, stopping at its first Committal Proposition. */
    public function seedFor(Proposition $position, ?ParticipationContext $context = null): ?Proposition
    {
        if ($context !== null) {
            return $context->seed($position);
        }
        $ancestors = DB::select(<<<'SQL'
            WITH RECURSIVE ancestors (id, tier) AS (
                SELECT id, tier FROM propositions WHERE id = ?
                UNION
                SELECT p.id, p.tier FROM propositions p
                JOIN supersessions s ON s.source_proposition_id = p.id
                JOIN ancestors a ON a.id = s.target_proposition_id
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                JOIN participants participant ON participant.id = c.participant_id
                WHERE a.tier = 'expressive' AND participant.substrate_id = ?
            ) SELECT id FROM ancestors WHERE tier = 'committal'
            SQL, [$position->getKey(), $position->phase->cycle->participant->substrate_id]);

        // A valid reconciliation joins one region. Never union unrelated seeds
        // when inspecting legacy cross-region or incomplete ancestry.
        return count($ancestors) === 1 ? Proposition::query()->find($ancestors[0]->id) : null;
    }

    /** Structural membership includes superseded intermediates, but never crosses a Commit. */
    public function membersFor(Proposition $position, ?ParticipationContext $context = null): Collection
    {
        $seed = $this->seedFor($position, $context);
        if ($seed === null) {
            return collect();
        }
        if ($context !== null) {
            return $context->members($seed);
        }
        $members = DB::select(<<<'SQL'
            WITH RECURSIVE region (id) AS (
                SELECT id FROM propositions WHERE id = ?
                UNION
                SELECT p.id FROM propositions p
                JOIN supersessions s ON s.target_proposition_id = p.id
                JOIN region r ON r.id = s.source_proposition_id
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                JOIN participants participant ON participant.id = c.participant_id
                WHERE p.tier = 'expressive' AND participant.substrate_id = ?
            ), ancestry (member_id, id, tier) AS (
                SELECT r.id, p.id, p.tier FROM region r JOIN propositions p ON p.id = r.id
                UNION
                SELECT a.member_id, p.id, p.tier FROM ancestry a
                JOIN supersessions s ON s.target_proposition_id = a.id
                JOIN propositions p ON p.id = s.source_proposition_id
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                JOIN participants participant ON participant.id = c.participant_id
                WHERE a.tier = 'expressive' AND participant.substrate_id = ?
            ) SELECT member_id AS id FROM ancestry
                WHERE tier = 'committal' AND member_id != ?
                GROUP BY member_id HAVING COUNT(DISTINCT id) = 1 AND MIN(id) = ?
            SQL, [$seed->getKey(), $seed->phase->cycle->participant->substrate_id,
            $seed->phase->cycle->participant->substrate_id, $seed->getKey(), $seed->getKey()]);

        return Proposition::query()->active()->whereKey(collect($members)->pluck('id'))->orderBy('id')->get();
    }

    /** Operational pool in the Cycle's Domain: Mediate Read, Bias Gate, or Closure Write. */
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
