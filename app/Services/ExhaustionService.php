<?php

namespace App\Services;

use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptConsumption;
use App\Models\Tragedy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Exhaustion requires a failed continuation after the unique Tragedy Receipt was exercised. */
final class ExhaustionService
{
    public function actionable(Builder $query): Builder
    {
        return $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('continuation_failures')
            ->whereColumn('continuation_failures.proposition_id', 'propositions.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('horizon_exhaustions')
                ->whereColumn('horizon_exhaustions.opening_proposition_id', 'propositions.id'))
            ->whereHas('phase.cycle', fn ($q) => $q->whereNotExists(fn ($closed) => $closed->selectRaw('1')
                ->from('horizon_exhaustions')->whereColumn('horizon_exhaustions.context_horizon_id', 'cycles.context_horizon_id')));
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
        return $query->leftJoin('continuation_failures as failure', 'failure.proposition_id', '=', 'propositions.id')
            ->leftJoin('horizon_exhaustions as opened', 'opened.opening_proposition_id', '=', 'propositions.id')
            ->join('phases as history_phase', 'history_phase.id', '=', 'propositions.phase_id')
            ->join('cycles as history_cycle', 'history_cycle.id', '=', 'history_phase.cycle_id')
            ->leftJoin('horizon_exhaustions as owner', 'owner.context_horizon_id', '=', 'history_cycle.context_horizon_id')
            ->where(fn ($q) => $q->whereNotNull('failure.proposition_id')->orWhereNotNull('opened.context_horizon_id')->orWhereNotNull('owner.context_horizon_id'))
            ->select(['propositions.id', 'propositions.statement', 'propositions.register', 'propositions.tier', 'propositions.created_at'])
            ->selectRaw("CASE WHEN propositions.tier = 'committal' THEN 'basin' ELSE 'excursion' END as exhaustion_kind")
            ->selectRaw('COALESCE(opened.exhausted_at, owner.exhausted_at, failure.failed_at) as exhausted_at');
    }

    public function inspect(Phase $phase): array
    {
        return app(HorizonFailureService::class)->inspect($phase);
    }

    public function publish(Phase $phase, array $proof): void
    {
        app(HorizonFailureService::class)->publish($phase, $proof);
    }

    /** The caller holds the root and owns a fresh authoritative no-Potential Rest witness. */
    public function afterFailure(Phase $phase, Tragedy $tragedy, array $proof): bool
    {
        if ($tragedy->phase_id === $phase->id || $proof['rest_reason'] !== 'no_remaining_potential'
            || ! ReceiptConsumption::whereKey($tragedy->receipt_id)->where('legacy', false)->exists()) {
            return false;
        }
        $horizon = ContextHorizon::whereKey($tragedy->context_horizon_id)->lockForUpdate()->firstOrFail();
        $changed = DB::table('horizon_exhaustions')->insertOrIgnore([
            'context_horizon_id' => $horizon->id, 'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id,
            // Origin has no predecessor opening Committal. It remains unique forever.
            'opening_proposition_id' => $horizon->opened_by_proposition_id,
            'phase_id' => $phase->id, 'terminal_proposition_id' => $phase->gate_proposition_id,
            'observed_field_version' => $proof['field_version'], 'exhausted_at' => now(),
        ]);
        if ($changed) {
            $horizon->forceFill(['exhausted_at' => now()])->save();
            $affected = array_values(array_filter([$horizon->id, $horizon->parent_context_horizon_id]));
            app(FieldMutationService::class)->mark($horizon->substrate_id, $affected,
                array_values(array_filter([$horizon->opened_by_proposition_id])), measurementsDirty: false);
            if ($horizon->opened_by_proposition_id !== null) {
                app(RealtimePublisher::class)->proposition($horizon->opened_by_proposition_id);
            }
            app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
                ['type' => 'horizon.exhausted', 'opening_proposition_id' => $horizon->opened_by_proposition_id]);
        }

        return true;
    }
}
