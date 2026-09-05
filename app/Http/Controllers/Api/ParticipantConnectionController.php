<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParticipantConnectionRequest;
use App\Http\Requests\UpdateParticipantConnectionRequest;
use App\Http\Resources\ParticipantConnectionResource;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Substrate;
use App\Services\ParticipantConnectionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ParticipantConnectionController extends Controller
{
    public function index(
        Substrate $substrate,
        Participant $participant,
    ): AnonymousResourceCollection {
        Gate::authorize('viewConnections', $participant);

        return ParticipantConnectionResource::collection(
            $participant->connections()->orderBy('name')->get(),
        );
    }

    public function store(
        StoreParticipantConnectionRequest $request,
        Substrate $substrate,
        Participant $participant,
        ParticipantConnectionService $connections,
    ): ParticipantConnectionResource {
        $connection = $connections->create(
            $participant,
            $request->validated(),
        );

        return new ParticipantConnectionResource($connection);
    }

    public function show(
        Substrate $substrate,
        Participant $participant,
        ParticipantConnection $connection,
    ): ParticipantConnectionResource {
        Gate::authorize('viewConnections', $participant);

        return new ParticipantConnectionResource($connection);
    }

    public function update(
        UpdateParticipantConnectionRequest $request,
        Substrate $substrate,
        Participant $participant,
        ParticipantConnection $connection,
        ParticipantConnectionService $connections,
    ): ParticipantConnectionResource {
        return new ParticipantConnectionResource(
            $connections->update($connection, $request->validated()),
        );
    }

    public function destroy(
        Substrate $substrate,
        Participant $participant,
        ParticipantConnection $connection,
    ): Response {
        Gate::authorize('manageConnections', $participant);

        $connection->delete();

        return response()->noContent();
    }
}
