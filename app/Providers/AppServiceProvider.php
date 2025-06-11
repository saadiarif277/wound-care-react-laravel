<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Services\CmsCoverageApiService;
use App\Services\ValidationBuilderEngine;
use App\Services\PatientService;
use App\Services\FhirService;
use App\Services\DocusealService;
use App\Services\IvrDocusealService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        Model::unguard();

        // Register CMS Coverage API Service
        $this->app->singleton(CmsCoverageApiService::class, function ($app) {
            return new CmsCoverageApiService();
        });

        // Register Wound Care Validation Engine
        $this->app->singleton(\App\Services\WoundCareValidationEngine::class, function ($app) {
            return new \App\Services\WoundCareValidationEngine(
                $app->make(CmsCoverageApiService::class)
            );
        });

        // Register Pulmonology + Wound Care Validation Engine
        $this->app->singleton(\App\Services\PulmonologyWoundCareValidationEngine::class, function ($app) {
            return new \App\Services\PulmonologyWoundCareValidationEngine(
                $app->make(CmsCoverageApiService::class),
                $app->make(\App\Services\WoundCareValidationEngine::class)
            );
        });

        // Register Validation Builder Engine (factory/coordinator)
        $this->app->singleton(ValidationBuilderEngine::class, function ($app) {
            return new ValidationBuilderEngine(
                $app->make(CmsCoverageApiService::class),
                $app->make(\App\Services\WoundCareValidationEngine::class),
                $app->make(\App\Services\PulmonologyWoundCareValidationEngine::class)
            );
        });

        // Register FhirService
        $this->app->singleton(FhirService::class, function ($app) {
            return new FhirService();
        });

        // Register PatientService with FhirService injection
        $this->app->singleton(PatientService::class, function ($app) {
            return new PatientService(
                $app->make(FhirService::class)
            );
        });

        // Register DocusealService
        $this->app->singleton(DocusealService::class, function ($app) {
            return new DocusealService();
        });

        // Register IvrDocusealService
        $this->app->singleton(IvrDocusealService::class, function ($app) {
            return new IvrDocusealService(
                $app->make(DocusealService::class),
                $app->make(FhirService::class),
                $app->make(\App\Services\IvrFieldMappingService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->bootRoute();
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

    }
}
