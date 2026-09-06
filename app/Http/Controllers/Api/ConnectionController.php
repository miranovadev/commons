<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConnectionRequest;
use App\Http\Requests\UpdateConnectionRequest;
use App\Http\Resources\ConnectionResource;
use App\Models\Connection;
use App\Services\ConnectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConnectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ConnectionResource::collection(
            $request->user()->connections()
                ->withCount('participantConnections')
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(
        StoreConnectionRequest $request,
        ConnectionService $connections,
    ): ConnectionResource {
        return new ConnectionResource(
            $connections->create($request->user(), $request->validated()),
        );
    }

    public function show(Connection $connection): ConnectionResource
    {
        Gate::authorize('view', $connection);

        return new ConnectionResource(
            $connection->loadCount('participantConnections'),
        );
    }

    public function update(
        UpdateConnectionRequest $request,
        Connection $connection,
        ConnectionService $connections,
    ): ConnectionResource {
        return new ConnectionResource(
            $connections->update($connection, $request->validated())
                ->loadCount('participantConnections'),
        );
    }

    public function destroy(Connection $connection): Response
    {
        Gate::authorize('delete', $connection);
        $connection->delete();

        return response()->noContent();
    }
}
