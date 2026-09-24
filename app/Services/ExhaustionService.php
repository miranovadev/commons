<?php

namespace App\Services;

use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Tragedy;
use App\Services\Participation\ParticipationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/** A later fresh expressive Charge boundary in the same Region proves exhaustion. */
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
        return $this->openingRegionExhaustedAt($horizon) !== null;
    }

    /** Boundary/Origin presentation derives directly from the opening Committal's proof. */
    public function openingRegionExhaustedAt(ContextHorizon $horizon): ?string
    {
        return ContextHorizon::whereKey($horizon->id)->withOpeningRegionExhaustion()->first()?->opening_region_exhausted_at;
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

    /** Called only by the fresh-expression boundary observer under the Substrate lock. */
    public function recordBoundary(Proposition $terminal, Tragedy $tragedy, array $field): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new \DomainException('Exhaustion belongs to the expressive contribution transaction.');
        }
        $phase = $terminal->phase;
        $seed = app(CommitRegionService::class)->seedFor($terminal);
        if (! $terminal->wasRecentlyCreated || $terminal->tier !== 'expressive' || $field['charge'] !== 0.0
            || $seed?->id !== $tragedy->governing_committal_id
            || $terminal->id === $tragedy->terminal_proposition_id
            || $phase->cycle->context_horizon_id !== $tragedy->context_horizon_id) {
            return false;
        }
        $horizon = ContextHorizon::whereKey($tragedy->context_horizon_id)->lockForUpdate()->firstOrFail();
        $changed = DB::table('commit_region_exhaustions')->insertOrIgnore([
            'governing_committal_id' => $seed->id,
            'context_horizon_id' => $horizon->id, 'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id,
            'phase_id' => $phase->id, 'terminal_proposition_id' => $terminal->id,
            'observed_field_version' => Substrate::whereKey($horizon->substrate_id)->value('field_version'),
            'boundary_field' => json_encode($field, JSON_THROW_ON_ERROR), 'exhausted_at' => now(),
        ]);
        if ($changed) {
            app(RegionalExhaustionMembership::class)->publish($seed->id,
                app(RegionalExhaustionMembership::class)->capture($horizon->id, $seed->id));
            $boundary = $horizon->opened_by_proposition_id === $seed->id;
            $origin = $horizon->parent_context_horizon_id === null
                && $seed->commitmentSnapshot?->calculator_version === 'origin-bootstrap-v1';
            $affected = array_values(array_filter([$horizon->id, $boundary ? $horizon->parent_context_horizon_id : null]));
            app(FieldMutationService::class)->mark($horizon->substrate_id, $affected, [$seed->id], measurementsDirty: false);
            app(RealtimePublisher::class)->proposition($seed->id);
            app(RealtimePublisher::class)->horizons($horizon->substrate_id);
            app(RealtimePublisher::class)->graphMetrics($horizon->substrate_id);
            if ($boundary || $origin) {
                app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
                    ['type' => 'horizon.exhausted', 'opening_proposition_id' => $horizon->opened_by_proposition_id,
                        'governing_committal_id' => $seed->id, 'opening_region_exhausted' => true,
                        'boundary_region_exhausted' => $boundary]);
            }
            app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
                ['type' => 'region.exhausted', 'governing_committal_id' => $seed->id, 'boundary_exhausted' => $boundary]);
        }

        // A separately accepted concurrent birth remains historical even when
        // another proposition won the unique proof. Never replace that witness.
        if (! $changed) {
            app(RegionalExhaustionMembership::class)->publish($seed->id, [$terminal->id]);
        }

        return true;
    }
}
