<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropositionResource;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\GraphProjectionService;
use App\Services\Observables\ReinforcementService;
use App\Services\PropositionPresentationService;
use App\Services\PropositionStateService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PropositionController extends Controller
{
    public function show(Substrate $substrate, Proposition $proposition,
        PropositionStateService $state,
        ReinforcementService $reinforcement,
        PropositionPresentationService $presentation,
        GraphProjectionService $graph,
    ): PropositionResource {
        Gate::authorize('view', $substrate);
        abort_unless($proposition->phase->cycle->participant->substrate_id === $substrate->id, 404);
        $proposition->load(['phase.cycle.participant:id,callsign,substrate_id', 'measurement',
            'contextHorizonMembership.horizon', 'parents:id,register', 'children:id',
            'instrumentExecutions.receipt']);
        $state->loadAttestationCounts(collect([$proposition]));
        $reinforcement->loadCounts(collect([$proposition]));
        $proposition->setAttribute('attestation_summary', $presentation->attestations($proposition));
        $graph->load($substrate, collect([$proposition]));

        return new PropositionResource($proposition);
    }

    public function index(
        Substrate $substrate,
        PropositionStateService $state,
        ReinforcementService $reinforcement,
        GraphProjectionService $graph,
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

        $state->loadAttestationCounts($propositions);
        $reinforcement->loadCounts($propositions);
        $graph->load($substrate, $propositions);

        return PropositionResource::collection($propositions);
    }
}
