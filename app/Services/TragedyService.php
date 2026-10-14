<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Tragedy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Direct expressive zero-Charge encounter, scoped to immutable creating-Cycle ownership. */
final class TragedyService
{
    /** Caller owns the substrate contribution lock. Unique governing-Committal and Receipt keys back it. */
    public function record(Proposition $proposition, Phase $encounterPhase, string $action): ?Tragedy
    {
        if ($proposition->tier !== 'expressive' || ! in_array($action, [
            'proposition.variant', 'proposition.revise', 'proposition.reclassify', 'proposition.reconcile',
            'attestation.create', 'instrument.execute', 'proposition.traverse',
        ], true)) {
            return null;
        }
        if (DB::transactionLevel() === 0) {
            throw new \DomainException('Tragedy belongs to the expressive contribution transaction.');
        }
        $measurement = $proposition->measurement()->first();
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
        $participant = $encounterPhase->cycle->participant;
        if ($participant->substrate_id !== $horizon->substrate_id) {
            throw new \DomainException('A terminal encounter cannot cross Substrate boundaries.');
        }
        $existing = Tragedy::where('governing_committal_id', $seed->id)->first();
        if (DB::table('terminal_encounters')->where('proposition_id', $proposition->id)->exists()) {
            return $existing;
        }
        $encounter = (string) Str::ulid();
        $version = Substrate::whereKey($horizon->substrate_id)->value('field_version');
        DB::table('terminal_encounters')->insert([
            'id' => $encounter, 'proposition_id' => $proposition->id, 'governing_committal_id' => $seed->id,
            'phase_id' => $encounterPhase->id, 'participant_id' => $participant->id, 'action' => $action,
            'field' => json_encode($field, JSON_THROW_ON_ERROR), 'observed_field_version' => $version, 'occurred_at' => now(),
        ]);
        app(FieldMutationService::class)->mark($horizon->substrate_id, [$horizon->id], [$seed->id, $proposition->id], measurementsDirty: false);
        app(RealtimePublisher::class)->proposition($proposition->id);
        if ($existing !== null) {
            app(ExhaustionService::class)->evaluate($seed, $encounter);

            return $existing;
        }
        $opener = $horizon->opened_by_proposition_id ?? CommitmentSnapshot::where('substrate_id', $horizon->substrate_id)
            ->where('calculator_version', 'origin-bootstrap-v1')->sole()->proposition_id;
        $id = (string) Str::ulid();
        $receipt = (new Receipt)->forceFill([
            'phase_id' => $encounterPhase->id, 'context_horizon_id' => $horizon->id,
            'type' => 'tragedy', 'reference' => $id, 'capabilities' => ['readable'],
            'metadata' => ['tragedy_id' => $id, 'governing_committal_id' => $seed->id, 'terminal_proposition_id' => $proposition->id,
                'opening_proposition_id' => $opener, 'trigger' => 'direct_expressive_encounter', 'action' => $action, 'terminal_encounter_id' => $encounter, 'field' => $field,
                'exhausted_committal_ids' => app(TragedyEvidenceService::class)->references($horizon->id)],
        ]);
        // This is the observed subject, not an attachment. Only Reclassify attaches.
        $receipt->receiptable()->associate($proposition);
        $receipt->save();
        $tragedy = (new Tragedy)->forceFill([
            'id' => $id, 'governing_committal_id' => $seed->id, 'context_horizon_id' => $horizon->id, 'receipt_id' => $receipt->id,
            'phase_id' => $encounterPhase->id, 'participant_id' => $participant->id, 'terminal_encounter_id' => $encounter,
            'terminal_proposition_id' => $proposition->id, 'opening_proposition_id' => $opener,
            'observed_field_version' => $version, 'occurred_at' => now(),
        ]);
        $tragedy->save();
        app(TragedyReceiptScopeService::class)->initialize($tragedy);
        app(LedgerService::class)->record($receipt);
        app(RealtimePublisher::class)->horizon($horizon->id, $horizon->substrate_id,
            ['type' => 'horizon.tragedy', 'tragedy_id' => $id, 'receipt_id' => $receipt->id,
                'governing_committal_id' => $seed->id, 'scope' => 'commit_region']);

        app(ExhaustionService::class)->evaluate($seed, $encounter);

        return $tragedy;
    }
}
