<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\GraphProjectionService;
use App\Services\ParticipantActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class GraphObservationController extends Controller
{
    public function participants(Substrate $substrate, ParticipantActivityService $activity): JsonResponse
    {
        Gate::authorize('view', $substrate);
        $participants = $substrate->participants()->orderBy('callsign')->orderBy('id')
            ->get(['id', 'callsign', 'current_proposition_id']);

        return response()->json(['data' => $activity->snapshot($participants)]);
    }

    public function metrics(Substrate $substrate, GraphProjectionService $graph): JsonResponse
    {
        Gate::authorize('view', $substrate);
        $nodes = Proposition::active()->whereHas('phase.cycle.participant', fn ($q) => $q->whereBelongsTo($substrate))
            ->get(['id', 'tier']);
        $graph->load($substrate, $nodes, historical: false);

        // Phase changes invalidate measurements across the substrate. Refresh
        // these small projections together, without resending statements/topology.
        return response()->json(['data' => $nodes->map(fn ($node) => [
            'id' => $node->id, 'field' => $node->field, 'participation_count' => $node->participation_count,
        ])]);
    }
}
