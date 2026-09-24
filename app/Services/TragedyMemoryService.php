<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

/** Cognitive projection only. Never supplies evidence, measurements or warrant authority. */
final class TragedyMemoryService
{
    public const CHAIN_LIMIT = 32;

    public function describe(Receipt $receipt, Phase $phase): ?array
    {
        $tragedy = DB::table('tragedies as t')
            ->join('context_horizons as h', 'h.id', '=', 't.context_horizon_id')
            ->join('propositions as p', 'p.id', '=', 't.terminal_proposition_id')
            ->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->where('t.receipt_id', $receipt->id)
            ->where('h.substrate_id', $phase->cycle->participant->substrate_id)
            ->first(['t.*', 'p.statement', 'p.register', 'ph.kind as phase']);
        if ($tragedy === null) {
            return null;
        }

        // Same canonical edge as CommittalLineageService: creating Cycle -> entry.
        // Bounded SQL, not graph hydration or Supersession/foreign-input traversal.
        $rows = collect(DB::select(<<<'SQL'
            WITH RECURSIVE chain (id, predecessor, position) AS (
                SELECT p.id, c.entry_committal_id, 0
                FROM propositions p
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                JOIN commitment_snapshots s ON s.proposition_id = p.id
                WHERE p.id = ? AND s.substrate_id = ?
                UNION ALL
                SELECT p.id, c.entry_committal_id, child.position + 1
                FROM chain child
                JOIN propositions p ON p.id = child.predecessor
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                JOIN commitment_snapshots s ON s.proposition_id = p.id
                WHERE s.substrate_id = ? AND child.position < ?
            )
            SELECT chain.*, p.statement, p.register, s.direction_after AS domain,
                s.depth, s.cadence, s.tension
            FROM chain
            JOIN propositions p ON p.id = chain.id
            JOIN commitment_snapshots s ON s.proposition_id = chain.id
            ORDER BY chain.position
            SQL, [$tragedy->governing_committal_id, $phase->cycle->participant->substrate_id,
            $phase->cycle->participant->substrate_id, self::CHAIN_LIMIT - 1]));
        $current = $rows->search(fn ($row) => $row->id === $phase->cycle->entry_committal_id);
        if ($current !== false) {
            $rows = $rows->take($current + 1);
        }
        $truncated = $current === false && $rows->last()?->predecessor !== null;
        $field = $receipt->metadata['field'] ?? null;

        return [
            'tragedy_id' => $tragedy->id, 'receipt_id' => $receipt->id,
            'governing_committal_id' => $tragedy->governing_committal_id,
            'committed_chain' => $rows->reverse()->values()->map(fn ($row) => [
                'proposition_id' => $row->id, 'statement' => $row->statement, 'register' => $row->register,
                'domain' => $row->domain, 'depth' => $row->depth === null ? null : (float) $row->depth,
                'cadence' => $row->cadence === null ? null : (float) $row->cadence,
                'tension' => $row->tension === null ? null : (float) $row->tension,
            ])->all(),
            'chain_limit' => self::CHAIN_LIMIT, 'earlier_history_omitted' => $truncated,
            'starts_at_current_committal' => $current !== false,
            'catastrophe' => [
                'proposition_id' => $tragedy->terminal_proposition_id,
                'statement' => $tragedy->statement, 'phase' => (int) $tragedy->phase,
                'register' => $tragedy->register,
                // Archived pre-boundary Receipts may lack a field: never reconstruct it.
                'tension' => $field['tension'] ?? null, 'charge' => $field['charge'] ?? null,
                'recorded_field_available' => $field !== null,
                'observed_at' => $tragedy->occurred_at,
            ],
        ];
    }
}
