<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\EpisodeTemplateCacheService;
use App\Services\FhirService;

class EpisodeCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register EpisodeTemplateCacheService as singleton
        $this->app->singleton(EpisodeTemplateCacheService::class, function ($app) {
            return new EpisodeTemplateCacheService(
                $app->make(FhirService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish cache configuration
        $this->publishes([
            __DIR__.'/../../config/episode-cache.php' => config_path('episode-cache.php'),
        ], 'episode-cache-config');

        // Schedule cache warming for upcoming episodes
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                
                // Warm cache for upcoming episodes every 15 minutes
                $schedule->call(function () {
                    $cacheService = $this->app->make(EpisodeTemplateCacheService::class);
                    $cacheService->preCacheUpcomingEpisodes();
                })->everyFifteenMinutes()->name('warm-episode-cache')->withoutOverlapping();
            });
        }
    }
}
