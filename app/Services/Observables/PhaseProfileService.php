<?php

namespace App\Services\Observables;

use App\Domain\Observables\PhaseProfile;
use App\Enums\LedgerEntryKind;
use App\Exceptions\PhaseProfileHistoryUnavailable;
use App\Models\ContextHorizon;
use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

final readonly class PhaseProfileService
{
    /** Rolling profile, or a frozen prefix ending at a substrate ledger sequence. */
    public function for(ContextHorizon $horizon, ?int $throughSequence = null): PhaseProfile
    {
        // Creating ownership is authoritative, not opened_by_proposition_id.
        // Membership projections can be rebuilt without changing these samples.
        $rows = DB::table('propositions as p')
            ->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->join('cycles as c', 'c.id', '=', 'ph.cycle_id')
            ->leftJoin('substrate_ledger_entries as l', function ($join): void {
                $join->on('l.ledgerable_id', '=', 'p.id')
                    ->where('l.ledgerable_type', (new Proposition)->getMorphClass())
                    ->where('l.kind', LedgerEntryKind::Proposition->value);
            })
            ->leftJoin('phase_profile_observations as o', 'o.proposition_id', '=', 'p.id')
            ->leftJoin('commitment_snapshots as s', 's.proposition_id', '=', 'p.id')
            ->where('c.context_horizon_id', $horizon->id)
            ->when($throughSequence !== null, fn ($query) => $query->where('l.sequence', '<=', $throughSequence))
            ->orderBy('l.sequence')
            ->get(['p.id', 'ph.kind', 'o.developmental_cycle_position', 'o.cycle_interval', 's.calculator_version']);

        // Canonical axiom, not a backfill. It requires the actual Origin boundary,
        // not merely an empty root Horizon or any later Emergence Horizon.
        if ($horizon->parent_context_horizon_id === null && $rows->count() === 1
            && $rows->first()->calculator_version === 'origin-bootstrap-v1') {
            return PhaseProfile::origin();
        }

        $sums = array_fill(0, 7, 0);
        $counts = array_fill(0, 7, 0);
        foreach ($rows as $row) {
            if ($row->developmental_cycle_position === null || $row->cycle_interval === null) {
                throw new PhaseProfileHistoryUnavailable('Phase Profile history is unavailable at proposition '.$row->id.'; event-born observations cannot be backfilled.');
            }
            $slot = (int) $row->kind - 1;
            $sums[$slot] += (int) $row->cycle_interval;
            $counts[$slot]++;
        }

        return new PhaseProfile(array_map(fn ($sum, $count) => $count === 0 ? null : $sum / $count, $sums, $counts));
    }
}
