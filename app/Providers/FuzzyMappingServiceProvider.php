<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FuzzyMapping\EnhancedFuzzyFieldMatcher;
use App\Services\FuzzyMapping\ManufacturerTemplateHandler;
use App\Services\FuzzyMapping\ValidationEngine;
use App\Services\FuzzyMapping\FallbackStrategy;
use App\Services\FuzzyMapping\IVRMappingOrchestrator;

class FuzzyMappingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register as singletons for performance
        $this->app->singleton(EnhancedFuzzyFieldMatcher::class);
        $this->app->singleton(ManufacturerTemplateHandler::class);
        $this->app->singleton(FallbackStrategy::class);
        
        // ValidationEngine depends on ManufacturerTemplateHandler
        $this->app->singleton(ValidationEngine::class, function ($app) {
            return new ValidationEngine(
                $app->make(ManufacturerTemplateHandler::class)
            );
        });
        
        // IVRMappingOrchestrator depends on all services
        $this->app->singleton(IVRMappingOrchestrator::class, function ($app) {
            return new IVRMappingOrchestrator(
                $app->make(EnhancedFuzzyFieldMatcher::class),
                $app->make(ManufacturerTemplateHandler::class),
                $app->make(ValidationEngine::class),
                $app->make(FallbackStrategy::class),
                $app->make(\App\Services\FhirService::class)
            );
        });
        
        // Alias for easier access
        $this->app->alias(IVRMappingOrchestrator::class, 'ivr.mapping');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/ivr-mapping.php' => config_path('ivr-mapping.php'),
        ], 'ivr-mapping-config');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}