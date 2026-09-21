<?php

namespace App\Services;

use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptConsumption;
use App\Models\Tragedy;
use App\Services\Participation\ParticipationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/** Exhaustion requires a failed continuation after the unique Tragedy Receipt was exercised. */
final class ExhaustionService
{
    public function actionable(Builder $query): Builder
    {
        return $this->scopeActionable($query);
    }

    /** Indexed membership references regional proof; Horizon ownership never retires a position. */
    public function scopeActionable(Builder|QueryBuilder $query, ?string $horizonColumn = null): Builder|QueryBuilder
    {
        return $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('exhausted_region_memberships as membership')
            ->join('commit_region_exhaustions as proof', 'proof.governing_committal_id', '=', 'membership.governing_committal_id')
            ->whereColumn('membership.proposition_id', 'propositions.id'));
    }

    public function regionFor(Phase $phase, ?ParticipationContext $context = null): ?Proposition
    {
        $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate;

        return $gate === null ? null : app(CommitRegionService::class)->seedFor($gate, $context);
    }

    public function boundaryExhausted(ContextHorizon $horizon): bool
    {
        return $horizon->opened_by_proposition_id !== null && DB::table('commit_region_exhaustions')
            ->where('governing_committal_id', $horizon->opened_by_proposition_id)
            ->where('context_horizon_id', $horizon->id)->exists();
    }

    /** Compatibility entry point: archived retirement markers have no semantic authority. */
    public function withoutLegacyHolds(Builder $query): Builder
    {
        return $query;
    }

    public function unavailable(Proposition|string $node): bool
    {
        if ($node instanceof Proposition && ! $node->exists) {
            return false;
        }

        return ! $this->actionable(Proposition::whereKey($node instanceof Proposition ? $node->id : $node))->exists();
    }

    /** Presentation over a caller-scoped proposition query; never an action source. */
    public function withHistoricalState(Builder $query): Builder
    {
        return $query->join('exhausted_region_memberships as membership', 'membership.proposition_id', '=', 'propositions.id')
            ->join('commit_region_exhaustions as exhaustion', 'exhaustion.governing_committal_id', '=', 'membership.governing_committal_id')
            ->select(['propositions.id', 'propositions.statement', 'propositions.register', 'propositions.tier', 'propositions.created_at'])
            ->selectRaw("CASE WHEN propositions.tier = 'committal' THEN 'basin' ELSE 'excursion' END as exhaustion_kind")
            ->addSelect(['exhaustion.exhausted_at', 'exhaustion.governing_committal_id']);
    }

    public function inspect(Phase $phase): array
    {
        return app(HorizonFailureService::class)->inspect($phase);
    }

    public function publish(Phase $phase, array $proof): void
    {
        app(HorizonFailureService::class)->publish($phase, $proof);
    }

    /** Read-only proof that this participant actually continued from the consumed evidence. */
    public function consumedContinuationFor(Phase $phase): ?string
    {
        $seed = $this->regionFor($phase);
        if ($seed === null) {
            return null;
        }
        $target = ReceiptConsumption::query()->where('context_horizon_id', $phase->cycle->context_horizon_id)
            ->where('legacy', false)->whereIn('receipt_id', Tragedy::where('governing_committal_id', $seed->id)->select('receipt_id'))
            ->value('proposition_id');
        if ($target === null || app(CommitRegionService::class)->seedFor(Proposition::findOrFail($target))?->id !== $seed->id) {
            return null;
        }
        $path = app(BackwardRecoveryLineage::class)->distances($phase->gate, $phase->cycle->participant,
            fn (string $id) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());

        return isset($path[$target]) ? $target : null;
    }

    /** The caller holds the root and owns a fresh authoritative no-Potential Rest witness. */
    public function afterFailure(Phase $phase, Tragedy $tragedy, array $proof): bool
    {
        $seed = $this->regionFor($phase);
        if ($seed === null || $seed->id !== $tragedy->governing_committal_id
            || ($proof['governing_committal_id'] ?? null) !== $seed->id
            || $tragedy->context_horizon_id !== $phase->cycle->context_horizon_id
            || $this->consumedContinuationFor($phase) !== ($proof['consumed_continuation_id'] ?? null)
            || $tragedy->phase_id === $phase->id || $proof['rest_reason'] !== 'no_remaining_potential'
            || ($proof['consumed_continuation_id'] ?? null) === null
            || ! ReceiptConsumption::whereKey($tragedy->receipt_id)->where('legacy', false)
                ->where('context_horizon_id', $phase->cycle->context_horizon_id)
                ->where('proposition_id', $proof['consumed_continuation_id'])->exists()) {
            return false;
        }
        $horizon = ContextHorizon::whereKey($tragedy->context_horizon_id)->lockForUpdate()->firstOrFail();
        $changed = DB::table('commit_region_exhaustions')->insertOrIgnore([
            'governing_committal_id' => $seed->id,
            'context_horizon_id' => $horizon->id, 'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id,
            'phase_id' => $phase->id, 'terminal_proposition_id' => $phase->gate_proposition_id,
            'observed_field_version' => $proof['field_version'], 'exhausted_at' => now(),
        ]);
        if ($changed) {
            app(RegionalExhaustionMembership::class)->publish($seed->id, $proof['region_member_ids'] ?? []);
            $boundary = $horizon->opened_by_proposition_id === $seed->id;
            $origin = $horizon->parent_context_horizon_id === null
                && $seed->commitmentSnapshot?->calculator_version === 'origin-bootstrap-v1';
            if ($boundary || $origin) {
                $horizon->forceFill(['exhausted_at' => now()])->save();
            }
            $affected = array_values(array_filter([$horizon->id, $boundary ? $horizon->parent_context_horizon_id : null]));
            app(FieldMutationService::class)->mark($horizon->substrate_id, $affected, [$seed->id], measurementsDirty: false);
            app(RealtimePublisher::class)->proposition($seed->id);
            if ($boundary || $origin) {
                app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
                    ['type' => 'horizon.exhausted', 'opening_proposition_id' => $horizon->opened_by_proposition_id,
                        'governing_committal_id' => $seed->id]);
            }
            app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
                ['type' => 'region.exhausted', 'governing_committal_id' => $seed->id, 'boundary_exhausted' => $boundary]);
        }

        return true;
    }
}
