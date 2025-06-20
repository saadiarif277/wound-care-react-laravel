<?php

namespace App\Services\Eligibility;

use App\Services\Eligibility\Providers\EligibilityProviderInterface;
use App\Services\Eligibility\Providers\AvailityProvider;
use App\Services\Eligibility\Providers\OptumProvider;
use App\Services\Eligibility\Providers\OfficeAllyProvider;
use App\Models\Insurance\EligibilityCheck;
use App\Services\FhirDataLake\FhirAuditEventService;
use Illuminate\Support\Facades\Log;
use Exception;

class UnifiedEligibilityService
{
    private array $providers = [];
    private FhirAuditEventService $auditService;
    
    public function __construct(FhirAuditEventService $auditService)
    {
        $this->auditService = $auditService;
        $this->initializeProviders();
    }
    
    /**
     * Initialize all available eligibility providers
     */
    private function initializeProviders(): void
    {
        // Initialize providers based on config
        if (config('services.availity.enabled', false)) {
            $this->providers['availity'] = app(AvailityProvider::class);
        }
        
        if (config('services.optum.enabled', false)) {
            $this->providers['optum'] = app(OptumProvider::class);
        }
        
        if (config('services.office_ally.enabled', false)) {
            $this->providers['office_ally'] = app(OfficeAllyProvider::class);
        }
    }
    
    /**
     * Check eligibility using the best available provider
     */
    public function checkEligibility(array $request): array
    {
        $startTime = microtime(true);
        $provider = $this->selectProvider($request);
        
        if (!$provider) {
            throw new Exception('No eligibility provider available for this payer');
        }
        
        try {
            // Perform eligibility check
            $response = $provider->checkEligibility($request);
            
            // Save to database
            $eligibilityCheck = $this->saveEligibilityCheck($request, $response, $provider->getName());
            
            // Log to FHIR Data Lake
            $this->auditService->logEligibilityCheck(
                $request['coverage_id'] ?? null,
                $provider->getName(),
                $request,
                array_merge($response, [
                    'duration' => (microtime(true) - $startTime) * 1000
                ])
            );
            
            return [
                'success' => true,
                'data' => $response,
                'provider' => $provider->getName(),
                'eligibility_check_id' => $eligibilityCheck->id
            ];
            
        } catch (Exception $e) {
            Log::error('Eligibility check failed', [
                'provider' => $provider->getName(),
                'error' => $e->getMessage(),
                'request' => $request
            ]);
            
            // Try fallback provider if available
            return $this->tryFallbackProvider($request, $provider->getName());
        }
    }
    
    /**
     * Select the best provider for the given payer
     */
    private function selectProvider(array $request): ?EligibilityProviderInterface
    {
        $payerId = $request['payer_id'] ?? null;
        $payerName = $request['payer_name'] ?? null;
        
        // Check provider-specific payer support
        foreach ($this->providers as $provider) {
            if ($provider->supportsPayer($payerId, $payerName)) {
                return $provider;
            }
        }
        
        // Return first available provider as fallback
        return !empty($this->providers) ? reset($this->providers) : null;    }
    
    /**
     * Try fallback provider if primary fails
     */
    private function tryFallbackProvider(array $request, string $failedProvider): array
    {
        foreach ($this->providers as $name => $provider) {
            if ($name === $failedProvider) continue;
            
            try {
                $response = $provider->checkEligibility($request);
                return [
                    'success' => true,
                    'data' => $response,
                    'provider' => $provider->getName(),
                    'fallback' => true
                ];
            } catch (Exception $e) {
                continue;
            }
        }
        
        return [
            'success' => false,
            'error' => 'All eligibility providers failed',
            'providers_tried' => array_keys($this->providers)
        ];
    }
    
    /**
     * Save eligibility check to database
     */
    private function saveEligibilityCheck(array $request, array $response, string $provider): EligibilityCheck
    {
        return EligibilityCheck::create([
            'coverage_id' => $request['coverage_id'] ?? null,
            'patient_id' => $request['patient_id'] ?? null,
            'provider' => $provider,
            'request_data' => $request,
            'response_data' => $response,
            'is_eligible' => $response['eligible'] ?? false,
            'status' => $response['status'] ?? 'completed',
            'checked_at' => now()
        ]);
    }
}