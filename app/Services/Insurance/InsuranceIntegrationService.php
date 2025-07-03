<?php

namespace App\Services\Insurance;

use App\Services\Eligibility\UnifiedEligibilityService;
use App\Services\UnifiedFieldMappingService;
use App\Services\FhirDataLake\FhirAuditEventService;
use App\Services\FhirDataLake\InsuranceAnalyticsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InsuranceIntegrationService
{
    private InsuranceDataNormalizer $normalizer;
    private UnifiedFieldMappingService $fieldMappingService;
    private UnifiedEligibilityService $eligibilityService;
    private FhirAuditEventService $auditService;
    private InsuranceAnalyticsService $analyticsService;

    public function __construct(
        InsuranceDataNormalizer $normalizer,
        UnifiedFieldMappingService $fieldMappingService,
        UnifiedEligibilityService $eligibilityService,
        FhirAuditEventService $auditService,
        InsuranceAnalyticsService $analyticsService
    ) {
        $this->normalizer = $normalizer;
        $this->fieldMappingService = $fieldMappingService;
        $this->eligibilityService = $eligibilityService;
        $this->auditService = $auditService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Process insurance data from card scan
     */
    public function processInsuranceCard(string $patientId, array $ocrData, string $ocrProvider = 'azure_ocr'): array
    {
        // Log the insurance card scan
        $this->auditService->logInsuranceCardScan($patientId, $ocrData, $ocrProvider);

        // Normalize the OCR data
        $normalized = $this->normalizer->normalize($ocrData, 'insurance_card');

        // Check if we should run eligibility automatically
        if ($this->shouldAutoCheckEligibility($normalized)) {
            $eligibilityResult = $this->checkEligibility($normalized);
            $normalized['eligibility'] = $eligibilityResult;
        }

        // Map to canonical fields using UnifiedFieldMappingService
        $templateData = $this->fieldMappingService->mapFields($normalized, 'insurance_card');
        return [
            'normalized_data' => $normalized,
            'template_data' => $templateData,
            'confidence_score' => $normalized['_metadata']['confidence_score'] ?? 0,
            'requires_verification' => $this->requiresManualVerification($normalized)
        ];
    }

    /**
     * Process IVR submission with insurance data
     */
    public function processIVRSubmission(string $episodeId, array $submissionData, float $prefillPercentage): array
    {
        // Log the IVR completion
        $submissionId = data_get($submissionData, 'submission_id');
        $this->auditService->logIVRCompletion($episodeId, $submissionId, $prefillPercentage);

        // Normalize the submission data
        $normalized = $this->normalizer->normalize($submissionData, 'docuseal_ivr');

        // Check eligibility if we have enough data
        if ($this->hasRequiredEligibilityData($normalized)) {
            $eligibilityResult = $this->checkEligibility($normalized);

            // Store eligibility result for later use
            $this->cacheEligibilityResult($episodeId, $eligibilityResult);
        }

        return [
            'normalized_data' => $normalized,
            'eligibility_checked' => isset($eligibilityResult),
            'eligibility_result' => $eligibilityResult ?? null
        ];
    }

    /**
     * Process quick request with insurance data
     */
    public function processQuickRequest(array $requestData): array
    {
        // Normalize the request data
        $normalized = $this->normalizer->normalize($requestData, 'quick_request');

        // If we have existing insurance card data, merge it
        if ($patientId = data_get($requestData, 'patient_id')) {
            $cardData = $this->getLatestInsuranceCardData($patientId);
            if ($cardData) {
                $normalized = $this->normalizer->mergeFromMultipleSources([
                    'insurance_card' => $cardData,
                    'quick_request' => $requestData
                ]);
            }
        }

        // Check eligibility
        $eligibilityResult = $this->checkEligibility($normalized);

        // Prepare response
        return [
            'normalized_data' => $normalized,
            'eligibility' => $eligibilityResult,
            'template_data' => $this->fieldMappingService->mapFields($normalized, 'quick_request_form'),
            'recommendations' => $this->generateRecommendations($normalized, $eligibilityResult)
        ];
    }

    /**
     * Check eligibility using normalized data
     */
    private function checkEligibility(array $normalizedData): array
    {
        $coverageId = uniqid('coverage_');

        // Prepare eligibility request
        $eligibilityRequest = [
            'member_id' => $normalizedData['patient_member_id'] ?? '',
            'patient_first_name' => $normalizedData['patient_first_name'] ?? '',
            'patient_last_name' => $normalizedData['patient_last_name'] ?? '',
            'patient_dob' => $normalizedData['patient_dob'] ?? '',
            'payer_id' => $normalizedData['payer_id'] ?? '',
            'provider_npi' => $normalizedData['provider_npi'] ?? '',
            'coverage_id' => $coverageId
        ];

        // Check with cached results first
        $cacheKey = $this->generateEligibilityCacheKey($eligibilityRequest);
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // Check eligibility
        $result = $this->eligibilityService->checkEligibility($eligibilityRequest);

        // Cache successful results
        if ($result['success']) {
            Cache::put($cacheKey, $result, now()->addHours(24));
        }

        return $result;
    }

    /**
     * Get analytics insights for insurance data
     */
    public function getInsuranceAnalytics(string $patientId): array
    {
        return $this->analyticsService->getPatientInsuranceAnalytics($patientId);
    }

    /**
     * Get payer-specific insights
     */
    public function getPayerInsights(string $payerId): array
    {
        $analytics = $this->analyticsService->getPayerAnalytics($payerId);

        return [
            'common_rejection_reasons' => $analytics['rejection_patterns'] ?? [],
            'average_approval_time' => $analytics['avg_approval_time'] ?? null,
            'prior_auth_requirements' => $analytics['prior_auth_patterns'] ?? [],
            'best_submission_times' => $analytics['optimal_submission_times'] ?? [],
            'success_rate' => $analytics['success_rate'] ?? 0
        ];
    }

    /**
     * Generate recommendations based on insurance data
     */
    private function generateRecommendations(array $normalizedData, array $eligibilityResult): array
    {
        $recommendations = [];

        // Check if we need prior authorization
        if ($eligibilityResult['requires_prior_auth'] ?? false) {
            $recommendations[] = [
                'type' => 'prior_auth',
                'priority' => 'high',
                'message' => 'Prior authorization required for this payer',
                'action' => 'Submit prior auth request before proceeding'
            ];
        }

        // Check network status
        if (($normalizedData['network_status'] ?? '') === 'out_of_network') {
            $recommendations[] = [
                'type' => 'network',
                'priority' => 'medium',
                'message' => 'Provider is out of network',
                'action' => 'Consider in-network alternatives or verify patient cost share'
            ];
        }

        // Check deductible status
        $deductible = $eligibilityResult['deductible_amount'] ?? 0;
        $deductibleMet = $eligibilityResult['deductible_met'] ?? 0;
        if ($deductible > 0 && $deductibleMet < $deductible) {
            $remaining = $deductible - $deductibleMet;
            $recommendations[] = [
                'type' => 'deductible',
                'priority' => 'low',
                'message' => "Patient has $" . number_format($remaining, 2) . " remaining deductible",
                'action' => 'Inform patient of out-of-pocket responsibility'
            ];
        }

        return $recommendations;
    }

    /**
     * Helper methods
     */
    private function shouldAutoCheckEligibility(array $normalizedData): bool
    {
        // Auto-check if we have minimum required data
        return !empty($normalizedData['patient_member_id'])
            && !empty($normalizedData['payer_id'])
            && !empty($normalizedData['patient_last_name']);
    }

    private function requiresManualVerification(array $normalizedData): bool
    {
        $confidenceScore = $normalizedData['_metadata']['confidence_score'] ?? 0;

        // Require verification if confidence is low or critical fields are missing
        return $confidenceScore < 0.8
            || empty($normalizedData['patient_member_id'])
            || empty($normalizedData['payer_id']);
    }

    private function hasRequiredEligibilityData(array $normalizedData): bool
    {
        return !empty($normalizedData['patient_member_id'])
            && !empty($normalizedData['payer_id'])
            && (!empty($normalizedData['patient_first_name']) || !empty($normalizedData['patient_last_name']));
    }

    private function generateEligibilityCacheKey(array $eligibilityRequest): string
    {
        $key = implode('_', [
            $eligibilityRequest['payer_id'] ?? '',
            $eligibilityRequest['member_id'] ?? '',
            $eligibilityRequest['patient_dob'] ?? ''
        ]);

        return 'eligibility:' . md5($key);
    }

    private function cacheEligibilityResult(string $episodeId, array $result): void
    {
        Cache::put("episode_eligibility:{$episodeId}", $result, now()->addDays(7));
    }

    private function getLatestInsuranceCardData(string $patientId): ?array
    {
        // This would typically query the FHIR audit logs for the latest insurance card scan
        $latestScan = $this->analyticsService->getLatestInsuranceCardScan($patientId);

        return $latestScan['data'] ?? null;
    }
}
