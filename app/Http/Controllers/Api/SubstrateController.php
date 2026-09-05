<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubstrateRequest;
use App\Http\Resources\SubstrateResource;
use App\Models\Substrate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SubstrateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Substrate::class);

        $user = request()->user();
        $query = Substrate::query()->withCount('participants');

        if (! $user->hasSystemAccess()) {
            $query->where(function ($query) use ($user): void {
                $query->where('visibility', 'public')
                    ->orWhereHas(
                        'memberships',
                        fn ($membership) => $membership
                            ->whereBelongsTo($user)
                            ->whereIn('status', ['invited', 'active']),
                    );
            });
        }

        return SubstrateResource::collection(
            $query->orderBy('title')->get(),
        );
    }

    public function store(
        StoreSubstrateRequest $request,
    ): SubstrateResource {
        Gate::authorize('create', Substrate::class);

        $attributes = $request->validated();
        $attributes['configuration'] = [
            'maximum_participants' => 100,
            ...($attributes['configuration'] ?? []),
        ];

        $substrate = new Substrate;
        $substrate->forceFill($attributes);
        $substrate->save();

        return new SubstrateResource(
            $substrate->loadCount('participants'),
        );
    }

    public function show(Substrate $substrate): SubstrateResource
    {
        Gate::authorize('view', $substrate);

        return new SubstrateResource(
            $substrate->loadCount('participants'),
        );
    }
}
