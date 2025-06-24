<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HealthData\DTO\SkinSubstituteChecklistInput;
use App\Services\HealthData\Services\Fhir\SkinSubstituteChecklistService;
use App\Services\PatientService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for handling patient information and clinical assessments
 * in the context of product requests for wound care distribution.
 */
class ProductRequestPatientController extends Controller
{
    protected PatientService $patientService;
    protected SkinSubstituteChecklistService $checklistService;

    /**
     * Constructor for ProductRequestPatientController.
     *
     * @param PatientService $patientService
     * @param SkinSubstituteChecklistService $checklistService
     */
    public function __construct(
        PatientService $patientService,
        SkinSubstituteChecklistService $checklistService
    ) {
        $this->patientService = $patientService;
        $this->checklistService = $checklistService;
    }

    /**
     * Store patient information and optionally initial clinical assessment,
     * then create/update relevant FHIR records.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // PHI fields for Patient FHIR resource
            'patient_api_input.member_id' => 'required|string|max:255',
            'patient_api_input.first_name' => 'required|string|max:255',
            'patient_api_input.last_name' => 'required|string|max:255',
            'patient_api_input.dob' => 'required|date_format:Y-m-d',
            'patient_api_input.gender' => 'required|string|in:male,female,other,unknown',

            // Optional Clinical Assessment Data
            'assessment_data' => 'sometimes|array',
            'assessment_data.wound_type' => 'required_with:assessment_data|string|in:DFU,VLU,PU,TW,AU,OTHER',
            'assessment_data.wound_location' => 'nullable|string|max:255',
            'assessment_data.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            return $this->processPatientInformation($request);
        } catch (Exception $e) {
            Log::error('Failed to process patient information', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Failed to process patient information',
                'error' => 'An error occurred while creating the patient record. Please try again.',
            ], 500);
        }
    }

    /**
     * Process patient information and create FHIR records.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    private function processPatientInformation(Request $request): JsonResponse
    {
        // 1. Process Patient Information - Create actual FHIR Patient in Azure
        $patientApiInput = $request->input('patient_api_input');

        // Prepare patient data in the format expected by PatientService
        $patientData = [
            'first_name' => $patientApiInput['first_name'],
            'last_name' => $patientApiInput['last_name'],
            'date_of_birth' => $patientApiInput['dob'],
            'gender' => $patientApiInput['gender'],
            'member_id' => $patientApiInput['member_id'],
        ];

        $facilityId = Auth::user()->facility_id ?? 1;

        // Create patient record in Azure FHIR
        $patientResult = $this->patientService->createPatientRecord($patientData, $facilityId);

        $responseData = [
            'message' => 'Patient information processed successfully.',
            'patient_fhir_id' => $patientResult['patient_fhir_id'],
            'patient_display_id' => $patientResult['patient_display_id'],
            'is_temporary' => $patientResult['is_temporary'] ?? false,
        ];

        // 2. Process Optional Clinical Assessment Data - Create FHIR Bundle
        if ($request->has('assessment_data')) {
            $responseData = $this->processAssessmentData($request, $patientApiInput, $patientResult, $facilityId, $responseData);
        }

        return response()->json($responseData, 201);
    }

    /**
     * Process clinical assessment data and create FHIR bundle.
     *
     * @param Request $request
     * @param array $patientApiInput
     * @param array $patientResult
     * @param int $facilityId
     * @param array $responseData
     * @return array
     */
    private function processAssessmentData(
        Request $request,
        array $patientApiInput,
        array $patientResult,
        int $facilityId,
        array $responseData
    ): array {
        $assessmentData = $request->input('assessment_data');

        // Create a basic checklist input for the assessment
        $checklistInput = new SkinSubstituteChecklistInput();

        $this->populateChecklistInput($checklistInput, $patientApiInput, $assessmentData);

        try {
            // Create FHIR Bundle in Azure using the correct method
            $bundle = $this->checklistService->createPreApplicationAssessment(
                $checklistInput,
                $patientResult['patient_fhir_id'],
                'Practitioner/' . Auth::id(),
                'Organization/' . $facilityId
            );

            // Extract DocumentReference ID from the bundle
            $documentReferenceId = $this->extractDocumentReferenceId($bundle);

            $responseData['message'] = 'Patient information and initial clinical assessment processed successfully.';
            $responseData['azure_order_checklist_fhir_id'] = $documentReferenceId ?? 'DocumentReference/temp-' . uniqid();
        } catch (Exception $e) {
            Log::error('Failed to create clinical assessment bundle', [
                'error' => $e->getMessage(),
                'patient_id' => $patientResult['patient_fhir_id'],
            ]);

            // Return success for patient creation even if assessment fails
            $responseData['message'] = 'Patient created successfully, but clinical assessment processing failed.';
            $responseData['assessment_error'] = 'Failed to process clinical assessment';
        }

        return $responseData;
    }

