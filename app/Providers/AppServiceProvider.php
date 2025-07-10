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
use Illuminate\Support\Facades\Response;
use App\Models\Order\Order;
use App\Observers\OrderObserver;

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

        // Register Unified Field Mapping Services
        $this->app->singleton(\App\Services\FieldMapping\DataExtractor::class, function ($app) {
            return new \App\Services\FieldMapping\DataExtractor(
                $app->make(FhirService::class)
            );
        });

        $this->app->singleton(\App\Services\FieldMapping\FieldTransformer::class, function ($app) {
            return new \App\Services\FieldMapping\FieldTransformer();
        });

        $this->app->singleton(\App\Services\FieldMapping\FieldMatcher::class, function ($app) {
            return new \App\Services\FieldMapping\FieldMatcher();
        });

        $this->app->singleton(\App\Services\UnifiedFieldMappingService::class, function ($app) {
            return new \App\Services\UnifiedFieldMappingService(
                $app->make(\App\Services\FieldMapping\DataExtractor::class),
                $app->make(\App\Services\FieldMapping\FieldTransformer::class),
                $app->make(\App\Services\FieldMapping\FieldMatcher::class),
                $app->make(FhirService::class),
                $app->make(\App\Services\MedicalTerminologyService::class)
            );
        });

        // Register Unified Docuseal Service
        $this->app->singleton(\App\Services\DocusealService::class, function ($app) {
            return new \App\Services\DocusealService(
                $app->make(\App\Services\UnifiedFieldMappingService::class)
            );
        });

        // Register PayerService
        $this->app->singleton(\App\Services\PayerService::class, function ($app) {
            return new \App\Services\PayerService();
        });

        // Register Azure Document Intelligence Service
        $this->app->singleton(\App\Services\DocumentIntelligenceService::class, function ($app) {
            if (app()->runningUnitTests()) {
                return \Mockery::mock(\App\Services\DocumentIntelligenceService::class);
            }
            return new \App\Services\DocumentIntelligenceService();
        });

        // Register DocuSeal Template Discovery Service
        $this->app->singleton(\App\Services\DocuSeal\DocuSealTemplateDiscoveryService::class, function ($app) {
            return new \App\Services\DocuSeal\DocuSealTemplateDiscoveryService();
        });
        
        // Medical Terminology Service
        $this->app->singleton(\App\Services\MedicalTerminologyService::class, function ($app) {
            if (app()->runningUnitTests()) {
                return \Mockery::mock(\App\Services\MedicalTerminologyService::class);
            }
            return new \App\Services\MedicalTerminologyService();
        });


    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->bootRoute();

        // Register Order Observer
        Order::observe(OrderObserver::class);

        // Response macro for security headers
        // Response::macro('withSecurityHeaders', function ($response) {
        //     $response->headers->set('X-Content-Type-Options', 'nosniff');
        //     $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        //     $response->headers->set('X-XSS-Protection', '1; mode=block');
        //     return $response;
        // });
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
