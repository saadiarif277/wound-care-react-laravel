<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\PatientService;
use App\Services\HealthData\Services\Fhir\SkinSubstituteChecklistService;
use Illuminate\Support\Facades\Log;

class ProductRequestPatientController extends Controller
{
    protected PatientService $patientService;
    protected SkinSubstituteChecklistService $checklistService;

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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
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
            // Add other minimal assessment fields if needed for MVP, ensure they are 'required_with:assessment_data' or nullable
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
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
            
            // Get facility ID from authenticated user or default
            $facilityId = auth()->user()->facility_id ?? 1;
            
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
                $assessmentData = $request->input('assessment_data');
                
                // Create a basic checklist input for the assessment
                $checklistInput = new \App\Services\HealthData\DTO\SkinSubstituteChecklistInput(
                    patientId: $patientResult['patient_fhir_id'],
                    providerId: 'Practitioner/' . auth()->id(),
                    facilityId: 'Organization/' . $facilityId,
                    
                    woundType: $assessmentData['wound_type'],
                    woundLocation: $assessmentData['wound_location'] ?? null,
                    
                    // Set some defaults for MVP
                    conservativeCareWeeks: 4,
                    conservativeCareTypes: ['offloading', 'dressings'],
                    measurementDocumented: true,
                    conservativeCareDocumented: true,
                    
                    clinicalNotes: $assessmentData['notes'] ?? null
                );
                
                try {
                    // Create FHIR Bundle in Azure
                    $bundleResponse = $this->checklistService->createChecklistBundle($checklistInput);
                    
                    // Extract DocumentReference ID
                    $documentReferenceId = null;
                    if (isset($bundleResponse['entry']) && is_array($bundleResponse['entry'])) {
                        foreach ($bundleResponse['entry'] as $entry) {
                            if (isset($entry['response']['location']) && 
                                str_contains($entry['response']['location'], 'DocumentReference/')) {
                                $parts = explode('/', $entry['response']['location']);
                                $documentReferenceId = 'DocumentReference/' . end($parts);
                                break;
                            }
                        }
                    }

                    $responseData['message'] = 'Patient information and initial clinical assessment processed successfully.';
                    $responseData['azure_order_checklist_fhir_id'] = $documentReferenceId ?? 'DocumentReference/temp-' . uniqid();
                    
                } catch (\Exception $e) {
                    Log::error('Failed to create clinical assessment bundle', [
                        'error' => $e->getMessage(),
                        'patient_id' => $patientResult['patient_fhir_id']
                    ]);
                    
                    // Return success for patient creation even if assessment fails
                    $responseData['message'] = 'Patient created successfully, but clinical assessment processing failed.';
                    $responseData['assessment_error'] = 'Failed to process clinical assessment';
                }
            }

            return response()->json($responseData, 201);
            
        } catch (\Exception $e) {
            Log::error('Failed to process patient information', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Failed to process patient information',
                'error' => 'An error occurred while creating the patient record. Please try again.'
            ], 500);
        }
    }
}
