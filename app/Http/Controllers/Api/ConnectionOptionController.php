<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConnectionProvider;
use App\Enums\ConnectionTriggerMode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ConnectionOptionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'providers' => array_column(
                    ConnectionProvider::cases(),
                    'value',
                ),
                'trigger_modes' => array_column(
                    ConnectionTriggerMode::cases(),
                    'value',
                ),
            ],
        ]);
    }
}
