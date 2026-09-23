<?php

namespace App\Services;

use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

/** Rebuildable membership of proven regions. The referenced regional proof is the only authority. */
final class RegionalExhaustionMembership
{
    /** Capture from the already version-checked projection, outside the contribution lock. */
    public function capture(string $horizonId, string $seed): array
    {
        $nodes = app(PotentialProjectionService::class)->region($horizonId)['local']['nodes'];

        return array_keys(array_filter($nodes, fn ($node) => ($node['seed_id'] ?? null) === $seed));
    }

    public function publish(string $seed, array $ids): void
    {
        foreach (array_chunk(array_values(array_unique([$seed, ...$ids])), 500) as $chunk) {
            DB::table('exhausted_region_memberships')->insertOrIgnore(array_map(fn ($id) => [
                'proposition_id' => $id, 'governing_committal_id' => $seed,
            ], $chunk));
        }
    }

    /** Migration/explicit maintenance only: derive identity from Cycle governance, never legacy flags. */
    public function rebuild(): void
    {
        $proofs = DB::table('commit_region_exhaustions as proof')->join('context_horizons as horizon',
            'horizon.id', '=', 'proof.context_horizon_id')->get(['proof.governing_committal_id', 'horizon.substrate_id']);
        foreach ($proofs->groupBy('substrate_id') as $substrateId => $regional) {
            $nodes = Proposition::whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $substrateId))->get()->keyBy('id');
            $seeds = \Illuminate\Support\Facades\Schema::hasColumn('cycles', 'entry_committal_id')
                ? app(CommitRegionService::class)->seedIdsFor($nodes, $nodes)
                : $nodes->mapWithKeys(fn ($node) => [$node->id => app(\App\Services\Maintenance\LegacyCommitRegionOwnership::class)->seedFor($node)?->id])->all();
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

