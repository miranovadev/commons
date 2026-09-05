<?php

use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SubstrateController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/csrf-cookie', fn () => response()->noContent());

    Route::middleware('guest')->group(function (): void {
        Route::post('/bootstrap', [BootstrapController::class, 'store'])
            ->middleware('throttle:3,1');
        Route::post('/session', [SessionController::class, 'store'])
            ->middleware('throttle:6,1');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('/session', [SessionController::class, 'show']);
        Route::delete('/session', [SessionController::class, 'destroy']);

        Route::apiResource('substrates', SubstrateController::class)
            ->only(['index', 'store', 'show']);

        Route::scopeBindings()->group(function (): void {
            Route::apiResource(
                'substrates.participants',
                ParticipantController::class,
            )->only(['index', 'store', 'show']);
        });
    });
});
