<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Read-only causal ancestry. Origin's bootstrap snapshot is never branch maturity. */
final class BranchCommitmentService
{
    public function inspect(string $root, string $origin, string $terminal, string $substrate): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE ancestry (id) AS (
                SELECT proposition_id FROM origin_branch_memberships
                WHERE root_proposition_id = ? AND proposition_id = ?
                UNION
                SELECT s.source_proposition_id FROM supersessions s
                JOIN ancestry a ON a.id = s.target_proposition_id
                JOIN origin_branch_memberships m ON m.proposition_id = s.source_proposition_id
                WHERE m.root_proposition_id = ?
            )
            SELECT p.id, p.tier, c.context_horizon_id,
                COALESCE(opened.id, c.context_horizon_id) AS entry_horizon_id,
                CASE WHEN p.tier = 'committal' AND snapshot.substrate_id = ?
                    AND snapshot.calculator_version != 'origin-bootstrap-v1'
                    AND committed.type = 'commit' AND committed.target_proposition_id = p.id
                    AND source.tier = 'expressive' THEN 1 ELSE 0 END AS valid_commit,
                parent.source_proposition_id AS parent_id
            FROM ancestry a JOIN propositions p ON p.id = a.id
            JOIN phases ph ON ph.id = p.phase_id JOIN cycles c ON c.id = ph.cycle_id
            LEFT JOIN commitment_snapshots snapshot ON snapshot.proposition_id = p.id
            LEFT JOIN supersessions committed ON committed.id = snapshot.supersession_id
            LEFT JOIN propositions source ON source.id = committed.source_proposition_id
            LEFT JOIN context_horizons opened ON opened.opened_by_proposition_id = p.id
            LEFT JOIN supersessions parent ON parent.target_proposition_id = p.id
            SQL, [$root, $terminal, $root, $substrate]);
        $nodes = $children = $commits = [];
        $validRoot = false;
        foreach ($rows as $row) {
            $nodes[$row->id] = $row;
            $children[$row->parent_id][] = $row->id;
            if ($row->id === $root && $row->tier === 'expressive' && $row->parent_id === $origin) {
                $validRoot = true;
            }
            if ($row->valid_commit) {
                $commits[$row->id] = ['proposition_id' => $row->id, 'context_horizon_id' => $row->entry_horizon_id];
            }
        }
        // Stop each root-to-terminal path at its first valid Commit. Reconciled
        // paths can have several earliest checkpoints: all must be exhausted.
        $earliest = $seen = [];
        $queue = [$root];
        for ($i = 0; $i < count($queue); $i++) {
            $id = $queue[$i];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if (isset($commits[$id])) {
                $earliest[$id] = $commits[$id];

                continue;
            }
            foreach ($children[$id] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return ['valid_root' => $validRoot, 'has_commit' => $commits !== [],
            'root_context_horizon_id' => $nodes[$root]->context_horizon_id ?? null,
            'terminal_is_committal' => ($nodes[$terminal]->tier ?? null) === 'committal',
            'committed_regions' => array_values($commits), 'earliest_commits' => array_values($earliest)];
    }
}
