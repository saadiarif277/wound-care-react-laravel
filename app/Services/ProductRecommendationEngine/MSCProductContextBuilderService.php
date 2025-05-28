<?php

namespace App\Services\ProductRecommendationEngine;

use App\Models\Order\ProductRequest;
use App\Services\PatientService;
use Illuminate\Support\Facades\Log;

class MSCProductContextBuilderService
{
    protected PatientService $patientService;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }

    /**
     * Build comprehensive context for product recommendations
     */
    public function buildProductContext(ProductRequest $productRequest): array
    {
        try {
            return [
                'product_request_id' => $productRequest->id,
                'wound_type' => $productRequest->wound_type,
                'wound_characteristics' => $this->buildWoundCharacteristics($productRequest),
                'clinical_data' => $this->buildClinicalData($productRequest),
                'patient_factors' => $this->buildPatientFactors($productRequest),
                'payer_context' => $this->buildPayerContext($productRequest),
                'mac_validation_status' => $productRequest->mac_validation_status ?? 'not_checked',
                'prior_treatments' => $this->buildPriorTreatments($productRequest),
                'facility_context' => $this->buildFacilityContext($productRequest),
                'provider_context' => $this->buildProviderContext($productRequest)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to build product context', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            // Return minimal context as fallback
            return [
                'product_request_id' => $productRequest->id,
                'wound_type' => $productRequest->wound_type,
                'wound_characteristics' => [],
                'clinical_data' => [],
                'patient_factors' => [],
                'payer_context' => [],
                'mac_validation_status' => 'not_checked',
                'prior_treatments' => [],
                'facility_context' => [],
                'provider_context' => []
            ];
        }
    }

    /**
     * Build wound characteristics from clinical summary
     */
    protected function buildWoundCharacteristics(ProductRequest $productRequest): array
    {
        $clinicalSummary = $productRequest->clinical_summary ?? [];
        $woundDetails = $clinicalSummary['wound_characteristics'] ?? [];

        return [
            'wound_type' => $productRequest->wound_type,
            'wound_depth' => $woundDetails['depth'] ?? 'unknown',
            'wound_stage' => $woundDetails['stage'] ?? null,
            'wound_size_cm2' => $woundDetails['size_cm2'] ?? null,
            'wound_length_cm' => $woundDetails['length_cm'] ?? null,
            'wound_width_cm' => $woundDetails['width_cm'] ?? null,
            'wound_depth_cm' => $woundDetails['depth_cm'] ?? null,
            'exposed_structures' => $woundDetails['exposed_structures'] ?? [],
            'exudate_level' => $woundDetails['exudate_level'] ?? 'moderate',
            'infection_status' => $woundDetails['infection_status'] ?? 'none',
            'wound_age_weeks' => $woundDetails['duration_weeks'] ?? null,
            'wagner_grade' => $woundDetails['wagner_grade'] ?? null,
            'ankle_brachial_index' => $woundDetails['abi'] ?? null,
            'circulation_status' => $woundDetails['circulation'] ?? 'unknown'
        ];
    }

    /**
     * Build clinical data context
     */
    protected function buildClinicalData(ProductRequest $productRequest): array
    {
        $clinicalSummary = $productRequest->clinical_summary ?? [];

        return [
            'conservative_care_provided' => $clinicalSummary['conservative_care_provided'] ?? [],
            'assessment_complete' => $clinicalSummary['assessment_complete'] ?? false,
            'clinical_notes' => $clinicalSummary['clinical_notes'] ?? '',
            'wound_bed_preparation' => $clinicalSummary['wound_bed_preparation'] ?? false,
            'debridement_performed' => $clinicalSummary['debridement_performed'] ?? false,
            'offloading_provided' => $clinicalSummary['offloading_provided'] ?? false,
            'compression_therapy' => $clinicalSummary['compression_therapy'] ?? false,
            'previous_treatments' => $clinicalSummary['previous_treatments'] ?? []
        ];
    }

    /**
     * Build patient factors from FHIR data
     */
    protected function buildPatientFactors(ProductRequest $productRequest): array
    {
        try {
            // Get patient data from PatientService (non-PHI)
            $patientData = $this->patientService->getPatientClinicalFactors(
                $productRequest->patient_fhir_id,
                $productRequest->facility_id
            );

            return [
                'age_range' => $patientData['age_range'] ?? 'unknown',
                'gender' => $patientData['gender'] ?? 'unknown',
                'diabetes_type' => $patientData['diabetes_type'] ?? null,
                'hba1c_level' => $patientData['hba1c_level'] ?? null,
                'comorbidities' => $patientData['comorbidities'] ?? [],
                'medications' => $patientData['medications'] ?? [],
                'allergies' => $patientData['allergies'] ?? [],
                'mobility_status' => $patientData['mobility_status'] ?? 'unknown',
                'nutrition_status' => $patientData['nutrition_status'] ?? 'unknown',
                'smoking_status' => $patientData['smoking_status'] ?? 'unknown',
                'immunocompromised' => $patientData['immunocompromised'] ?? false
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get patient factors', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return [
                'age_range' => 'unknown',
                'gender' => 'unknown',
                'comorbidities' => [],
                'medications' => [],
                'allergies' => []
            ];
        }
    }

    /**
     * Build payer context for cost considerations
     */
    protected function buildPayerContext(ProductRequest $productRequest): array
    {
        return [
            'payer_name' => $productRequest->payer_name_submitted,
            'payer_type' => $this->determinePayerType($productRequest->payer_name_submitted),
            'eligibility_status' => $productRequest->eligibility_status ?? 'unknown',
            'prior_auth_required' => $productRequest->pre_auth_required_determination === 'required',
            'coverage_details' => $productRequest->eligibility_results['coverage_details'] ?? [],
            'copay_amount' => $productRequest->eligibility_results['copay_amount'] ?? null,
            'deductible_remaining' => $productRequest->eligibility_results['deductible_remaining'] ?? null
        ];
    }

    /**
     * Build prior treatments context
     */
    protected function buildPriorTreatments(ProductRequest $productRequest): array
    {
        $clinicalSummary = $productRequest->clinical_summary ?? [];

        return [
            'previous_products_used' => $clinicalSummary['previous_products'] ?? [],
            'treatment_failures' => $clinicalSummary['treatment_failures'] ?? [],
            'response_to_treatment' => $clinicalSummary['treatment_response'] ?? 'unknown',
            'treatment_duration_weeks' => $clinicalSummary['treatment_duration'] ?? null
        ];
    }

    /**
     * Build facility context
     */
    protected function buildFacilityContext(ProductRequest $productRequest): array
    {
        $facility = $productRequest->facility;

        if (!$facility) {
            return [];
        }

        return [
            'facility_type' => $facility->type ?? 'unknown',
            'facility_capabilities' => $facility->capabilities ?? [],
            'wound_care_specialty' => $facility->wound_care_specialty ?? false,
            'geographic_region' => $facility->region ?? 'unknown'
        ];
    }

    /**
     * Build provider context
     */
    protected function buildProviderContext(ProductRequest $productRequest): array
    {
        $provider = $productRequest->provider;

        return [
            'provider_specialty' => $provider->specialty ?? 'unknown',
            'wound_care_experience' => $provider->wound_care_experience ?? 'unknown',
            'certification_level' => $provider->certifications ?? []
        ];
    }

    /**
     * Determine payer type from payer name
     */
    protected function determinePayerType(string $payerName): string
    {
        $payerName = strtolower($payerName);

        if (str_contains($payerName, 'medicare')) {
            return 'medicare';
        }

        if (str_contains($payerName, 'medicaid')) {
            return 'medicaid';
        }

        if (str_contains($payerName, 'aetna') || str_contains($payerName, 'cigna') ||
            str_contains($payerName, 'anthem') || str_contains($payerName, 'humana')) {
            return 'commercial';
        }

        return 'unknown';
    }
}
