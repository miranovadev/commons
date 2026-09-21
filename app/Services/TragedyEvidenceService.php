<?php

namespace App\Services;

use App\Models\Receipt;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Compact local proof references; statements are always resolved at read time. */
final class TragedyEvidenceService
{
    private function localCommittals(string $horizonId): Builder
    {
        return DB::table('commit_region_exhaustions as exhaustion')
            ->join('context_horizons as proof_horizon', 'proof_horizon.id', '=', 'exhaustion.context_horizon_id')
            ->join('propositions as node', 'node.id', '=', 'exhaustion.governing_committal_id')
            ->join('phases as phase', 'phase.id', '=', 'node.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->where('cycle.context_horizon_id', $horizonId)->where('node.tier', 'committal')
            ->orderByDesc('node.created_at')->orderByDesc('node.id');
    }

    public function references(string $horizonId): array
    {
        return $this->localCommittals($horizonId)->pluck('node.id')->all();
    }

    public function describe(Receipt $receipt): array
    {
        $ids = $receipt->metadata['exhausted_committal_ids'] ?? [];
        $history = $this->localCommittals($receipt->context_horizon_id)->whereIn('node.id', $ids)
            ->get(['node.id', 'node.statement', 'node.register', 'node.tier', 'phase.kind as phase_number',
                'node.phase_id', 'cycle.context_horizon_id as owner_horizon_id',
                'exhaustion.context_horizon_id as exhausted_horizon_id', 'exhaustion.tragedy_id',
                'exhaustion.governing_committal_id', 'proof_horizon.opened_by_proposition_id',
                'exhaustion.terminal_proposition_id', 'exhaustion.exhausted_at'])
            ->map(fn ($row) => [...(array) $row, 'statement_truncated' => false,
                'exhausted' => true, 'actionable' => false,
                'boundary_exhausted' => $row->opened_by_proposition_id === $row->governing_committal_id])->all();

        return ['tragedy_id' => $receipt->reference,
            'governing_committal_id' => $receipt->metadata['governing_committal_id'] ?? null,
            'terminal_proposition_id' => $receipt->metadata['terminal_proposition_id'],
            'opening_proposition_id' => $receipt->metadata['opening_proposition_id'],
            'exhausted_committals' => $history];
    }
}
