<?php

namespace App\Services\EligibilityEngine;

use App\Models\Order\ProductRequest;
use App\Models\User;
use App\Models\Fhir\Facility;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

class AvailityEligibilityService
{
    private string $apiBaseUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->apiBaseUrl = config('availity.api_base_url', 'https://api.availity.com/availity/development-partner/v1');
        $this->clientId = config('availity.client_id');
        $this->clientSecret = config('availity.client_secret');
    }

    /**
     * Check eligibility for a product request using Availity Coverages API
     */
    public function checkEligibility(ProductRequest $productRequest): array
    {
        Log::info('Starting Availity eligibility check', ['request_id' => $productRequest->id]);

        try {
            // Build the coverage request payload
            $payload = $this->buildCoverageRequest($productRequest);

            // Send the request to Availity
            $response = $this->sendCoverageRequest($payload);

            // Process the response
            $eligibilityResult = $this->processCoverageResponse($response);

            Log::info('Availity eligibility check completed', [
                'request_id' => $productRequest->id,
                'status' => $eligibilityResult['status']
            ]);

            return $eligibilityResult;

        } catch (\Exception $e) {
            Log::error('Availity eligibility check failed', [
                'request_id' => $productRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Get coverage details by ID from Availity
     */
    public function getCoverageById(string $coverageId): array
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->get($this->apiBaseUrl . "/coverages/{$coverageId}");

            if (!$response->successful()) {
                throw new \Exception('Failed to retrieve coverage details: ' . $response->status() . ' - ' . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to get coverage by ID', [
                'coverage_id' => $coverageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build coverage request payload from ProductRequest
     */
    private function buildCoverageRequest(ProductRequest $productRequest): array
    {
        $provider = $productRequest->provider;
        $facility = $productRequest->facility;

        // Get patient data from FHIR (this would normally come from Azure FHIR)
        $patientData = $this->getPatientDataFromFhir($productRequest->patient_fhir_id);

        $payload = [
            // Payer information
            'payerId' => $productRequest->payer_id ?? $this->mapPayerNameToId($productRequest->payer_name_submitted),

            // Provider information
            'providerNpi' => $provider->npi_number,
            'providerFirstName' => $provider->first_name,
            'providerLastName' => $provider->last_name,
            'providerType' => $this->mapProviderType($provider),

            // Provider address from facility
            'providerCity' => $facility->city ?? '',
            'providerState' => $facility->state ?? '',
            'providerZipCode' => $facility->zip ?? '',

            // Service information
            'asOfDate' => now()->format('Y-m-d\TH:i:s\Z'),
            'serviceType' => $this->mapWoundTypeToServiceType($productRequest->wound_type),
            'procedureCode' => $this->getProcedureCodesForRequest($productRequest),

            // Patient information (from FHIR)
            'patientFirstName' => $patientData['first_name'] ?? '',
            'patientLastName' => $patientData['last_name'] ?? '',
            'patientBirthDate' => isset($patientData['birth_date']) ? Carbon::parse($patientData['birth_date'])->format('Y-m-d\TH:i:s\Z') : null,
            'patientGender' => $this->mapGender($patientData['gender'] ?? ''),
            'memberId' => $patientData['member_id'] ?? '',

            // Additional patient identifiers if available
            'patientSSN' => $patientData['ssn'] ?? null,
            'groupNumber' => $patientData['group_number'] ?? null,
        ];

        // Remove null values
        return array_filter($payload, function($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Send coverage request to Availity API
     */
    private function sendCoverageRequest(array $payload): array
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])
        ->timeout(30)
        ->asForm()
        ->post($this->apiBaseUrl . '/coverages', $payload);

        if (!$response->successful()) {
            throw new \Exception('Availity API request failed: ' . $response->status() . ' - ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Process coverage response from Availity
     */
    private function processCoverageResponse(array $response): array
    {
        $status = $this->determineEligibilityStatus($response);
        $benefits = $this->extractBenefitInformation($response);
        $priorAuthRequired = $this->checkPriorAuthRequirement($response);

        return [
            'status' => $status,
            'coverage_id' => $response['id'] ?? null,
            'control_number' => $response['controlNumber'] ?? null,
            'payer' => [
                'id' => $response['payer']['payerId'] ?? null,
                'name' => $response['payer']['name'] ?? null,
                'response_payer_id' => $response['payer']['responsePayerId'] ?? null,
                'response_name' => $response['payer']['responseName'] ?? null,
            ],
            'benefits' => $benefits,
            'prior_authorization_required' => $priorAuthRequired,
            'coverage_details' => $this->extractCoverageDetails($response),
            'validation_messages' => $response['validationMessages'] ?? [],
            'response_raw' => $response,
            'checked_at' => now(),
        ];
    }

    /**
     * Determine eligibility status from response
     */
    private function determineEligibilityStatus(array $response): string
    {
        $status = $response['status'] ?? '';
        $statusCode = $response['statusCode'] ?? '';

        // Map Availity status to our internal status
        if (stripos($status, 'active') !== false || $statusCode === '1') {
            return 'eligible';
        } elseif (stripos($status, 'inactive') !== false || $statusCode === '6') {
            return 'not_eligible';
        } elseif (stripos($status, 'pending') !== false) {
            return 'pending';
        } else {
            return 'needs_review';
        }
    }

    /**
     * Extract benefit information from response
     */
    private function extractBenefitInformation(array $response): array
    {
        $benefits = [];
        $plans = $response['plans'] ?? [];

        foreach ($plans as $plan) {
            // Extract copay, deductible, coinsurance from plan
            $planBenefits = [
                'plan_name' => $plan['description'] ?? null,
                'group_number' => $plan['groupNumber'] ?? null,
                'effective_date' => $plan['eligibilityStartDate'] ?? null,
                'termination_date' => $plan['eligibilityEndDate'] ?? null,
                'insurance_type' => $plan['insuranceType'] ?? null,
            ];

            $benefits[] = $planBenefits;
        }

        return [
            'plans' => $benefits,
            'copay_amount' => null, // Would need to be extracted from detailed benefit response
            'deductible_amount' => null,
            'coinsurance_percentage' => null,
            'out_of_pocket_max' => null,
        ];
    }

    /**
     * Check if prior authorization is required
     */
    private function checkPriorAuthRequirement(array $response): bool
    {
        $plans = $response['plans'] ?? [];

        foreach ($plans as $plan) {
            // Check for authorization requirements in the plan
            if (isset($plan['authorizationRequired']) && $plan['authorizationRequired']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract coverage details from response
     */
    private function extractCoverageDetails(array $response): array
    {
        return [
            'status' => $response['status'] ?? null,
            'status_code' => $response['statusCode'] ?? null,
            'as_of_date' => $response['asOfDate'] ?? null,
            'to_date' => $response['toDate'] ?? null,
            'subscriber' => $response['subscriber'] ?? null,
            'patient' => $response['patient'] ?? null,
            'requesting_provider' => $response['requestingProvider'] ?? null,
        ];
    }

    /**
     * Get access token for Availity API
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->isTokenValid()) {
            return $this->accessToken;
        }

        $response = Http::asForm()->post('https://api.availity.com/availity/v1/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'hipaa',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to obtain access token: ' . $response->body());
        }

        $tokenData = $response->json();
        $this->accessToken = $tokenData['access_token'];

        return $this->accessToken;
    }

    /**
     * Check if current token is valid
     */
    private function isTokenValid(): bool
    {
        // For simplicity, assume token expires in 1 hour
        // In production, you'd store the expiration time
        return false;
    }

    /**
     * Get patient data from FHIR server
     */
    private function getPatientDataFromFhir(string $patientFhirId): array
    {
        // This would integrate with your Azure FHIR service
        // For now, return mock data structure
        return [
            'first_name' => 'John', // This would come from FHIR
            'last_name' => 'Doe',
            'birth_date' => '1980-01-15',
            'gender' => 'male',
            'member_id' => 'MEMBER123',
            'ssn' => null, // Optional
            'group_number' => null, // Optional
        ];
    }

    /**
     * Map payer name to Availity payer ID
     */
    private function mapPayerNameToId(string $payerName): ?string
    {
        $payerMappings = config('availity.payer_mappings', [
            'Medicare' => 'MEDICARE',
            'Medicaid' => 'MEDICAID',
            'Aetna' => 'AETNA',
            'Blue Cross Blue Shield' => 'BCBS',
            'Humana' => 'HUMANA',
            'UnitedHealthcare' => 'UHC',
        ]);

        foreach ($payerMappings as $name => $id) {
            if (stripos($payerName, $name) !== false) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Map provider type for Availity
     */
    private function mapProviderType(User $provider): string
    {
        // Map based on provider specialty or default to physician
        return '1'; // 1 = Person, 2 = Non-Person Entity
    }

    /**
     * Map wound type to service type for Availity
     */
    private function mapWoundTypeToServiceType(string $woundType): string
    {
        $serviceTypeMappings = [
            'DFU' => '30', // Durable Medical Equipment
            'VLU' => '30',
            'PU' => '30',
            'TW' => '30',
            'AU' => '30',
            'OTHER' => '30',
        ];

        return $serviceTypeMappings[$woundType] ?? '30';
    }

    /**
     * Get procedure codes for the request
     */
    private function getProcedureCodesForRequest(ProductRequest $productRequest): array
    {
        $procedureCodes = [];

        // Add codes based on products selected
        $products = $productRequest->products ?? collect();
        foreach ($products as $product) {
            if (isset($product->q_code) && $product->q_code) {
                $procedureCodes[] = $product->q_code;
            }
        }

        // Add default wound care codes if none found
        if (empty($procedureCodes)) {
            $procedureCodes = ['Q4100', 'Q4101']; // Common skin substitute codes
        }

        return $procedureCodes;
    }

    /**
     * Map gender for Availity API
     */
    private function mapGender(string $gender): string
    {
        $genderMap = [
            'male' => 'M',
            'female' => 'F',
            'other' => 'U',
        ];

        return $genderMap[strtolower($gender)] ?? 'U';
    }
}
