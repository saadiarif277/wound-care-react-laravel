<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ValidationBuilderEngine;
use App\Services\CmsCoverageApiService;
use App\Models\Order;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ValidationBuilderController extends Controller
{
    private ValidationBuilderEngine $validationEngine;
    private CmsCoverageApiService $cmsService;

    public function __construct(
        ValidationBuilderEngine $validationEngine,
        CmsCoverageApiService $cmsService
    ) {
        $this->validationEngine = $validationEngine;
        $this->cmsService = $cmsService;
    }

    /**
     * Get validation rules for a specific specialty
     */
    public function getValidationRules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');
            $state = $request->input('state');

            $validationRules = $this->validationEngine->buildValidationRulesForSpecialty($specialty, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'state' => $state,
                    'validation_rules' => $validationRules
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting validation rules', [
                'specialty' => $request->input('specialty'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving validation rules'
            ], 500);
        }
    }

    /**
     * Get validation rules for current user's specialty
     */
    public function getUserValidationRules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $state = $request->input('state');

            $validationRules = $this->validationEngine->buildValidationRulesForUser($user, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'state' => $state,
                    'validation_rules' => $validationRules
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user validation rules', [
                'user_id' => $request->user()->id,
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user validation rules'
            ], 500);
        }
    }

    /**
     * Validate an order
     */
    public function validateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'specialty' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $orderId = $request->input('order_id');
            $specialty = $request->input('specialty');

            $order = Order::where('id', $orderId)->firstOrFail();
            $validationResults = $this->validationEngine->validateOrder($order, $specialty);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'specialty' => $specialty,
                    'validation_results' => $validationResults
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating order', [
                'order_id' => $request->input('order_id'),
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error validating order'
            ], 500);
        }
    }

    /**
     * Validate a product request
     */
    public function validateProductRequest(Request $request): JsonResponse
    {
        // Support both direct validation (from frontend) and product request ID validation
        $validator = Validator::make($request->all(), [
            // For direct validation (frontend form)
            'patient_data' => 'nullable|array',
            'clinical_data' => 'nullable|array',
            'wound_type' => 'nullable|string',
            'facility_id' => 'nullable|integer|exists:facilities,id',
            'facility_state' => 'nullable|string|size:2',
            'expected_service_date' => 'nullable|date',
            'provider_specialty' => 'nullable|string',
            'selected_products' => 'nullable|array',
            'validation_type' => 'nullable|string',
            'enable_cms_integration' => 'nullable|boolean',
            'enable_mac_validation' => 'nullable|boolean',
            'state' => 'nullable|string|size:2',

            // For product request ID validation
            'product_request_id' => 'nullable|integer|exists:product_requests,id',
            'specialty' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if this is a direct validation request (from frontend) or product request ID validation
            if ($request->has('facility_id') || $request->has('patient_data')) {
                // Direct validation request - extract facility address for place of service
                $facilityId = $request->input('facility_id');
                $facilityAddress = null;
                $placeOfService = null;

                if ($facilityId) {
                    $facility = \App\Models\Facility::find($facilityId);
                    if ($facility) {
                        // Use complete facility address as place of service for CMS Coverage API
                        $facilityAddress = [
                            'id' => $facility->id,
                            'name' => $facility->name,
                            'address' => $facility->address,
                            'city' => $facility->city,
                            'state' => $facility->state,
                            'zip_code' => $facility->zip_code,
                            'full_address' => $facility->full_address
                        ];

                        // Format place of service for MAC validation
                        $placeOfService = [
                            'facility_id' => $facility->id,
                            'facility_name' => $facility->name,
                            'service_address' => $facility->address,
                            'service_city' => $facility->city,
                            'service_state' => $facility->state,
                            'service_zip' => $facility->zip_code,
                            'full_service_address' => $facility->full_address,
                            'npi' => $facility->npi
                        ];
                    }
                }

                // Build validation request with place of service
                $validationData = [
                    'patient_data' => $request->input('patient_data', []),
                    'clinical_data' => $request->input('clinical_data', []),
                    'wound_type' => $request->input('wound_type'),
                    'facility_address' => $facilityAddress,
                    'place_of_service' => $placeOfService,
                    'expected_service_date' => $request->input('expected_service_date'),
                    'provider_specialty' => $request->input('provider_specialty', 'wound_care_specialty'),
                    'selected_products' => $request->input('selected_products', []),
                    'validation_type' => $request->input('validation_type', 'wound_care_only'),
                    'enable_cms_integration' => $request->input('enable_cms_integration', true),
                    'enable_mac_validation' => $request->input('enable_mac_validation', true),
                    'state' => $facilityAddress['state'] ?? $request->input('state', 'CA')
                ];

                $validationResults = $this->validationEngine->validateDirectRequest($validationData);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'facility_address' => $facilityAddress,
                        'place_of_service' => $placeOfService,
                        'validation_type' => $validationData['validation_type'],
                        'specialty' => $validationData['provider_specialty'],
                        'validation_results' => $validationResults,
                        'overall_status' => $validationResults['overall_status'] ?? 'pending',
                        'compliance_score' => $validationResults['compliance_score'] ?? 0,
                        'mac_contractor' => $validationResults['mac_contractor'] ?? 'Unknown',
                        'jurisdiction' => $validationResults['jurisdiction'] ?? 'Unknown',
                        'cms_compliance' => $validationResults['cms_compliance'] ?? [],
                        'issues' => $validationResults['issues'] ?? [],
                        'requirements_met' => $validationResults['requirements_met'] ?? [],
                        'reimbursement_risk' => $validationResults['reimbursement_risk'] ?? 'medium'
                    ]
                ]);

            } else {
                // Traditional product request ID validation
                $productRequestId = $request->input('product_request_id');
                $specialty = $request->input('specialty');

                $productRequest = ProductRequest::with('facility')->where('id', $productRequestId)->firstOrFail();

                // Extract facility address as place of service
                $facility = $productRequest->facility;
                $placeOfService = null;

                if ($facility) {
                    $placeOfService = [
                        'facility_id' => $facility->id,
                        'facility_name' => $facility->name,
                        'service_address' => $facility->address,
                        'service_city' => $facility->city,
                        'service_state' => $facility->state,
                        'service_zip' => $facility->zip_code,
                        'full_service_address' => $facility->full_address,
                        'npi' => $facility->npi
                    ];
                }

                $validationResults = $this->validationEngine->validateProductRequest($productRequest, $specialty, $placeOfService);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'product_request_id' => $productRequestId,
                        'specialty' => $specialty,
                        'place_of_service' => $placeOfService,
                        'validation_results' => $validationResults
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error validating product request', [
                'facility_id' => $request->input('facility_id'),
                'product_request_id' => $request->input('product_request_id'),
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error validating product request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate a specific section of clinical assessment data
     */
    public function validateSection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'section' => 'required|string',
            'data' => 'required|array',
            'wound_type' => 'required|string',
            'assessment_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $section = $request->input('section');
            $data = $request->input('data');
            $woundType = $request->input('wound_type');
            $assessmentType = $request->input('assessment_type');

            $validationResults = $this->validateClinicalSection($section, $data, $woundType, $assessmentType);

            return response()->json([
                'success' => true,
                'data' => [
                    'section' => $section,
                    'wound_type' => $woundType,
                    'assessment_type' => $assessmentType,
                    'validation_results' => $validationResults
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating clinical section', [
                'section' => $request->input('section'),
                'wound_type' => $request->input('wound_type'),
                'assessment_type' => $request->input('assessment_type'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error validating clinical section'
            ], 500);
        }
    }

    /**
     * Get CMS LCDs for a specialty
     */
    public function getCmsLcds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');
            $state = $request->input('state');

            $lcds = $this->cmsService->getLCDsBySpecialty($specialty, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'state' => $state,
                    'lcds' => $lcds,
                    'count' => count($lcds)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting CMS LCDs', [
                'specialty' => $request->input('specialty'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving CMS LCDs'
            ], 500);
        }
    }

    /**
     * Get CMS NCDs for a specialty
     */
    public function getCmsNcds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');

            $ncds = $this->cmsService->getNCDsBySpecialty($specialty);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'ncds' => $ncds,
                    'count' => count($ncds)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting CMS NCDs', [
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving CMS NCDs'
            ], 500);
        }
    }

    /**
     * Get CMS Articles for a specialty
     */
    public function getCmsArticles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');
            $state = $request->input('state');

            $articles = $this->cmsService->getArticlesBySpecialty($specialty, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'state' => $state,
                    'articles' => $articles,
                    'count' => count($articles)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting CMS Articles', [
                'specialty' => $request->input('specialty'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving CMS Articles'
            ], 500);
        }
    }

    /**
     * Search CMS coverage documents
     */
    public function searchCmsDocuments(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string|min:3',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $keyword = $request->input('keyword');
            $state = $request->input('state');

            $searchResults = $this->cmsService->searchCoverageDocuments($keyword, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword,
                    'state' => $state,
                    'results' => $searchResults
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching CMS documents', [
                'keyword' => $request->input('keyword'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching CMS documents'
            ], 500);
        }
    }

    /**
     * Get MAC jurisdiction for a state
     */
    public function getMacJurisdiction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'state' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $state = $request->input('state');

            $macInfo = $this->cmsService->getMACJurisdiction($state);

            return response()->json([
                'success' => true,
                'data' => [
                    'state' => $state,
                    'mac_info' => $macInfo
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting MAC jurisdiction', [
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving MAC jurisdiction'
            ], 500);
        }
    }

    /**
     * Get available specialties
     */
    public function getAvailableSpecialties(): JsonResponse
    {
        try {
            $specialties = $this->cmsService->getAvailableSpecialties();

            return response()->json([
                'success' => true,
                'data' => [
                    'specialties' => $specialties
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting available specialties', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available specialties'
            ], 500);
        }
    }

    /**
     * Clear cache for a specialty
     */
    public function clearSpecialtyCache(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');

            $this->cmsService->clearSpecialtyCache($specialty);

            return response()->json([
                'success' => true,
                'message' => "Cache cleared for specialty: {$specialty}"
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing specialty cache', [
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error clearing specialty cache'
            ], 500);
        }
    }

    /**
     * Validate a specific clinical section based on assessment type and wound type
     */
    private function validateClinicalSection(string $section, array $data, string $woundType, string $assessmentType): array
    {
        $errors = [];
        $warnings = [];
        $score = 100;

        // Validation logic based on section type
        switch ($section) {
            case 'wound_details':
                $errors = array_merge($errors, $this->validateWoundDetails($data, $woundType));
                break;

            case 'conservative_care':
                $errors = array_merge($errors, $this->validateConservativeCare($data));
                break;

            case 'vascular_evaluation':
                $errors = array_merge($errors, $this->validateVascularEvaluation($data));
                break;

            case 'pulmonary_history':
                $errors = array_merge($errors, $this->validatePulmonaryHistory($data));
                break;

            case 'tissue_oxygenation':
                $errors = array_merge($errors, $this->validateTissueOxygenation($data));
                break;

            case 'coordinated_care':
                $warnings = array_merge($warnings, $this->validateCoordinatedCare($data));
                break;

            case 'lab_results':
                $warnings = array_merge($warnings, $this->validateLabResults($data, $woundType));
                break;
        }

        // Calculate score based on errors and warnings
        $score -= (count($errors) * 20);
        $score -= (count($warnings) * 5);
        $score = max(0, $score);

        return [
            'is_valid' => empty($errors),
            'score' => $score,
            'errors' => $errors,
            'warnings' => $warnings,
            'section_complete' => $this->isSectionComplete($section, $data, $assessmentType)
        ];
    }

    /**
     * Validate wound details section
     */
    private function validateWoundDetails(array $data, string $woundType): array
    {
        $errors = [];

        // Required fields for all wound types
        if (empty($data['location'])) {
            $errors[] = 'Wound location is required';
        }

        if (empty($data['length']) || $data['length'] <= 0) {
            $errors[] = 'Wound length must be greater than 0';
        }

        if (empty($data['width']) || $data['width'] <= 0) {
            $errors[] = 'Wound width must be greater than 0';
        }

        if (empty($data['duration_value']) || $data['duration_value'] <= 0) {
            $errors[] = 'Wound duration is required';
        }

        if (empty($data['duration_unit'])) {
            $errors[] = 'Wound duration unit is required';
        }

        // DFU-specific validation
        if ($woundType === 'diabetic_foot_ulcer') {
            if (empty($data['wagner_grade'])) {
                $errors[] = 'Wagner grade is required for diabetic foot ulcers';
            }
        }

        return $errors;
    }

    /**
     * Validate conservative care section
     */
    private function validateConservativeCare(array $data): array
    {
        $errors = [];

        if (empty($data['duration_value']) || $data['duration_value'] < 4) {
            $errors[] = 'Conservative care duration must be at least 4 weeks for Medicare compliance';
        }

        if (empty($data['duration_unit'])) {
            $errors[] = 'Conservative care duration unit is required';
        }

        if (empty($data['treatments']) || count($data['treatments']) < 2) {
            $errors[] = 'At least 2 conservative treatments must be documented';
        }

        if (empty($data['response'])) {
            $errors[] = 'Response to conservative care must be documented';
        }

        return $errors;
    }

    /**
     * Validate vascular evaluation section
     */
    private function validateVascularEvaluation(array $data): array
    {
        $errors = [];

        // ABI validation
        if (isset($data['abi_right']) && ($data['abi_right'] < 0 || $data['abi_right'] > 2)) {
            $errors[] = 'Right ABI value must be between 0 and 2';
        }

        if (isset($data['abi_left']) && ($data['abi_left'] < 0 || $data['abi_left'] > 2)) {
            $errors[] = 'Left ABI value must be between 0 and 2';
        }

        return $errors;
    }

    /**
     * Validate pulmonary history section
     */
    private function validatePulmonaryHistory(array $data): array
    {
        $errors = [];

        if (empty($data['primary_diagnosis'])) {
            $errors[] = 'Primary pulmonary diagnosis is required';
        }

        if (empty($data['smoking_status'])) {
            $errors[] = 'Smoking status is required';
        }

        return $errors;
    }

    /**
     * Validate tissue oxygenation section
     */
    private function validateTissueOxygenation(array $data): array
    {
        $errors = [];

        if (empty($data['resting_spo2']) || $data['resting_spo2'] < 70 || $data['resting_spo2'] > 100) {
            $errors[] = 'Resting SpO2 must be between 70% and 100%';
        }

        return $errors;
    }

    /**
     * Validate coordinated care section
     */
    private function validateCoordinatedCare(array $data): array
    {
        $warnings = [];

        if (empty($data['team_members']) || count($data['team_members']) < 2) {
            $warnings[] = 'Consider involving additional team members for comprehensive care';
        }

        return $warnings;
    }

    /**
     * Validate lab results section
     */
    private function validateLabResults(array $data, string $woundType): array
    {
        $warnings = [];

        if ($woundType === 'diabetic_foot_ulcer') {
            if (empty($data['hba1c'])) {
                $warnings[] = 'HbA1c is recommended for diabetic foot ulcer patients';
            } elseif ($data['hba1c'] > 7) {
                $warnings[] = 'HbA1c > 7% indicates suboptimal diabetes control';
            }
        }

        return $warnings;
    }

    /**
     * Check if section is complete based on assessment type
     */
    private function isSectionComplete(string $section, array $data, string $assessmentType): bool
    {
        $requiredFields = $this->getRequiredFieldsForSection($section, $assessmentType);

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get required fields for a section based on assessment type
     */
    private function getRequiredFieldsForSection(string $section, string $assessmentType): array
    {
        $requiredFields = [
            'wound_details' => ['location', 'length', 'width', 'duration_value', 'duration_unit'],
            'conservative_care' => ['duration_value', 'duration_unit', 'treatments', 'response'],
            'vascular_evaluation' => [],
            'pulmonary_history' => ['primary_diagnosis', 'smoking_status'],
            'tissue_oxygenation' => ['resting_spo2'],
            'coordinated_care' => [],
            'lab_results' => []
        ];

        return $requiredFields[$section] ?? [];
    }
}
