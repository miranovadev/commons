<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        // Authored Preparation history preserves exact submitted wording.
        $middleware->trimStrings(except: [
            'contribution.purpose', 'contribution.reason',
            // Validate the submitted length before the dispatcher trims the child's statement.
            fn (Request $request) => $request->isMethod('POST') && $request->is('api/substrates/*/participants/*/participate')
                && is_string($action = $request->input('contribution.action')) && Str::trim($action) === 'proposition.reconcile',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
