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
        return $this->withoutLegacyHolds($query)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('exhaustion_states')
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

    /**
     * Optimistic, indexed parent propagation. A Rest witness closes only a leaf;
     * every internal node requires ALL children closed, including fresh basins.
     * A failed parent is reconsidered if another input later closes in a diamond.
     */
    public function inspect(Phase $phase): array
    {
        $substrateId = $phase->cycle->participant->substrate_id;
        $version = app(PotentialProjectionService::class)->assertCurrent($substrateId);
        $proof = ['substrate_id' => $substrateId, 'field_version' => $version, 'phase_id' => $phase->id,
            'participant_id' => $phase->cycle->participant_id, 'position_id' => $phase->gate_proposition_id,
            'transitions' => [], 'horizons' => [], 'branches' => [], 'stable' => false];
        // Diagnostic calls must not turn a currently actionable Phase into a witness.
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        if (! isset($plan['schema']['$defs']['actions']['participant.rest'])) {
            $proof['reason'] = 'actions_remain';
            $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

            return $proof;
        }
        if (! $this->withoutLegacyHolds(Proposition::whereKey($phase->gate_proposition_id))->exists()) {
            $proof['reason'] = 'legacy_retirement_hold';
            $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

            return $proof;
        }
        $pending = [$phase->gate_proposition_id];
        $closed = [];
        $origin = CommitmentSnapshot::where('substrate_id', $substrateId)->where('calculator_version', 'origin-bootstrap-v1')->value('proposition_id');
        while ($pending !== []) {
            $id = array_shift($pending);
            if ($id === null || $id === $origin || isset($closed[$id])) {
                continue;
            }
            $node = Proposition::with('phase.cycle')->findOrFail($id);
            if ($node->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new \DomainException('Exhaustion cannot cross substrates.');
            }
            $existing = DB::table('exhaustion_states')->where('proposition_id', $id)->exists();
            $children = DB::table('supersessions as edge')->leftJoin('exhaustion_states as state', 'state.proposition_id', '=', 'edge.target_proposition_id')
                ->where('edge.source_proposition_id', $id)->get(['edge.target_proposition_id', 'state.exhausted_at']);
            if (! $existing && ($children->contains(fn ($child) => $child->exhausted_at === null && ! isset($closed[$child->target_proposition_id]))
                || ($children->isEmpty() && $id !== $phase->gate_proposition_id))) {
                continue;
            }
            if (! $existing && $children->isEmpty() && $node->tier === 'expressive') {
                // A fresh Write may reach a later Phase before another participant
                // grounds/attests it. That local Rest is not spent possibility.
                $state = app(PropositionStateService::class);
                if ($state->receipts($node)->isEmpty() || $state->attestations(collect([$node]))->isEmpty()) {
                    continue;
                }
            }
            if (! $existing && $children->isEmpty() && $node->tier === 'committal') {
                continue; // An unopened outgoing possibility has no exploration witness.
            }
            if ($node->tier === 'committal' && $node->commitmentSnapshot === null) {
                continue; // Incomplete legacy provenance cannot establish a basin.
            }
            $closed[$id] = true;
            if (! $existing) {
                $entryHorizon = $node->tier === 'committal'
                    ? app(ContextHorizonProjector::class)->forEntry($node)->id : $node->phase->cycle->context_horizon_id;
                $proof['transitions'][] = ['proposition_id' => $id, 'kind' => $node->tier === 'committal' ? 'basin' : 'excursion',
                    'context_horizon_id' => $entryHorizon, 'owner_horizon_id' => $node->phase->cycle->context_horizon_id,
                    'evidence' => ['reason' => $children->isEmpty() ? 'validated_rest' : 'all_children_exhausted',
                        'child_ids' => $children->pluck('target_proposition_id')->unique()->values()->all()]];
                if ($node->tier === 'committal') {
                    $child = ContextHorizon::where('opened_by_proposition_id', $id)->first();
                    if ($child !== null) {
                        $proof['horizons'][] = $child->id;
                    }
                }
            }
            $branch = DB::table('origin_branches')->where('root_proposition_id', $id)
                ->whereNull('legacy_retired_at')->whereNull('exhausted_at')->first();
            if ($branch !== null && $branch->origin_proposition_id === $origin && $node->tier === 'expressive') {
                $proof['branches'][] = ['root_proposition_id' => $id, 'origin_proposition_id' => $origin,
                    'exhausted' => true, 'would_tragedy' => true, 'reason' => 'origin_lineage_exhausted',
                    'opened_at' => $branch->opened_at];
            }
            foreach (DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id') as $parent) {
                $pending[] = $parent;
            }
        }
        $proof['reason'] = $proof['transitions'] === [] ? 'open_continuations_or_already_exhausted' : 'structural_exhaustion';
        $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

        return $proof;
    }

    /** Caller already owns the tiny version-guarded contribution transaction. */
    public function publish(Phase $phase, array $proof): void
    {
        DB::transaction(function () use ($phase, $proof): void {
            $substrate = Substrate::whereKey($proof['substrate_id'])->lockForUpdate()->firstOrFail();
            if (! $proof['stable'] || $proof['field_version'] !== (int) $substrate->field_version
                || $proof['phase_id'] !== $phase->id || $proof['position_id'] !== $phase->gate_proposition_id
                || $proof['participant_id'] !== $phase->cycle->participant_id
                || $proof['substrate_id'] !== $phase->cycle->participant->substrate_id
                || $phase->fresh()->completed_at === null
                || $phase->cycle->fresh()->rested_at === null) {
                return;
            }
            $affected = $ids = [];
            foreach ($proof['transitions'] as $transition) {
                $changed = DB::table('exhaustion_states')->insertOrIgnore([
                    ...collect($transition)->except(['owner_horizon_id', 'evidence'])->all(),
                    'substrate_id' => $substrate->id, 'phase_id' => $phase->id,
                    'terminal_proposition_id' => $phase->gate_proposition_id,
                    'observed_field_version' => $proof['field_version'], 'exhausted_at' => now(),
                    'evidence' => json_encode($transition['evidence'], JSON_THROW_ON_ERROR),
                ]);
                if ($changed) {
                    $affected[] = $transition['owner_horizon_id'];
                    $affected[] = $transition['context_horizon_id'];
                    $ids[] = $transition['proposition_id'];
                    app(RealtimePublisher::class)->proposition($transition['proposition_id']);
                }
            }
            if ($ids === []) {
                return;
            }
            ContextHorizon::whereKey($proof['horizons'])->whereNull('exhausted_at')->update(['exhausted_at' => now()]);
            app(TragedyService::class)->record($phase, $proof);
            app(FieldMutationService::class)->mark($substrate->id, array_unique($affected), $ids, measurementsDirty: false);
            app(RealtimePublisher::class)->horizons($substrate->id);
            app(RealtimePublisher::class)->graphMetrics($substrate->id);
        });
    }

    /** Compact local alternatives, never a recursively serialized historical tree. */
    public function context(Proposition $gate, int $limit = 25): array
    {
        $seed = app(CommitRegionService::class)->seedFor($gate);
        if ($seed === null) {
            return [];
        }

        return DB::table('supersessions as edge')->join('exhaustion_states as state', 'state.proposition_id', '=', 'edge.target_proposition_id')
            ->join('propositions as node', 'node.id', '=', 'state.proposition_id')
            ->whereIn('edge.source_proposition_id', array_unique([$seed->id, $gate->id]))
            ->select(['node.id', 'node.statement', 'node.tier', 'node.register', 'state.kind', 'state.exhausted_at'])
            ->distinct()->orderBy('node.id')->limit(min(25, max(1, $limit)))->get()
            ->map(fn ($row) => ['id' => $row->id, 'statement' => str($row->statement)->limit(240)->toString(),
                'tier' => $row->tier, 'register' => $row->register, 'exhausted' => $row->kind === 'excursion',
                'basin_exhausted' => $row->kind === 'basin', 'exhausted_at' => $row->exhausted_at, 'actionable' => false])->all();
    }
}
