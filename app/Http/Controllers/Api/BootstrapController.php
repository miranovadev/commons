<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BootstrapRequest;
use App\Models\User;
use App\Services\SystemBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class BootstrapController extends Controller
{
    public function show(): Response|JsonResponse
    {
        if (User::query()->doesntExist()) {
            return response()->json([
                'message' => 'The Commons must be bootstrapped before it can be used.',
                'code' => 'bootstrap_required',
            ], 428);
        }

        return response()->noContent();
    }

    public function store(
        BootstrapRequest $request,
        SystemBootstrapService $bootstrap,
    ): JsonResponse {
        $user = $bootstrap->bootstrap($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'data' => [
                'user' => [
                    ...$user->only(['id', 'name', 'email']),
                    'has_system_access' => true,
                ],
                'role' => config(
                    'authorization.roles.system_administrator',
                ),
            ],
        ], 201);
    }
}
