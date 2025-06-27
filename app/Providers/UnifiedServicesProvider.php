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
            return new UnifiedEligibilityService(
                $app->make(FhirAuditEventService::class)
            );
        });


        // Register FHIR Data Lake Services
        $this->app->singleton(FhirAuditEventService::class);
        $this->app->singleton(InsuranceAnalyticsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register event subscribers
        $this->app['events']->subscribe(LogInsuranceEvents::class);
    }
}
