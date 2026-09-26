<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Observables\CommittalLineageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only observer representation. Never consult current action eligibility. */
final readonly class PropositionInspectionService
{
    public function __construct(private CommittalLineageService $lineage) {}

    /** Batch graph markers; independent of visible parents and active/exhausted filtering. */
    public function loadMarkers(Substrate $substrate, Collection $nodes): void
    {
        if ($nodes->isEmpty()) {
            return;
        }
        $ids = $nodes->pluck('id');
        $transitions = DB::table('context_horizons as opened')
            ->join('context_horizons as owner', 'owner.id', '=', 'opened.parent_context_horizon_id')
            ->join('propositions as p', 'p.id', '=', 'opened.opened_by_proposition_id')
            ->join('phases as phase', 'phase.id', '=', 'p.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->join('commitment_snapshots as snapshot', 'snapshot.proposition_id', '=', 'p.id')
            ->whereIn('p.id', $ids)->where('p.tier', 'committal')
            ->where('opened.substrate_id', $substrate->id)->where('owner.substrate_id', $substrate->id)
            ->where('snapshot.substrate_id', $substrate->id)
            ->whereColumn('cycle.context_horizon_id', 'owner.id')
            ->whereColumn('snapshot.direction_before', 'owner.domain')
            ->whereColumn('snapshot.direction_after', 'opened.domain')
            ->whereColumn('owner.domain', '!=', 'opened.domain')
            ->select('p.id', 'owner.domain as before', 'opened.domain as after')->get()->keyBy('id');

        // An accepted Bias classification, not simply a node with two parents.
        $confluence = DB::table('reclassification_results as result')
            ->join('propositions as target', 'target.id', '=', 'result.target_proposition_id')
            ->join('phases as bias', 'bias.id', '=', 'target.phase_id')
            ->join('cycles as local_cycle', 'local_cycle.id', '=', 'bias.cycle_id')
            ->join('participants as actor', 'actor.id', '=', 'local_cycle.participant_id')
            ->join('propositions as source', 'source.id', '=', 'result.source_proposition_id')
            ->join('phases as mediate', 'mediate.id', '=', 'source.phase_id')
            ->join('cycles as source_cycle', 'source_cycle.id', '=', 'mediate.cycle_id')
            ->join('participants as author', 'author.id', '=', 'source_cycle.participant_id')
            ->join('supersessions as classification', fn ($join) => $join
                ->on('classification.target_proposition_id', '=', 'target.id')
                ->on('classification.source_proposition_id', '=', 'source.id')
                ->on('classification.phase_id', '=', 'bias.id')->where('classification.type', 'reclassify'))
            ->whereIn('target.id', $ids)->where('actor.substrate_id', $substrate->id)->where('author.substrate_id', $substrate->id)
            ->where('bias.kind', PhaseKind::Bias->value)->where('mediate.kind', PhaseKind::Mediate->value)
            ->where('target.tier', 'expressive')->where('source.tier', 'expressive')
            ->whereColumn('result.register', 'target.register')
            ->whereRaw('(select count(*) from supersessions inputs where inputs.target_proposition_id = target.id) = 1')
            ->where(function ($query) use ($substrate) {
                // A recorded foreign Mediate source brought into local Bias (homology or incoming mediation).
                $query->whereColumn('source_cycle.entry_committal_id', '!=', 'local_cycle.entry_committal_id')
                    // Or local Mediate incorporated an exact foreign other-operand before local Bias.
                    ->orWhereExists(function ($incoming) use ($substrate) {
                        $incoming->selectRaw('1')->from('supersessions as operand')
                            ->join('propositions as input', 'input.id', '=', 'operand.source_proposition_id')
                            ->join('phases as input_phase', 'input_phase.id', '=', 'input.phase_id')
                            ->join('cycles as input_cycle', 'input_cycle.id', '=', 'input_phase.cycle_id')
                            ->join('participants as input_author', 'input_author.id', '=', 'input_cycle.participant_id')
                            ->join('supersessions as dominant', fn ($join) => $join
                                ->on('dominant.target_proposition_id', '=', 'operand.target_proposition_id')
                                ->on('dominant.phase_id', '=', 'operand.phase_id')->where('dominant.type', 'reconcile'))
                            ->whereColumn('operand.target_proposition_id', 'source.id')
                            ->whereColumn('operand.phase_id', 'mediate.id')->where('operand.type', 'reconcile')
                            ->whereColumn('dominant.source_proposition_id', 'mediate.gate_proposition_id')
                            ->whereColumn('operand.source_proposition_id', '!=', 'mediate.gate_proposition_id')
                            ->whereColumn('input_cycle.entry_committal_id', '!=', 'local_cycle.entry_committal_id')
                            ->where('input_author.substrate_id', $substrate->id)->where('input.tier', 'expressive')
                            ->whereRaw('(select count(*) from supersessions inputs where inputs.target_proposition_id = source.id) = 2');
                    });
            })->distinct()->pluck('target.id')->flip();

        foreach ($nodes as $node) {
            $transition = $transitions->get($node->id);
            $node->setAttribute('confluence', $confluence->has($node->id));
            $node->setAttribute('domain_transition', $transition === null ? null : $transition->before.'-'.$transition->after);
        }
    }

    /** Frozen committed values in canonical entry order; no frontend Closure calculation. */
    public function history(Proposition $position): array
    {
        $records = $this->lineage->records($position);
        $id = $position->id;
        $points = [];
        $seen = [];
        while ($id !== null && ! isset($seen[$id]) && ($record = $records->get($id)) !== null) {
            $seen[$id] = true;
            $points[] = [
                'proposition_id' => $id,
                'depth' => $record->depth, 'cadence' => $record->cadence,
                'depth_direction' => $record->depth_direction?->value,
                'cadence_direction' => $record->cadence_direction?->value,
            ];
            $id = $record->entry_committal_id;
        }

        return ['points' => array_reverse($points), 'current' => $points[0] ?? null,
            'source' => 'selected-committal'];
    }
}
