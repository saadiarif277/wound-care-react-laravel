<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\HealthData\Clients\AzureFhirClient;
use App\Services\HealthData\Services\Fhir\SkinSubstituteChecklistService;
use App\Services\HealthData\Services\ChecklistValidationService;
use Illuminate\Support\Facades\Http;

class FhirServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AzureFhirClient::class, function ($app) {
            // Ensure config keys exist or provide defaults
            $baseUrl = config('services.azure.fhir.base_url');
            if (!$baseUrl) {
                // Log error or throw exception if critical config is missing
                logger()->error('Azure FHIR base_url is not configured.');
                // return null; // Or throw an exception
            }
            return new AzureFhirClient(
                $baseUrl ?? '', // Provide a default empty string if null to satisfy constructor
                $this->getAzureFhirAccessToken() ?? '' // Provide default for token as well
            );
        });

        $this->app->bind(SkinSubstituteChecklistService::class, function ($app) {
            return new SkinSubstituteChecklistService(
                $app->make(AzureFhirClient::class)
            );
        });

        // Register ChecklistValidationService
        $this->app->bind(ChecklistValidationService::class, function ($app) {
            return new ChecklistValidationService(); // Assuming it has no constructor dependencies for now
        });
    }

    /**
     * Get Azure FHIR access token
     */
    private function getAzureFhirAccessToken(): ?string // Return type can be nullable
    {
        // Implementation would retrieve token from cache or request new one
        return cache()->remember('azure_fhir_token', 3500, function () {
            // Token acquisition logic here
            return $this->requestNewToken();
        });
    }

    /**
     * Request new token from Azure AD
     */
    private function requestNewToken(): ?string // Return type can be nullable
    {
        $tenantId = config('services.azure.tenant_id');
        $clientId = config('services.azure.client_id');
        $clientSecret = config('services.azure.client_secret');
        $fhirBaseUrl = config('services.azure.fhir.base_url');

        if (!$tenantId || !$clientId || !$clientSecret || !$fhirBaseUrl) {
            logger()->error('Azure AD credentials for FHIR token acquisition are not fully configured.');
            return null;
        }

        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => rtrim($fhirBaseUrl, '/') . '/.default'
                ]
            );

            if (!$response->successful()) {
                logger()->error('Failed to request new Azure FHIR token', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }
            return $response->json('access_token');
        } catch (\Exception $e) {
            logger()->error('Exception during Azure FHIR token request', ['message' => $e->getMessage()]);
            return null;
        }
    }
}