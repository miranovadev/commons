<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Recovery entries already persist the carrier's route. Receipt/proof rows stay immutable. */
final class TragedyReceiptScopeService
{
    public function seeds(ParticipationContext $context): array
    {
        return $context->remember('tragedy-receipt-scopes', function () use ($context): array {
            $tragedies = DB::table('horizon_tragedies as tragedy')
                ->join('phases as terminal', 'terminal.id', '=', 'tragedy.phase_id')
                ->join('cycles as start', 'start.id', '=', 'terminal.cycle_id')
                ->join('participants as carrier', 'carrier.id', '=', 'tragedy.participant_id')
                ->where('carrier.substrate_id', $context->substrate->id)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('receipt_consumptions as consumed')
                    ->whereColumn('consumed.receipt_id', 'tragedy.receipt_id'))
                ->get(['tragedy.*', 'start.sequence as start_sequence']);
            if ($tragedies->isEmpty()) {
                return [];
            }
            // Only entry after Rest is recovery; initial entry and post-Commit rollover never carry it.
            $entries = DB::table('cycles as next')
                ->join('cycles as previous', fn ($join) => $join->on('previous.participant_id', '=', 'next.participant_id')
                    ->whereRaw('previous.sequence = next.sequence - 1'))
                ->join('phases as entry', fn ($join) => $join->on('entry.cycle_id', '=', 'next.id')->where('entry.kind', 1))
                ->whereIn('next.participant_id', $tragedies->pluck('participant_id'))
                ->whereNotNull('previous.rested_at')->orderByDesc('next.sequence')
                ->get(['next.participant_id', 'next.sequence', 'entry.gate_proposition_id']);
            $seeds = app(ReceiptRegionService::class)->seeds($context);
            $result = [];
            foreach ($tragedies as $tragedy) {
                $result[$tragedy->receipt_id] = $seeds[$tragedy->terminal_proposition_id] ?? null;
                $entry = $entries->first(fn ($entry) => $entry->participant_id === $tragedy->participant_id
                    && $entry->sequence > $tragedy->start_sequence);
                if ($entry === null) {
                    continue;
                }
                $terminal = $context->propositions->get($tragedy->terminal_proposition_id)
                    ?? Proposition::find($tragedy->terminal_proposition_id);
                $destination = $context->propositions->get($entry->gate_proposition_id)
                    ?? Proposition::find($entry->gate_proposition_id);
                if ($terminal === null || $destination?->tier !== 'committal') {
                    $result[$tragedy->receipt_id] = null;

                    continue;
                }
                try {
                    $path = app(BackwardRecoveryLineage::class)->distances($terminal,
                        Participant::find($tragedy->participant_id),
                        fn (string $id) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
                } catch (\DomainException) {
                    $path = [];
                }
                $result[$tragedy->receipt_id] = isset($path[$destination->id]) ? $destination->id : null;
            }

            return $result;
        });
    }
}
