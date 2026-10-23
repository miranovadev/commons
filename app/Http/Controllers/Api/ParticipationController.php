<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnterCycleRequest;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Substrate;
use App\Services\ParticipationService;
use App\Services\PreparationService;
use DomainException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ParticipationController extends Controller
{
    public function show(
        Substrate $substrate,
        Participant $participant,
        ParticipationService $participation,
    ): JsonResponse {
        Gate::authorize('act', $participant);

        return response()->json([
            'data' => $participation->stateFor($participant),
        ]);
    }

    public function workspace(
        Substrate $substrate,
        Participant $participant,
        ParticipationService $participation,
    ): JsonResponse {
        Gate::authorize('act', $participant);

        return response()->json(['data' => $participation->workspaceFor($participant)]);
    }

    public function reading(Request $request, Substrate $substrate, Participant $participant, string $execution): JsonResponse
    {
        Gate::authorize('act', $participant);
        try {
            $delivery = app(PreparationService::class)->delivery($participant,
                EvidenceExecution::findOrFail($execution), 'human-text');

            return response()->json(['data' => $delivery])->header('Cache-Control', 'private, no-store');
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422)->header('Cache-Control', 'private, no-store');
        }
    }

    public function presented(Request $request, Substrate $substrate, Participant $participant, string $execution): JsonResponse
    {
        Gate::authorize('act', $participant);
        $input = $request->validate(['presentation_token' => ['required', 'string', 'max:40000']]);
        try {
            app(PreparationService::class)->acknowledge($participant,
                EvidenceExecution::findOrFail($execution), $input['presentation_token']);
        } catch (DecryptException|DomainException $exception) {
            return response()->json(['message' => 'The presentation acknowledgement is invalid.'], 422);
        }

        return response()->json(['data' => ['presented' => true]]);
    }

    public function enter(
        EnterCycleRequest $request,
        Substrate $substrate,
        Participant $participant,
        ParticipationService $participation,
    ): JsonResponse {
        Gate::authorize('act', $participant);

        try {
            $state = $participation->enter(
                $participant,
                $request->validated('origin', null),
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'cycle_entry_rejected',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(
            ['data' => $state],
            Response::HTTP_CREATED,
        );
    }

    public function store(
        Request $request,
        Substrate $substrate,
        Participant $participant,
        ParticipationService $participation,
    ): JsonResponse {
        Gate::authorize('act', $participant);
        $attributes = $request->validate([
            'phase_id' => ['required', 'string', 'size:26'],
            'contribution' => ['required', 'array'],
            'expected_closure' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            $state = $participation->contribute(
                $participant,
                $attributes['phase_id'],
                $attributes['contribution'],
                expectedClosureObservation: $attributes['expected_closure'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'The contribution is not admissible.',
                'errors' => [
                    'contribution' => [$exception->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'contribution_rejected',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $state]);
    }
}
