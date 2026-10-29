<?php

namespace Tests\Fixtures;

use App\Models\Receipt;
use App\Services\ParticipantContextSerializer;
use Illuminate\Support\Facades\DB;

/** Read-only assertions over real journeys; never manufactures prerequisite state. */
trait EvidenceLifecycleAudit
{
    private function auditEvidencePacket(array $state): array
    {
        $before = DB::table('participant_receipt_contexts')->orderBy('id')->get()->toJson();
        $events = DB::table('receipt_context_events')->count();
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $this->assertSame($before, DB::table('participant_receipt_contexts')->orderBy('id')->get()->toJson());
        $this->assertSame($events, DB::table('receipt_context_events')->count());
        $ids = array_column($packet['receipts'], 'id');
        $this->assertSame($ids, array_values(array_unique($ids)));
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
        $models = Receipt::with(['evidenceExecution', 'consumption'])->whereKey($ids)->get()->keyBy('id');
        foreach ($packet['receipts'] as $receipt) {
            $this->assertNotEmpty($receipt['summary'], $receipt['id']);
            $model = $models[$receipt['id']];
            $this->assertSame($model->evidenceExecution->proposition_id, $receipt['originating_gate_id']);
            if ($model->consumption !== null) {
                $this->assertSame($model->consumption->proposition_id, $receipt['consumed_by_proposition_id']);
            }
        }
        foreach (['read', 'gate', 'write'] as $register) {
            foreach ($packet[$register]['propositions'] as $node) {
                foreach ($node['receipts'] as $id) {
                    $this->assertIsString($id);
                    $this->assertContains($id, $ids);
                }
            }
        }

        return $packet;
    }
}
