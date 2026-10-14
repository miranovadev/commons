<?php

namespace App\Services;

use App\Models\Phase;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Derived terminal paths; the underlying encounter and regional proofs are the authorities. */
final class ExhaustedContinuationService
{
    public function ids(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $calculate = fn () => $this->idsForHorizons(collect([$phase->cycle->context_horizon_id]));

        return $context === null ? $calculate() : $context->remember('terminal-continuations:'.$phase->id, $calculate);
    }

    public function idsForHorizons(Collection $horizons): Collection
    {
        $result = collect();
        foreach (DB::table('context_horizons')->whereIn('id', $horizons)->distinct()->pluck('substrate_id') as $substrate) {
            $result = $result->union(app(TerminalContinuationService::class)->graph($substrate)['closed']);
        }

        return $result;
    }
}
