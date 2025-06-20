<?php

namespace App\Services\Eligibility\Providers;

use App\Services\AvailityEligibilityService;
use Exception;

class AvailityProvider implements EligibilityProviderInterface
{
    private AvailityEligibilityService $availityService;
    
    public function __construct(AvailityEligibilityService $availityService)
    {
        $this->availityService = $availityService;
    }
    
    public function getName(): string
    {
        return 'availity';
    }
    
    public function supportsPayer(?string $payerId, ?string $payerName): bool
    {
        // Check Availity's payer list
        $supportedPayers = config('availity.supported_payers', []);
        
        if ($payerId && in_array($payerId, $supportedPayers)) {
            return true;
        }
        
        // Check by payer name patterns
        if ($payerName) {
            $patterns = ['Blue Cross', 'Aetna', 'Cigna', 'United Healthcare'];
            foreach ($patterns as $pattern) {
                if (stripos($payerName, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    public function checkEligibility(array $request): array
    {
        // Transform request to Availity format
        $availityRequest = $this->transformRequest($request);
        
        // Call existing Availity service
        $response = $this->availityService->checkEligibility($availityRequest);
        
        // Transform response to unified format
        return $this->transformResponse($response);
    }
    
    public function getConfig(): array
    {
        return [
            'endpoint' => config('availity.endpoint'),
            'version' => config('availity.version', 'v1'),
            'timeout' => config('availity.timeout', 30)
        ];
    }
    
    public function testConnection(): bool
    {
        try {
            // Implement health check
            return $this->availityService->healthCheck();
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function transformRequest(array $request): array
    {
        return [
            'subscriber_id' => $request['member_id'] ?? null,
            'patient' => [
                'first_name' => $request['patient_first_name'] ?? null,
                'last_name' => $request['patient_last_name'] ?? null,
                'dob' => $request['patient_dob'] ?? null,
            ],
            'provider' => [
                'npi' => $request['provider_npi'] ?? null,
            ],
            'payer_id' => $request['payer_id'] ?? null,
        ];
    }
    
    private function transformResponse(array $response): array
    {
        return [
            'eligible' => $response['eligible'] ?? false,
            'status' => $response['status'] ?? 'unknown',
            'coverage_details' => $response['coverage'] ?? [],
            'copay' => $response['copay'] ?? null,
            'deductible' => $response['deductible'] ?? null,
            'out_of_pocket_max' => $response['out_of_pocket_max'] ?? null,
            'raw_response' => $response
        ];
    }
}