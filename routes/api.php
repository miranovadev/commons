<?php

use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\ConnectionOptionController;
use App\Http\Controllers\Api\ParticipantConnectionController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\ParticipantPlanController;
use App\Http\Controllers\Api\ParticipationController;
use App\Http\Controllers\Api\PropositionController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SubstrateController;
use App\Http\Middleware\EnsureSystemIsBootstrapped;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/csrf-cookie', fn () => response()->noContent());

    Route::post('/bootstrap', [BootstrapController::class, 'store'])
        ->middleware('throttle:3,1');

    Route::middleware(EnsureSystemIsBootstrapped::class)
        ->group(function (): void {
            Route::get('/substrates', [SubstrateController::class, 'index']);
            Route::get('/substrates/{substrate}', [SubstrateController::class, 'show']);
            Route::get(
                '/substrates/{substrate}/propositions',
                [PropositionController::class, 'index'],
            );

            Route::middleware('guest')->post(
                '/session',
                [SessionController::class, 'store'],
            )->middleware('throttle:6,1');

            Route::middleware('auth')->group(function (): void {
                Route::get('/session', [SessionController::class, 'show']);
                Route::delete('/session', [SessionController::class, 'destroy']);
                Route::get('/connection-options', ConnectionOptionController::class);

                Route::apiResource('connections', ConnectionController::class)
                    ->except(['create', 'edit']);

                Route::post('/substrates', [SubstrateController::class, 'store']);

                Route::scopeBindings()->group(function (): void {
                    Route::apiResource(
                        'substrates.participants',
                        ParticipantController::class,
                    )->only(['index', 'store', 'show']);

                    Route::get(
                        'substrates/{substrate}/participants/{participant}/plan',
                        ParticipantPlanController::class,
                    );
                    Route::get(
                        'substrates/{substrate}/participants/{participant}/participate',
                        [ParticipationController::class, 'show'],
                    );
                    Route::post(
                        'substrates/{substrate}/participants/{participant}/participate',
                        [ParticipationController::class, 'store'],
                    );
                    Route::post(
                        'substrates/{substrate}/participants/{participant}/participate/enter',
                        [ParticipationController::class, 'enter'],
                    );

                    Route::apiResource(
                        'substrates.participants.connections',
                        ParticipantConnectionController::class,
                    )->except(['create', 'edit']);
                });
            });
        });
});
