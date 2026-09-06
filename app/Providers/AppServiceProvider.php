<?php

namespace App\Providers;

use App\Contracts\ParticipantInference;
use App\Services\ParticipantInferenceManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ParticipantInference::class,
            ParticipantInferenceManager::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
