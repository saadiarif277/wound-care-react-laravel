<?php

namespace App\Http\Controllers;

use App\Services\Insurance\InsuranceIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InsuranceController extends Controller
{
    private InsuranceIntegrationService $insuranceService;
    
    public function __construct(InsuranceIntegrationService $insuranceService)
    {
        $this->insuranceService = $insuranceService;
    }
    
    /**
     * Process insurance card scan
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function processCardScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'ocr_data' => 'required|array',
            'ocr_provider' => 'string|in:azure_ocr,aws_textract,google_vision'
        ]);
        
        $result = $this->insuranceService->processInsuranceCard(
            $validated['patient_id'],
            $validated['ocr_data'],
            $validated['ocr_provider'] ?? 'azure_ocr'
        );
        
        return response()->json([
            'success' => true,
            'data' => $result,
            'next_steps' => $this->determineNextSteps($result)
        ]);
    }
    
    /**
     * Quick eligibility check
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function quickEligibilityCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_first_name' => 'required|string',
            'patient_last_name' => 'required|string',
            'patient_dob' => 'required|date',
            'patient_member_id' => 'required|string',
            'payer_name' => 'required|string',
            'payer_id' => 'nullable|string',
            'provider_npi' => 'nullable|string',
            'facility_npi' => 'nullable|string'
        ]);
        
        $result = $this->insuranceService->processQuickRequest($validated);
        
        return response()->json([
            'success' => true,
            'eligibility' => $result['eligibility'],
            'recommendations' => $result['recommendations'],
            'normalized_data' => $result['normalized_data']
        ]);
    }
    
    /**
     * Get insurance analytics for a patient
     * 
     * @param string $patientId
     * @return JsonResponse
     */
    public function getPatientInsuranceAnalytics(string $patientId): JsonResponse
    {
        $analytics = $this->insuranceService->getInsuranceAnalytics($patientId);
        
        return response()->json([
            'success' => true,
            'analytics' => $analytics
        ]);
    }
    
    /**
     * Get payer insights
     * 
     * @param string $payerId
     * @return JsonResponse
     */
    public function getPayerInsights(string $payerId): JsonResponse
    {
        $insights = $this->insuranceService->getPayerInsights($payerId);
        
        return response()->json([
            'success' => true,
            'insights' => $insights
        ]);
    }
    
    /**
     * Determine next steps based on processing result
     * 
     * @param array $result
     * @return array
     */
    private function determineNextSteps(array $result): array
    {
        $steps = [];
        
        if ($result['requires_verification']) {
            $steps[] = [
                'action' => 'verify_data',
                'description' => 'Please verify the extracted insurance information',
                'priority' => 'high'
            ];
        }
        
        if (empty($result['normalized_data']['eligibility'])) {
            $steps[] = [
                'action' => 'check_eligibility',
                'description' => 'Run eligibility verification',
                'priority' => 'medium'
            ];
        }
        
        if (!empty($result['normalized_data']['eligibility']['requires_prior_auth'])) {
            $steps[] = [
                'action' => 'submit_prior_auth',
                'description' => 'Submit prior authorization request',
                'priority' => 'high'
            ];
        }
        
        return $steps;
    }
}

// Example Usage in QuickRequestController
class QuickRequestController extends Controller
{
    private InsuranceIntegrationService $insuranceService;
    
    public function submitRequest(Request $request): JsonResponse
    {
        // ... validation ...
        
        // Process insurance data
        $insuranceResult = $this->insuranceService->processQuickRequest($request->all());
        
        // Use normalized data for downstream processing
        $normalizedData = $insuranceResult['normalized_data'];
        
        // The data is now normalized and ready for:
        // 1. IVR pre-filling
        // 2. Eligibility checking
        // 3. Order creation
        // 4. DocuSeal template mapping
        
        return response()->json([
            'success' => true,
            'insurance_processed' => true,
            'eligibility' => $insuranceResult['eligibility'],
            'recommendations' => $insuranceResult['recommendations'],
            'confidence_score' => $normalizedData['_metadata']['confidence_score'] ?? 0
        ]);
    }
}
