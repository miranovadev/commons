<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropositionResource;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ExhaustionService;
use App\Services\GraphProjectionService;
use App\Services\Observables\ReinforcementService;
use App\Services\PropositionPresentationService;
use App\Services\PropositionInspectionService;
use App\Services\PropositionStateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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
            'contextHorizonMembership.horizon' => fn ($query) => $query->withOpeningRegionExhaustion(), 'parents' => fn ($query) => $query->select('propositions.id', 'propositions.register')
                ->orderBy('supersessions.created_at')->orderBy('supersessions.id'), 'children:id',
            'instrumentExecutions.receipt']);
        $state->loadAttestationCounts(collect([$proposition]));
        $reinforcement->loadCounts(collect([$proposition]));
        $proposition->setAttribute('attestation_summary', $presentation->attestations($proposition));
        $graph->load($substrate, collect([$proposition]), app(ExhaustionService::class)->unavailable($proposition));

        if ($proposition->tier === 'committal') {
            $proposition->setAttribute('closure_history', app(PropositionInspectionService::class)->history($proposition));
        }

        return new PropositionResource($proposition);
    }

    public function index(
        Substrate $substrate,
        PropositionStateService $state,
        ReinforcementService $reinforcement,
        GraphProjectionService $graph,
        Request $request,
    ): AnonymousResourceCollection {
        Gate::authorize('view', $substrate);
        $input = $request->validate(['view' => ['sometimes', 'in:active,exhausted,history'], 'history' => ['sometimes', 'boolean'], 'ids' => ['sometimes', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'ulid', 'distinct']]);

        $view = $input['view'] ?? ($request->boolean('history') ? 'history' : 'active');
        $historical = $view !== 'active';
        $propositions = Proposition::query()->when($view === 'active', fn ($query) => $query->active())
            ->when($view === 'exhausted', fn ($query) => $query->where(fn ($q) => $q
                ->whereIn('propositions.id', Proposition::active()->select('propositions.id'))
                ->orWhereIn('propositions.id', DB::table('supersessions')->select('target_proposition_id')
                    ->whereIn('source_proposition_id', Proposition::active()->select('propositions.id')))))
            ->whereHas(
                'phase.cycle.participant',
                fn ($query) => $query->whereBelongsTo($substrate),
            )
            ->when(isset($input['ids']), fn ($query) => $query->whereKey($input['ids']))
            ->with([
                'phase.cycle.participant:id,callsign,substrate_id',
                'measurement',
                'contextHorizonMembership.horizon' => fn ($query) => $query->withOpeningRegionExhaustion(),
                'parents' => fn ($query) => $query->select('propositions.id', 'propositions.register')
                    ->orderBy('supersessions.created_at')->orderBy('supersessions.id'),
                'children' => fn ($query) => $query->when(! $historical, fn ($q) => $q->active())->select('propositions.id'),
            ])
            ->oldest('created_at')
            ->limit(500)
            ->get();

        $state->loadAttestationCounts($propositions);
        $reinforcement->loadCounts($propositions);
        $graph->load($substrate, $propositions, $historical);

        return PropositionResource::collection($propositions);
    }
}

