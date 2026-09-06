<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Resources\ParticipantResource;
use App\Models\Participant;
use App\Models\Substrate;
use App\Services\SubstrateMembershipService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ParticipantController extends Controller
{
    public function index(
        Substrate $substrate,
    ): AnonymousResourceCollection {
        Gate::authorize('viewParticipants', $substrate);

        return ParticipantResource::collection(
            $substrate->participants()
                ->with('latestCycle.currentPhase.gate')
                ->orderBy('callsign')
                ->get(),
        );
    }

    public function store(
        StoreParticipantRequest $request,
        Substrate $substrate,
        SubstrateMembershipService $memberships,
    ): ParticipantResource {
        Gate::authorize('join', $substrate);

        $participant = $memberships->join(
            $request->user(),
            $substrate,
            $request->validated(),
        );

        return new ParticipantResource(
            $participant->load('latestCycle.currentPhase.gate'),
        );
    }

    public function show(
        Substrate $substrate,
        Participant $participant,
    ): ParticipantResource {
        Gate::authorize('viewParticipants', $substrate);

        return new ParticipantResource(
            $participant->load('latestCycle.currentPhase.gate'),
        );
    }
}
