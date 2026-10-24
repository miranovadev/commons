<?php

namespace App\Providers;

use App\Contracts\ParticipantInference;
use App\Contracts\WebSearchProvider;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Observers\RealtimeObserver;
use App\Services\Evidence\PdfTextReader;
use App\Services\Evidence\TavilyWebSearchProvider;
use App\Services\ParticipantInferenceManager;
use App\Services\PreparationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PdfTextReader::class);
        $this->app->scoped(PreparationService::class);
        $this->app->bind(WebSearchProvider::class, TavilyWebSearchProvider::class);
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
        foreach ([Phase::class, Cycle::class,
            Participant::class, ContextHorizon::class] as $model) {
            $model::observe(RealtimeObserver::class);
        }

        if (config('app.log_queries')) {
            DB::listen(function ($query) {
                logger()->info('sql', [
                    'time_ms' => $query->time,
                    'sql' => $query->sql,
                ]);
            });
        }
    }
}
