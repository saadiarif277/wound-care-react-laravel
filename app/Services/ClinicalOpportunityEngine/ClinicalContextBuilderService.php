<?php

namespace App\Services\ClinicalOpportunityEngine;

use App\Models\Fhir\Patient;
use App\Models\Fhir\Condition;
use App\Models\Fhir\Observation;
use App\Models\Fhir\Encounter;
use App\Models\Order\Order;
use App\Models\Order\ProductRequest;
use App\Services\FhirService;
use App\Services\CmsCoverageApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

class ClinicalContextBuilderService
{
    protected $fhirService;
    protected $cmsService;

    public function __construct(
        FhirService $fhirService,
        CmsCoverageApiService $cmsService
    ) {
        $this->fhirService = $fhirService;
        $this->cmsService = $cmsService;
    }

    /**
     * Build comprehensive clinical context for a patient
     */
    public function buildPatientContext(string $patientId, array $options = []): array
    {
        try {
            $context = [
                'patient_id' => $patientId,
                'timestamp' => now()->toISOString(),
                'demographics' => $this->getPatientDemographics($patientId),
                'practitioner' => $this->fhirService->getLinkedPractitioner($patientId) ?? [],
                'clinical_data' => $this->getClinicalData($patientId),
                'care_history' => $this->getCareHistory($patientId),
                'risk_factors' => $this->calculateRiskFactors($patientId),
                'payer_context' => $this->getPayerContext($patientId),
                'care_gaps' => $this->identifyCareGaps($patientId),
                'quality_metrics' => $this->getQualityMetrics($patientId)
            ];

            Log::info('Practitioner data in context', [
                'patient_id' => $patientId,
                'name' => $context['practitioner']['name'][0]['text'] ?? 'missing',
                'npi' => collect($context['practitioner']['identifier'] ?? [])->firstWhere('system', 'http://hl7.org/fhir/sid/us-npi')['value'] ?? 'missing',
                'has_data' => !empty($context['practitioner']),
            ]);

            // Add order-specific context if provided
            if (isset($options['order_id'])) {
                $context['order_context'] = $this->getOrderContext($options['order_id']);
            }

            // Add product request context if provided
            if (isset($options['product_request_id'])) {
                $context['product_request_context'] = $this->getProductRequestContext($options['product_request_id']);
            }

            return $context;

        } catch (\Exception $e) {
            Log::error('Failed to build clinical context', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return $this->getMinimalContext($patientId);
        }
    }

    /**
     * Get patient demographics from FHIR
     */
    protected function getPatientDemographics(string $patientId): array
    {
        try {
            $patient = $this->fhirService->getPatientById($patientId);
            
            if (!$patient) {
                return ['status' => 'not_found'];
            }

            return [
                'age' => $this->calculateAge($patient['birthDate'] ?? null),
                'gender' => $patient['gender'] ?? 'unknown',
                'address' => $this->extractAddress($patient['address'] ?? []),
                'contact' => $this->extractContact($patient['telecom'] ?? []),
                'language' => $this->extractLanguage($patient),
                'insurance_type' => $this->extractInsuranceType($patient)
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to get patient demographics', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error'];
        }
    }

    /**
     * Get comprehensive clinical data
     */
    protected function getClinicalData(string $patientId): array
    {
        $clinicalData = [
            'conditions' => $this->getActiveConditions($patientId),
            'wound_data' => $this->getWoundData($patientId),
            'observations' => $this->getRecentObservations($patientId),
            'medications' => $this->getCurrentMedications($patientId),
            'allergies' => $this->getAllergies($patientId),
            'procedures' => $this->getRecentProcedures($patientId),
            'lab_results' => $this->getRecentLabResults($patientId)
        ];

        // Calculate clinical severity scores
        $clinicalData['severity_scores'] = $this->calculateSeverityScores($clinicalData);

        return $clinicalData;
    }

    /**
     * Get active conditions from FHIR
     */
    protected function getActiveConditions(string $patientId): array
    {
        try {
            // Use generic FHIR resource search via HTTP
            $azureFhirEndpoint = Config::get('services.azure.fhir_endpoint');
            $azureAccessToken = $this->getAzureAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$azureFhirEndpoint}/Condition", [
                'patient' => $patientId,
                'clinical-status' => 'active'
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to search conditions: " . $response->body());
            }

            $bundle = $response->json();
            
            return array_map(function($entry) {
                $condition = $entry['resource'];
                return [
                    'code' => $condition['code']['coding'][0]['code'] ?? null,
                    'display' => $condition['code']['coding'][0]['display'] ?? null,
                    'onset' => $condition['onsetDateTime'] ?? null,
                    'severity' => $condition['severity']['coding'][0]['display'] ?? null,
                    'category' => $this->categorizeCondition($condition)
                ];
            }, $bundle['entry'] ?? []);

        } catch (\Exception $e) {
            Log::warning('Failed to get active conditions', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get wound-specific data
     */
    protected function getWoundData(string $patientId): array
    {
        $woundObservations = $this->fhirService->searchObservations([
            'patient' => $patientId,
            'category' => 'wound-assessment',
            '_count' => 10,
            '_sort' => '-date'
        ]);

        $wounds = [];
        foreach ($woundObservations['entry'] ?? [] as $entry) {
            $observation = $entry['resource'];
            $wounds[] = [
                'type' => $this->extractWoundType($observation),
                'location' => $this->extractWoundLocation($observation),
                'size' => $this->extractWoundSize($observation),
                'depth' => $this->extractWoundDepth($observation),
                'duration' => $this->calculateWoundDuration($observation),
                'healing_progress' => $this->assessHealingProgress($observation)
            ];
        }

        return $wounds;
    }

    /**
     * Get care history
     */
    protected function getCareHistory(string $patientId): array
    {
        $history = [
            'encounters' => $this->getRecentEncounters($patientId),
            'hospitalizations' => $this->getHospitalizations($patientId),
            'er_visits' => $this->getERVisits($patientId),
            'wound_treatments' => $this->getWoundTreatmentHistory($patientId),
            'product_usage' => $this->getProductUsageHistory($patientId)
        ];

        // Calculate care utilization metrics
        $history['utilization_metrics'] = $this->calculateUtilizationMetrics($history);

        return $history;
    }

    /**
     * Calculate risk factors
     */
    protected function calculateRiskFactors(string $patientId): array
    {
        $clinicalData = $this->getClinicalData($patientId);
        $demographics = $this->getPatientDemographics($patientId);

        return [
            'diabetes_risk' => $this->assessDiabetesRisk($clinicalData),
            'vascular_risk' => $this->assessVascularRisk($clinicalData),
            'infection_risk' => $this->assessInfectionRisk($clinicalData),
            'non_healing_risk' => $this->assessNonHealingRisk($clinicalData),
            'readmission_risk' => $this->assessReadmissionRisk($clinicalData),
            'fall_risk' => $this->assessFallRisk($demographics, $clinicalData),
            'social_determinants' => $this->assessSocialDeterminants($demographics)
        ];
    }

    /**
     * Get payer context
     */
    protected function getPayerContext(string $patientId): array
    {
        // Get coverage information using direct HTTP call
        try {
            $azureFhirEndpoint = Config::get('services.azure.fhir_endpoint');
            $azureAccessToken = $this->getAzureAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$azureFhirEndpoint}/Coverage", [
                'patient' => $patientId,
                'status' => 'active'
            ]);

            $coverage = $response->successful() ? $response->json() : ['entry' => []];
            
            $context = [
                'primary_payer' => $this->extractPrimaryPayer($coverage),
                'secondary_payer' => $this->extractSecondaryPayer($coverage),
                'mac_jurisdiction' => $this->determineMACJurisdiction($patientId),
                'coverage_policies' => $this->getApplicablePolicies($coverage),
                'prior_auth_requirements' => $this->checkPriorAuthRequirements($coverage)
            ];

            return $context;
            
        } catch (\Exception $e) {
            Log::warning('Failed to get payer context', ['error' => $e->getMessage()]);
            return [
                'primary_payer' => [],
                'secondary_payer' => [],
                'mac_jurisdiction' => 'unknown',
                'coverage_policies' => [],
                'prior_auth_requirements' => []
            ];
        }
    }

    /**
     * Identify care gaps
     */
    protected function identifyCareGaps(string $patientId): array
    {
        $gaps = [];

        // Check for missing assessments
        $gaps['missing_assessments'] = $this->checkMissingAssessments($patientId);

        // Check for overdue procedures
        $gaps['overdue_procedures'] = $this->checkOverdueProcedures($patientId);

        // Check for medication adherence issues
        $gaps['medication_gaps'] = $this->checkMedicationGaps($patientId);

        // Check quality measure gaps
        $gaps['quality_measure_gaps'] = $this->checkQualityMeasureGaps($patientId);

        // Check preventive care gaps
        $gaps['preventive_care_gaps'] = $this->checkPreventiveCareGaps($patientId);

        return $gaps;
    }

    /**
     * Get quality metrics
     */
    protected function getQualityMetrics(string $patientId): array
    {
        return [
            'wound_healing_rate' => $this->calculateWoundHealingRate($patientId),
            'treatment_adherence' => $this->calculateTreatmentAdherence($patientId),
            'appointment_compliance' => $this->calculateAppointmentCompliance($patientId),
            'outcome_scores' => $this->calculateOutcomeScores($patientId),
            'patient_satisfaction' => $this->getPatientSatisfactionScores($patientId)
        ];
    }

    /**
     * Get order context
     */
    protected function getOrderContext(string $orderId): array
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            return ['status' => 'not_found'];
        }

        return [
            'order_type' => $order->type,
            'products' => $order->products->map(function($product) {
                return [
                    'name' => $product->name,
                    'category' => $product->category,
                    'q_code' => $product->q_code
                ];
            }),
            'clinical_justification' => $order->clinical_justification,
            'mac_validation_status' => $order->mac_validation_status,
            'order_date' => $order->created_at->toISOString()
        ];
    }

    /**
     * Get product request context
     */
    protected function getProductRequestContext(string $productRequestId): array
    {
        $productRequest = ProductRequest::find($productRequestId);
        
        if (!$productRequest) {
            return ['status' => 'not_found'];
        }

        return [
            'wound_type' => $productRequest->wound_type,
            'wound_characteristics' => $productRequest->wound_characteristics,
            'clinical_summary' => $productRequest->clinical_summary,
            'request_date' => $productRequest->created_at->toISOString(),
            'urgency' => $productRequest->urgency ?? 'routine'
        ];
    }

    // Helper methods
    protected function calculateAge($birthDate): ?int
    {
        if (!$birthDate) return null;
        return Carbon::parse($birthDate)->age;
    }

    protected function extractAddress($addresses): array
    {
        if (empty($addresses)) return [];
        
        $primary = null;
        foreach ($addresses as $address) {
            if (isset($address['use']) && $address['use'] === 'home') {
                $primary = $address;
                break;
            }
        }
        
        if (!$primary && !empty($addresses)) {
            $primary = $addresses[0];
        }
        
        return [
            'city' => $primary['city'] ?? null,
            'state' => $primary['state'] ?? null,
            'postal_code' => $primary['postalCode'] ?? null
        ];
    }

    protected function extractContact($telecoms): array
    {
        $contact = [];
        
        foreach ($telecoms as $telecom) {
            if ($telecom['system'] === 'phone' && !isset($contact['phone'])) {
                $contact['phone'] = $telecom['value'];
            }
            if ($telecom['system'] === 'email' && !isset($contact['email'])) {
                $contact['email'] = $telecom['value'];
            }
        }
        
        return $contact;
    }

    protected function extractLanguage($patient): ?string
    {
        return $patient['communication'][0]['language']['coding'][0]['display'] ?? null;
    }

    protected function extractInsuranceType($patient): ?string
    {
        // This would be extracted from coverage resources
        return 'Medicare'; // Placeholder
    }

    protected function categorizeCondition($condition): string
    {
        $code = $condition['code']['coding'][0]['code'] ?? '';
        
        // ICD-10 code categorization
        if (str_starts_with($code, 'E11')) return 'diabetes';
        if (str_starts_with($code, 'I70')) return 'vascular';
        if (str_starts_with($code, 'L89')) return 'pressure_injury';
        if (str_starts_with($code, 'L97')) return 'ulcer';
        
        return 'other';
    }

    protected function getMinimalContext(string $patientId): array
    {
        return [
            'patient_id' => $patientId,
            'timestamp' => now()->toISOString(),
            'status' => 'minimal_data',
            'demographics' => ['status' => 'limited'],
            'clinical_data' => ['status' => 'limited']
        ];
    }

    // Additional helper methods would be implemented here...
    protected function getRecentObservations(string $patientId): array { return []; }
    protected function getCurrentMedications(string $patientId): array { return []; }
    protected function getAllergies(string $patientId): array { return []; }
    protected function getRecentProcedures(string $patientId): array { return []; }
    protected function getRecentLabResults(string $patientId): array { return []; }
    protected function calculateSeverityScores(array $clinicalData): array { return []; }
    protected function extractWoundType($observation): string { return 'unknown'; }
    protected function extractWoundLocation($observation): string { return 'unknown'; }
    protected function extractWoundSize($observation): array { return []; }
    protected function extractWoundDepth($observation): ?float { return null; }
    protected function calculateWoundDuration($observation): ?int { return null; }
    protected function assessHealingProgress($observation): string { return 'unknown'; }
    protected function getRecentEncounters(string $patientId): array { return []; }
    protected function getHospitalizations(string $patientId): array { return []; }
    protected function getERVisits(string $patientId): array { return []; }
    protected function getWoundTreatmentHistory(string $patientId): array { return []; }
    protected function getProductUsageHistory(string $patientId): array { return []; }
    protected function calculateUtilizationMetrics(array $history): array { return []; }
    protected function assessDiabetesRisk(array $clinicalData): float { return 0.0; }
    protected function assessVascularRisk(array $clinicalData): float { return 0.0; }
    protected function assessInfectionRisk(array $clinicalData): float { return 0.0; }
    protected function assessNonHealingRisk(array $clinicalData): float { return 0.0; }
    protected function assessReadmissionRisk(array $clinicalData): float { return 0.0; }
    protected function assessFallRisk(array $demographics, array $clinicalData): float { return 0.0; }
    protected function assessSocialDeterminants(array $demographics): array { return []; }
    protected function extractPrimaryPayer($coverage): array { return []; }
    protected function extractSecondaryPayer($coverage): array { return []; }
    protected function determineMACJurisdiction(string $patientId): string { return 'unknown'; }
    protected function getApplicablePolicies($coverage): array { return []; }
    protected function checkPriorAuthRequirements($coverage): array { return []; }
    protected function checkMissingAssessments(string $patientId): array { return []; }
    protected function checkOverdueProcedures(string $patientId): array { return []; }
    protected function checkMedicationGaps(string $patientId): array { return []; }
    protected function checkQualityMeasureGaps(string $patientId): array { return []; }
    protected function checkPreventiveCareGaps(string $patientId): array { return []; }
    protected function calculateWoundHealingRate(string $patientId): float { return 0.0; }
    protected function calculateTreatmentAdherence(string $patientId): float { return 0.0; }
    protected function calculateAppointmentCompliance(string $patientId): float { return 0.0; }
    protected function calculateOutcomeScores(string $patientId): array { return []; }
    protected function getPatientSatisfactionScores(string $patientId): array { return []; }

    /**
     * Get Azure access token for FHIR API
     */
    private function getAzureAccessToken(): string
    {
        $tenantId = Config::get('services.azure.tenant_id');
        $clientId = Config::get('services.azure.client_id');
        $clientSecret = Config::get('services.azure.client_secret');
        $azureFhirEndpoint = Config::get('services.azure.fhir_endpoint');

        if (!$tenantId || !$clientId || !$clientSecret || !$azureFhirEndpoint) {
            throw new \Exception('Azure FHIR configuration not complete');
        }

        $response = Http::asForm()->post('https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => $azureFhirEndpoint . '/.default'
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to get Azure access token: " . $response->body());
        }

        $tokenData = $response->json();
        return $tokenData['access_token'];
    }
}