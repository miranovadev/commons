<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Tragedy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** First newly created expressive state at zero canonical Charge, scoped to its owning Commit Region. */
final class TragedyService
{
    /** Caller owns the substrate contribution lock. Unique governing-Committal and Receipt keys back it. */
    public function record(Proposition $proposition): ?Tragedy
    {
        if ($proposition->tier !== 'expressive') {
            return null;
        }
        if (DB::transactionLevel() === 0) {
            throw new \DomainException('Tragedy belongs to the expressive contribution transaction.');
        }
        $measurement = $proposition->measurement;
        if ($measurement === null || $measurement->correspondence === null
            || $measurement->chirality === null || $measurement->closure === null) {
            return null;
        }
        $field = app(ProjectionService::class)->field(new ObservableValues(
            $measurement->correspondence, $measurement->chirality, $measurement->closure,
        ));
        if ($field['charge'] !== 0.0) {
            return null;
        }
        $phase = $proposition->phase;
        $horizon = ContextHorizon::whereKey($phase->cycle->context_horizon_id)->lockForUpdate()->firstOrFail();
        $seed = app(CommitRegionService::class)->seedFor($proposition)
            ?? throw new \DomainException('Tragedy requires an unambiguous governing Committal.');
        $existing = Tragedy::where('governing_committal_id', $seed->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $opener = $horizon->opened_by_proposition_id ?? CommitmentSnapshot::where('substrate_id', $horizon->substrate_id)
            ->where('calculator_version', 'origin-bootstrap-v1')->sole()->proposition_id;
        $version = Substrate::whereKey($horizon->substrate_id)->value('field_version');
        $id = (string) Str::ulid();
        $receipt = (new Receipt)->forceFill([
            'phase_id' => $phase->id, 'context_horizon_id' => $horizon->id,
            'type' => 'tragedy', 'reference' => $id, 'capabilities' => ['readable'],
            'metadata' => ['tragedy_id' => $id, 'governing_committal_id' => $seed->id, 'terminal_proposition_id' => $proposition->id,
                'opening_proposition_id' => $opener, 'trigger' => 'expressive_charge_boundary', 'field' => $field,
                'exhausted_committal_ids' => app(TragedyEvidenceService::class)->references($horizon->id)],
        ]);
        // This is the observed subject, not an attachment. Only Reclassify attaches.
        $receipt->receiptable()->associate($proposition);
        $receipt->save();
        $tragedy = (new Tragedy)->forceFill([
            'id' => $id, 'governing_committal_id' => $seed->id, 'context_horizon_id' => $horizon->id, 'receipt_id' => $receipt->id,
            'phase_id' => $phase->id, 'participant_id' => $phase->cycle->participant_id,
            'terminal_proposition_id' => $proposition->id, 'opening_proposition_id' => $opener,
            'observed_field_version' => $version, 'occurred_at' => now(),
        ]);
        $tragedy->save();
        app(TragedyReceiptScopeService::class)->initialize($tragedy);
        app(LedgerService::class)->record($receipt);
        app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
            ['type' => 'horizon.tragedy', 'tragedy_id' => $id, 'receipt_id' => $receipt->id,
                'governing_committal_id' => $seed->id, 'scope' => 'commit_region']);

        return $tragedy;
    }
}
