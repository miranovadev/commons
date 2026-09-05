<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The supplied credentials are invalid.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'data' => $this->user($request->user()),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->user($request->user()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function user(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'email']),
            'has_system_access' => $user->hasSystemAccess(),
        ];
    }
}
