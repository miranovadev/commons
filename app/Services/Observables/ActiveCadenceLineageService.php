<?php

namespace App\Services\Observables;

use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Supersession;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Recent own intervals on the recorded selected lineage, never a second Attestation history. */
final readonly class ActiveCadenceLineageService
{
    /**
     * One bounded-scope query, independent of lineage depth. The existing birth
     * cursor's source edge selects unary ancestry / the acting Reconcile Gate.
     * All non-Variant edges are hard (SupersessionStrength); provenance remains
     * traversable even when its own observations no longer qualify.
     *
     * @return array{reference: list<array{proposition_id: string, interval_index: int, interval: int}>, beat_sequence: ?int, attestation_id: ?string}
     */
    public function reference(Proposition $node, ?Supersession $birth = null): array
    {
        if ($birth !== null && ($birth->target_proposition_id !== $node->id
            || ($birth->type === 'reconcile' && $birth->source_proposition_id !== $birth->phase->gate_proposition_id))) {
            throw new DomainException('Cadence birth reference requires the selected local source edge.');
        }
        $rows = collect(DB::select(<<<'SQL'
            WITH RECURSIVE selected(id, samples) AS (
                SELECT id, 0 FROM propositions WHERE id = ?
                UNION
                SELECT edge.source_proposition_id,
                    selected.samples + CASE WHEN predecessor.tier = 'expressive'
                        AND NOT EXISTS (SELECT 1 FROM supersessions replacement
                            WHERE replacement.source_proposition_id = predecessor.id AND replacement.type <> 'variant')
                        THEN CASE WHEN json_array_length(history.intervals) >= 2 THEN 2
                            ELSE COALESCE(json_array_length(history.intervals), 0) END
                        ELSE 0 END
                FROM selected
                JOIN propositions current ON current.id = selected.id
                LEFT JOIN proposition_attestation_cursors cursor ON cursor.proposition_id = current.id
                JOIN supersessions edge ON edge.id = COALESCE(cursor.source_supersession_id, CASE WHEN current.id = ? THEN ? ELSE NULL END)
                    AND edge.target_proposition_id = current.id
                JOIN propositions predecessor ON predecessor.id = edge.source_proposition_id
                LEFT JOIN local_chirality_states history ON history.proposition_id = predecessor.id
                WHERE current.tier = 'expressive' AND selected.samples < 2
            )
            SELECT node.id, node.tier, edge.source_proposition_id AS predecessor_id,
                cursor.beat_sequence, cursor.attestation_id, beat.proposition_id AS beat_owner_id,
                state.intervals, state.act_count,
                CASE WHEN EXISTS (SELECT 1 FROM supersessions replacement
                    WHERE replacement.source_proposition_id = node.id AND replacement.type <> 'variant')
                    THEN 1 ELSE 0 END AS hard_superseded,
                CASE WHEN EXISTS (SELECT 1 FROM attestations act
                    JOIN substrate_ledger_entries ledger ON ledger.ledgerable_id = act.id
                        AND ledger.ledgerable_type = ?
                    WHERE act.proposition_id = node.id) THEN 1 ELSE 0 END AS has_recorded_act
            FROM selected
            JOIN propositions node ON node.id = selected.id
            LEFT JOIN proposition_attestation_cursors cursor ON cursor.proposition_id = node.id
            LEFT JOIN supersessions edge ON edge.id = COALESCE(cursor.source_supersession_id, CASE WHEN node.id = ? THEN ? ELSE NULL END)
                AND edge.target_proposition_id = node.id
            LEFT JOIN attestations beat ON beat.id = cursor.attestation_id
            LEFT JOIN local_chirality_states state ON state.proposition_id = node.id
            SQL, [$node->id, $node->id, $birth?->id, (new Attestation)->getMorphClass(), $node->id, $birth?->id]))->keyBy('id');
        $reference = [];
        $beat = $attestation = null;
        $id = $rows->get($node->id)?->predecessor_id;
        $seen = [$node->id => true];
        while ($id !== null) {
            if (isset($seen[$id]) || ! $rows->has($id)) {
                throw new DomainException('Cadence requires complete, acyclic selected birth ancestry.');
            }
            $seen[$id] = true;
            $row = $rows[$id];
            if ($row->tier === 'committal') {
                // Preserve canonical cursor transport, but never import intervals
                // or rewalk the expressive history behind this boundary.
                if ($beat === null && $row->beat_sequence !== null) {
                    $beat = (int) $row->beat_sequence;
                    $attestation = $row->attestation_id;
                }
                break;
            }
            if (! $row->hard_superseded) {
                if ($row->intervals === null && $row->has_recorded_act) {
                    throw new DomainException('Historical cadence has no event-born local state; live rhythm is unavailable at the prospective boundary.');
                }
                if ((int) $row->act_count > 0) {
                    if ($row->beat_sequence === null || $row->beat_owner_id !== $row->id) {
                        throw new DomainException('Historical cadence has no own event-born beat cursor.');
                    }
                    if ($beat === null) {
                        $beat = (int) $row->beat_sequence;
                        $attestation = $row->attestation_id;
                    }
                    $own = json_decode($row->intervals, true, flags: JSON_THROW_ON_ERROR);
                    $tail = [];
                    for ($i = max(0, count($own) - (2 - count($reference))); $i < count($own); $i++) {
                        $tail[] = ['proposition_id' => $id, 'interval_index' => $i, 'interval' => (int) $own[$i]];
                    }
                    $reference = [...$tail, ...$reference];
                    if (count($reference) === 2) {
                        break;
                    }
                }
            }
            $id = $row->predecessor_id;
        }

        return ['reference' => $reference, 'beat_sequence' => $beat, 'attestation_id' => $attestation];
    }
}
