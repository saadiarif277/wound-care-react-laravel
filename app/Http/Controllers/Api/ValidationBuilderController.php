<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ValidationBuilderEngine;
use App\Services\CmsCoverageApiService;
use App\Models\Order\Order;
use App\Models\Order\ProductRequest;
use App\Models\User;
use App\Models\Fhir\Facility;
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
                    $facility = Facility::find($facilityId);
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
            'wound_type' => 'required|string|wound_type',
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
            $woundType = \App\Services\WoundTypeService::normalizeToEnum($request->input('wound_type'));
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
    private function validateClinicalSection(string $sectionUiKey, array $fullChecklistData, string $woundType, string $assessmentType): array
    {
        $errors = [];
        $warnings = [];
        $score = 100;

        // Validation logic based on the UI section key
        // The $fullChecklistData is the entire SkinSubstituteChecklistInput DTO data
        switch ($sectionUiKey) {
            // Case for old generic sections if assessmentType is not 'wound_care'
            case 'wound_details':
                if ($assessmentType !== 'wound_care') $errors = array_merge($errors, $this->validateWoundDetails($fullChecklistData, $woundType));
                break;
            case 'conservative_care':
                if ($assessmentType !== 'wound_care') $errors = array_merge($errors, $this->validateConservativeCare($fullChecklistData));
                break;
            case 'vascular_evaluation':
                if ($assessmentType !== 'wound_care') $errors = array_merge($errors, $this->validateVascularEvaluation($fullChecklistData));
                break;
            case 'pulmonary_history': // Assuming this is only for pulmonary_wound assessmentType
                if ($assessmentType === 'pulmonary_wound') $errors = array_merge($errors, $this->validatePulmonaryHistory($fullChecklistData));
                break;
            case 'tissue_oxygenation': // Assuming this is only for pulmonary_wound assessmentType
                if ($assessmentType === 'pulmonary_wound') $errors = array_merge($errors, $this->validateTissueOxygenation($fullChecklistData));
                break;
            case 'coordinated_care': // Assuming this is only for pulmonary_wound assessmentType
                if ($assessmentType === 'pulmonary_wound') $warnings = array_merge($warnings, $this->validateCoordinatedCare($fullChecklistData));
                break;
            case 'lab_results': // Generic lab results for non-wound_care types
                if ($assessmentType !== 'wound_care') $warnings = array_merge($warnings, $this->validateLabResults($fullChecklistData, $woundType));
                break;

            // New SSP Checklist Sections - use the constants for case matching
            case 'ssp_checklist_diagnosis': // Matches SSP_UI_SECTIONS.DIAGNOSIS
                $errors = array_merge($errors, $this->validateSspDiagnosis($fullChecklistData, $woundType));
                break;
            case 'ssp_checklist_lab_results': // Matches SSP_UI_SECTIONS.LAB_RESULTS
                $errors = array_merge($errors, $this->validateSspLabResults($fullChecklistData, $woundType));
                break;
            case 'ssp_checklist_wound': // Matches SSP_UI_SECTIONS.WOUND_DESCRIPTION
                $errors = array_merge($errors, $this->validateSspWoundDescription($fullChecklistData, $woundType));
                break;
            case 'ssp_checklist_circulation': // Matches SSP_UI_SECTIONS.CIRCULATION
                $errors = array_merge($errors, $this->validateSspCirculation($fullChecklistData, $woundType));
                break;
            case 'ssp_checklist_conservative_measures': // Matches SSP_UI_SECTIONS.CONSERVATIVE_TREATMENT
                $errors = array_merge($errors, $this->validateSspConservativeMeasures($fullChecklistData, $woundType));
                break;
            case 'clinical_photos':
                // Validation for clinical_photos section data if any
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
            'section_complete' => $this->isSectionComplete($sectionUiKey, $fullChecklistData, $assessmentType)
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
        if ($woundType === 'DFU') {
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

        if ($woundType === 'DFU') {
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
            // Original generic sections (review if still needed for 'wound_care' assessmentType alongside SSP)
            'wound_details' => ['location', 'length', 'width', 'duration_value', 'duration_unit'],
            'conservative_care' => ['duration_value', 'duration_unit', 'treatments', 'response'],
            'vascular_evaluation' => [], // Example: may have required fields depending on context
            'pulmonary_history' => ['primary_diagnosis', 'smoking_status'],
            'tissue_oxygenation' => ['resting_spo2'],
            'coordinated_care' => [],
            'lab_results' => [], // Example: may have required fields

            // SSP (Skin Substitute Pre-application) Checklist Sections
            // Define required fields for each SSP section based on what makes them minimally complete for progression
            // These might differ from fields that only throw validation errors if malformed but aren't strictly required to exist.
            'ssp_checklist_diagnosis' => ['date_of_procedure', 'laterality', 'location_general'], // Requires at least one diagnosis type indicated too.
            'ssp_checklist_lab_results' => ['hba1c_value', 'hba1c_date', 'albumin_prealbumin_value', 'albumin_prealbumin_date'],
            'ssp_checklist_wound' => [
                'location_detailed', 'depth_type', 'duration_overall', 'exposed_structure',
                'length_cm', 'width_cm',
                'infection_osteomyelitis_evidence', 'necrotic_tissue_evidence',
                'charcot_deformity_active', 'malignancy_suspected',
                'tissue_type', 'exudate_amount', 'exudate_type'
            ],
            'ssp_checklist_circulation' => ['doppler_waveforms_adequate', 'imaging_type'], // Plus at least one set of test results (ABI, Pedal, TcPO2) with their dates
            'ssp_checklist_conservative_measures' => [
                // All Yes/No questions from the checklist are generally required for a complete assessment here.
                'debridement_performed', 'moist_dressings_applied', 'non_weight_bearing_regimen',
                'pressure_reducing_footwear', 'compression_therapy_vsu', 'hbot_current',
                'smoking_status',
                // smoking_cessation_counselled is conditional on smoking_status being 'smoker'
                'radiation_chemo_current', 'immune_modulators_current',
                'autoimmune_ctd_diagnosis'
                // non_weight_bearing_type is conditional
                // pressure_ulcer_leading_type is conditional on main woundType
            ],
            'clinical_photos' => [] // No specific data fields are required by default for completion, upload is interaction-based
        ];

        // If assessmentType is 'wound_care', prioritize SSP section definitions if the $section key matches an SSP one.
        if ($assessmentType === 'wound_care') {
            if (strpos($section, 'ssp_') === 0 && isset($requiredFields[$section])) {
                return $requiredFields[$section];
            }
            // If it's a generic section key but assessment is 'wound_care', decide if it has SSP equivalent or is separate
            // For example, 'wound_details' for 'wound_care' type should map to 'ssp_wound_description' requirements.
            if ($section === 'wound_details') return $requiredFields['ssp_checklist_wound'] ?? [];
            if ($section === 'conservative_care') return $requiredFields['ssp_checklist_conservative_measures'] ?? [];
            if ($section === 'lab_results') return $requiredFields['ssp_checklist_lab_results'] ?? [];
            if ($section === 'vascular_evaluation') return $requiredFields['ssp_checklist_circulation'] ?? [];
        }

        return $requiredFields[$section] ?? [];
    }

    // --- SSP VALIDATION METHODS START ---
    private function validateSspDiagnosis(array $checklistData, string $woundType): array
    {
        $errors = [];
        if (empty($checklistData['dateOfProcedure'])) {
            $errors[] = 'SSP Diagnosis: Date of Procedure is required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['dateOfProcedure'])) {
            $errors[] = 'SSP Diagnosis: Date of Procedure must be in YYYY-MM-DD format.';
        }

        $hasDiabetes = $checklistData['hasDiabetes'] ?? false;
        $diabetesType = $checklistData['diabetesType'] ?? null;
        $hasVenousStasisUlcer = $checklistData['hasVenousStasisUlcer'] ?? false;
        $hasPressureUlcer = $checklistData['hasPressureUlcer'] ?? false;
        $pressureUlcerStage = $checklistData['pressureUlcerStage'] ?? null;

        if (!$hasDiabetes && !$hasVenousStasisUlcer && !$hasPressureUlcer) {
            $errors[] = 'SSP Diagnosis: At least one primary diagnosis condition (Diabetes, Venous Stasis Ulcer, or Pressure Ulcer) must be indicated as present.';
        }

        if ($hasDiabetes && empty($diabetesType)){
            $errors[] = 'SSP Diagnosis: If Diabetes is present, a Type (1 or 2) must be selected.';
        } elseif ($hasDiabetes && !in_array($diabetesType, ['1', '2'])) {
            $errors[] = 'SSP Diagnosis: Invalid value for Diabetes Type (must be 1 or 2 if present).';
        }

        if (empty($checklistData['location'])) {
            $errors[] = 'SSP Diagnosis: General Diagnosis Location/Laterality is required.';
        }

        if ($hasPressureUlcer && empty($pressureUlcerStage)) {
             $errors[] = 'SSP Diagnosis: Stage is required if Pressure Ulcer is indicated.';
        }
        return $errors;
    }

    private function validateSspLabResults(array $checklistData, string $woundType): array
    {
        $errors = [];
        if (isset($checklistData['hba1cResult']) || isset($checklistData['hba1cDate'])) {
            if (!isset($checklistData['hba1cResult']) || $checklistData['hba1cResult'] === '' || $checklistData['hba1cResult'] === null) {
                $errors[] = 'SSP Lab Results: HbA1c value is required if date is provided (or vice-versa).';
            } elseif (!is_numeric($checklistData['hba1cResult'])) {
                $errors[] = 'SSP Lab Results: HbA1c value must be a number.';
            }
            if (empty($checklistData['hba1cDate'])) {
                $errors[] = 'SSP Lab Results: Date for HbA1c is required if value is provided.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['hba1cDate'])) {
                $errors[] = 'SSP Lab Results: HbA1c Date must be in YYYY-MM-DD format.';
            }
        } elseif ($woundType === 'diabetic_foot_ulcer') {
            $errors[] = 'SSP Lab Results: HbA1c information (value and date) is required for diabetic foot ulcers.';
        }

        if (isset($checklistData['albuminResult']) || isset($checklistData['albuminDate'])) {
            if (!isset($checklistData['albuminResult']) || $checklistData['albuminResult'] === '' || $checklistData['albuminResult'] === null) {
                $errors[] = 'SSP Lab Results: Albumin value is required if date is provided (or vice-versa).';
            } elseif (!is_numeric($checklistData['albuminResult'])) {
                $errors[] = 'SSP Lab Results: Albumin value must be a number.';
            }
            if (empty($checklistData['albuminDate'])) {
                $errors[] = 'SSP Lab Results: Date for Albumin is required if value is provided.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['albuminDate'])) {
                $errors[] = 'SSP Lab Results: Albumin Date must be in YYYY-MM-DD format.';
            }
        } else {
            $errors[] = 'SSP Lab Results: Albumin information (value and date) is required.';
        }

        // Assuming DTO uses 'crpResult' not 'crapResult' as in previous version of code_edit
        if (isset($checklistData['crpResult']) && !is_numeric($checklistData['crpResult'])) {
            $errors[] = 'SSP Lab Results: CRP value must be a number if provided.';
        }
        if (isset($checklistData['sedRate']) && !is_numeric($checklistData['sedRate'])) {
            $errors[] = 'SSP Lab Results: Sed Rate must be a number if provided.';
        }
        if (!empty($checklistData['cultureDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['cultureDate'])) {
            $errors[] = 'SSP Lab Results: Culture Date must be in YYYY-MM-DD format if provided.';
        }
        if (isset($checklistData['treated']) && !is_bool($checklistData['treated'])) {
             $errors[] = 'SSP Lab Results: Invalid value for \'Treated for infection?\'. Must be true or false.';
        }
        return $errors;
    }

    private function validateSspWoundDescription(array $checklistData, string $woundType): array
    {
        $errors = [];
        // Fields from PHP DTO structure (which is flatter for wound than the TS interface's nested 'wound' object)
        if (empty($checklistData['ulcerLocation'])) $errors[] = 'SSP Wound Description: Specific Ulcer Location is required.';

        if (empty($checklistData['depth'])) { // This is top-level 'depth' in DTO, maps to depth_type (full/partial)
            $errors[] = 'SSP Wound Description: Wound Depth Classification (full-thickness/partial-thickness) is required.';
        } elseif (!in_array($checklistData['depth'], ['full-thickness', 'partial-thickness'])) {
            $errors[] = 'SSP Wound Description: Invalid value for Wound Depth Classification.';
        }

        if (empty($checklistData['ulcerDuration'])) $errors[] = 'SSP Wound Description: Ulcer Duration is required.';

        // exposedStructures is an array
        if (isset($checklistData['exposedStructures']) && !is_array($checklistData['exposedStructures'])) {
            $errors[] = 'SSP Wound Description: Exposed Structures must be a list.';
        } elseif (isset($checklistData['exposedStructures']) && is_array($checklistData['exposedStructures'])) {
            $validStructures = ['muscle', 'tendon', 'bone']; // 'none' is represented by empty array or not set based on DTO
            foreach($checklistData['exposedStructures'] as $structure){
                if(!in_array($structure, $validStructures)) $errors[] = "SSP Wound Description: Invalid exposed structure '{$structure}'. Allowed: muscle, tendon, bone.";
            }
        }

        if (!isset($checklistData['length']) || !is_numeric($checklistData['length']) || $checklistData['length'] <= 0) {
            $errors[] = 'SSP Wound Description: Length (cm) must be a number greater than 0.';
        }
        if (!isset($checklistData['width']) || !is_numeric($checklistData['width']) || $checklistData['width'] <= 0) {
            $errors[] = 'SSP Wound Description: Width (cm) must be a number greater than 0.';
        }
        // 'woundDepth' is numeric depth from DTO
        if (isset($checklistData['woundDepth']) && (!is_numeric($checklistData['woundDepth']) || $checklistData['woundDepth'] < 0)) {
            $errors[] = 'SSP Wound Description: Numeric Wound Depth (cm) must be a number equal to or greater than 0.';
        }

        if (!isset($checklistData['hasInfection']) || !is_bool($checklistData['hasInfection'])) {
            $errors[] = 'SSP Wound Description: Indication for \'Evidence of Infection/Osteomyelitis\' is required (Yes/No).';
        }
        if (!isset($checklistData['hasNecroticTissue']) || !is_bool($checklistData['hasNecroticTissue'])) {
            $errors[] = 'SSP Wound Description: Indication for \'Evidence of necrotic tissue\' is required (Yes/No).';
        }
        if (!isset($checklistData['hasCharcotDeformity']) || !is_bool($checklistData['hasCharcotDeformity'])) {
            $errors[] = 'SSP Wound Description: Indication for \'Active Charcot deformity\' is required (Yes/No).';
        }
        if (!isset($checklistData['hasMalignancy']) || !is_bool($checklistData['hasMalignancy'])) {
            $errors[] = 'SSP Wound Description: Indication for \'Known or suspected malignancy\' is required (Yes/No).';
        }

        // Fields like wagner_grade, tissue_type, exudate_amount, exudate_type, infection_signs
        // are NOT in the provided PHP DTO for SkinSubstituteChecklistInput at the top level or under a 'wound' sub-object.
        // If they are needed, the PHP DTO needs to be updated to include them.
        // Removing validation for them here to align with the provided DTO.

        return $errors;
    }

    private function validateSspCirculation(array $checklistData, string $woundType): array
    {
        $errors = [];
        $hasAbi = isset($checklistData['abiResult']);
        $hasPedalPulses = !empty($checklistData['pedalPulsesResult']);
        $hasTcpo2 = isset($checklistData['tcpo2Result']);

        if (!$hasAbi && !$hasPedalPulses && !$hasTcpo2) {
            $errors[] = 'SSP Circulation: At least one circulation test result (ABI, Pedal Pulses, or TcPO2) is strongly recommended.';
        }

        if ($hasAbi && (!isset($checklistData['abiResult']) || !is_numeric($checklistData['abiResult']))) $errors[] = 'SSP Circulation: ABI Result must be numeric.';
        if ($hasAbi && empty($checklistData['abiDate'])) {
            $errors[] = 'SSP Circulation: Date for ABI result is required if ABI result is provided.';
        } elseif (!empty($checklistData['abiDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['abiDate'])) {
            $errors[] = 'SSP Circulation: ABI Date must be in YYYY-MM-DD format.';
        }

        if ($hasPedalPulses && empty($checklistData['pedalPulsesDate'])) {
            $errors[] = 'SSP Circulation: Date for Pedal pulses result is required if result is provided.';
        } elseif (!empty($checklistData['pedalPulsesDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['pedalPulsesDate'])) {
            $errors[] = 'SSP Circulation: Pedal Pulses Date must be in YYYY-MM-DD format.';
        }

        if ($hasTcpo2 && (!isset($checklistData['tcpo2Result']) || !is_numeric($checklistData['tcpo2Result']))) $errors[] = 'SSP Circulation: TcPO2 Result must be numeric.';
        if ($hasTcpo2 && empty($checklistData['tcpo2Date'])) {
            $errors[] = 'SSP Circulation: Date for TcPO2 result is required if result is provided.';
        } elseif (!empty($checklistData['tcpo2Date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['tcpo2Date'])) {
            $errors[] = 'SSP Circulation: TcPO2 Date must be in YYYY-MM-DD format.';
        }

        if (!isset($checklistData['hasTriphasicWaveforms']) || !is_bool($checklistData['hasTriphasicWaveforms'])) {
            $errors[] = 'SSP Circulation: Indication for Doppler arterial waveforms (Yes/No) is required.';
        }

        $hasWaveformResult = !empty($checklistData['waveformResult']);
        if ($hasWaveformResult && empty($checklistData['waveformDate'])) {
            $errors[] = 'SSP Circulation: Date for Doppler result is required if Doppler result notes are provided.';
        } elseif (!empty($checklistData['waveformDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checklistData['waveformDate'])) {
            $errors[] = 'SSP Circulation: Doppler Date must be in YYYY-MM-DD format.';
        }

        if (empty($checklistData['imagingType']) && $checklistData['imagingType'] !== null && $checklistData['imagingType'] !== 'none') { // if it's empty string, but not explicitly 'none' or null
            $errors[] = 'SSP Circulation: Imaging type selection is required (select None if not applicable).';
        } elseif (isset($checklistData['imagingType']) && $checklistData['imagingType'] !== null && !in_array($checklistData['imagingType'], ['xray', 'ct', 'mri', 'none', ''])) {
            $errors[] = 'SSP Circulation: Invalid value for Imaging type.';
        }

        return $errors;
    }

    private function validateSspConservativeMeasures(array $checklistData, string $woundType): array
    {
        $errors = [];

        $boolean_fields_to_check = [
            'debridementPerformed' => 'Debridement of necrotic tissue was performed',
            'moistDressingsApplied' => 'Application of dressings to maintain a moist wound environment',
            'nonWeightBearing' => 'Non-weight bearing regimen',
            'pressureReducingFootwear' => 'Uses pressure-reducing footwear',
            'standardCompression' => 'Standard compression therapy used',
            'currentHbot' => 'Current HBOT',
            'smokingCounselingProvided' => 'If Smoker, has patient been counselled on smoking cessation',
            'receivingRadiationOrChemo' => 'Is Patient receiving radiation therapy or chemotherapy',
            'takingImmuneModulators' => 'Is Patient taking medications considered to be immune system modulators',
            'hasAutoimmuneDiagnosis' => 'Does Patient have an autoimmune connective tissue disease diagnosis'
        ];

        foreach ($boolean_fields_to_check as $field => $label) {
            if ($field === 'smokingCounselingProvided' && ($checklistData['smokingStatus'] ?? '') !== 'smoker') {
                continue;
            }
            if (!isset($checklistData[$field]) || !is_bool($checklistData[$field])) {
                 $errors[] = "SSP Conservative Measures: A Yes/No answer for '{$label}' is required.";
            }
        }

        if (($checklistData['nonWeightBearing'] ?? false) === true && empty($checklistData['footwearType'])) {
            // DTO has footwearType, which could serve as nonWeightBearing type if nonWeightBearing is true
            // This logic might need adjustment based on how these two fields are intended to interact from the DTO perspective.
        }
         if (($checklistData['pressureReducingFootwear'] ?? false) === true && empty($checklistData['footwearType'])) {
            $errors[] = 'SSP Conservative Measures: Type of pressure reducing footwear is required if indicated Yes.';
        }

        if (empty($checklistData['smokingStatus'])) {
            $errors[] = 'SSP Conservative Measures: Smoking Status is required.';
        } elseif (!in_array($checklistData['smokingStatus'], ['smoker', 'previous-smoker', 'non-smoker'])) {
            $errors[] = 'SSP Conservative Measures: Invalid value for Smoking Status.';
        }

        if (($checklistData['smokingStatus'] ?? '') === 'smoker' && !isset($checklistData['smokingCounselingProvided'])) {
            if(!isset($checklistData['smokingCounselingProvided']) || !is_bool($checklistData['smokingCounselingProvided'])){
                 $errors[] = 'SSP Conservative Measures: If Smoker, counselling status on smoking cessation (Yes/No) is required.';
            }
        }

        if ($woundType === 'pressure_ulcer' && empty($checklistData['pressureUlcerLeadingType'])) {
            $errors[] = 'SSP Conservative Measures: Leading type for pressure ulcer is required if the main wound type is pressure ulcer.';
        } elseif (!empty($checklistData['pressureUlcerLeadingType']) && !in_array($checklistData['pressureUlcerLeadingType'], ['bed', 'wheelchair-cushion'])) {
            $errors[] = 'SSP Conservative Measures: Invalid value for leading type of pressure ulcer.';
        }
        return $errors;
    }
    // --- SSP VALIDATION METHODS END ---
}
