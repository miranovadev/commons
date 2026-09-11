<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Enums\RegisterPosition;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CommitRegionService
{
    /** Walk every parent path, stopping at its first Committal Proposition. */
    public function seedFor(Proposition $position): ?Proposition
    {
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
    public function membersFor(Proposition $position): Collection
    {
        $seed = $this->seedFor($position);
        if ($seed === null) {
            return collect();
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

        return Proposition::query()->whereKey(collect($members)->pluck('id'))->orderBy('id')->get();
    }

    /** Shared structural pool: Mediate Read, Bias Gate, or Closure Write. */
    public function forPhase(Phase $phase, ?Proposition $position = null): Collection
    {
        $register = match ($phase->kind) {
            PhaseKind::Mediate, PhaseKind::Order => RegisterPosition::Third,
            PhaseKind::Bias => RegisterPosition::Second,
            default => null,
        };
        if ($register === null || $phase->write_register === null) {
            return collect();
        }

        return $this->atPosition($phase, $position ?? $phase->gate()->firstOrFail(), $register);
    }

    public function atPosition(Phase $phase, Proposition $position, RegisterPosition $register): Collection
    {
        if ($position->phase->cycle->participant->substrate_id !== $phase->cycle->participant->substrate_id) {
            return collect();
        }

        return Proposition::query()->whereKey($this->membersFor($position)->pluck('id'))
            ->whereIn('register', $register->registers())
            ->whereDoesntHave('children', fn ($query) => $query->where('supersessions.type', '!=', 'variant'))
            ->orderBy('id')->get();
    }
}
