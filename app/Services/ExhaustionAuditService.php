<?php

namespace App\Services;

use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

/** Read-only audit of event-born authority, never a recalculation of historical Charge. */
final class ExhaustionAuditService
{
    public function inspect(string $substrateId): array
    {
        $horizons = DB::table('context_horizons')->where('substrate_id', $substrateId)->pluck('id');
        $tragedies = DB::table('tragedies')->whereIn('context_horizon_id', $horizons)->get();
        $proofs = DB::table('commit_region_exhaustions')->whereIn('context_horizon_id', $horizons)->get();
        $encounters = DB::table('terminal_encounters')->whereIn('governing_committal_id',
            Proposition::whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $substrateId))->select('id'))
            ->get()->keyBy('id');
        $invalidEvents = [];
        foreach ($encounters as $event) {
            $node = Proposition::find($event->proposition_id);
            $field = json_decode($event->field, true);
            $participant = DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->where('phase.id', $event->phase_id)->value('cycle.participant_id');
            if ($node?->tier !== 'expressive' || app(CommitRegionService::class)->seedFor($node)?->id !== $event->governing_committal_id
                || $participant !== $event->participant_id || ($field['charge'] ?? null) != 0
                || abs($field['tension'] ?? 0) != 1) {
                $invalidEvents[] = $event->id;
            }
        }
        $invalidTragedies = [];
        foreach ($tragedies as $tragedy) {
            $event = $encounters->get($tragedy->terminal_encounter_id);
            $receipt = DB::table('receipts')->where('id', $tragedy->receipt_id)->first();
            if ($event === null || $event->governing_committal_id !== $tragedy->governing_committal_id
                || $event->proposition_id !== $tragedy->terminal_proposition_id || $event->phase_id !== $tragedy->phase_id
                || $event->participant_id !== $tragedy->participant_id || $receipt?->type !== 'tragedy'
                || $receipt?->phase_id !== $event?->phase_id) {
                $invalidTragedies[] = $tragedy->id;
            }
        }
        $invalidProofs = [];
        foreach ($proofs as $proof) {
            $evaluation = json_decode($proof->continuation_evaluation ?? 'null', true);
            $event = $encounters->get($proof->terminal_encounter_id);
            $recursive = $proof->descendant_exhaustion_id !== null;
            $descendant = $recursive ? $proofs->firstWhere('governing_committal_id', $proof->descendant_exhaustion_id) : null;
            if ($event === null || ($evaluation['rule'] ?? null) !== 'direct-terminal-recursive-v1'
                || ($evaluation['remaining'] ?? null) !== []
                || array_diff($evaluation['members'] ?? [], $evaluation['closed'] ?? []) !== []
                || (! $recursive && $event->governing_committal_id !== $proof->governing_committal_id)
                || ($recursive && ($descendant === null || $descendant->exhausted_at > $proof->exhausted_at))) {
                $invalidProofs[] = $proof->governing_committal_id;
            }
        }

        return ['substrate_id' => $substrateId,
            'matches' => $invalidEvents === [] && $invalidTragedies === [] && $invalidProofs === [],
            'invalid_boundary_witnesses' => $invalidEvents, 'tragedy_mismatches' => $invalidTragedies,
            'lineage_mismatches' => $invalidProofs, 'missing' => [], 'unexpected' => [],
            'invalid_rest_witnesses' => [], 'invalid_receipt_consumptions' => [], 'horizon_mismatches' => [],
            'derived_states' => $proofs->count(), 'projected_states' => $proofs->count()];
    }
}
