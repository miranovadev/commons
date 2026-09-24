<?php

namespace App\Services;

use App\Models\Proposition;
use App\Services\Maintenance\LegacyCommitRegionOwnership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Rebuildable membership of proven regions. The referenced regional proof is the only authority. */
final class RegionalExhaustionMembership
{
    /** Capture current immutable Cycle ownership, including the just-written witness. */
    public function capture(string $horizonId, string $seed): array
    {
        return DB::table('propositions as node')->join('phases as phase', 'phase.id', '=', 'node.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('cycle.context_horizon_id', $horizonId)->where('cycle.entry_committal_id', $seed)
            ->where('node.tier', 'expressive')->pluck('node.id')->all();
    }

    public function publish(string $seed, array $ids): void
    {
        foreach (array_chunk(array_values(array_unique([$seed, ...$ids])), 500) as $chunk) {
            DB::table('exhausted_region_memberships')->insertOrIgnore(array_map(fn ($id) => [
                'proposition_id' => $id, 'governing_committal_id' => $seed,
            ], $chunk));
        }
    }

    /** Migration/explicit maintenance only: derive identity from Cycle entry, never legacy flags. */
    public function rebuild(): void
    {
        $proofs = DB::table('commit_region_exhaustions as proof')->join('context_horizons as horizon',
            'horizon.id', '=', 'proof.context_horizon_id')->get(['proof.governing_committal_id', 'horizon.substrate_id']);
        foreach ($proofs->groupBy('substrate_id') as $substrateId => $regional) {
            $nodes = Proposition::whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $substrateId))->get()->keyBy('id');
            $seeds = Schema::hasColumn('cycles', 'entry_committal_id')
                ? app(CommitRegionService::class)->seedIdsFor($nodes, $nodes)
                : $nodes->mapWithKeys(fn ($node) => [$node->id => app(LegacyCommitRegionOwnership::class)->seedFor($node)?->id])->all();
            DB::transaction(function () use ($regional, $seeds): void {
                DB::table('exhausted_region_memberships')->whereIn('governing_committal_id', $regional->pluck('governing_committal_id'))->delete();
                foreach ($regional as $proof) {
                    $this->publish($proof->governing_committal_id, array_keys(array_filter($seeds,
                        fn ($seed) => $seed === $proof->governing_committal_id)));
                }
            });
        }
    }
}
