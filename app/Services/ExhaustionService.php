<?php

namespace App\Services;

use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Explicit structural closure, independent of numeric Potential and observer visibility. */
final class ExhaustionService
{
    public function actionable(Builder $query): Builder
    {
        return $this->withoutLegacyHolds($query)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('continuation_failures')
            ->whereColumn('continuation_failures.proposition_id', 'propositions.id'))->whereNotExists(fn ($q) => $q->selectRaw('1')->from('exhaustion_states')
            ->whereColumn('exhaustion_states.proposition_id', 'propositions.id'));
    }

    public function withoutLegacyHolds(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereNotExists(fn ($m) => $m->selectRaw('1')->from('origin_branch_memberships as membership')
                ->whereColumn('membership.proposition_id', 'propositions.id'))
            ->orWhereExists(fn ($m) => $m->selectRaw('1')->from('origin_branch_memberships as membership')
                ->join('origin_branches as branch', 'branch.root_proposition_id', '=', 'membership.root_proposition_id')
                ->whereColumn('membership.proposition_id', 'propositions.id')->whereNull('branch.legacy_retired_at')));
    }

    public function unavailable(Proposition|string $node): bool
    {
        if ($node instanceof Proposition && ! $node->exists) {
            return false;
        }

        return ! $this->actionable(Proposition::whereKey($node instanceof Proposition ? $node->id : $node))->exists();
    }

    public function inspect(Phase $phase): array
    {
        return app(HorizonFailureService::class)->inspect($phase);
    }

    public function publish(Phase $phase, array $proof): void
    {
        app(HorizonFailureService::class)->publish($phase, $proof);
    }
}
