<?php

namespace App\Services\EligibilityEngine;

use App\Models\Order\ProductRequest;
use App\Models\Insurance\PreAuthorization;
use App\Models\Fhir\Facility;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AvailityServiceReviewsService
{
    private string $apiBaseUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->apiBaseUrl = config('availity.service_reviews_base_url', 'https://api.availity.com/availity/development-partner/v2');
        $this->clientId = config('availity.client_id');
        $this->clientSecret = config('availity.client_secret');
    }

    /**
     * Submit a service review (pre-authorization) request to Availity
     */
    public function submitServiceReview(ProductRequest $productRequest, array $additionalData = []): array
    {
        Log::info('Starting Availity Service Review submission', ['request_id' => $productRequest->id]);

        try {
            // Build the service review payload
            $payload = $this->buildServiceReviewRequest($productRequest, $additionalData);

            // Send the request to Availity
            $response = $this->sendServiceReviewRequest($payload);

            // Process the response
            $serviceReviewResult = $this->processServiceReviewResponse($response);

            // Create local pre-authorization record
            $preAuth = $this->createPreAuthorizationRecord($productRequest, $serviceReviewResult);

            Log::info('Availity Service Review submitted successfully', [
                'request_id' => $productRequest->id,
                'service_review_id' => $serviceReviewResult['id'],
                'pre_auth_id' => $preAuth->id
            ]);

            return [
                'success' => true,
                'service_review' => $serviceReviewResult,
                'pre_authorization' => $preAuth,
                'status' => 'submitted'
            ];

        } catch (\Exception $e) {
            Log::error('Availity Service Review submission failed', [
                'request_id' => $productRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Check the status of a service review
     */
    public function checkServiceReviewStatus(string $serviceReviewId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->get($this->apiBaseUrl . "/service-reviews/{$serviceReviewId}");

            if (!$response->successful()) {
                throw new \Exception('Availity Service Review status check failed: ' . $response->status() . ' - ' . $response->body());
            }

            return $this->processServiceReviewResponse($response->json());

        } catch (\Exception $e) {
            Log::error('Service review status check failed', [
                'service_review_id' => $serviceReviewId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing service review
     */
    public function updateServiceReview(string $serviceReviewId, array $updateData): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->put($this->apiBaseUrl . "/service-reviews", $updateData);

            if (!$response->successful()) {
                throw new \Exception('Availity Service Review update failed: ' . $response->status() . ' - ' . $response->body());
            }

            return $this->processServiceReviewResponse($response->json());

        } catch (\Exception $e) {
            Log::error('Service review update failed', [
                'service_review_id' => $serviceReviewId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Void a service review
     */
    public function voidServiceReview(string $serviceReviewId): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->delete($this->apiBaseUrl . "/service-reviews/{$serviceReviewId}");

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Service review void failed', [
                'service_review_id' => $serviceReviewId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Build service review request payload
     */
    private function buildServiceReviewRequest(ProductRequest $productRequest, array $additionalData): array
    {
        $productRequest->load(['provider', 'facility', 'products']);

        // Get patient data from eligibility results or FHIR
        $patientData = $this->getPatientDataFromEligibility($productRequest);

        // Get payer information from eligibility results
        $payerData = $this->getPayerDataFromEligibility($productRequest);

        return [
            'payer' => [
                'id' => $payerData['id'] ?? $productRequest->payer_id,
                'name' => $payerData['name'] ?? $productRequest->payer_name_submitted,
            ],
            'requestingProvider' => $this->buildRequestingProviderData($productRequest),
            'subscriber' => $this->buildSubscriberData($patientData),
            'patient' => $this->buildPatientData($patientData),
            'diagnoses' => $this->buildDiagnosesData($productRequest, $additionalData),
            'procedures' => $this->buildProceduresData($productRequest),
            'requestType' => 'AR', // Authorization Request
            'requestTypeCode' => 'AR',
            'serviceType' => 'DME', // Durable Medical Equipment
            'serviceTypeCode' => '30',
            'placeOfService' => $this->getPlaceOfServiceFromFacility($productRequest->facility),
            'placeOfServiceCode' => $this->getPlaceOfServiceCodeFromFacility($productRequest->facility),
            'fromDate' => $productRequest->expected_service_date->format('Y-m-d'),
            'toDate' => $productRequest->expected_service_date->format('Y-m-d'),
            'quantity' => $this->calculateTotalQuantity($productRequest),
            'quantityType' => 'Units',
            'quantityTypeCode' => 'UN',
            'certificationIssueDate' => now()->format('Y-m-d'),
            'urgency' => $additionalData['urgency'] ?? 'routine',
            'providerNotes' => $this->buildProviderNotes($productRequest, $additionalData),
        ];
    }

    /**
     * Build requesting provider data
     */
    private function buildRequestingProviderData(ProductRequest $productRequest): array
    {
        $provider = $productRequest->provider;
        $facility = $productRequest->facility;

        return [
            'lastName' => $provider->last_name,
            'firstName' => $provider->first_name,
            'middleName' => $provider->middle_name,
            'npi' => $provider->npi_number,
            'specialty' => $provider->specialty,
            'specialtyCode' => $provider->specialty_code,
            'addressLine1' => $facility->address,
            'addressLine2' => $facility->address_line_2,
            'city' => $facility->city,
            'state' => $facility->state,
            'stateCode' => $facility->state,
            'zipCode' => $facility->zip,
            'contactName' => $provider->first_name . ' ' . $provider->last_name,
            'phone' => $facility->phone ?? $provider->phone,
            'fax' => $facility->fax,
            'emailAddress' => $provider->email,
        ];
    }

    /**
     * Build subscriber data from eligibility results
     */
    private function buildSubscriberData(array $patientData): array
    {
        return [
            'firstName' => $patientData['first_name'] ?? '',
            'lastName' => $patientData['last_name'] ?? '',
            'middleName' => $patientData['middle_name'] ?? '',
            'memberId' => $patientData['member_id'] ?? '',
            'addressLine1' => $patientData['address'] ?? '',
            'city' => $patientData['city'] ?? '',
            'state' => $patientData['state'] ?? '',
            'stateCode' => $patientData['state'] ?? '',
            'zipCode' => $patientData['zip'] ?? '',
        ];
    }

    /**
     * Build patient data
     */
    private function buildPatientData(array $patientData): array
    {
        return [
            'firstName' => $patientData['first_name'] ?? '',
            'lastName' => $patientData['last_name'] ?? '',
            'middleName' => $patientData['middle_name'] ?? '',
            'birthDate' => $patientData['dob'] ?? '',
            'gender' => $this->mapGender($patientData['gender'] ?? ''),
            'genderCode' => $this->mapGenderCode($patientData['gender'] ?? ''),
            'subscriberRelationship' => $patientData['relationship_to_subscriber'] ?? 'Self',
            'subscriberRelationshipCode' => $patientData['relationship_code'] ?? '18',
        ];
    }

    /**
     * Build diagnoses data from clinical assessment
     */
    private function buildDiagnosesData(ProductRequest $productRequest, array $additionalData): array
    {
        $diagnoses = [];

        // Get diagnoses from clinical summary or additional data
        $clinicalSummary = $productRequest->clinical_summary ?? [];
        $primaryDiagnosis = $additionalData['primary_diagnosis'] ?? $clinicalSummary['primary_diagnosis'] ?? null;
        $secondaryDiagnoses = $additionalData['secondary_diagnoses'] ?? $clinicalSummary['secondary_diagnoses'] ?? [];

        if ($primaryDiagnosis) {
            $diagnoses[] = [
                'qualifier' => 'Primary',
                'qualifierCode' => 'ABK',
                'code' => $primaryDiagnosis['code'] ?? '',
                'value' => $primaryDiagnosis['description'] ?? '',
                'date' => $primaryDiagnosis['date'] ?? now()->format('Y-m-d'),
            ];
        }

        foreach ($secondaryDiagnoses as $diagnosis) {
            $diagnoses[] = [
                'qualifier' => 'Secondary',
                'qualifierCode' => 'ABF',
                'code' => $diagnosis['code'] ?? '',
                'value' => $diagnosis['description'] ?? '',
                'date' => $diagnosis['date'] ?? now()->format('Y-m-d'),
            ];
        }

        return $diagnoses;
    }

    /**
     * Build procedures data from product selection
     */
    private function buildProceduresData(ProductRequest $productRequest): array
    {
        $procedures = [];

        foreach ($productRequest->products as $product) {
            $procedures[] = [
                'qualifier' => 'Healthcare Common Procedure Coding System',
                'qualifierCode' => 'HC',
                'code' => $product->q_code ?? $product->cpt_code ?? '',
                'value' => $product->name,
                'description' => $product->description ?? $product->name,
                'quantity' => (string) $product->pivot->quantity,
                'quantityType' => 'Units',
                'quantityTypeCode' => 'UN',
                'fromDate' => $productRequest->expected_service_date->format('Y-m-d'),
                'toDate' => $productRequest->expected_service_date->format('Y-m-d'),
            ];
        }

        return $procedures;
    }

    /**
     * Build provider notes
     */
    private function buildProviderNotes(ProductRequest $productRequest, array $additionalData): array
    {
        $notes = [];

        // Clinical justification
        if ($clinicalJustification = $additionalData['clinical_justification'] ?? null) {
            $notes[] = [
                'type' => 'Clinical Justification',
                'typeCode' => 'CER',
                'message' => $clinicalJustification,
            ];
        }

        // Wound assessment summary
        if ($woundAssessment = $additionalData['wound_assessment'] ?? null) {
            $notes[] = [
                'type' => 'Clinical Information',
                'typeCode' => 'ADD',
                'message' => $woundAssessment,
            ];
        }

        // Treatment history
        if ($treatmentHistory = $additionalData['treatment_history'] ?? null) {
            $notes[] = [
                'type' => 'Treatment History',
                'typeCode' => 'DCP',
                'message' => $treatmentHistory,
            ];
        }

        return $notes;
    }

    /**
     * Get patient data from eligibility results or fallback
     */
    private function getPatientDataFromEligibility(ProductRequest $productRequest): array
    {
        $eligibilityResults = $productRequest->eligibility_results ?? [];

        // Try to get patient data from eligibility results
        if (isset($eligibilityResults['subscriber'])) {
            return [
                'first_name' => $eligibilityResults['subscriber']['firstName'] ?? '',
                'last_name' => $eligibilityResults['subscriber']['lastName'] ?? '',
                'middle_name' => $eligibilityResults['subscriber']['middleName'] ?? '',
                'member_id' => $eligibilityResults['subscriber']['memberId'] ?? '',
                'dob' => $eligibilityResults['patient']['birthDate'] ?? '',
                'gender' => $eligibilityResults['patient']['gender'] ?? '',
                'address' => $eligibilityResults['subscriber']['addressLine1'] ?? '',
                'city' => $eligibilityResults['subscriber']['city'] ?? '',
                'state' => $eligibilityResults['subscriber']['state'] ?? '',
                'zip' => $eligibilityResults['subscriber']['zipCode'] ?? '',
            ];
        }

        // Fallback - would need to get from FHIR in real implementation
        return [
            'first_name' => 'Patient',
            'last_name' => 'Name',
            'member_id' => 'MEMBER_ID_PLACEHOLDER',
            'dob' => '1980-01-01',
            'gender' => 'unknown',
        ];
    }

    /**
     * Get payer data from eligibility results
     */
    private function getPayerDataFromEligibility(ProductRequest $productRequest): array
    {
        $eligibilityResults = $productRequest->eligibility_results ?? [];

        return [
            'id' => $eligibilityResults['payer']['id'] ?? $productRequest->payer_id,
            'name' => $eligibilityResults['payer']['name'] ?? $productRequest->payer_name_submitted,
        ];
    }

    /**
     * Get place of service from facility
     */
    private function getPlaceOfServiceFromFacility(?Facility $facility): string
    {
        if (!$facility) {
            return 'Office';
        }

        return match(strtolower($facility->facility_type ?? '')) {
            'hospital' => 'Hospital Outpatient',
            'clinic' => 'Office',
            'wound care center' => 'Office',
            'ambulatory surgery center' => 'Ambulatory Surgical Center',
            default => 'Office'
        };
    }

    /**
     * Get place of service code from facility
     */
    private function getPlaceOfServiceCodeFromFacility(?Facility $facility): string
    {
        if (!$facility) {
            return '11';
        }

        return match(strtolower($facility->facility_type ?? '')) {
            'hospital' => '22',
            'clinic' => '11',
            'wound care center' => '11',
            'ambulatory surgery center' => '24',
            default => '11'
        };
    }

    /**
     * Calculate total quantity from products
     */
    private function calculateTotalQuantity(ProductRequest $productRequest): string
    {
        $totalQuantity = $productRequest->products->sum(function ($product) {
            return $product->pivot->quantity ?? 1;
        });

        return (string) $totalQuantity;
    }

    /**
     * Map gender to string
     */
    private function mapGender(string $gender): string
    {
        return match(strtolower($gender)) {
            'male', 'm' => 'Male',
            'female', 'f' => 'Female',
            default => 'Unknown'
        };
    }

    /**
     * Map gender to code
     */
    private function mapGenderCode(string $gender): string
    {
        return match(strtolower($gender)) {
            'male', 'm' => 'M',
            'female', 'f' => 'F',
            default => 'U'
        };
    }

    /**
     * Send service review request to Availity API
     */
    private function sendServiceReviewRequest(array $payload): array
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])
        ->timeout(60)
        ->post($this->apiBaseUrl . '/service-reviews', $payload);

        if (!$response->successful()) {
            throw new \Exception('Availity Service Review API request failed: ' . $response->status() . ' - ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Process service review response from Availity
     */
    private function processServiceReviewResponse(array $response): array
    {
        return [
            'id' => $response['id'] ?? null,
            'control_number' => $response['controlNumber'] ?? null,
            'status' => $response['status'] ?? 'pending',
            'status_code' => $response['statusCode'] ?? null,
            'certification_number' => $response['certificationNumber'] ?? null,
            'certification_issue_date' => $response['certificationIssueDate'] ?? null,
            'certification_effective_date' => $response['certificationEffectiveDate'] ?? null,
            'certification_expiration_date' => $response['certificationExpirationDate'] ?? null,
            'reference_number' => $response['referenceNumber'] ?? null,
            'trace_numbers' => $response['traceNumbers'] ?? [],
            'payer_notes' => $response['payerNotes'] ?? [],
            'validation_messages' => $response['validationMessages'] ?? [],
            'updatable' => $response['updatable'] ?? false,
            'deletable' => $response['deletable'] ?? false,
            'raw_response' => $response,
            'processed_at' => now(),
        ];
    }

    /**
     * Create local pre-authorization record
     */
    private function createPreAuthorizationRecord(ProductRequest $productRequest, array $serviceReviewResult): PreAuthorization
    {
        // Extract codes from service review result and find matching database records
        $diagnosisCodes = $this->extractAndMapDiagnosisCodes($serviceReviewResult);
        $procedureCodes = $this->extractAndMapProcedureCodes($serviceReviewResult);

        $preAuthData = [
            'product_request_id' => $productRequest->id,
            'authorization_number' => $serviceReviewResult['certification_number'] ?? $serviceReviewResult['id'],
            'payer_name' => $productRequest->payer_name_submitted,
            'patient_id' => $productRequest->patient_display_id,
            'clinical_documentation' => $this->extractClinicalDocumentation($serviceReviewResult),
            'urgency' => 'routine',
            'status' => $this->mapServiceReviewStatusToPreAuthStatus($serviceReviewResult['status']),
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
            'payer_transaction_id' => $serviceReviewResult['id'],
            'payer_confirmation' => $serviceReviewResult['control_number'],
            'payer_response' => $serviceReviewResult,
            'estimated_approval_date' => $serviceReviewResult['certification_effective_date'] ?
                Carbon::parse($serviceReviewResult['certification_effective_date']) : null,
            'expires_at' => $serviceReviewResult['certification_expiration_date'] ?
                Carbon::parse($serviceReviewResult['certification_expiration_date']) : null,
        ];

        return PreAuthorization::createWithCodes($preAuthData, $diagnosisCodes, $procedureCodes);
    }

    /**
     * Extract diagnosis codes from service review response and map to database records
     */
    private function extractAndMapDiagnosisCodes(array $serviceReviewResult): array
    {
        $diagnoses = $serviceReviewResult['raw_response']['diagnoses'] ?? [];
        $mappedCodes = [];

        foreach ($diagnoses as $index => $diagnosis) {
            if (!isset($diagnosis['code'])) continue;

            // Find the ICD-10 code in our database
            $icd10Code = \App\Models\Medical\Icd10Code::where('code', $diagnosis['code'])
                                                     ->where('is_active', true)
                                                     ->first();

            if ($icd10Code) {
                $mappedCodes[] = [
                    'icd10_code_id' => $icd10Code->id,
                    'type' => $diagnosis['qualifier'] === 'Primary' ? 'primary' : 'secondary',
                    'sequence' => $index + 1,
                ];
            } else {
                // Log missing ICD-10 code for later addition to database
                Log::warning('ICD-10 code not found in database', [
                    'code' => $diagnosis['code'],
                    'description' => $diagnosis['value'] ?? 'Unknown',
                ]);
            }
        }

        return $mappedCodes;
    }

    /**
     * Extract procedure codes from service review response and map to database records
     */
    private function extractAndMapProcedureCodes(array $serviceReviewResult): array
    {
        $procedures = $serviceReviewResult['raw_response']['procedures'] ?? [];
        $mappedCodes = [];

        foreach ($procedures as $index => $procedure) {
            if (!isset($procedure['code'])) continue;

            // Find the CPT code in our database
            $cptCode = \App\Models\Medical\CptCode::where('code', $procedure['code'])
                                                 ->where('is_active', true)
                                                 ->first();

            if ($cptCode) {
                $mappedCodes[] = [
                    'cpt_code_id' => $cptCode->id,
                    'quantity' => (int) ($procedure['quantity'] ?? 1),
                    'modifier' => $procedure['modifier'] ?? null,
                    'sequence' => $index + 1,
                ];
            } else {
                // Log missing CPT code for later addition to database
                Log::warning('CPT code not found in database', [
                    'code' => $procedure['code'],
                    'description' => $procedure['description'] ?? $procedure['value'] ?? 'Unknown',
                ]);
            }
        }

        return $mappedCodes;
    }

    /**
     * Extract clinical documentation from service review response
     */
    private function extractClinicalDocumentation(array $serviceReviewResult): string
    {
        $notes = $serviceReviewResult['payer_notes'] ?? [];
        return implode("\n\n", array_column($notes, 'message'));
    }

    /**
     * Map service review status to pre-authorization status
     */
    private function mapServiceReviewStatusToPreAuthStatus(string $status): string
    {
        return match(strtolower($status)) {
            'approved', 'certified' => 'approved',
            'denied', 'rejected' => 'denied',
            'pending', 'submitted' => 'pending',
            'cancelled', 'voided' => 'cancelled',
            default => 'pending'
        };
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
}
