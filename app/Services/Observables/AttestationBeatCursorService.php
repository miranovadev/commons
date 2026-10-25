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
        if ($target->tier === 'committal') {
            // Canonical Commit retains cursor transport; it never owns intervals.
            $cursor = $this->for($edge->source);
            if ($cursor === null && Attestation::where('proposition_id', $edge->source_proposition_id)->whereHas('ledgerEntry')->exists()) {
                throw new DomainException('Historical expressive source has no event-born beat cursor.');
            }
            $selected = ['beat_sequence' => $cursor?->beat_sequence, 'attestation_id' => $cursor?->attestation_id];
        } else {
            $selected = app(ActiveCadenceLineageService::class)->reference($target, $edge);
        }
        DB::table('proposition_attestation_cursors')->insert([
            'proposition_id' => $target->id,
            'beat_sequence' => $selected['beat_sequence'],
            'attestation_id' => $selected['attestation_id'],
            'source_supersession_id' => $edge->id,
        ]);
    }

    /** Count only the ending proposition's represented entries between genuine acts. */
    public function interval(Proposition $proposition, int $endingSequence): ?int
    {
        return $this->intervalFrom($proposition, $endingSequence, $this->for($proposition)?->beat_sequence);
    }

    public function intervalFrom(Proposition $proposition, int $endingSequence, ?int $from): ?int
    {
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

    /** Own beats advance; the selected birth edge remains immutable provenance. */
    public function attest(Attestation $attestation, int $sequence): void
    {
        DB::table('proposition_attestation_cursors')->upsert([
            ['proposition_id' => $attestation->proposition_id, 'beat_sequence' => $sequence,
                'attestation_id' => $attestation->id, 'source_supersession_id' => null],
        ], ['proposition_id'], ['beat_sequence', 'attestation_id']);
    }
}
