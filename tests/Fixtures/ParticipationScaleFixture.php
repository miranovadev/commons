<?php

namespace Tests\Fixtures;

use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\ReceiptContextEvent;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use Illuminate\Support\Facades\DB;

/** Historical sibling population: no new causal input to the active Horizon. */
final class ParticipationScaleFixture
{
    public static function addHistory(Proposition $origin, int $count): void
    {
        $phase = $origin->phase;
        $substrateId = $phase->cycle->participant->substrate_id;
        $sequence = (int) SubstrateLedgerEntry::where('substrate_id', $substrateId)->max('sequence');
        $rows = [];
        $at = now()->toDateTimeString();
        $record = function (string $kind, string $type, string $id) use (&$rows, &$sequence, $substrateId, $at): string {
            $entryId = (string) str()->ulid();
            $rows['substrate_ledger_entries'][] = ['id' => $entryId, 'substrate_id' => $substrateId,
                'sequence' => ++$sequence, 'kind' => $kind, 'ledgerable_type' => $type, 'ledgerable_id' => $id, 'recorded_at' => $at];

            return $entryId;
        };
        for ($i = 0; $i < $count; $i++) {
            [$id, $edge, $attestation, $execution, $receipt, $load, $event] = array_map(fn () => (string) str()->ulid(), range(1, 7));
            $rows['propositions'][] = ['id' => $id, 'phase_id' => $phase->id, 'register' => 'activity', 'tier' => 'expressive',
                'statement' => 'Historical sibling '.$i, 'created_at' => $at, 'updated_at' => $at];
            $entryId = $record('proposition', (new Proposition)->getMorphClass(), $id);
            $rows['context_horizon_memberships'][] = ['proposition_id' => $id, 'context_horizon_id' => $phase->cycle->context_horizon_id,
                'ledger_entry_id' => $entryId, 'projected_at' => $at];
            $rows['supersessions'][] = ['id' => $edge, 'phase_id' => $phase->id, 'source_proposition_id' => $origin->id,
                'target_proposition_id' => $id, 'type' => 'variant', 'created_at' => $at, 'updated_at' => $at];
            $record('supersession', (new Supersession)->getMorphClass(), $edge);
            $rows['attestations'][] = ['id' => $attestation, 'phase_id' => $phase->id, 'proposition_id' => $id,
                'direction' => $i % 2 ? 'support' : 'refute', 'created_at' => $at, 'updated_at' => $at];
            $record('attestation', (new Attestation)->getMorphClass(), $attestation);
            $reference = 'https://example.test/history/'.$i;
            $rows['evidence_executions'][] = ['id' => $execution, 'phase_id' => $phase->id, 'cycle_id' => $phase->cycle_id,
                'type' => 'instrument', 'proposition_id' => $id,
                'kind' => 'hyperlink', 'reference' => $reference, 'status' => 'completed',
                'input' => json_encode(['kind' => 'hyperlink', 'reference' => $reference]), 'started_at' => $at, 'completed_at' => $at];
            $rows['receipts'][] = ['id' => $receipt, 'evidence_execution_id' => $execution, 'receiptable_type' => (new Proposition)->getMorphClass(),
                'receiptable_id' => $id, 'type' => 'hyperlink', 'reference' => $reference, 'capabilities' => json_encode(['readable', 'replayable']),
                'signature' => hash('sha256', $reference), 'metadata' => json_encode(['content_verified' => true, 'reinforcement_version' => 'accepted-version']),
                'created_at' => $at, 'updated_at' => $at];
            $record('receipt', (new Receipt)->getMorphClass(), $receipt);
            $rows['participant_receipt_contexts'][] = ['id' => $load, 'participant_id' => $phase->cycle->participant_id,
                'receipt_id' => $receipt, 'proposition_id' => $id, 'evidence_signature' => hash('sha256', $reference), 'loaded_at' => $at];
            $rows['receipt_context_events'][] = ['id' => $event, 'participant_receipt_context_id' => $load, 'phase_id' => $phase->id,
                'action' => 'loaded', 'created_at' => $at];
            $record('receipt_context', (new ReceiptContextEvent)->getMorphClass(), $event);
        }
        // FK parents precede projections/events and ledger entries reference polymorphic subjects.
        foreach (['propositions', 'supersessions', 'attestations', 'evidence_executions', 'receipts', 'participant_receipt_contexts',
            'receipt_context_events', 'substrate_ledger_entries', 'context_horizon_memberships'] as $table) {
            foreach (array_chunk($rows[$table], 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }
}
