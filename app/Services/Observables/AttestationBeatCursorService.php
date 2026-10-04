<?php

namespace App\Services\Observables;

use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Models\Supersession;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Rhythm continuity only; no Attestation inheritance or expressive measurement. */
final readonly class AttestationBeatCursorService
{
    public function for(Proposition $proposition): ?object
    {
        return DB::table('proposition_attestation_cursors')->where('proposition_id', $proposition->id)->first();
    }

    /** Birth-time selection, inside the substrate-locked ledger transaction. */
    public function inherit(Supersession $edge): void
    {
        if ($edge->type === 'reconcile' && $edge->source_proposition_id !== $edge->phase->gate_proposition_id) {
            return; // Other parent is causal evidence only, even when its beat is newer.
        }
        $target = $edge->target;
        $existing = $this->for($target);
        if ($existing !== null) {
            // A cursor is selected at birth. Re-recording or adding causal
            // evidence later cannot replace that event-born branch state.
            return;
        }
        $source = $edge->source;
        $cursor = $this->for($source);
        if ($cursor === null && $source->tier === 'expressive' && Attestation::where('proposition_id', $source->id)->whereHas('ledgerEntry')->exists()) {
            throw new DomainException('Historical expressive proposition '.$source->id.' has no event-born beat cursor; live vNext rhythm is unavailable at the prospective migration boundary.');
        }
        DB::table('proposition_attestation_cursors')->insert([
            'proposition_id' => $target->id,
            'beat_sequence' => $cursor?->beat_sequence,
            'attestation_id' => $cursor?->attestation_id,
            'source_supersession_id' => $edge->id,
        ]);
    }

    /** Count only the ending proposition's represented entries between genuine acts. */
    public function interval(Proposition $proposition, int $endingSequence): ?int
    {
        $from = $this->for($proposition)?->beat_sequence;
        if ($from === null) {
            return null;
        }
        if ($endingSequence <= (int) $from) {
            throw new DomainException('An Attestation beat must follow its branch cursor.');
        }
        $entry = $proposition->phase->cycle->entry_committal_id;
        if ($entry === null) {
            return 0;
        }

        // A Cycle recorded after ledger sequence S is after beat S, but before
        // the next ledger event. Inclusive lower / exclusive upper bounds are intentional.
        return Cycle::where('entry_committal_id', $entry)
            ->where('context_horizon_id', $proposition->phase->cycle->context_horizon_id)
            ->where('chirality_entry_after_sequence', '>=', $from)
            ->where('chirality_entry_after_sequence', '<', $endingSequence)->count();
    }

    public function attest(Attestation $attestation, int $sequence): void
    {
        DB::table('proposition_attestation_cursors')->upsert([
            ['proposition_id' => $attestation->proposition_id, 'beat_sequence' => $sequence,
                'attestation_id' => $attestation->id, 'source_supersession_id' => null],
        ], ['proposition_id'], ['beat_sequence', 'attestation_id', 'source_supersession_id']);
    }
}
