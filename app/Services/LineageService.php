<?php

namespace App\Services;

use App\Models\Proposition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LineageService
{
    /** Immediate forward edges only; history/recovery still use neighbourIds. */
    public function immediateDescendantIds(Proposition $gate): Collection
    {
        return $gate->children()->pluck('propositions.id')
            ->reject(fn (string $id): bool => $id === $gate->getKey())
            ->unique()->values();
    }

    /** Forward family inside one Horizon; Committal boundaries terminate expansion. */
    public function descendantsInHorizon(Proposition $parent, string $horizonId): Collection
    {
        $seen = collect([$parent->getKey()]);
        $frontier = $seen;
        $descendants = collect();
        while ($frontier->isNotEmpty()) {
            $next = Proposition::query()
                ->whereIn('id', DB::table('supersessions')
                    ->whereIn('source_proposition_id', $frontier)->select('target_proposition_id'))
                ->whereHas('contextHorizonMembership', fn ($query) => $query->where('context_horizon_id', $horizonId))
                ->whereHas('phase.cycle.participant', fn ($query) => $query->where(
                    'substrate_id', $parent->phase->cycle->participant->substrate_id,
                ))
                ->whereNotIn('id', $seen)->get();
            $descendants = $descendants->concat($next);
            $seen = $seen->merge($next->pluck('id'));
            $frontier = $next->where('tier', 'expressive')->pluck('id');
        }
        return $descendants;
    }

    /** @return Collection<int, string> */
    public function neighbourIds(Proposition $position): Collection
    {
        return $position->parents()->pluck('propositions.id')
            ->merge($position->children()->pluck('propositions.id'))
            ->reject(fn (string $id): bool => $id === $position->getKey())
            ->unique()->values();
    }

    /**
     * @return Collection<int, Proposition>
     */
    public function endingAt(
        Proposition $gate,
        int $maxReadCount,
        ?int $maxTokenCount = null,
    ): Collection {
        $lineage = collect();
        $frontier = collect([$gate]);
        $tokens = 0;

        while ($frontier->isNotEmpty() && $lineage->count() < $maxReadCount) {
            $nextIds = collect();

            foreach ($frontier as $proposition) {
                $estimate = $this->estimateTokens($proposition->statement);

                if (
                    $maxTokenCount !== null
                    && $lineage->isNotEmpty()
                    && $tokens + $estimate > $maxTokenCount
                ) {
                    return $lineage;
                }

                $lineage->push($proposition);
                $tokens += $estimate;

                if ($lineage->count() === $maxReadCount) {
                    return $lineage;
                }

                $nextIds->push(
                    ...DB::table('supersessions')
                        ->where('target_proposition_id', $proposition->getKey())
                        ->pluck('source_proposition_id')
                );
            }

            $frontier = Proposition::query()
                ->whereIn('id', $nextIds->unique())
                ->latest('created_at')
                ->get();
        }

        return $lineage;
    }

    private function estimateTokens(string $statement): int
    {
        return max(1, (int) ceil(mb_strlen($statement) / 4));
    }
}
