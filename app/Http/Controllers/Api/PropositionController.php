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
    public function show(Substrate $substrate, Proposition $proposition,
        PropositionMeasurementService $measurements,
        \App\Services\PropositionStateService $state,
        \App\Services\PropositionPresentationService $presentation,
    ): PropositionResource {
        Gate::authorize('view', $substrate);
        abort_unless($proposition->phase->cycle->participant->substrate_id === $substrate->id, 404);
        $proposition->load(['phase.cycle.participant:id,callsign,substrate_id', 'measurement',
            'contextHorizonMembership.horizon', 'parents:id,register', 'children:id',
            'instrumentExecutions.receipt']);
        $measurements->repairMissing(collect([$proposition]));
        $state->loadAttestationCounts(collect([$proposition]));
        $proposition->setAttribute('attestation_summary', $presentation->attestations($proposition));

        return new PropositionResource($proposition);
    }

    public function index(
        Substrate $substrate,
        PropositionMeasurementService $measurements,
        \App\Services\PropositionStateService $state,
    ): AnonymousResourceCollection {
        Gate::authorize('view', $substrate);

        $propositions = Proposition::query()
            ->whereHas(
                'phase.cycle.participant',
                fn ($query) => $query->whereBelongsTo($substrate),
            )
            ->with([
                'phase.cycle.participant:id,callsign,substrate_id',
                'measurement',
                'contextHorizonMembership.horizon',
                'parents:id,register',
                'children:id',
            ])
            ->oldest('created_at')
            ->limit(500)
            ->get();

        $measurements->repairMissing($propositions);
        $state->loadAttestationCounts($propositions);

        return PropositionResource::collection($propositions);
    }
}
