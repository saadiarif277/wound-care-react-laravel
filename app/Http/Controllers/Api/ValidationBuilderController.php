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
                if ($assessmentType !== 'wound_care') {
                    $warnings = array_merge($warnings, $this->validateLabResults($data, $woundType));
                }
                break;

            case 'ssp_diagnosis':
                $errors = array_merge($errors, $this->validateSspDiagnosis($data, $woundType));
                break;
            case 'ssp_lab_results':
                $errors = array_merge($errors, $this->validateSspLabResults($data, $woundType));
                break;
            case 'ssp_wound_description':
                $errors = array_merge($errors, $this->validateSspWoundDescription($data, $woundType));
                break;
            case 'ssp_circulation':
                $errors = array_merge($errors, $this->validateSspCirculation($data, $woundType));
                break;
            case 'ssp_conservative_measures':
                $errors = array_merge($errors, $this->validateSspConservativeMeasures($data, $woundType));
                break;
            case 'clinical_photos':
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
            'ssp_diagnosis' => ['date_of_procedure', 'laterality', 'location_general'], // Requires at least one diagnosis type indicated too.
            'ssp_lab_results' => ['hba1c_value', 'hba1c_date', 'albumin_prealbumin_value', 'albumin_prealbumin_date'],
            'ssp_wound_description' => [
                'location_detailed', 'depth_type', 'duration_overall', 'exposed_structure',
                'length_cm', 'width_cm',
                'infection_osteomyelitis_evidence', 'necrotic_tissue_evidence',
                'charcot_deformity_active', 'malignancy_suspected',
                'tissue_type', 'exudate_amount', 'exudate_type'
            ],
            'ssp_circulation' => ['doppler_waveforms_adequate', 'imaging_type'], // Plus at least one set of test results (ABI, Pedal, TcPO2) with their dates
            'ssp_conservative_measures' => [
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
            if ($section === 'wound_details') return $requiredFields['ssp_wound_description'] ?? [];
            if ($section === 'conservative_care') return $requiredFields['ssp_conservative_measures'] ?? [];
            if ($section === 'lab_results') return $requiredFields['ssp_lab_results'] ?? [];
            if ($section === 'vascular_evaluation') return $requiredFields['ssp_circulation'] ?? [];
        }

        return $requiredFields[$section] ?? [];
    }

    // --- SSP VALIDATION METHODS START ---
    private function validateSspDiagnosis(array $data, string $woundType): array
    {
        $errors = [];
        if (empty($data['date_of_procedure'])) {
            $errors[] = 'SSP Diagnosis: Date of Procedure is required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date_of_procedure'])) {
            $errors[] = 'SSP Diagnosis: Date of Procedure must be in YYYY-MM-DD format.';
        }

        // Diagnosis structure from SkinSubstituteChecklistInput: $data is the 'diagnosis' object
        $hasDiabetes = $data['diabetes']['present'] ?? false;
        $diabetesType = $data['diabetes']['type'] ?? null;
        $isVenousStasisUlcer = $data['venousStasisUlcer'] ?? false;
        $isPressureUlcer = $data['pressureUlcer']['present'] ?? false;
        $pressureUlcerStage = $data['pressureUlcer']['stage'] ?? null;

        if (!$hasDiabetes && !$isVenousStasisUlcer && !$isPressureUlcer) {
            $errors[] = 'SSP Diagnosis: At least one primary diagnosis condition (Diabetes, Venous Stasis Ulcer, or Pressure Ulcer) must be indicated as present.';
        }

        if ($hasDiabetes && empty($diabetesType)){
            $errors[] = 'SSP Diagnosis: If Diabetes is present, a Type (1 or 2) must be selected.';
        } elseif ($hasDiabetes && !in_array($diabetesType, ['1', '2'])) {
            // Note: SkinSubstituteChecklistInput allows diabetes_type to be 'none' if present is false.
            // Here, if present is true, type must be 1 or 2.
            $errors[] = 'SSP Diagnosis: Invalid value for Diabetes Type (must be 1 or 2 if present).';
        }

        // Laterality might be associated with the wound itself, not the general diagnosis section in the new structure.
        // The checklist shows laterality under the diabetes radio buttons. This should be $data['diabetes']['laterality']
        // if (empty($data['laterality'])) {
        //     $errors[] = 'SSP Diagnosis: Laterality is required.';
        // } elseif (!in_array($data['laterality'], ['right', 'left', 'bilateral'])) {
        //     $errors[] = 'SSP Diagnosis: Invalid value for Laterality.';
        // }
        // If laterality is tied to a specific diagnosis like DFU:
        if ($hasDiabetes && empty($data['diabetes']['laterality'])) {
             $errors[] = 'SSP Diagnosis: Laterality for diabetic condition is required if Diabetes is present.';
        } elseif ($hasDiabetes && !empty($data['diabetes']['laterality']) && !in_array($data['diabetes']['laterality'], ['right', 'left', 'bilateral'])) {
             $errors[] = 'SSP Diagnosis: Invalid value for diabetic condition Laterality.';
        }


        if (empty($data['location'])) { // This is diagnosis.location from SkinSubstituteChecklistInput
            $errors[] = 'SSP Diagnosis: General Location for diagnosis context is required.';
        }

        if ($isPressureUlcer && empty($pressureUlcerStage)) {
             $errors[] = 'SSP Diagnosis: Stage is required if Pressure Ulcer is indicated.';
        }
        // Consider validating format of $pressureUlcerStage if it has expected values

        return $errors;
    }

    private function validateSspLabResults(array $data, string $woundType): array
    {
        $errors = [];

        // HbA1c
        if (isset($data['hba1c'])) {
            if (empty($data['hba1c']['value']) && $data['hba1c']['value'] !== 0) { // Allow 0 as a value
                $errors[] = 'SSP Lab Results: HbA1c value is required if HbA1c section is provided.';
            } elseif (!is_numeric($data['hba1c']['value'])) {
                $errors[] = 'SSP Lab Results: HbA1c value must be a number.';
            }
            if (empty($data['hba1c']['date'])) {
                $errors[] = 'SSP Lab Results: Date for HbA1c is required if HbA1c section is provided.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['hba1c']['date'])) {
                $errors[] = 'SSP Lab Results: HbA1c Date must be in YYYY-MM-DD format.';
            }
        } elseif ($woundType === 'diabetic_foot_ulcer') {
            // If DFU, and hba1c section itself is missing, it might be an error or warning.
            // The checklist has HbA1c result (last 90 days) without an outer "if applicable"
            $errors[] = 'SSP Lab Results: HbA1c information (value and date) is required for diabetic foot ulcers.';
        }

        // Albumin
        if (isset($data['albumin'])) {
            if (empty($data['albumin']['value']) && $data['albumin']['value'] !== 0) { // Allow 0
                $errors[] = 'SSP Lab Results: Albumin value is required if Albumin section is provided.';
            } elseif (!is_numeric($data['albumin']['value'])) {
                $errors[] = 'SSP Lab Results: Albumin value must be a number.';
            }
            if (empty($data['albumin']['date'])) {
                $errors[] = 'SSP Lab Results: Date for Albumin is required if Albumin section is provided.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['albumin']['date'])) {
                $errors[] = 'SSP Lab Results: Albumin Date must be in YYYY-MM-DD format.';
            }
        } else {
            // Checklist has Pre-Albumin/Albumin results without "if applicable"
            $errors[] = 'SSP Lab Results: Albumin information (value and date) is required.';
        }

        // Other lab fields from the "If Applicable" section of the checklist, now part of labResults in SkinSubstituteChecklistInput
        if (isset($data['crp']) && !is_numeric($data['crp'])) {
            $errors[] = 'SSP Lab Results: CRP value must be a number if provided.';
        }
        if (isset($data['sedRate']) && !is_numeric($data['sedRate'])) {
            $errors[] = 'SSP Lab Results: Sed Rate must be a number if provided.';
        }
        if (!empty($data['cultureDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['cultureDate'])) {
            $errors[] = 'SSP Lab Results: Culture Date must be in YYYY-MM-DD format if provided.';
        }
        if (isset($data['treated']) && !is_bool($data['treated'])) {
             $errors[] = 'SSP Lab Results: Invalid value for \'Treated for infection?\'.';
        }
        // cbc (boolean) and hh (string) don't have specific format validation here beyond type correctness if set.

        return $errors;
    }

    private function validateSspWoundDescription(array $data, string $woundType): array
    {
        $errors = [];
        if (empty($data['location_detailed'])) $errors[] = 'SSP Wound Description: Detailed location of ulcer is required.';

        if (empty($data['depth_type'])) {
            $errors[] = 'SSP Wound Description: Depth Type (full/partial) is required.';
        } elseif (!in_array($data['depth_type'], ['full', 'partial'])) {
            $errors[] = 'SSP Wound Description: Invalid value for Depth Type.';
        }

        if (empty($data['duration_overall'])) $errors[] = 'SSP Wound Description: Duration of Ulcer is required.';

        if (empty($data['exposed_structure'])) {
            $errors[] = 'SSP Wound Description: Exposed Structure indication is required.';
        } elseif (!in_array($data['exposed_structure'], ['muscle', 'tendon', 'bone', 'none'])) {
            $errors[] = 'SSP Wound Description: Invalid value for Exposed Structure.';
        }

        if (!isset($data['length_cm']) || !is_numeric($data['length_cm']) || $data['length_cm'] <= 0) {
            $errors[] = 'SSP Wound Description: Length (cm) must be a number greater than 0.';
        }
        if (!isset($data['width_cm']) || !is_numeric($data['width_cm']) || $data['width_cm'] <= 0) {
            $errors[] = 'SSP Wound Description: Width (cm) must be a number greater than 0.';
        }
        if (isset($data['depth_cm']) && (!is_numeric($data['depth_cm']) || $data['depth_cm'] < 0)) {
            $errors[] = 'SSP Wound Description: Depth (cm) must be a number equal to or greater than 0.';
        }

        if (!isset($data['infection_osteomyelitis_evidence']) || !is_bool($data['infection_osteomyelitis_evidence'])) {
            $errors[] = 'SSP Wound Description: Indication for Evidence of Infection/Osteomyelitis is required (Yes/No).';
        }
        if (!isset($data['necrotic_tissue_evidence']) || !is_bool($data['necrotic_tissue_evidence'])) {
            $errors[] = 'SSP Wound Description: Indication for Evidence of necrotic tissue is required (Yes/No).';
        }
        if (!isset($data['charcot_deformity_active']) || !is_bool($data['charcot_deformity_active'])) {
            $errors[] = 'SSP Wound Description: Indication for Active Charcot deformity is required (Yes/No).';
        }
        if (!isset($data['malignancy_suspected']) || !is_bool($data['malignancy_suspected'])) {
            $errors[] = 'SSP Wound Description: Indication for Known or suspected malignancy is required (Yes/No).';
        }

        // General Wound Characteristics from original form section
        if ($woundType === 'diabetic_foot_ulcer') {
            if (empty($data['wagner_grade']) && ($data['wagner_grade'] !== '0' && $data['wagner_grade'] !== 0)) { // Allow '0' or 0 as a valid non-empty value
                 $errors[] = 'SSP Wound Description: Wagner grade is required for diabetic foot ulcers.';
            } elseif (isset($data['wagner_grade']) && !in_array(strval($data['wagner_grade']), ['0', '1', '2', '3', '4', '5'])) {
                 $errors[] = 'SSP Wound Description: Invalid Wagner grade selected. Must be one of: 0, 1, 2, 3, 4, 5.';
            }
        }

        $validTissueTypes = ['granulation', 'slough', 'eschar', 'epithelial', 'mixed'];
        if (empty($data['tissue_type'])) {
            $errors[] = 'SSP Wound Description: Predominant Tissue Type is required.';
        } elseif (!in_array($data['tissue_type'], $validTissueTypes)) {
            $errors[] = 'SSP Wound Description: Invalid Predominant Tissue Type selected. Must be one of: ' . implode(', ', $validTissueTypes) . '.';
        }

        $validExudateAmounts = ['none', 'minimal', 'moderate', 'heavy'];
        if (empty($data['exudate_amount'])) {
            $errors[] = 'SSP Wound Description: Exudate Amount is required.';
        } elseif (!in_array($data['exudate_amount'], $validExudateAmounts)) {
            $errors[] = 'SSP Wound Description: Invalid Exudate Amount selected. Must be one of: ' . implode(', ', $validExudateAmounts) . '.';
        }

        $validExudateTypes = ['serous', 'serosanguinous', 'sanguinous', 'purulent'];
        if (empty($data['exudate_type'])) {
            $errors[] = 'SSP Wound Description: Exudate Type is required.';
        } elseif (!in_array($data['exudate_type'], $validExudateTypes)) {
            $errors[] = 'SSP Wound Description: Invalid Exudate Type selected. Must be one of: ' . implode(', ', $validExudateTypes) . '.';
        }

        if (isset($data['infection_signs']) && is_array($data['infection_signs'])) {
            $allowedSigns = ['erythema', 'warmth', 'swelling', 'pain', 'purulent_drainage', 'odor', 'delayed_healing', 'friable_granulation'];
            foreach ($data['infection_signs'] as $sign) {
                if (!in_array($sign, $allowedSigns)) {
                    $errors[] = "SSP Wound Description: Invalid infection sign '{$sign}' selected.";
                    break;
                }
            }
        } elseif (isset($data['infection_signs']) && !is_array($data['infection_signs'])) {
            $errors[] = 'SSP Wound Description: Signs of Infection must be a list of selected signs.';
        }

        return $errors;
    }

    private function validateSspCirculation(array $data, string $woundType): array
    {
        $errors = [];
        $hasAbi = !empty($data['abi_result_value']);
        $hasPedalPulses = !empty($data['pedal_pulses_result']);
        $hasTcpo2 = !empty($data['tcpo2_value_text']);

        if (!$hasAbi && !$hasPedalPulses && !$hasTcpo2) {
            // This could be a warning or a less severe error depending on strictness
            $errors[] = 'SSP Circulation: At least one circulation test result (ABI, Pedal Pulses, or TcPO2) is strongly recommended.';
        }

        if ($hasAbi && empty($data['abi_date'])) {
            $errors[] = 'SSP Circulation: Date for ABI result is required if ABI result is provided.';
        } elseif (!empty($data['abi_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['abi_date'])) {
            $errors[] = 'SSP Circulation: ABI Date must be in YYYY-MM-DD format.';
        }

        if ($hasPedalPulses && empty($data['pedal_pulses_date'])) {
            $errors[] = 'SSP Circulation: Date for Pedal pulses result is required if result is provided.';
        } elseif (!empty($data['pedal_pulses_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['pedal_pulses_date'])) {
            $errors[] = 'SSP Circulation: Pedal Pulses Date must be in YYYY-MM-DD format.';
        }

        if ($hasTcpo2 && empty($data['tcpo2_date'])) {
            $errors[] = 'SSP Circulation: Date for TcPO2 result is required if result is provided.';
        } elseif (!empty($data['tcpo2_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['tcpo2_date'])) {
            $errors[] = 'SSP Circulation: TcPO2 Date must be in YYYY-MM-DD format.';
        }

        if (!isset($data['doppler_waveforms_adequate']) || !is_bool($data['doppler_waveforms_adequate'])) {
            $errors[] = 'SSP Circulation: Indication for Doppler arterial waveforms (Yes/No) is required.';
        }

        $hasDopplerResultNotes = !empty($data['doppler_result_notes']);
        if ($hasDopplerResultNotes && empty($data['doppler_date'])) {
            $errors[] = 'SSP Circulation: Date for Doppler result is required if Doppler result notes are provided.';
        } elseif (!empty($data['doppler_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['doppler_date'])) {
            $errors[] = 'SSP Circulation: Doppler Date must be in YYYY-MM-DD format.';
        }

        if (empty($data['imaging_type'])) {
            $errors[] = 'SSP Circulation: Imaging type selection is required.';
        } elseif (!in_array($data['imaging_type'], ['xray', 'ctscan', 'mri', 'none'])) {
            $errors[] = 'SSP Circulation: Invalid value for Imaging type.';
        }

        return $errors;
    }

    private function validateSspConservativeMeasures(array $data, string $woundType): array
    {
        $errors = [];

        // Fields that are direct Yes/No questions and should have a boolean value if answered
        $boolean_fields_to_check = [
            'debridement_performed' => 'Debridement of necrotic tissue was performed',
            'moist_dressings_applied' => 'Application of dressings to maintain a moist wound environment',
            'non_weight_bearing_regimen' => 'Non-weight bearing regimen',
            'pressure_reducing_footwear' => 'Uses pressure-reducing footwear',
            'hbot_current' => 'Current HBOT',
            'smoking_cessation_counselled' => 'If Smoker, has patient been counselled on smoking cessation', // Conditional
            'radiation_chemo_current' => 'Is Patient receiving radiation therapy or chemotherapy',
            'immune_modulators_current' => 'Is Patient taking medications considered to be immune system modulators',
            'autoimmune_ctd_diagnosis' => 'Does Patient have an autoimmune connective tissue disease diagnosis'
        ];

        foreach ($boolean_fields_to_check as $field => $label) {
            // Skip smoking cessation counselling if not a smoker, it's handled conditionally later
            if ($field === 'smoking_cessation_counselled' && ($data['smoking_status'] ?? '') !== 'smoker') {
                continue;
            }
            if (!isset($data[$field]) || !is_bool($data[$field])) {
                 $errors[] = "SSP Conservative Measures: A Yes/No answer for '{$label}' is required.";
            }
        }

        // Conditional requirement for non_weight_bearing_type
        if (($data['non_weight_bearing_regimen'] ?? false) === true && empty($data['non_weight_bearing_type'])) {
            $errors[] = 'SSP Conservative Measures: Type of non-weight bearing regimen is required if regimen is indicated Yes.';
        }

        // Compression therapy for VSU (Venous Stasis Ulcer)
        if (empty($data['compression_therapy_vsu'])) {
            $errors[] = 'SSP Conservative Measures: Response for \'Used Standard compression therapy for venous stasis ulcers\' is required.';
        } elseif (!in_array($data['compression_therapy_vsu'], ['yes', 'no', 'na'])) {
            $errors[] = 'SSP Conservative Measures: Invalid value for \'Used Standard compression therapy for venous stasis ulcers\'.';
        }

        // Smoking Status
        if (empty($data['smoking_status'])) {
            $errors[] = 'SSP Conservative Measures: Smoking Status is required.';
        } elseif (!in_array($data['smoking_status'], ['smoker', 'previous', 'non_smoker'])) {
            $errors[] = 'SSP Conservative Measures: Invalid value for Smoking Status.';
        }

        // Conditional requirement for smoking_cessation_counselled
        if (($data['smoking_status'] ?? '') === 'smoker' && !isset($data['smoking_cessation_counselled'])) {
            // This will be caught by the loop above if we make it strictly required there when smoker.
            // Re-evaluating the boolean_fields_to_check logic for this specific case.
            // For now, ensuring it triggers an error if smoker and not set.
            if(!isset($data['smoking_cessation_counselled']) || !is_bool($data['smoking_cessation_counselled'])){
                 $errors[] = 'SSP Conservative Measures: If Smoker, counselling status on smoking cessation (Yes/No) is required.';
            }
        }

        // Conditional requirement for pressure_ulcer_leading_type
        // Note: $woundType is the overall wound type for the request, e.g. 'pressure_ulcer'
        if ($woundType === 'pressure_ulcer') {
            if (empty($data['pressure_ulcer_leading_type'])) {
                $errors[] = 'SSP Conservative Measures: Leading type for pressure ulcer is required if the main wound type is pressure ulcer.';
            } elseif (!in_array($data['pressure_ulcer_leading_type'], ['bed', 'wheelchair_cushion'])) {
                $errors[] = 'SSP Conservative Measures: Invalid value for leading type of pressure ulcer.';
            }
        }

        // Validate response_to_conservative_care and conservative_care_notes (if deemed required for SSP)
        // These were from the original generic form. Checklist does not explicitly show them, but they are good practice.
        // For now, let's assume they are optional for SSP unless specified.
        // if (empty($data['response_to_conservative_care'])) $errors[] = 'Response to conservative care is required.';
        // if (empty($data['conservative_care_notes'])) $errors[] = 'Conservative care notes are required.';

        return $errors;
    }
    // --- SSP VALIDATION METHODS END ---
}
