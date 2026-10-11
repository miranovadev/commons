<?php

namespace App\Services\Observables;

use App\Domain\Observables\PhaseProfile;
use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Read-only Phase Profile C1 derivation from durable birth observations. */
final readonly class PhaseProfileService
{
    public function __construct(private PhaseProfileComparisonCalculator $comparison) {}

    /**
     * Follow only durable operation-selected local temporal predecessors.
     * Interval references may skip Revision parents; temporal ancestry must not.
     * Missing birth history stops traversal without guessing from causal edges.
     */
    public function profile(Proposition $head, ?ParticipationContext $context = null): array
    {
        return $context === null ? $this->buildProfile($head)
            : $context->remember('phase-profile:'.$head->id, fn () => $this->buildProfile($head, $context));
    }

    private function buildProfile(Proposition $head, ?ParticipationContext $context = null): array
    {
        $horizon = $head->phase->cycle->contextHorizon;
        if ($head->ledgerEntry === null) {
            throw new DomainException('Phase Profile inspection requires a ledgered Proposition.');
        }
        if ($head->tier === 'committal' && $horizon->parent_context_horizon_id === null
            && ($context === null ? $head->commitmentSnapshot
                : $context->snapshots->firstWhere('proposition_id', $head->id))?->calculator_version === 'origin-bootstrap-v1') {
            return ['head_id' => $head->id, 'kind' => 'constitutional_origin',
                'means' => PhaseProfile::origin()->values, 'sample_counts' => array_fill(0, 7, 0),
                'history_complete' => true, 'observations' => []];
        }
        if ($context === null) {
            // Load only the selected predecessor chain in one bounded query.
            // The LEFT JOIN retains a missing birth as an unavailable stopping point.
            $rows = collect(DB::select(<<<'SQL'
                WITH RECURSIVE temporal_lineage(proposition_id) AS (
                    SELECT CAST(? AS TEXT)
                    UNION
                    SELECT birth.temporal_predecessor_proposition_id
                    FROM phase_profile_observations AS birth
                    JOIN temporal_lineage ON birth.proposition_id = temporal_lineage.proposition_id
                    WHERE birth.temporal_predecessor_proposition_id IS NOT NULL
                )
                SELECT lineage.proposition_id AS selected_id, birth.*
                FROM temporal_lineage AS lineage
                LEFT JOIN phase_profile_observations AS birth ON birth.proposition_id = lineage.proposition_id
                SQL, [$head->id]));
            $stored = $rows->whereNotNull('proposition_id')->keyBy('proposition_id');
            $nodes = Proposition::whereIn('id', $rows->pluck('selected_id'))->where('id', '!=', $head->id)
                ->with(['phase.cycle', 'ledgerEntry'])->get()->keyBy('id');
            $nodes->put($head->id, $head);
        } else {
            $nodes = collect();
            $stored = collect();
            $node = $head;
            while ($node !== null && ! $nodes->has($node->id)) {
                $nodes->put($node->id, $node);
                $row = $context !== null && $context->propositions->has($node->id)
                    ? $context->remember('phase-profile:births', fn () => DB::table('phase_profile_observations')
                        ->whereIn('proposition_id', $context->propositions->keys())->get()->keyBy('proposition_id'))->get($node->id)
                    : DB::table('phase_profile_observations')->where('proposition_id', $node->id)->first();
                if ($row === null) {
                    break;
                }
                $stored->put($node->id, $row);
                $node = $row->temporal_predecessor_proposition_id === null ? null
                    : ($context?->propositions->get($row->temporal_predecessor_proposition_id)
                        ?? Proposition::with(['phase.cycle', 'ledgerEntry'])->findOrFail($row->temporal_predecessor_proposition_id));
            }
        }
        $sums = $counts = array_fill(0, 7, 0);
        $missing = array_fill(0, 7, false);
        $observations = [];
        foreach ($nodes->sortBy(fn ($node) => $node->ledgerEntry?->sequence) as $node) {
            $row = $stored->get($node->id);
            $slot = $node->phase->kind->value - 1;
            $reason = $row === null ? 'missing_birth_history' : $row->unavailable_reason;
            if ($reason !== null && ! in_array($reason, [
                'first_expressive_observation', 'no_local_predecessor', 'no_preceding_phase_observation',
            ], true)) {
                $missing[$slot] = true;
            }
            if ($row?->cycle_interval !== null) {
                $sums[$slot] += (int) $row->cycle_interval;
                $counts[$slot]++;
            }
            $observations[] = [
                'proposition_id' => $node->id, 'phase' => $slot + 1,
                'kind' => $node->tier === 'committal' ? 'commit_boundary' : 'expressive_birth',
                'clock_entry_committal_id' => $node->phase->cycle->entry_committal_id,
                'birth_cycle_coordinate' => $row?->birth_cycle_coordinate === null ? null : (int) $row->birth_cycle_coordinate,
                'temporal_predecessor_proposition_id' => $row?->temporal_predecessor_proposition_id,
                'preceding_phase_proposition_id' => $row?->preceding_phase_proposition_id,
                'cycle_interval' => $row?->cycle_interval === null ? null : (int) $row->cycle_interval,
                'unavailable_reason' => $reason,
            ];
        }

        return ['head_id' => $head->id, 'kind' => 'observed_lineage',
            'means' => array_map(fn ($sum, $count) => $count === 0 ? null : (float) $sum / $count, $sums, $counts),
            'sample_counts' => $counts, 'history_complete' => ! in_array(true, $missing, true),
            'observations' => $observations];
    }

    /** Production never evaluates the prospective Order preview. */
    public function calculate(Proposition $head, ?ParticipationContext $context = null): float
    {
        $current = $this->profile($head, $context);
        $boundaryId = $head->phase->cycle->contextHorizon->opened_by_proposition_id;
        $boundary = $boundaryId === null ? null : ($context?->propositions->get($boundaryId)
            ?? Proposition::findOrFail($boundaryId));
        $reference = $boundary === null ? null : $this->profile($boundary, $context);

        return $this->comparison->calculate(new PhaseProfile($current['means']),
            new PhaseProfile($reference['means'] ?? array_fill(0, 7, null)));
    }

    public function inspect(Proposition $head): array
    {
        $current = $this->profile($head);
        $horizon = $head->phase->cycle->contextHorizon;
        // The completed immediately preceding Domain ends at this opening boundary,
        // not at whichever Proposition was most recently created in its container.
        $boundary = $horizon->opened_by_proposition_id === null ? null : Proposition::findOrFail($horizon->opened_by_proposition_id);
        $reference = $boundary === null ? null : $this->profile($boundary);
        $a = new PhaseProfile($current['means']);
        $b = new PhaseProfile($reference['means'] ?? array_fill(0, 7, null));

        return ['diagnostic_only' => true, 'current' => $current, 'reference' => $reference,
            'contributions' => $this->comparison->contributions($a, $b),
            'phase_profile' => $this->comparison->calculate($a, $b),
            'prospective_order' => $this->prospectiveOrder($head)];
    }

    /** Phase 7 preview only; never updates the source, Closure or its profile. */
    private function prospectiveOrder(Proposition $head): ?array
    {
        if ($head->tier !== 'expressive' || $head->phase->kind !== PhaseKind::Bias) {
            return null;
        }
        $cycle = $head->phase->cycle;
        $birth = DB::table('phase_profile_observations')->where('proposition_id', $head->id)->first();
        $cycles = DB::table('cycles')->where('entry_committal_id', $cycle->entry_committal_id)
            ->where('context_horizon_id', $cycle->context_horizon_id);
        $coordinate = $cycle->entry_committal_id === null || (clone $cycles)->whereNull('chirality_entry_after_sequence')->exists()
            ? null : $cycles->count();
        $interval = $coordinate === null || $birth?->birth_cycle_coordinate === null ? null
            : $coordinate - (int) $birth->birth_cycle_coordinate;

        return ['preceding_phase_proposition_id' => $head->id, 'cycle_coordinate' => $coordinate,
            'cycle_interval' => $interval, 'persisted' => false];
    }
}
