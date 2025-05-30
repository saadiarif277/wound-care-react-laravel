<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use App\Services\PatientFhirService; // To be created later
// use App\Services\ClinicalFhirService; // To be created later

class ProductRequestPatientController extends Controller
{
    // protected $patientFhirService;

    // public function __construct(PatientFhirService $patientFhirService)
    // {
    //     $this->patientFhirService = $patientFhirService;
    // }

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

        // 1. Process Patient Information (Simulate FHIR Patient creation)
        $patientApiInput = $request->input('patient_api_input');
        // In a real implementation: $patientFhirId = $this->patientFhirService->createOrUpdatePatient($patientApiInput);
        $simulatedPatientFhirId = 'fhir-patient-' . uniqid();
        $simulatedPatientDisplayId = strtoupper(substr($patientApiInput['first_name'], 0, 2) . substr($patientApiInput['last_name'], 0, 2)) . rand(100,999);

        $responseData = [
            'message' => 'Patient information processed successfully.',
            'patient_fhir_id' => $simulatedPatientFhirId,
            'patient_display_id' => $simulatedPatientDisplayId,
        ];

        // 2. Process Optional Clinical Assessment Data (Simulate FHIR Clinical Resource creation)
        if ($request->has('assessment_data')) {
            $assessmentData = $request->input('assessment_data');
            // In a real implementation: $azureOrderChecklistFhirId = $this->clinicalFhirService->createClinicalAssessment($simulatedPatientFhirId, $assessmentData);
            $simulatedAzureOrderChecklistFhirId = 'fhir-docref-' . uniqid();

            $responseData['message'] = 'Patient information and initial clinical assessment processed successfully.';
            $responseData['azure_order_checklist_fhir_id'] = $simulatedAzureOrderChecklistFhirId;
            $responseData['received_assessment_data'] = $assessmentData; // For MVP debugging
        }

        return response()->json($responseData, 201);
    }
}
