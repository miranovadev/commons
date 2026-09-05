<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BootstrapRequest;
use App\Services\SystemBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BootstrapController extends Controller
{
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
