<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContextHorizon;
use App\Models\Substrate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class RealtimeController extends Controller
{
    public function __invoke(Substrate $substrate): JsonResponse
    {
        Gate::authorize('view', $substrate);

        // The existing Graph spans a Substrate, so it observes its horizons.
        // This read does not create a horizon on an empty Substrate.
        return response()->json(['data' => [
            'substrate_id' => $substrate->id,
            'public' => $substrate->isPublic(),
            'context_horizon_ids' => ContextHorizon::whereBelongsTo($substrate)
                ->orderBy('sequence')->pluck('id')->all(),
        ]]);
    }
}
