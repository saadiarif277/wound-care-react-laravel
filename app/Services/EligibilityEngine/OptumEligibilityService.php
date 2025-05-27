<?php

namespace App\Services\EligibilityEngine;

use App\Models\Facility;
use App\Services\EligibilityEngine\EligibilityRequestValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class OptumEligibilityService
{
    private string $baseUrl;
    private string $apiKey;
    private string $clientId;
    private EligibilityRequestValidator $validator;
    private array $supportedPayers;

    public function __construct(EligibilityRequestValidator $validator)
    {
        $this->baseUrl = config('services.optum.eligibility_url');
        $this->apiKey = config('services.optum.api_key');
        $this->clientId = config('services.optum.client_id');
        $this->validator = $validator;
        $this->supportedPayers = $this->loadSupportedPayers();
    }

    /**
     * Check eligibility using facility address as place of service
     */
    public function checkEligibility(array $patientData, array $facilityData, array $serviceDetails): array
    {
        try {
            // Build eligibility request with facility as place of service
            $eligibilityRequest = $this->buildEligibilityRequest($patientData, $facilityData, $serviceDetails);

            // Validate request structure
            $this->validator->validate($eligibilityRequest);

            // Check if payer is supported
            if (!$this->isPayerSupported($patientData['insurance_id'] ?? '')) {
                return $this->createUnsupportedPayerResponse($patientData['insurance_id'] ?? '');
            }

            // Make API call to Optum
            $response = $this->makeEligibilityCall($eligibilityRequest);

            // Parse and format response
            $eligibilityResult = $this->parseEligibilityResponse($response);

            // Cache result for faster subsequent checks
            $this->cacheEligibilityResult($eligibilityResult, $patientData, $facilityData);

            return $eligibilityResult;

        } catch (\Exception $e) {
            Log::error('Optum eligibility check failed', [
                'patient_id' => $patientData['member_id'] ?? null,
                'facility_id' => $facilityData['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Build Optum V3 API eligibility request with facility address as place of service
     */
    private function buildEligibilityRequest(array $patientData, array $facilityData, array $serviceDetails): array
    {
        $controlNumber = $this->generateControlNumber();

        return [
            'controlNumber' => $controlNumber,
            'submitterTransactionIdentifier' => 'MSC-' . uniqid(),
            'tradingPartnerServiceId' => config('services.optum.trading_partner_id'),

            // Provider information using facility data as place of service
            'provider' => [
                'npi' => $facilityData['npi'],
                'organizationName' => $facilityData['name'],
                'providerCode' => $this->mapFacilityTypeToProviderCode($facilityData['facility_type']),
                'serviceLocation' => [
                    'address' => $facilityData['address'],
                    'city' => $facilityData['city'],
                    'state' => $facilityData['state'],
                    'postalCode' => $facilityData['zip_code']
                ]
            ],

            // Patient/Subscriber information
            'subscriber' => [
                'memberId' => $patientData['member_id'],
                'firstName' => $patientData['first_name'],
                'lastName' => $patientData['last_name'],
                'dateOfBirth' => $this->formatDateOfBirth($patientData['dob']),
                'gender' => strtoupper(substr($patientData['gender'], 0, 1)),
                'address' => [
                    'address1' => $patientData['address'] ?? $facilityData['address'],
                    'city' => $patientData['city'] ?? $facilityData['city'],
                    'state' => $patientData['state'] ?? $facilityData['state'],
                    'postalCode' => $patientData['zip'] ?? $facilityData['zip_code']
                ]
            ],

            // Service encounter information
            'encounter' => [
                'dateOfService' => $this->formatServiceDate($serviceDetails['expected_service_date']),
                'serviceTypeCodes' => $this->mapWoundTypeToServiceCodes($serviceDetails['wound_type']),
                'placeOfService' => $this->mapFacilityTypeToPlaceOfService($facilityData['facility_type']),
                'procedureCodes' => $serviceDetails['procedure_codes'] ?? []
            ]
        ];
    }

    /**
     * Make the actual API call to Optum
     */
    private function makeEligibilityCall(array $request): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
            'X-Client-ID' => $this->clientId
        ])->timeout(30)->post($this->baseUrl . '/eligibility/v3/check', $request);

        if (!$response->successful()) {
            throw new \Exception("Optum API error: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Parse Optum eligibility response into standardized format
     */
    private function parseEligibilityResponse(array $response): array
    {
        $transaction = $response['transactions'][0] ?? [];
        $eligibility = $transaction['eligibility'] ?? [];

        return [
            'status' => $this->mapEligibilityStatus($eligibility['status'] ?? 'unknown'),
            'control_number' => $response['controlNumber'] ?? null,
            'transaction_id' => $transaction['transactionId'] ?? null,

            // Coverage information
            'coverage' => [
                'is_covered' => ($eligibility['status'] ?? '') === 'eligible',
                'effective_date' => $eligibility['effectiveDate'] ?? null,
                'termination_date' => $eligibility['terminationDate'] ?? null,
                'plan_name' => $eligibility['planName'] ?? null,
                'group_number' => $eligibility['groupNumber'] ?? null
            ],

            // Benefits information
            'benefits' => $this->parseBenefits($eligibility['benefits'] ?? []),

            // Cost sharing information
            'cost_sharing' => $this->parseCostSharing($eligibility['costSharing'] ?? []),

            // Prior authorization requirements
            'prior_authorization' => [
                'required' => $this->isPriorAuthRequired($eligibility),
                'contact_info' => $eligibility['priorAuthContact'] ?? null,
                'turnaround_time' => $eligibility['authTurnaroundTime'] ?? null
            ],

            // Place of service validation
            'place_of_service' => [
                'covered' => $this->isPlaceOfServiceCovered($eligibility, $transaction['provider'] ?? []),
                'facility_type_supported' => true,
                'network_status' => $eligibility['networkStatus'] ?? 'unknown'
            ],

            // Raw response for debugging
            'raw_response' => $response,
            'checked_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Check if payer is supported by Optum
     */
    private function isPayerSupported(string $payerId): bool
    {
        return in_array(strtolower($payerId), array_map('strtolower', $this->supportedPayers));
    }

    /**
     * Load supported payers from CSV
     */
    private function loadSupportedPayers(): array
    {
        $csvPath = storage_path('app/data/optum-eligibility-list.csv');

        if (!file_exists($csvPath)) {
            Log::warning('Optum eligibility payer list not found', ['path' => $csvPath]);
            return [];
        }

        $payers = [];
        if (($handle = fopen($csvPath, 'r')) !== false) {
            // Skip header row
            fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== false) {
                if (isset($data[0])) {
                    $payers[] = trim($data[0]);
                }
            }
            fclose($handle);
        }

        return $payers;
    }

    /**
     * Map facility type to Optum provider code
     */
    private function mapFacilityTypeToProviderCode(string $facilityType): string
    {
        return match(strtolower($facilityType)) {
            'hospital' => 'H',
            'clinic' => 'PC',
            'wound care center' => 'PC',
            'outpatient facility' => 'PC',
            default => 'PC'
        };
    }

    /**
     * Map facility type to place of service code
     */
    private function mapFacilityTypeToPlaceOfService(string $facilityType): string
    {
        return match(strtolower($facilityType)) {
            'hospital' => '21', // Inpatient Hospital
            'hospital outpatient' => '22', // Outpatient Hospital
            'clinic' => '11', // Office
            'wound care center' => '11', // Office
            'ambulatory surgery center' => '24', // Ambulatory Surgical Center
            default => '11' // Office
        };
    }

    /**
     * Map wound type to service type codes
     */
    private function mapWoundTypeToServiceCodes(string $woundType): array
    {
        return match($woundType) {
            'DFU' => ['30'], // Medical Care
            'VLU' => ['30'], // Medical Care
            'PU' => ['30'], // Medical Care
            'TW' => ['1'], // Surgery
            'AU' => ['30'], // Medical Care
            default => ['30'] // Medical Care
        };
    }

    /**
     * Parse benefits from response
     */
    private function parseBenefits(array $benefits): array
    {
        $parsedBenefits = [];

        foreach ($benefits as $benefit) {
            $serviceType = $benefit['serviceType'] ?? 'unknown';
            $parsedBenefits[$serviceType] = [
                'covered' => ($benefit['status'] ?? '') === 'covered',
                'coverage_level' => $benefit['coverageLevel'] ?? null,
                'benefit_amount' => $benefit['benefitAmount'] ?? null,
                'limitations' => $benefit['limitations'] ?? []
            ];
        }

        return $parsedBenefits;
    }

    /**
     * Parse cost sharing information
     */
    private function parseCostSharing(array $costSharing): array
    {
        return [
            'deductible' => [
                'individual' => $costSharing['deductibleIndividual'] ?? null,
                'family' => $costSharing['deductibleFamily'] ?? null,
                'remaining' => $costSharing['deductibleRemaining'] ?? null
            ],
            'copay' => $costSharing['copay'] ?? null,
            'coinsurance' => $costSharing['coinsurance'] ?? null,
            'out_of_pocket_max' => [
                'individual' => $costSharing['oopMaxIndividual'] ?? null,
                'family' => $costSharing['oopMaxFamily'] ?? null,
                'remaining' => $costSharing['oopMaxRemaining'] ?? null
            ]
        ];
    }

    /**
     * Check if prior authorization is required
     */
    private function isPriorAuthRequired(array $eligibility): bool
    {
        return ($eligibility['priorAuthRequired'] ?? false) ||
               in_array('prior_auth_required', $eligibility['requirements'] ?? []);
    }

    /**
     * Check if place of service is covered
     */
    private function isPlaceOfServiceCovered(array $eligibility, array $provider): bool
    {
        $placeOfService = $provider['placeOfService'] ?? null;
        $coveredPlaces = $eligibility['coveredPlacesOfService'] ?? [];

        return empty($coveredPlaces) || in_array($placeOfService, $coveredPlaces);
    }

    /**
     * Generate control number for tracking
     */
    private function generateControlNumber(): string
    {
        return str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
    }

    /**
     * Format date of birth for API
     */
    private function formatDateOfBirth(string $dob): string
    {
        return Carbon::parse($dob)->format('Ymd');
    }

    /**
     * Format service date for API
     */
    private function formatServiceDate(string $serviceDate): string
    {
        return Carbon::parse($serviceDate)->format('Ymd');
    }

    /**
     * Map eligibility status to standardized format
     */
    private function mapEligibilityStatus(string $status): string
    {
        return match(strtolower($status)) {
            'eligible', 'active' => 'eligible',
            'not_eligible', 'inactive', 'terminated' => 'not_eligible',
            'pending', 'unknown' => 'needs_review',
            default => 'unknown'
        };
    }

    /**
     * Get access token for API calls
     */
    private function getAccessToken(): string
    {
        return Cache::remember('optum_access_token', 3600, function () {
            $response = Http::asForm()->post($this->baseUrl . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => config('services.optum.client_secret'),
                'scope' => 'eligibility'
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to obtain Optum access token');
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Cache eligibility result
     */
    private function cacheEligibilityResult(array $result, array $patientData, array $facilityData): void
    {
        $cacheKey = "eligibility_{$patientData['member_id']}_{$facilityData['id']}_" .
                   Carbon::parse($result['checked_at'])->format('Ymd');

        Cache::put($cacheKey, $result, 3600); // Cache for 1 hour
    }

    /**
     * Create error response
     */
    private function createErrorResponse(string $message): array
    {
        return [
            'status' => 'error',
            'error_message' => $message,
            'coverage' => ['is_covered' => false],
            'benefits' => [],
            'cost_sharing' => [],
            'prior_authorization' => ['required' => false],
            'place_of_service' => ['covered' => false],
            'checked_at' => Carbon::now()->toISOString()
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
            'message' => 'This payer is not supported by Optum eligibility service',
            'coverage' => ['is_covered' => null],
            'benefits' => [],
            'cost_sharing' => [],
            'prior_authorization' => ['required' => null],
            'place_of_service' => ['covered' => null],
            'checked_at' => Carbon::now()->toISOString()
        ];
    }
}
