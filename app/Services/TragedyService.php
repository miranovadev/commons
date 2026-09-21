<?php

namespace App\Services;

use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Receipt;
use App\Models\Tragedy;
use Illuminate\Support\Str;

/** First evidenced regional no-Potential Rest. Tragedy opens a final attempt; it does not exhaust the region. */
final class TragedyService
{
    /** Caller owns the substrate contribution lock. Unique governing-Committal and Receipt keys back it. */
    public function record(Phase $phase, array $proof): Tragedy
    {
        $horizon = ContextHorizon::whereKey($phase->cycle->context_horizon_id)->lockForUpdate()->firstOrFail();
        $seed = app(ExhaustionService::class)->regionFor($phase)
            ?? throw new \DomainException('Tragedy requires an unambiguous governing Committal.');
        $existing = Tragedy::where('governing_committal_id', $seed->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $opener = $horizon->opened_by_proposition_id ?? CommitmentSnapshot::where('substrate_id', $horizon->substrate_id)
            ->where('calculator_version', 'origin-bootstrap-v1')->sole()->proposition_id;
        $id = (string) Str::ulid();
        $receipt = (new Receipt)->forceFill([
            'phase_id' => $phase->id, 'context_horizon_id' => $horizon->id,
            'type' => 'tragedy', 'reference' => $id, 'capabilities' => ['readable'],
            'metadata' => ['tragedy_id' => $id, 'governing_committal_id' => $seed->id, 'terminal_proposition_id' => $phase->gate_proposition_id,
                'opening_proposition_id' => $opener,
                'exhausted_committal_ids' => app(TragedyEvidenceService::class)->references($horizon->id)],
        ]);
        // This is the observed subject, not an attachment. Only Reclassify attaches.
        $receipt->receiptable()->associate($phase->gate);
        $receipt->save();
        $tragedy = (new Tragedy)->forceFill([
            'id' => $id, 'governing_committal_id' => $seed->id, 'context_horizon_id' => $horizon->id, 'receipt_id' => $receipt->id,
            'phase_id' => $phase->id, 'participant_id' => $phase->cycle->participant_id,
            'terminal_proposition_id' => $phase->gate_proposition_id, 'opening_proposition_id' => $opener,
            'observed_field_version' => $proof['field_version'], 'occurred_at' => now(),
        ]);
        $tragedy->save();
        app(TragedyReceiptScopeService::class)->initialize($tragedy);
        app(LedgerService::class)->record($receipt);
        app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
            ['type' => 'horizon.tragedy', 'tragedy_id' => $id, 'receipt_id' => $receipt->id]);

        return $tragedy;
    }
}
