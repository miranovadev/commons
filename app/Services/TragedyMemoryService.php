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
            WITH RECURSIVE chain (id, predecessor, position, root) AS (
                SELECT p.id, c.entry_committal_id, 0, p.id
                FROM propositions p
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                JOIN commitment_snapshots s ON s.proposition_id = p.id
                WHERE p.id IN (?, ?) AND s.substrate_id = ?
                UNION ALL
                SELECT p.id, c.entry_committal_id, child.position + 1, child.root
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
            SQL, [$tragedy->governing_committal_id, $phase->cycle->entry_committal_id, $phase->cycle->participant->substrate_id,
            $phase->cycle->participant->substrate_id, self::CHAIN_LIMIT - 1]));
        $ownerChain = $rows->where('root', $tragedy->governing_committal_id)->values();
        $currentChain = $rows->where('root', $phase->cycle->entry_committal_id)->values();
        $current = $ownerChain->search(fn ($row) => $row->id === $phase->cycle->entry_committal_id);
        $owner = $currentChain->search(fn ($row) => $row->id === $tragedy->governing_committal_id);
        $backward = $current === false && $owner !== false;
        if ($current !== false) {
            $chain = $ownerChain->take($current + 1)->reverse()->values();
        } elseif ($backward) {
            // A descendant's memory walks backward to the owner, never through a sibling.
            $chain = $currentChain->take($owner + 1);
        } else {
            $chain = $ownerChain->reverse()->values();
        }
        $truncated = $current === false && ! $backward && $ownerChain->last()?->predecessor !== null;
        $field = $receipt->metadata['field'] ?? null;

        return [
            'tragedy_id' => $tragedy->id, 'receipt_id' => $receipt->id,
            'governing_committal_id' => $tragedy->governing_committal_id,
            'committed_chain' => $chain->map(fn ($row) => [
                'proposition_id' => $row->id, 'statement' => $row->statement, 'register' => $row->register,
                'domain' => $row->domain, 'depth' => $row->depth === null ? null : (float) $row->depth,
                'cadence' => $row->cadence === null ? null : (float) $row->cadence,
                'tension' => $row->tension === null ? null : (float) $row->tension,
            ])->all(),
            'chain_limit' => self::CHAIN_LIMIT, 'earlier_history_omitted' => $truncated,
            'starts_at_current_committal' => $current !== false || $backward,
            'chain_direction' => $backward ? 'backward' : 'forward',
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
