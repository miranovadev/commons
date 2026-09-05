<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemIsBootstrapped
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->doesntExist()) {
            return new JsonResponse([
                'message' => 'The Commons must be bootstrapped before it can be used.',
                'code' => 'bootstrap_required',
            ], Response::HTTP_PRECONDITION_REQUIRED);
        }

        return $next($request);
    }
}
