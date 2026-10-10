<?php

namespace App\Services\Observables;

use App\Domain\Observables\PhaseProfile;
use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Supersession;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Read-only diagnostics. No production measurement or projection consumes this service. */
final readonly class PhaseProfileService
{
    public function __construct(private PhaseProfileComparisonCalculator $comparison) {}

    /**
     * Select genuine all-parent ancestry within its creating Domain/Horizon.
     * Containers bound this traversal; they never supply additional heads.
     * Each selected node contributes only its own recorded interval, whose
     * operation-specific temporal reference may select just one causal parent.
     */
    public function profile(Proposition $head): array
    {
        $horizon = $head->phase->cycle->contextHorizon;
        if ($head->ledgerEntry === null) {
            throw new DomainException('Phase Profile inspection requires a ledgered Proposition.');
        }
        if ($head->tier === 'committal' && $horizon->parent_context_horizon_id === null
            && $head->commitmentSnapshot?->calculator_version === 'origin-bootstrap-v1') {
            return ['head_id' => $head->id, 'kind' => 'constitutional_origin',
                'means' => PhaseProfile::origin()->values, 'sample_counts' => array_fill(0, 7, 0),
                'history_complete' => true, 'observations' => []];
        }
        $nodes = collect();
        $frontier = collect([$head]);
        while ($frontier->isNotEmpty()) {
            foreach ($frontier as $node) {
                $nodes->put($node->id, $node);
            }
            $parents = Supersession::whereIn('target_proposition_id', $frontier->pluck('id'))
                ->pluck('source_proposition_id');
            $frontier = Proposition::whereIn('id', $parents)->whereNotIn('id', $nodes->keys())
                ->whereHas('phase.cycle', fn ($query) => $query->where('context_horizon_id', $horizon->id))
                ->with(['phase.cycle', 'ledgerEntry'])->get();
        }
        $stored = DB::table('phase_profile_observations')->whereIn('proposition_id', $nodes->keys())
            ->get()->keyBy('proposition_id');
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
