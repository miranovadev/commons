<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

final class LedgerService
{
    /**
     * Fetch a Commons "feed" (ledger slice).
     *
     * @return array<string, mixed>
     */
    public function fetch(int $limit = 25, int $offset = 0, ?string $visibility = null): array
    {
        // Base proposition query
        $propQuery = DB::table('propositions')
            ->select([
                'propositions.id',
                'propositions.participant_id',
                'propositions.register',
                'propositions.tier',
                'propositions.visibility',
                'propositions.statement',
                'propositions.meta',
                'propositions.created_at',
                'propositions.updated_at',
            ])
            ->orderByDesc('propositions.created_at')
            ->limit($limit)
            ->offset($offset);

        if ($visibility !== null) {
            $propQuery->where('propositions.visibility', $visibility);
        }

        $propositions = $propQuery->get();

        // If empty, return quickly
        if ($propositions->isEmpty()) {
            return [
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'visibility' => $visibility,
                    'count' => 0,
                ],
                'items' => [],
            ];
        }

        $propIds = $propositions->pluck('id')->all();

        // Attestation counts per proposition
        $attCounts = DB::table('attestations')
            ->select('proposition_id')
            ->selectRaw("sum(case when stance = 'support' then 1 else 0 end) as support_count")
            ->selectRaw("sum(case when stance = 'refute' then 1 else 0 end) as refute_count")
            ->selectRaw("sum(case when stance = 'cite' then 1 else 0 end) as cite_count")
            ->whereIn('proposition_id', $propIds)
            ->groupBy('proposition_id')
            ->get()
            ->keyBy('proposition_id');

        // Receipts directly on propositions
        $propReceiptCounts = DB::table('receipts')
            ->select('receiptable_id')
            ->selectRaw('count(*) as receipt_count')
            ->where('receiptable_type', Proposition::class)
            ->whereIn('receiptable_id', $propIds)
            ->groupBy('receiptable_id')
            ->get()
            ->keyBy('receiptable_id');

        // Receipts on attestations (join attestations->receipts)
        $attReceiptCounts = DB::table('receipts')
            ->join('attestations', function ($join) {
                $join->on('receipts.receiptable_id', '=', 'attestations.id')
                    ->where('receipts.receiptable_type', '=', Attestation::class);
            })
            ->select('attestations.proposition_id')
            ->selectRaw('count(*) as receipt_count')
            ->whereIn('attestations.proposition_id', $propIds)
            ->groupBy('attestations.proposition_id')
            ->get()
            ->keyBy('proposition_id');

        // Assemble response
        $items = [];

        foreach ($propositions as $p) {
            $ac = $attCounts[$p->id] ?? null;
            $support = (int) ($ac->support_count ?? 0);
            $refute  = (int) ($ac->refute_count ?? 0);
            $cite    = (int) ($ac->cite_count ?? 0);

            $rProp = (int) (($propReceiptCounts[$p->id]->receipt_count ?? 0));
            $rAtt  = (int) (($attReceiptCounts[$p->id]->receipt_count ?? 0));
            $rTotal = $rProp + $rAtt;

            // Optional qualifiers (not epithets)
            $qualifiers = [];
            $qualifiers[] = $rTotal > 0 ? 'Evidence-backed' : 'Receiptless';
            if ($support > 0 && $refute > 0) $qualifiers[] = 'Contested';

            $items[] = [
                'id' => $p->id,
                'participant_id' => $p->participant_id,
                'register' => $p->register,
                'tier' => $p->tier,
                'visibility' => $p->visibility,
                'statement' => $p->statement,
                'meta' => $p->meta,
                'created_at' => (string) $p->created_at,
                'updated_at' => (string) $p->updated_at,

                'qualifiers' => $qualifiers,

                'counts' => [
                    'support' => $support,
                    'refute' => $refute,
                    'cite' => $cite,
                    'receipts_direct' => $rProp,
                    'receipts_attestations' => $rAtt,
                    'receipts_total' => $rTotal,
                ],
            ];
        }

        return [
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'visibility' => $visibility,
                'count' => count($items),
            ],
            'items' => $items,
        ];
    }
}