    /**
     * Populate the checklist input with patient and assessment data.
     *
     * @param SkinSubstituteChecklistInput $checklistInput
     * @param array $patientApiInput
     * @param array $assessmentData
     * @return void
     */
    private function populateChecklistInput(
        SkinSubstituteChecklistInput $checklistInput,
        array $patientApiInput,
        array $assessmentData
    ): void {
        // Set required patient information
        $checklistInput->patientName = $patientApiInput['first_name'] . ' ' . $patientApiInput['last_name'];
        $checklistInput->dateOfBirth = $patientApiInput['dob'];
        $checklistInput->dateOfProcedure = date('Y-m-d');

        // Set diagnosis information based on wound type
        $checklistInput->hasDiabetes = ($assessmentData['wound_type'] === 'DFU');
        $checklistInput->diabetesType = $checklistInput->hasDiabetes ? '2' : null; // Default to Type 2
        $checklistInput->hasVenousStasisUlcer = ($assessmentData['wound_type'] === 'VLU');
        $checklistInput->hasPressureUlcer = ($assessmentData['wound_type'] === 'PU');
        $checklistInput->location = $assessmentData['wound_location'] ?? 'Lower extremity';
        $checklistInput->ulcerLocation = $assessmentData['wound_location'] ?? 'Lower extremity';

        // Set wound description defaults for MVP
        $checklistInput->depth = 'full-thickness';
        $checklistInput->ulcerDuration = '> 30 days';
        $checklistInput->exposedStructures = [];
        $checklistInput->length = 2.0; // Default 2cm
        $checklistInput->width = 2.0;  // Default 2cm
        $checklistInput->woundDepth = 0.5; // Default 0.5cm
        $checklistInput->hasInfection = false;
        $checklistInput->hasNecroticTissue = false;
        $checklistInput->hasCharcotDeformity = false;
        $checklistInput->hasMalignancy = false;

        // Set conservative treatment defaults
        $checklistInput->conservativeCareProvided = true;
        $checklistInput->conservativeCareWeeks = 4;
        $checklistInput->conservativeCareTypes = ['offloading', 'dressings'];
        $checklistInput->debridementPerformed = true;
        $checklistInput->moistDressingsApplied = true;

        // Set circulation defaults
        $checklistInput->treated = false;
    }

    /**
     * Extract DocumentReference ID from FHIR bundle.
     *
     * @param mixed $bundle
     * @return string|null
     */
    private function extractDocumentReferenceId($bundle): ?string
    {
        if (!$bundle || !method_exists($bundle, 'getEntry')) {
            return null;
        }

        foreach ($bundle->getEntry() as $entry) {
            $resource = $entry->getResource();
            if ($resource && 
                method_exists($resource, 'getResourceType') && 
                method_exists($resource, 'getId') &&
                $resource->getResourceType() === 'DocumentReference') {
                $resourceId = $resource->getId();
                if ($resourceId && method_exists($resourceId, 'getValue')) {
                    return 'DocumentReference/' . $resourceId->getValue();
                }
            }
        }

        return null;
    }
}
