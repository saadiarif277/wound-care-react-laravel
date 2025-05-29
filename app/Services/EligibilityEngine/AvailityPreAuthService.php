<?php

namespace App\Services\EligibilityEngine;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AvailityPreAuthService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private array $supportedPayers;

    public function __construct()
    {
        $this->baseUrl = config('services.availity.base_url');
        $this->clientId = config('services.availity.client_id');
        $this->clientSecret = config('services.availity.client_secret');
        $this->supportedPayers = $this->loadSupportedPayers();
    }

    /**
     * Submit prior authorization request using correct addressing
     */
    public function submitPreAuthorization(array $requestData): array
    {
        try {
            // Validate request data
            $this->validatePreAuthRequest($requestData);

            // Check if payer is supported
            if (!$this->isPayerSupported($requestData['payer_id'])) {
                return $this->createUnsupportedPayerResponse($requestData['payer_id']);
            }

            // Build pre-auth request with correct addressing
            $preAuthRequest = $this->buildPreAuthRequest($requestData);

            // Submit to Availity
            $response = $this->submitToAvailty($preAuthRequest);

            // Parse response
            $result = $this->parsePreAuthResponse($response);

            // Store tracking information
            $this->storePreAuthTracking($result, $requestData);

            return $result;

        } catch (\Exception $e) {
            Log::error('Availity pre-authorization submission failed', [
                'patient_id' => $requestData['patient_data']['member_id'] ?? null,
                'facility_id' => $requestData['facility_data']['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Check status of submitted pre-authorization
     */
    public function checkPreAuthStatus(string $authorizationId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json'
            ])->timeout(30)->get($this->baseUrl . "/authorizations/{$authorizationId}");

            if (!$response->successful()) {
                throw new \Exception("Availity API error: {$response->status()} - {$response->body()}");
            }

            return $this->parseStatusResponse($response->json());

        } catch (\Exception $e) {
            Log::error('Availity pre-auth status check failed', [
                'authorization_id' => $authorizationId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'checked_at' => Carbon::now()->toISOString()
            ];
        }
    }

    /**
     * Get list of supported payers for pre-authorization
     */
    public function getSupportedPayers(): array
    {
        return Cache::remember('availity_payers', 3600, function () {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json'
                ])->timeout(30)->get($this->baseUrl . '/payers');

                if ($response->successful()) {
                    return $response->json()['payers'] ?? [];
                }

                return $this->supportedPayers;

            } catch (\Exception $e) {
                Log::warning('Failed to fetch Availity payers', ['error' => $e->getMessage()]);
                return $this->supportedPayers;
            }
        });
    }

    /**
     * Build pre-authorization request with correct addressing
     */
    private function buildPreAuthRequest(array $requestData): array
    {
        $patientData = $requestData['patient_data'];
        $facilityData = $requestData['facility_data'];
        $serviceDetails = $requestData['service_details'];
        $eligibilityResults = $requestData['eligibility_results'] ?? [];
        $macValidation = $requestData['mac_validation'] ?? [];

        return [
            'submissionId' => 'MSC-PA-' . uniqid(),
            'submissionDate' => Carbon::now()->format('Y-m-d'),

            // Payer information
            'payer' => [
                'payerId' => $requestData['payer_id'],
                'payerName' => $requestData['payer_name']
            ],

            // Patient/Subscriber information (for MAC jurisdiction)
            'subscriber' => [
                'memberId' => $patientData['member_id'],
                'firstName' => $patientData['first_name'],
                'lastName' => $patientData['last_name'],
                'dateOfBirth' => $this->formatDate($patientData['dob']),
                'gender' => strtoupper(substr($patientData['gender'], 0, 1)),
                'address' => [
                    'address1' => $patientData['address'],
                    'city' => $patientData['city'],
                    'state' => $patientData['state'], // Used for MAC jurisdiction
                    'postalCode' => $patientData['zip']
                ]
            ],

            // Provider/Facility information (place of service)
            'provider' => [
                'npi' => $facilityData['npi'],
                'organizationName' => $facilityData['name'],
                'taxonomyCode' => $this->mapFacilityTypeToTaxonomy($facilityData['facility_type']),
                'serviceLocation' => [
                    'address' => $facilityData['address'],
                    'city' => $facilityData['city'],
                    'state' => $facilityData['state'],
                    'postalCode' => $facilityData['zip_code']
                ]
            ],

            // Service information
            'services' => $this->buildServiceDetails($serviceDetails, $facilityData),

            // Clinical information
            'clinicalInfo' => $this->buildClinicalInfo($requestData),

            // Supporting documentation
            'supportingDocuments' => $this->buildSupportingDocuments($requestData),

            // MAC validation information
            'macInformation' => [
                'jurisdiction' => $macValidation['mac_jurisdiction']['jurisdiction'] ?? null,
                'contractor' => $macValidation['mac_jurisdiction']['contractor'] ?? null,
                'addressing_method' => 'patient_address_for_mac',
                'place_of_service_code' => $macValidation['place_of_service']['pos_code'] ?? null
            ]
        ];
    }

    /**
     * Build service details with place of service
     */
    private function buildServiceDetails(array $serviceDetails, array $facilityData): array
    {
        return [
            'requestedServices' => array_map(function ($service) use ($facilityData) {
                return [
                    'procedureCode' => $service['procedure_code'],
                    'procedureDescription' => $service['description'] ?? '',
                    'diagnosisCode' => $service['diagnosis_code'] ?? '',
                    'serviceDate' => $this->formatDate($service['service_date']),
                    'placeOfService' => $this->mapFacilityTypeToPlaceOfService($facilityData['facility_type']),
                    'serviceLocation' => [
                        'name' => $facilityData['name'],
                        'address' => $facilityData['address'],
                        'city' => $facilityData['city'],
                        'state' => $facilityData['state'],
                        'zip' => $facilityData['zip_code']
                    ],
                    'quantity' => $service['quantity'] ?? 1,
                    'unitPrice' => $service['unit_price'] ?? null
                ];
            }, $serviceDetails['services'] ?? [])
        ];
    }

    /**
     * Build clinical information section
     */
    private function buildClinicalInfo(array $requestData): array
    {
        $clinicalData = $requestData['clinical_data'] ?? [];

        return [
            'diagnosis' => [
                'primaryDiagnosis' => $clinicalData['primary_diagnosis'] ?? '',
                'secondaryDiagnoses' => $clinicalData['secondary_diagnoses'] ?? []
            ],
            'clinicalHistory' => $clinicalData['clinical_history'] ?? '',
            'treatmentPlan' => $clinicalData['treatment_plan'] ?? '',
            'priorTreatments' => $clinicalData['prior_treatments'] ?? [],
            'woundAssessment' => $clinicalData['wound_assessment'] ?? []
        ];
    }

    /**
     * Build supporting documents section
     */
    private function buildSupportingDocuments(array $requestData): array
    {
        return [
            'physicianOrder' => $requestData['documents']['physician_order'] ?? null,
            'clinicalNotes' => $requestData['documents']['clinical_notes'] ?? null,
            'woundPhotos' => $requestData['documents']['wound_photos'] ?? [],
            'labResults' => $requestData['documents']['lab_results'] ?? null,
            'priorAuthHistory' => $requestData['documents']['prior_auth_history'] ?? null
        ];
    }

    /**
     * Submit request to Availity API
     */
    private function submitToAvailty(array $request): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json'
        ])->timeout(60)->post($this->baseUrl . '/authorizations', $request);

        if (!$response->successful()) {
            throw new \Exception("Availity API error: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Parse pre-authorization response
     */
    private function parsePreAuthResponse(array $response): array
    {
        return [
            'status' => 'submitted',
            'authorization_id' => $response['authorizationId'] ?? null,
            'submission_id' => $response['submissionId'] ?? null,
            'tracking_number' => $response['trackingNumber'] ?? null,
            'payer_reference' => $response['payerReference'] ?? null,
            'estimated_decision_date' => $response['estimatedDecisionDate'] ?? null,
            'submission_date' => $response['submissionDate'] ?? null,
            'status_details' => [
                'current_status' => $response['status'] ?? 'pending',
                'status_reason' => $response['statusReason'] ?? null,
                'next_steps' => $response['nextSteps'] ?? []
            ],
            'submitted_at' => Carbon::now()->toISOString(),
            'raw_response' => $response
        ];
    }

    /**
     * Parse status check response
     */
    private function parseStatusResponse(array $response): array
    {
        return [
            'authorization_id' => $response['authorizationId'] ?? null,
            'current_status' => $response['status'] ?? 'unknown',
            'decision' => $response['decision'] ?? null,
            'decision_date' => $response['decisionDate'] ?? null,
            'approval_number' => $response['approvalNumber'] ?? null,
            'denial_reason' => $response['denialReason'] ?? null,
            'additional_info_required' => $response['additionalInfoRequired'] ?? [],
            'expiration_date' => $response['expirationDate'] ?? null,
            'authorized_services' => $response['authorizedServices'] ?? [],
            'checked_at' => Carbon::now()->toISOString(),
            'raw_response' => $response
        ];
    }

    /**
     * Validate pre-authorization request
     */
    private function validatePreAuthRequest(array $requestData): void
    {
        $required = [
            'payer_id',
            'patient_data',
            'facility_data',
            'service_details'
        ];

        foreach ($required as $field) {
            if (!isset($requestData[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        // Validate patient data
        $patientRequired = ['member_id', 'first_name', 'last_name', 'dob', 'address', 'city', 'state', 'zip'];
        foreach ($patientRequired as $field) {
            if (!isset($requestData['patient_data'][$field])) {
                throw new \InvalidArgumentException("Required patient field '{$field}' is missing");
            }
        }

        // Validate facility data
        $facilityRequired = ['id', 'name', 'npi', 'address', 'city', 'state', 'zip_code'];
        foreach ($facilityRequired as $field) {
            if (!isset($requestData['facility_data'][$field])) {
                throw new \InvalidArgumentException("Required facility field '{$field}' is missing");
            }
        }
    }

    /**
     * Check if payer is supported
     */
    private function isPayerSupported(string $payerId): bool
    {
        return in_array(strtolower($payerId), array_map('strtolower', array_column($this->supportedPayers, 'id')));
    }

    /**
     * Load supported payers (would be from API or config)
     */
    private function loadSupportedPayers(): array
    {
        // This would typically come from the Availity payer list API
        return [
            ['id' => 'aetna', 'name' => 'Aetna'],
            ['id' => 'anthem', 'name' => 'Anthem'],
            ['id' => 'cigna', 'name' => 'Cigna'],
            ['id' => 'humana', 'name' => 'Humana'],
            ['id' => 'unitedhealthcare', 'name' => 'UnitedHealthcare'],
            // Add more payers as configured
        ];
    }

    /**
     * Map facility type to taxonomy code
     */
    private function mapFacilityTypeToTaxonomy(string $facilityType): string
    {
        return match(strtolower($facilityType)) {
            'hospital' => '282N00000X',
            'clinic' => '261QP2300X',
            'wound care center' => '261QP2300X',
            'ambulatory surgery center' => '261QA1903X',
            default => '261QP2300X'
        };
    }

    /**
     * Map facility type to place of service code
     */
    private function mapFacilityTypeToPlaceOfService(string $facilityType): string
    {
        return match(strtolower($facilityType)) {
            'hospital inpatient' => '21',
            'hospital outpatient' => '22',
            'clinic', 'wound care center' => '11',
            'ambulatory surgery center' => '24',
            default => '11'
        };
    }

    /**
     * Store pre-authorization tracking information
     */
    private function storePreAuthTracking(array $result, array $requestData): void
    {
        $trackingData = [
            'authorization_id' => $result['authorization_id'],
            'patient_member_id' => $requestData['patient_data']['member_id'],
            'facility_id' => $requestData['facility_data']['id'],
            'payer_id' => $requestData['payer_id'],
            'submission_date' => $result['submitted_at'],
            'status' => $result['status']
        ];

        Cache::put(
            "preauth_tracking_{$result['authorization_id']}",
            $trackingData,
            3600 * 24 * 30 // 30 days
        );
    }

    /**
     * Get access token for Availity API
     */
    private function getAccessToken(): string
    {
        return Cache::remember('availity_access_token', 3600, function () {
            $response = Http::asForm()->post($this->baseUrl . '/oauth2/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'authorization'
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to obtain Availity access token');
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Format date for API
     */
    private function formatDate(string $date): string
    {
        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * Create error response
     */
    private function createErrorResponse(string $message): array
    {
        return [
            'status' => 'error',
            'error_message' => $message,
            'authorization_id' => null,
            'submitted_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Create unsupported payer response
     */
    private function createUnsupportedPayerResponse(string $payerId): array
    {
        return [
            'status' => 'unsupported_payer',
            'payer_id' => $payerId,
            'message' => 'This payer is not supported by Availity pre-authorization service',
            'authorization_id' => null,
            'submitted_at' => Carbon::now()->toISOString()
        ];
    }
}
