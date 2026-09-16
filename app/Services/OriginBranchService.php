<?php

namespace App\Services;

use App\Models\CommitmentSnapshot;
use App\Models\Proposition;
use App\Models\Supersession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Causal membership includes all inputs at a reconciliation, across Commit boundaries. */
final class OriginBranchService
{
    public function include(Supersession $edge): void
    {
        $origin = CommitmentSnapshot::where('proposition_id', $edge->source_proposition_id)
            ->where('calculator_version', 'origin-bootstrap-v1')->first();
        if ($origin !== null) {
            DB::table('origin_branches')->insertOrIgnore([
                'root_proposition_id' => $edge->target_proposition_id,
                'origin_proposition_id' => $origin->proposition_id,
                'substrate_id' => $origin->substrate_id,
                'opened_at' => $edge->target->created_at,
            ]);
            DB::table('origin_branch_memberships')->insertOrIgnore([
                'root_proposition_id' => $edge->target_proposition_id,
                'proposition_id' => $edge->target_proposition_id,
            ]);
        }
        $roots = DB::table('origin_branch_memberships as membership')
            ->join('origin_branches as branch', 'branch.root_proposition_id', '=', 'membership.root_proposition_id')
            ->where('membership.proposition_id', $edge->source_proposition_id)->whereNull('branch.pruned_at')
            ->pluck('membership.root_proposition_id');
        foreach ($roots as $root) {
            DB::table('origin_branch_memberships')->insertOrIgnore([
                'root_proposition_id' => $root, 'proposition_id' => $edge->target_proposition_id,
            ]);
        }
    }

    /** No global Eloquent scope: historical inspection keeps ordinary queries. */
    public function active(Builder $query): Builder
    {
        // A shared descendant stays active while any of its causal branches lives.
        return $query->where(fn ($q) => $q
            ->whereNotExists(fn ($m) => $m->selectRaw('1')->from('origin_branch_memberships as membership')
                ->whereColumn('membership.proposition_id', 'propositions.id'))
            ->orWhereExists(fn ($m) => $m->selectRaw('1')->from('origin_branch_memberships as membership')
                ->join('origin_branches as branch', 'branch.root_proposition_id', '=', 'membership.root_proposition_id')
                ->whereColumn('membership.proposition_id', 'propositions.id')->whereNull('branch.pruned_at')));
    }

    public function pruned(Proposition|string $proposition): bool
    {
        if ($proposition instanceof Proposition && ! $proposition->exists) {
            return false;
        }
        $id = $proposition instanceof Proposition ? $proposition->id : $proposition;

        return ! $this->active(Proposition::whereKey($id))->exists();
    }
}
