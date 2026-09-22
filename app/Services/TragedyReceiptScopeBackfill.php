<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Tragedy;
use Illuminate\Support\Facades\DB;

/** One-time projection of legacy recovery history, never a packet-time history scan. */
final class TragedyReceiptScopeBackfill
{
    public function run(string $tragedyTable = 'tragedies'): void
    {
        $substrates = DB::table($tragedyTable.' as tragedy')->join('context_horizons as horizon',
            'horizon.id', '=', 'tragedy.context_horizon_id')->distinct()->pluck('horizon.substrate_id');
        foreach ($substrates as $substrateId) {
            DB::transaction(function () use ($substrateId, $tragedyTable): void {
                Substrate::whereKey($substrateId)->lockForUpdate()->firstOrFail();
                $tragedies = Tragedy::from($tragedyTable)->whereIn('context_horizon_id', DB::table('context_horizons')
                    ->where('substrate_id', $substrateId)->select('id'))->get();
                // Entries after Rest are durable recovery witnesses, irrespective of who triggered Tragedy.
                $entries = DB::table('phases as entry')->join('cycles as next', 'next.id', '=', 'entry.cycle_id')
                    ->join('participants as participant', 'participant.id', '=', 'next.participant_id')
                    ->join('cycles as previous', fn ($j) => $j->on('previous.participant_id', '=', 'next.participant_id')
                        ->whereRaw('previous.sequence = next.sequence - 1'))
                    ->where('participant.substrate_id', $substrateId)->where('entry.kind', PhaseKind::Distinguish->value)
                    ->whereNotNull('previous.rested_at')->whereNotNull('previous.completed_at')
                    ->orderBy('entry.id')->get(['entry.id', 'entry.gate_proposition_id', 'previous.id as previous_cycle_id']);
                $seeds = [];
                foreach ($tragedies as $tragedy) {
                    $consumption = DB::table('receipt_consumptions')->where('receipt_id', $tragedy->receipt_id)->first();
                    $position = $consumption === null ? $tragedy->terminal_proposition_id
                        : Phase::findOrFail($consumption->phase_id)->gate_proposition_id;
                    $current = $this->seed($position, $seeds);
                    $last = null;
                    if ($consumption === null) {
                        $birth = substr($tragedy->id, 0, 10); // ULID time avoids second-resolution timestamps.
                        foreach ($entries->filter(fn ($e) => substr($e->id, 0, 10) >= $birth)
                            ->groupBy(fn ($e) => substr($e->id, 0, 10)) as $time => $group) {
                            if ($current === null) {
                                break;
                            }
                            foreach ($group as $entry) {
                                $terminal = Phase::where('cycle_id', $entry->previous_cycle_id)
                                    ->whereNotNull('completed_at')->latest('kind')->first();
                                if ($terminal === null || $this->seed($terminal->gate_proposition_id, $seeds) !== $current
                                    || $entry->gate_proposition_id === $current) {
                                    continue;
                                }
                                // No global ordering witness exists for equal-millisecond legacy events.
                                // Preserve all history and withhold ambiguous discovery rather than guess.
                                if ($time === $birth || $group->count() > 1) {
                                    $current = null;
                                    break;
                                }
                                $destination = Proposition::find($entry->gate_proposition_id);
                                try {
                                    // Governing Committal ancestry is independent of the later observer's route.
                                    $path = app(BackwardRecoveryLineage::class)->distances(Proposition::findOrFail($current), null,
                                        fn (string $id) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
                                } catch (\DomainException) {
                                    $path = [];
                                }
                                $current = $destination?->tier === 'committal' && isset($path[$destination->id]) ? $destination->id : null;
                                $last = $current === null ? null : $entry->id;
                            }
                        }
                    }
                    DB::table('tragedy_receipt_scopes')->insertOrIgnore([
                        'tragedy_id' => $tragedy->id, 'current_committal_id' => $current, 'recovery_phase_id' => $last,
                    ]);
                }
            });
        }
    }

    private function seed(?string $id, array &$seeds): ?string
    {
        if ($id === null) {
            return null;
        }
        if (! array_key_exists($id, $seeds)) {
            $position = Proposition::find($id);
            $seeds[$id] = $position === null ? null : app(\App\Services\Maintenance\LegacyCommitRegionOwnership::class)->seedFor($position)?->id;
        }

        return $seeds[$id];
    }
}

