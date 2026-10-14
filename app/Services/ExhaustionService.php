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

/** Unique regional proof after all actual continuations terminate. */
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

    /** Caller owns the Substrate lock; publication and recursion share its transaction. */
    public function evaluate(Proposition $seed, string $encounterId, ?string $descendant = null): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new \DomainException('Exhaustion belongs to the contribution transaction.');
        }
        if (DB::table('commit_region_exhaustions')->where('governing_committal_id', $seed->id)->exists()) {
            return false;
        }
        $encounter = DB::table('terminal_encounters')->where('id', $encounterId)->first();
        if ($encounter === null || ($descendant === null && $encounter->governing_committal_id !== $seed->id)) {
            throw new \DomainException('Exhaustion requires an authoritative terminal cause.');
        }
        if ($descendant !== null) {
            $exit = DB::table('supersessions as edge')
                ->join('propositions as source', 'source.id', '=', 'edge.source_proposition_id')
                ->join('phases as phase', 'phase.id', '=', 'source.phase_id')
                ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->where('source.tier', 'expressive')->where('cycle.entry_committal_id', $seed->id)
                ->where('edge.type', 'commit')->where('edge.target_proposition_id', $descendant)->exists();
            if (! $exit || ! DB::table('commit_region_exhaustions')->where('governing_committal_id', $descendant)->exists()) {
                throw new \DomainException('Parent Exhaustion requires its own genuinely committed exhausted descendant.');
            }
        }
        $tragedy = Tragedy::where('governing_committal_id', $seed->id)->first();
        if ($tragedy === null) {
            // Exploration still permits ordinary Variant opportunities. A child
            // proof does not fabricate a regional Tragedy or Resolution restriction.
            return false;
        }
        $substrateId = $seed->phase->cycle->participant->substrate_id;
        $graph = app(TerminalContinuationService::class)->graph($substrateId);
        $remaining = app(TerminalContinuationService::class)->remaining($seed->id, $graph);
        if ($remaining->isNotEmpty()) {
            return false;
        }
        $horizon = app(ContextHorizonProjector::class)->forEntry($seed);
        $changed = DB::table('commit_region_exhaustions')->insertOrIgnore([
            'governing_committal_id' => $seed->id,
            'context_horizon_id' => $horizon->id, 'tragedy_id' => $tragedy?->id, 'receipt_id' => $tragedy?->receipt_id,
            'phase_id' => $encounter->phase_id, 'terminal_proposition_id' => $encounter->proposition_id,
            'terminal_encounter_id' => $encounterId, 'descendant_exhaustion_id' => $descendant,
            'observed_field_version' => Substrate::whereKey($substrateId)->value('field_version'),
            'boundary_field' => $encounter->field,
            'continuation_evaluation' => json_encode([
                'rule' => 'direct-terminal-recursive-v1', 'remaining' => [],
                'members' => $graph['nodes']->filter(fn ($node) => $node->tier === 'expressive'
                    && $node->entry_committal_id === $seed->id)->keys()->all(),
                'closed' => $graph['closed']->keys()->all(),
                'edges' => $graph['edges']->map(fn ($edge) => [
                    'source' => $edge->source_proposition_id, 'target' => $edge->target_proposition_id, 'type' => $edge->type,
                ])->values()->all(),
            ], JSON_THROW_ON_ERROR), 'exhausted_at' => now(),
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

        if ($changed) {
            // Commit edges carry the real expressive source into this basin.
            // Re-evaluate only owning parent Regions, never neighbouring basins.
            $parents = DB::table('supersessions as edge')
                ->join('propositions as source', 'source.id', '=', 'edge.source_proposition_id')
                ->join('phases as phase', 'phase.id', '=', 'source.phase_id')
                ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->where('edge.type', 'commit')->where('edge.target_proposition_id', $seed->id)
                ->where('source.tier', 'expressive')->distinct()->pluck('cycle.entry_committal_id');
            foreach (Proposition::whereKey($parents)->get() as $parent) {
                $this->evaluate($parent, $encounterId, $seed->id);
            }
        }

        return (bool) $changed;
    }
}
