<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Substrate;
use App\Services\ContributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ParticipantPlanController extends Controller
{
    public function __invoke(
        Substrate $substrate,
        Participant $participant,
        ContributionService $contributions,
    ): JsonResponse {
        Gate::authorize('act', $participant);

        return response()->json([
            'data' => $contributions->planFor($participant),
        ]);
    }
}
