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

        return $context === null ? $calculate() : $context->remember('terminal-continuations:'.$phase->cycle->context_horizon_id, $calculate);
    }

    public function idsForHorizons(Collection $horizons): Collection
    {
        $result = collect();
        foreach (DB::table('context_horizons')->whereIn('id', $horizons)->distinct()->pluck('substrate_id') as $substrate) {
            $hasEvents = DB::table('terminal_encounters as encounter')
                ->join('participants as actor', 'actor.id', '=', 'encounter.participant_id')
                ->where('actor.substrate_id', $substrate)->exists();
            $hasProof = DB::table('commit_region_exhaustions as proof')
                ->join('context_horizons as horizon', 'horizon.id', '=', 'proof.context_horizon_id')
                ->where('horizon.substrate_id', $substrate)->exists();
            if ($hasEvents || $hasProof) {
                $result = $result->union(app(TerminalContinuationService::class)->graph($substrate)['closed']);
            }
        }

        return $result;
    }
}
