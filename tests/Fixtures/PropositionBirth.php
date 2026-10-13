<?php

namespace Tests\Fixtures;

use App\Models\Proposition;
use App\Services\Observables\PhaseProfileObservationService;
use App\Services\Observables\ProspectiveClosureService;
use Illuminate\Support\Facades\DB;

/** Complete a direct birth after its ledger and real edges, before projection or later Cycle entry. */
final class PropositionBirth
{
    public static function complete(Proposition $proposition): void
    {
        if ($proposition->tier !== 'expressive' || $proposition->ledgerEntry === null) {
            throw new \LogicException('Complete an expressive fixture birth only after its ledger entry.');
        }
        // A missing immutable observation is a construction defect, not a value
        // this helper may infer from a later graph or governing Cycle.
        if (app(PhaseProfileObservationService::class)->stored($proposition->id) === null) {
            throw new \LogicException('The fixture birth has no immutable Phase Profile observation.');
        }
        DB::transaction(fn () => app(ProspectiveClosureService::class)->initialize($proposition));
    }
}
