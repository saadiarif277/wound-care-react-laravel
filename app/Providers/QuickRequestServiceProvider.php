<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\QuickRequest\Handlers\OrderHandler;
use App\Services\QuickRequest\Handlers\NotificationHandler;

class QuickRequestServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register handlers as singletons for performance
        $this->app->singleton(PatientHandler::class);
        $this->app->singleton(ProviderHandler::class);
        $this->app->singleton(ClinicalHandler::class);
        $this->app->singleton(InsuranceHandler::class);
        $this->app->singleton(OrderHandler::class);
        $this->app->singleton(NotificationHandler::class);
        
        // Register the orchestrator
        $this->app->singleton(QuickRequestOrchestrator::class, function ($app) {
            return new QuickRequestOrchestrator(
                $app->make(PatientHandler::class),
                $app->make(ProviderHandler::class),
                $app->make(ClinicalHandler::class),
                $app->make(InsuranceHandler::class),
                $app->make(OrderHandler::class),
                $app->make(NotificationHandler::class),
                $app->make(\App\Logging\PhiSafeLogger::class)
            );
        });
        
        // Register facade accessor
        $this->app->alias(QuickRequestOrchestrator::class, 'quickrequest');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register event listeners for Quick Request workflow
        $this->registerEventListeners();
        
        // Register custom validation rules
        $this->registerValidationRules();
    }

    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        // Episode status changes
        \App\Models\Episode::observe(\App\Observers\EpisodeObserver::class);
        
        // Order status changes
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
    }

    /**
     * Register custom validation rules
     */
    protected function registerValidationRules(): void
    {
        // NPI validation
        \Validator::extend('npi', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\d{10}$/', $value) && $this->validateNpiChecksum($value);
        }, 'The :attribute must be a valid NPI number.');
        
        // ICD-10 validation
        \Validator::extend('icd10', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[A-Z]\d{2}(\.\d{1,4})?$/', $value);
        }, 'The :attribute must be a valid ICD-10 code.');
        
        // FHIR ID validation
        \Validator::extend('fhir_id', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[A-Za-z0-9\-\.]{1,64}$/', $value);
        }, 'The :attribute must be a valid FHIR resource ID.');
        
        // Patient display ID validation
        \Validator::extend('patient_display_id', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[A-Z]{4}\d{3}$/', $value);
        }, 'The :attribute must be in format XXXX###.');
    }

    /**
     * Validate NPI checksum using Luhn algorithm
     */
    protected function validateNpiChecksum(string $npi): bool
    {
        $npi = '80840' . $npi; // Add prefix for validation
        $sum = 0;
        $alternate = false;

        for ($i = strlen($npi) - 1; $i >= 0; $i--) {
            $n = (int) $npi[$i];
            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n = ($n % 10) + 1;
                }
            }
            $sum += $n;
            $alternate = !$alternate;
        }

        return ($sum % 10) == 0;
    }
}