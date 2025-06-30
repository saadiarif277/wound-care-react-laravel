<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Eligibility\UnifiedEligibilityService;
use App\Services\Eligibility\EligibilityProviderInterface;
use App\Services\Eligibility\Providers\AvailityEligibilityProvider;
use App\Services\Eligibility\Providers\OptumEligibilityProvider;
use App\Services\Eligibility\Providers\OfficeAllyEligibilityProvider;
use App\Services\FhirDataLake\FhirAuditEventService;
use App\Services\FhirDataLake\InsuranceAnalyticsService;
use App\Listeners\FhirDataLake\LogInsuranceEvents;

class UnifiedServicesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Unified Eligibility Service
        $this->app->singleton(UnifiedEligibilityService::class, function ($app) {
<<<<<<< HEAD
            return new UnifiedEligibilityService();
        });
        
        // Register Eligibility Providers
        $this->app->bind('eligibility.availity', function ($app) {
            return new AvailityEligibilityProvider(
                config('availity.client_id'),
                config('availity.client_secret'),
                config('availity.environment')
            );
        });
        
        $this->app->bind('eligibility.optum', function ($app) {
            return new OptumEligibilityProvider(
                config('services.optum.client_id'),
                config('services.optum.client_secret')
            );
        });
        
        $this->app->bind('eligibility.officeally', function ($app) {
            return new OfficeAllyEligibilityProvider(
                config('services.officeally.username'),
                config('services.officeally.password')
            );
        });
        
=======
            return new UnifiedEligibilityService(
                $app->make(FhirAuditEventService::class)
            );
        });


>>>>>>> origin/provider-side
        // Register FHIR Data Lake Services
        $this->app->singleton(FhirAuditEventService::class);
        $this->app->singleton(InsuranceAnalyticsService::class);
    }
<<<<<<< HEAD
    
=======

>>>>>>> origin/provider-side
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register event subscribers
        $this->app['events']->subscribe(LogInsuranceEvents::class);
    }
}
