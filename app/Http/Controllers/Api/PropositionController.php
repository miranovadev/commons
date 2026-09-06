<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropositionResource;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\PropositionMeasurementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PropositionController extends Controller
{
    public function index(
        Substrate $substrate,
        PropositionMeasurementService $measurements,
    ): AnonymousResourceCollection {
        Gate::authorize('view', $substrate);

        $propositions = Proposition::query()
            ->whereHas(
                'phase.cycle.participant',
                fn ($query) => $query->whereBelongsTo($substrate),
            )
            ->with([
                'phase.cycle.participant:id,callsign',
                'measurement',
                'contextHorizonMembership.horizon',
                'parents:id,register',
                'children:id',
            ])
            ->oldest('created_at')
            ->limit(500)
            ->get();

        $measurements->repairMissing($propositions);

        return PropositionResource::collection($propositions);
    }
}
