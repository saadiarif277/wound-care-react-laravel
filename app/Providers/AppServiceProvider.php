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
use App\Services\AI\FormFillingOptimizer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

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

        // FhirService is registered in FHIRServiceProvider with circuit breaker functionality

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
        // Register Entity Data Service
        $this->app->singleton(\App\Services\EntityDataService::class, function ($app) {
            return new \App\Services\EntityDataService(
                $app->make(\App\Logging\PhiSafeLogger::class)
            );
        });        
        // Medical Terminology Service
        $this->app->singleton(\App\Services\MedicalTerminologyService::class, function ($app) {
            if (app()->runningUnitTests()) {
                return \Mockery::mock(\App\Services\MedicalTerminologyService::class);
            }
            return new \App\Services\MedicalTerminologyService();
        });

        // Register Form Filling Optimizer
        $this->app->singleton(FormFillingOptimizer::class, function ($app) {
            return new FormFillingOptimizer(
                $app->make(\App\Services\Medical\OptimizedMedicalAiService::class),
                $app->make(\App\Services\DocumentIntelligenceService::class),
                $app->make(\App\Services\AI\AzureFoundryService::class)
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

        // Register Order Observer
        Order::observe(OrderObserver::class);

        // Response macro for security headers
        // Response::macro('withSecurityHeaders', function ($response) {
        //     $response->headers->set('X-Content-Type-Options', 'nosniff');
        //     $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        //     $response->headers->set('X-XSS-Protection', '1; mode=block');
        //     return $response;
        // });

        // Add exception handling for mail failures
        $this->handleMailExceptions();
    }

    public function bootRoute(): void
    {
        // Rate limiting is configured in SecurityServiceProvider with comprehensive error handling
    }

    /**
     * Handle mail exceptions to prevent application halting
     */
    protected function handleMailExceptions(): void
    {
        // Override the mail manager to catch exceptions
        $this->app->singleton('mail.manager', function ($app) {
            $manager = new \Illuminate\Mail\MailManager($app);
            
            // Add exception handling for mail failures
            $manager->beforeSending(function ($message) {
                try {
                    // Log mail attempt
                    Log::info('Attempting to send email', [
                        'to' => $message->getTo(),
                        'subject' => $message->getSubject(),
                        'from' => $message->getFrom()
                    ]);
                } catch (Exception $e) {
                    Log::warning('Failed to log mail attempt', ['error' => $e->getMessage()]);
                }
            });

            return $manager;
        });

        // Add global exception handler for mail failures
        $this->app->singleton('mail.exception.handler', function ($app) {
            return function (Exception $e) {
                Log::error('Mail sending failed', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Don't re-throw the exception - just log it and continue
                return false;
            };
        });
    }
}
