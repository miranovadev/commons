<?php

use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Substrate;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('participant.{id}', function (User $user, string $id): bool {
    $participant = Participant::find($id);

    return $participant !== null && Gate::forUser($user)->allows('act', $participant);
});

Broadcast::channel('context-horizon.{id}', function (User $user, string $id): bool {
    $horizon = ContextHorizon::with('substrate')->find($id);

    return $horizon !== null && Gate::forUser($user)->allows('view', $horizon->substrate);
});

Broadcast::channel('substrate.{id}', function (User $user, string $id): bool {
    $substrate = Substrate::find($id);

    return $substrate !== null && Gate::forUser($user)->allows('view', $substrate);
});
