<?php

namespace App\Services;

use App\Models\Proposition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LineageService
{
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

